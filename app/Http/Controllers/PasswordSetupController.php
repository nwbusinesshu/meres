<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\PasswordSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User; 

class PasswordSetupController extends Controller
{
    public function show(Request $request, string $org, string $token)
    {
        $organization = Organization::where('slug', $org)->firstOrFail();

        $row = DB::table('password_setup as ps')
            ->join('user as u', 'u.id', '=', 'ps.user_id')
            ->select('ps.id as ps_id', 'ps.expires_at', 'ps.used_at', 'u.id as user_id')
            ->where('ps.organization_id', $organization->id)
            ->whereNull('ps.used_at')
            ->where('ps.expires_at', '>', now())
            ->where('ps.token_hash', hash('sha256', $token))
            ->first();

        if (!$row) {
            // lejárt/érvénytelen
            return redirect()->route('login')->with('error', 'A link/token lejárt vagy nem létezik. Kérelem nem teljesíthető.');
        }

        $user = User::findOrFail($row->user_id);

        if (!$user || !is_null($user->removed_at)) {
            return redirect()->route('login')->with('error', 'A felhasználói fiók nem aktív.');
        }

        return view('password-setup', [
            'org'   => $organization,
            'email' => $user->email,
            'token' => $token,
            'user'  => $user,
        ]);
    }

    public function store(Request $request, string $org, string $token)
    {
        $organization = Organization::where('slug', $org)->firstOrFail();

        $ps = PasswordSetup::where('organization_id', $organization->id)
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->first();

        if (!$ps || now()->greaterThan($ps->expires_at)) {
            return redirect()->route('login')->with('error', 'A jelszó beállító link érvénytelen vagy lejárt.');
        }

        $user = $ps->user;
        if (!$user || !is_null($user->removed_at)) {
            return redirect()->route('login')->with('error', 'A felhasználói fiók nem aktív.');
        }

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        // jelszó mentése + email verifikáció
        $user->password = Hash::make($data['password']);
        if (empty($user->email_verified_at)) {
            $user->email_verified_at = now();
        }
        $user->save();

        // token lezárása
        $ps->used_at = now();
        $ps->save();

        // beléptetés + session
        $request->session()->regenerate();
        Auth::login($user, false);

        session([
            'uid'     => $user->id,
            'uname'   => $user->name,
            'utype'   => $user->type,
            'uavatar' => null,
            'org_id'  => $organization->id,
        ]);

        // login napló (minimális – csak a meglévő oszlopokra)
        try {
            $user->logins()->create([
                'logged_in_at' => now()->format('Y-m-d H:i:s'),
                'token'        => session()->getId(),
            ]);
        } catch (\Throwable $e) {
            // csendben tovább; ha később bővítjük a táblát ip/ua-val, itt írjuk hozzá
        }

        return redirect()->route('home-redirect')->with('success', 'Jelszó beállítva, beléptél a rendszerbe.');
    }
}
