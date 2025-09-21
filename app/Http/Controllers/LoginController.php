<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\EmailVerificationCode;
use App\Models\Enums\UserType;
use App\Notifications\EmailVerificationCode as EmailVerificationCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    /**
     * GET /login
     */
    public function index(Request $request)
    {
        return view('login');
    }

    /**
     * GET /logout
     */
    public function logout(Request $request)
    {
        $request->session()->flush();
        return redirect('login')->with('info', __('login.logged-out-normal'));
    }

    /**
     * GET /trigger-login  (Google OAuth redirect)
     */
    public function triggerLogin(Request $request)
    {
        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    public function triggerMicrosoftLogin(Request $request)
    {
        Log::info('Microsoft login redirect elindult');
        return Socialite::driver('microsoft')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * ANY /attempt-login   (Google OAuth callback)
     */
    public function attemptLogin(Request $request)
    {
        try {
            $u = Socialite::driver('google')->user();
        } catch (\Throwable $th) {
            return redirect('login')->with('error', __('login.failed-login'));
        }

        /** @var User|null $user */
        $user = User::where('email', $u->getEmail())
            ->whereNull('removed_at')
            ->first();

        if (is_null($user)) {
            abort(403);
        }

        // OAuth login bypasses 2FA - proceed directly to finish login
        return $this->finishLogin($request, $user, $u->getAvatar(), false);
    }

    public function attemptMicrosoftLogin(Request $request)
    {
        Log::info('Microsoft callback route meghívva', [
            'query' => $request->query(),
        ]);

        try {
            $u = Socialite::driver('microsoft')->user();
            Log::info('Microsoft user adatok', [
                'id'    => $u->getId(),
                'email' => $u->getEmail(),
                'name'  => $u->getName(),
            ]);
        } catch (\Throwable $th) {
            Log::error('Microsoft login hiba', ['exception' => $th]);
            return redirect('login')->with('error', __('login.failed-login'));
        }

        $user = \App\Models\User::where('email', $u->getEmail())
            ->whereNull('removed_at')
            ->first();

        if (is_null($user)) {
            Log::warning('Microsoft login: nincs ilyen user az adatbázisban', [
                'email' => $u->getEmail(),
            ]);
            abort(403, 'Nincs ilyen felhasználó az adatbázisban.');
        }

        Log::info('Microsoft login sikeres', ['user_id' => $user->id]);

        // OAuth login bypasses 2FA - proceed directly to finish login
        return $this->finishLogin($request, $user, $u->getAvatar(), false);
    }

    /**
     * POST /attempt-password-login   (Email + jelszó belépés)
     * Modified for 2FA - now triggers email verification instead of direct login
     */
    public function passwordLogin(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'nullable|boolean',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $data['email'])
            ->whereNull('removed_at')
            ->first();

        // Ha nincs user, nincs jelszó (Google-only), vagy rossz a jelszó → hiba
        if (! $user || empty($user->password) || ! Hash::check($data['password'], $user->password)) {
            return back()
                ->withErrors(['email' => 'Hibás email/jelszó, vagy ehhez a fiókhoz még nincs jelszó beállítva.'])
                ->withInput(['email' => $data['email']]);
        }

        // Password is correct - now trigger 2FA
        $remember = (bool)($data['remember'] ?? false);
        
        // Store user info and remember preference in session for later use
        session([
            'pending_2fa_user_id' => $user->id,
            'pending_2fa_remember' => $remember,
            'pending_2fa_email' => $user->email,
        ]);

        // Generate and send verification code
        $verificationCode = EmailVerificationCode::createForEmail(
            $user->email,
            session()->getId(),
            $request->ip(),
            $request->userAgent()
        );

        // Send email with verification code
        try {
            Notification::route('mail', $user->email)
                ->notify(new EmailVerificationCodeNotification($verificationCode->code, $user->name));
        } catch (\Throwable $e) {
            Log::error('Failed to send verification email', [
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return back()
                ->withErrors(['email' => 'Hiba történt az ellenőrző kód küldése során. Kérjük, próbáld újra.'])
                ->withInput(['email' => $data['email']]);
        }

        // Redirect back to login page with verification step
        return back()->with([
            'show_verification' => true,
            'verification_email' => $user->email,
            'success' => 'Ellenőrző kódot küldtünk a ' . $user->email . ' címre. A kód 10 percig érvényes.'
        ]);
    }

    /**
     * POST /verify-2fa-code
     * New method to handle 2FA verification
     */
    public function verify2faCode(Request $request)
    {
        $data = $request->validate([
            'verification_code' => 'required|string|size:6',
        ]);

        // Check if we have pending 2FA in session
        if (!session()->has('pending_2fa_user_id')) {
            return redirect()->route('login')
                ->withErrors(['verification_code' => 'Nincs folyamatban lévő belépés. Kérjük, próbáld újra.']);
        }

        $userId = session('pending_2fa_user_id');
        $email = session('pending_2fa_email');
        $remember = session('pending_2fa_remember', false);

        // Verify the code
        if (!EmailVerificationCode::verifyCode($email, $data['verification_code'], session()->getId())) {
            return back()
                ->withErrors(['verification_code' => 'Hibás vagy lejárt ellenőrző kód.'])
                ->with([
                    'show_verification' => true,
                    'verification_email' => $email
                ]);
        }

        // Code is valid - get user and finish login
        $user = User::find($userId);
        if (!$user || !is_null($user->removed_at)) {
            session()->forget(['pending_2fa_user_id', 'pending_2fa_remember', 'pending_2fa_email']);
            return redirect()->route('login')
                ->withErrors(['verification_code' => 'A felhasználói fiók nem aktív.']);
        }

        // Clear 2FA session data
        session()->forget(['pending_2fa_user_id', 'pending_2fa_remember', 'pending_2fa_email']);

        // Complete the login process
        return $this->finishLogin($request, $user, null, $remember);
    }

    /**
     * POST /resend-2fa-code
     * Resend verification code
     */
    public function resend2faCode(Request $request)
    {
        // Check if we have pending 2FA in session
        if (!session()->has('pending_2fa_user_id')) {
            return redirect()->route('login')
                ->withErrors(['verification_code' => 'Nincs folyamatban lévő belépés. Kérjük, próbáld újra.']);
        }

        $userId = session('pending_2fa_user_id');
        $email = session('pending_2fa_email');

        $user = User::find($userId);
        if (!$user || !is_null($user->removed_at)) {
            session()->forget(['pending_2fa_user_id', 'pending_2fa_remember', 'pending_2fa_email']);
            return redirect()->route('login')
                ->withErrors(['verification_code' => 'A felhasználói fiók nem aktív.']);
        }

        // Generate and send new verification code
        $verificationCode = EmailVerificationCode::createForEmail(
            $user->email,
            session()->getId(),
            $request->ip(),
            $request->userAgent()
        );

        // Send email with verification code
        try {
            Notification::route('mail', $user->email)
                ->notify(new EmailVerificationCodeNotification($verificationCode->code, $user->name));
        } catch (\Throwable $e) {
            Log::error('Failed to resend verification email', [
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return back()
                ->withErrors(['verification_code' => 'Hiba történt az ellenőrző kód küldése során. Kérjük, próbáld újra.'])
                ->with([
                    'show_verification' => true,
                    'verification_email' => $email
                ]);
        }

        return back()->with([
            'show_verification' => true,
            'verification_email' => $user->email,
            'success' => 'Új ellenőrző kódot küldtünk a ' . $user->email . ' címre.'
        ]);
    }

    /**
     * Közös lezáró metódus mindkét belépési ágra:
     * - Laravel Auth::login (remember opcióval)
     * - Session feltöltése
     * - user_login naplózás (session id mint token)
     * - Org választás/irányítás
     */
    private function finishLogin(Request $request, User $user, ?string $avatar = null, bool $remember = false)
    {
        // Laravel auth + remember cookie (framework kezeli az élettartamot)
        Auth::login($user, $remember);

        // Alap session adatok
        session([
            'uid'     => $user->id,
            'uname'   => $user->name,
            'utype'   => $user->type,
            'uavatar' => $avatar,
        ]);

        // Login naplózása a meglévő mechanizmusoddal
        $user->logins()->create([
            'logged_in_at' => date('Y-m-d H:i:s'),
            'token'        => session()->getId(),
            'ip'           => $request->ip(),
            'user_agent'   => substr($request->userAgent(), 0, 255),
        ]);

        // --- ORG kiválasztás / irányítás ---
        // 1) SUPERADMIN → dashboard (org választó helyett)
        if ($user->type === UserType::SUPERADMIN) {
            session()->forget('org_id');
            return redirect()->route('superadmin.dashboard');
        }

        // 2) Nem superadmin:
        //    - ha pontosan 1 org tagja → automatikus org_id, mehet tovább
        //    - különben org választó
        $orgIds = $user->organizations()->pluck('organization.id')->toArray();

        if (count($orgIds) === 1) {
            session(['org_id' => $orgIds[0]]);
            return redirect('home-redirect');
        }

        session()->forget('org_id');
        return redirect()->route('org.select');
    }
}