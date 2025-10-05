<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\PasswordSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Validation\Rules\Password;

class PasswordSetupController extends Controller
{
    public function show(Request $request, string $token)
    {
        $row = DB::table('password_setup as ps')
            ->join('user as u', 'u.id', '=', 'ps.user_id')
            ->join('organization as o', 'o.id', '=', 'ps.organization_id')
            ->select('ps.id as ps_id', 'ps.expires_at', 'ps.used_at', 'ps.organization_id', 'u.id as user_id', 'o.slug as org_slug')
            ->whereNull('ps.used_at')
            ->where('ps.expires_at', '>', now())
            ->where('ps.token_hash', hash('sha256', $token))
            ->first();

        if (!$row) {
            return redirect()->route('login')->with('error', 'A link/token lejárt vagy nem létezik. Kérelem nem teljesíthető.');
        }

        $user = User::findOrFail($row->user_id);
        $organization = Organization::findOrFail($row->organization_id);

        if (!$user || !is_null($user->removed_at)) {
            return redirect()->route('login')->with('error', 'A felhasználói fiók nem aktív.');
        }

        return view('password-setup', [
            'email' => $user->email,
            'token' => $token,
            'user'  => $user,
            'org'   => $organization,
        ]);
    }

    public function store(Request $request, string $token)
        {
            $ps = DB::table('password_setup as ps')
                ->join('organization as o', 'o.id', '=', 'ps.organization_id')
                ->select('ps.*', 'o.slug as org_slug')
                ->where('ps.token_hash', hash('sha256', $token))
                ->whereNull('ps.used_at')
                ->first();

            if (!$ps || now()->greaterThan($ps->expires_at)) {
                return redirect()->route('login')->with('error', 'A jelszó beállító link érvénytelen vagy lejárt.');
            }

            $organization = Organization::findOrFail($ps->organization_id);
            $passwordSetup = PasswordSetup::findOrFail($ps->id);
            $user = $passwordSetup->user;

            if (!$user || !is_null($user->removed_at)) {
                return redirect()->route('login')->with('error', 'A felhasználói fiók nem aktív.');
            }

            // reCAPTCHA validation
            if (!\App\Services\RecaptchaService::validateRequest($request)) {
                return back()
                    ->withErrors(['email' => __('auth.recaptcha_failed')])
                    ->withInput($request->except(['password', 'password_confirmation', 'g-recaptcha-response']));
            }

            // SECURITY FIX: Enforce strong password requirements (12+ chars, letters, numbers, not compromised)
            $data = $request->validate([
                'password' => [
                    'required',
                    'string',
                    'confirmed',
                    Password::min(12)
                        ->letters()
                        ->numbers()
                        ->uncompromised(),
                ],
            ]);

        // Save password + email verification
        $user->password = Hash::make($data['password']);
        if (empty($user->email_verified_at)) {
            $user->email_verified_at = now();
        }
        $user->save();

        // Mark token as used
        $passwordSetup->used_at = now();
        $passwordSetup->save();

        // Login user + session
        $request->session()->regenerate();
        Auth::login($user, false);

        session([
            'uid'     => $user->id,
            'uname'   => $user->name,
            'utype'   => $user->type,
            'uavatar' => null,
            'org_id'  => $organization->id,
        ]);

        // Login log
        try {
            $user->logins()->create([
                'logged_in_at' => now()->format('Y-m-d H:i:s'),
                'token'        => session()->getId(),
                'ip'           => $request->ip(),
                'user_agent'   => substr($request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Silent fail - non-critical
        }

        return redirect()->route('home-redirect')->with('success', 'Jelszó beállítva, beléptél a rendszerbe.');
    }
}