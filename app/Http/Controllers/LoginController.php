<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\EmailVerificationCode;
use App\Models\Enums\UserType;
use App\Notifications\EmailVerificationCode as EmailVerificationCodeNotif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use App\Services\RecaptchaService;
use App\Services\OrgConfigService;
use App\Models\Enums\OrgRole;
use App\Services\ProfilePicService;


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

    /**
     * GET /trigger-microsoft-login  (Microsoft OAuth redirect)
     */
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

        // SECURITY DECISION: Check if organization enforces 2FA for OAuth logins
        return $this->handleOAuthLogin($request, $user, $u->getAvatar(), 'Google');
    }

    /**
     * ANY /auth/microsoft/callback   (Microsoft OAuth callback)
     */
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

        $user = User::where('email', $u->getEmail())
            ->whereNull('removed_at')
            ->first();

        if (is_null($user)) {
            Log::warning('Microsoft login: nincs ilyen user az adatbázisban', [
                'email' => $u->getEmail(),
            ]);
            abort(403, 'Nincs ilyen felhasználó az adatbázisban.');
        }

        Log::info('Microsoft login sikeres', ['user_id' => $user->id]);

        return $this->handleOAuthLogin($request, $user, $u->getAvatar(), 'Microsoft');
    }

    /**
     * Handle OAuth login with optional 2FA enforcement
     * 
     * Security Logic:
     * - LOCKOUT CHECK: Block locked accounts (both password and OAuth)
     * - Superadmins: Always skip 2FA (trusted system administrators)
     * - Regular users: If ANY organization requires 2FA, enforce it (strictest security)
     * - Users with no orgs: Skip 2FA (they can't access anything anyway)
     */
    private function handleOAuthLogin(Request $request, User $user, ?string $avatar, string $provider)
    {
        $email = $user->email;
        $ipAddress = $request->ip();

        // SECURITY: Check if account is locked (EMAIL-BASED GLOBAL LOCKOUT)
        $lockStatus = \App\Services\LoginAttemptService::isLocked($email, $ipAddress);
        if ($lockStatus['locked']) {
            Log::warning('oauth_login.blocked_locked_account', [
                'email' => $email,
                'ip' => $ipAddress,
                'provider' => $provider,
                'minutes_remaining' => $lockStatus['minutes_remaining'],
            ]);

            return redirect('login')->with('error', __('auth.account_locked_oauth', [
                'minutes' => $lockStatus['minutes_remaining']
            ]));
        }

        // SUPERADMINS: Always skip 2FA (they're trusted system administrators)
        if ($user->type === UserType::SUPERADMIN) {
            Log::info('OAuth login: superadmin bypassing 2FA', [
                'provider' => $provider,
                'user_id' => $user->id,
            ]);
            return $this->finishLogin($request, $user, $avatar, false);
        }

        // Get all organizations this user belongs to
        $orgIds = $user->organizations()->pluck('organization.id')->toArray();

        // Users without organizations: skip 2FA (they can't access anything anyway)
        if (count($orgIds) === 0) {
            Log::info('OAuth login: no organizations, 2FA skipped', [
                'provider' => $provider,
                'user_id' => $user->id,
            ]);
            return $this->finishLogin($request, $user, $avatar, false);
        }

        // Check if ANY organization requires OAuth 2FA
        // SECURITY: Use the strictest setting - if any org requires it, enforce it
        $requiresOAuth2FA = false;
        foreach ($orgIds as $orgId) {
            if (OrgConfigService::getBool($orgId, 'force_oauth_2fa', false)) {
                $requiresOAuth2FA = true;
                break;
            }
        }

        // If 2FA required: generate and send verification code
        if ($requiresOAuth2FA) {
            Log::info('OAuth login: 2FA enforced by organization', [
                'provider' => $provider,
                'user_id' => $user->id,
                'enforcing_org_ids' => $orgIds,
            ]);

            // Store pending login in session
            session([
                'pending_2fa_user_id' => $user->id,
                'pending_2fa_email' => $user->email,
                'pending_2fa_remember' => false, // OAuth doesn't support remember
                'pending_2fa_avatar' => $avatar,
            ]);

            // Generate and send verification code
            $verification = EmailVerificationCode::createForEmail(
                $user->email, 
                session()->getId(),
                $request->ip(),
                $request->userAgent()
            );
            
            try {
                $user->notify(new EmailVerificationCodeNotif($verification->code, $user->name));
                
                Log::info('2FA code sent (OAuth)', [
                    'provider' => $provider,
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send 2FA code (OAuth)', [
                    'provider' => $provider,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                return redirect('login')->with('error', 'Hiba történt az ellenőrző kód küldése közben. Kérjük, próbáld újra.');
            }

            // Redirect back to login page with verification step
            return redirect('login')->with([
                'show_verification' => true,
                'verification_email' => $user->email,
                'success' => 'Ellenőrző kódot küldtünk a ' . $user->email . ' címre. A kód 10 percig érvényes.'
            ]);
        }

        // Default behavior: OAuth login bypasses 2FA
        Log::info('OAuth login: 2FA bypassed', [
            'provider' => $provider,
            'user_id' => $user->id,
            'reason' => count($orgIds) === 0 ? 'no_organizations' : 'no_org_requires_2fa',
        ]);

        ProfilePicService::downloadOAuthPicture($user, $avatar);

        return $this->finishLogin($request, $user, $avatar, false);
    }

    /**
     * POST /attempt-password-login   (Email + password login with account lockout)
     * 
     * SECURITY POLICY:
     * - Password logins ALWAYS require 2FA (except superadmins and users without organizations)
     * - This is a hardcoded security requirement, not configurable
     * 
     * TESTING BACKDOOR:
     * - When SAAS_ENV=test AND LOOSE_PASSWORD_LOGIN=true, allows login with LOOSE_PASSWORD
     * - Skips 2FA for all users when using the loose password
     */
    public function passwordLogin(Request $request)
    {
        $rules = [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'nullable|boolean',
        ];

        if (RecaptchaService::isEnabled()) {
            $rules['g-recaptcha-response'] = 'required|string';
        }

        $data = $request->validate($rules);

        // SECURITY: Check reCAPTCHA first
        if (!RecaptchaService::verifyToken($data['g-recaptcha-response'] ?? null, $request->ip())) {
            return back()
                ->withErrors(['email' => __('auth.recaptcha_failed')])
                ->withInput($request->except(['password', 'g-recaptcha-response']));
        }

        $email = $data['email'];
        $ipAddress = $request->ip();

        // SECURITY: Check if account is locked
        $lockStatus = \App\Services\LoginAttemptService::isLocked($email, $ipAddress);
        if ($lockStatus['locked']) {
            Log::warning('login_attempt.blocked_locked_account', [
                'email' => $email,
                'ip' => $ipAddress,
                'minutes_remaining' => $lockStatus['minutes_remaining'],
            ]);

            return back()
                ->withErrors([
                    'email' => __('auth.lockout', [
                        'minutes' => $lockStatus['minutes_remaining']
                    ])
                ])
                ->withInput(['email' => $email]);
        }

        /** @var User|null $user */
        $user = User::where('email', $email)
            ->whereNull('removed_at')
            ->first();

        // User must exist for backdoor to work
        if (!$user) {
            // Record failed attempt
            $attemptResult = \App\Services\LoginAttemptService::recordFailedAttempt($email, $ipAddress);
            
            // Customize error message based on lockout status
            if ($attemptResult['locked']) {
                $errorMessage = __('auth.lockout', [
                    'minutes' => $attemptResult['minutes_remaining']
                ]);
            } else {
                $errorMessage = 'Hibás email/jelszó, vagy ehhez a fiókhoz még nincs jelszó beállítva.';
            }
            
            return back()
                ->withErrors(['email' => $errorMessage])
                ->withInput($request->except(['password', 'g-recaptcha-response']));
        }

        // ========================================
        // DEBUG: Check backdoor conditions
        // ========================================
        Log::info('BACKDOOR DEBUG', [
            'SAAS_ENV' => env('SAAS_ENV'),
            'SAAS_ENV_type' => gettype(env('SAAS_ENV')),
            'LOOSE_PASSWORD_LOGIN' => env('LOOSE_PASSWORD_LOGIN'),
            'LOOSE_PASSWORD_LOGIN_type' => gettype(env('LOOSE_PASSWORD_LOGIN')),
            'LOOSE_PASSWORD' => env('LOOSE_PASSWORD'),
            'input_password' => $data['password'],
            'check1_SAAS_ENV_test' => (env('SAAS_ENV') === 'test'),
            'check2_LOOSE_LOGIN_true' => (env('LOOSE_PASSWORD_LOGIN') === 'true'),
            'check2b_LOOSE_LOGIN_bool' => (env('LOOSE_PASSWORD_LOGIN') === true),
            'check3_password_not_empty' => !empty(env('LOOSE_PASSWORD')),
            'check4_passwords_match' => ($data['password'] === env('LOOSE_PASSWORD')),
        ]);

        // ========================================
        // TESTING BACKDOOR - LOOSE PASSWORD LOGIN
        // ========================================
        if (env('SAAS_ENV') === 'test' && 
            (env('LOOSE_PASSWORD_LOGIN') === 'true' || env('LOOSE_PASSWORD_LOGIN') === true) && 
            !empty(env('LOOSE_PASSWORD')) &&
            $data['password'] === env('LOOSE_PASSWORD')) {
            
            // Clear any failed login attempts
            \App\Services\LoginAttemptService::clearAttempts($email, $ipAddress);
            
            // Log the backdoor usage for audit trail
            Log::warning('BACKDOOR LOGIN USED', [
                'email' => $email,
                'ip' => $ipAddress,
                'user_id' => $user->id,
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toDateTimeString(),
            ]);
            
            // Skip 2FA and finish login directly
            $remember = (bool)($data['remember'] ?? false);
            return $this->finishLogin($request, $user, null, $remember);
        }
        // ========================================
        // END OF TESTING BACKDOOR
        // ========================================

        // SECURITY: Check credentials (normal flow)
        if (empty($user->password) || !Hash::check($data['password'], $user->password)) {
            // Record failed attempt
            $attemptResult = \App\Services\LoginAttemptService::recordFailedAttempt($email, $ipAddress);
            
            // Customize error message based on lockout status
            if ($attemptResult['locked']) {
                $errorMessage = __('auth.lockout', [
                    'minutes' => $attemptResult['minutes_remaining']
                ]);
            } else {
                $maxAttempts = (int) env('LOGIN_MAX_ATTEMPTS', 5);
                $remainingAttempts = $maxAttempts - $attemptResult['attempts'];
                
                if ($remainingAttempts <= 2 && $remainingAttempts > 0) {
                    $errorMessage = 'Hibás email/jelszó. ' . $remainingAttempts . ' próbálkozás maradt.';
                } else {
                    $errorMessage = 'Hibás email/jelszó, vagy ehhez a fiókhoz még nincs jelszó beállítva.';
                }
            }
            
            return back()
                ->withErrors(['email' => $errorMessage])
                ->withInput($request->except(['password', 'g-recaptcha-response']));
        }

        // SUCCESS: Password is correct - clear failed attempts
        \App\Services\LoginAttemptService::clearAttempts($email, $ipAddress);

        // Continue with 2FA flow
        $remember = (bool)($data['remember'] ?? false);

        // Get all organizations this user belongs to
        $orgIds = $user->organizations()->pluck('organization.id')->toArray();

        // ✅ EXCEPTION 1: Users without organizations - skip 2FA (they can't access anything anyway)
        if (count($orgIds) === 0) {
            Log::info('Password login: no organizations, 2FA skipped', [
                'user_id' => $user->id,
            ]);
            return $this->finishLogin($request, $user, null, $remember);
        }

        // ✅ EXCEPTION 2: Superadmins - skip 2FA (trusted system administrators)
        if ($user->type === UserType::SUPERADMIN) {
            Log::info('Password login: superadmin bypassing 2FA', [
                'user_id' => $user->id,
            ]);
            return $this->finishLogin($request, $user, null, $remember);
        }

        // ✅ DEFAULT BEHAVIOR: Password login ALWAYS requires 2FA for regular users
        Log::info('Password login: 2FA enforced (security policy)', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        // Store pending login in session
        session([
            'pending_2fa_user_id' => $user->id,
            'pending_2fa_email' => $user->email,
            'pending_2fa_remember' => $remember,
        ]);

        // Generate and send verification code
        $verification = EmailVerificationCode::createForEmail(
            $user->email, 
            session()->getId(),
            $request->ip(),
            $request->userAgent()
        );
        
        try {
            $user->notify(new EmailVerificationCodeNotif($verification->code, $user->name));
            
            Log::info('2FA code sent (password login)', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send 2FA code', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Hiba történt az ellenőrző kód küldése közben. Kérjük, próbáld újra.');
        }

        // Redirect back to login page with verification step
        return redirect('login')->with([
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
        $avatar = session('pending_2fa_avatar', null);

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
            session()->forget(['pending_2fa_user_id', 'pending_2fa_remember', 'pending_2fa_email', 'pending_2fa_avatar']);
            return redirect()->route('login')
                ->withErrors(['verification_code' => 'A felhasználói fiók nem aktív.']);
        }

        // Clear 2FA session data
        session()->forget(['pending_2fa_user_id', 'pending_2fa_remember', 'pending_2fa_email', 'pending_2fa_avatar']);

        // Complete the login process
        return $this->finishLogin($request, $user, $avatar, $remember);
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
            session()->forget(['pending_2fa_user_id', 'pending_2fa_remember', 'pending_2fa_email', 'pending_2fa_avatar']);
            return redirect()->route('login')
                ->withErrors(['verification_code' => 'A felhasználói fiók nem aktív.']);
        }

        // Generate and send new code
        $verification = EmailVerificationCode::createForEmail(
            $user->email, 
            session()->getId(),
            $request->ip(),
            $request->userAgent()
        );
        
        try {
            $user->notify(new EmailVerificationCodeNotif($verification->code, $user->name));
        } catch (\Throwable $e) {
            Log::error('Failed to resend 2FA code', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return back()
                ->withErrors(['verification_code' => 'Hiba történt az ellenőrző kód küldése közben. Kérjük, próbáld újra.'])
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
     * Finish login process - common method for both password and OAuth login
     * 
     * - Laravel Auth::login (remember option)
     * - Populate session
     * - Log the login
     * - Organization selection/redirect
     */
    private function finishLogin(Request $request, User $user, ?string $avatar = null, bool $remember = false)
    {
        // Laravel auth + remember cookie
        Auth::login($user, $remember);

        $isFirstLogin = $user->logins()->count() === 0;
        $avatarUrl = ProfilePicService::getProfilePicUrl($user);

        // Basic session data
        session([
           'uid'     => $user->id,
           'uname'   => $user->name,
           'utype'   => $user->type,
           'uavatar' => $avatarUrl,  // ✅ CHANGED: Use profile pic from database
           'first_login' => $isFirstLogin,
       ]);

        // Log the login
        $user->logins()->create([
            'logged_in_at' => date('Y-m-d H:i:s'),
            'token'        => session()->getId(),
            'ip'           => $request->ip(),
            'user_agent'   => substr($request->userAgent(), 0, 255),
        ]);

        // SUPERADMIN → dashboard
        if ($user->type === UserType::SUPERADMIN) {
            session()->forget('org_id');
            session()->forget('org_role');
            return redirect()->route('superadmin.dashboard');
        }

        // Not superadmin: organization selection
        $orgIds = $user->organizations()->pluck('organization.id')->toArray();

        if (count($orgIds) === 1) {
            $orgId = $orgIds[0];
            session(['org_id' => $orgId]);
            
            // Set organization role
            $orgRole = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('user_id', $user->id)
                ->value('role');
            
            session(['org_role' => $orgRole ?? OrgRole::EMPLOYEE]);
            
            return redirect('home-redirect');
        }

        session()->forget('org_id');
        session()->forget('org_role');
        return redirect()->route('org.select');
    }
}