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
use App\Services\RecaptchaService;
use App\Services\OrgConfigService;

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

        // SECURITY DECISION: Check if organization enforces 2FA for OAuth logins
        // By default, OAuth logins bypass 2FA because Google/Microsoft provide strong authentication
        // including 2FA, device verification, and anomaly detection.
        // Admins can override this by enabling 'force_oauth_2fa' setting.
        return $this->handleOAuthLogin($request, $user, $u->getAvatar(), 'Google');
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

        // SECURITY DECISION: Check if organization enforces 2FA for OAuth logins
        return $this->handleOAuthLogin($request, $user, $u->getAvatar(), 'Microsoft');
    }

    /**
     * Handle OAuth login with optional 2FA enforcement
     * 
     * @param Request $request
     * @param User $user
     * @param string|null $avatar
     * @param string $provider OAuth provider name (for logging)
     * @return \Illuminate\Http\RedirectResponse
     */
    /**
 * Handle OAuth login with optional 2FA enforcement
 * 
 * Security Logic:
 * - Superadmins: Always skip 2FA (trusted system administrators)
 * - Regular users: If ANY organization requires 2FA, enforce it (strictest security)
 * - Users with no orgs: Skip 2FA (they can't access anything anyway)
 * 
 * @param Request $request
 * @param User $user
 * @param string|null $avatar
 * @param string $provider OAuth provider name (for logging)
 * @return \Illuminate\Http\RedirectResponse
 */
private function handleOAuthLogin(Request $request, User $user, ?string $avatar, string $provider)
{
    // SUPERADMINS: Always skip 2FA (they're trusted system administrators)
    if ($user->type === UserType::SUPERADMIN) {
        Log::info('OAuth login: Superadmin - 2FA bypassed', [
            'provider' => $provider,
            'user_id' => $user->id,
        ]);
        return $this->finishLogin($request, $user, $avatar, false);
    }

    // Get user's organizations to check security settings
    $orgIds = $user->organizations()->pluck('organization.id')->toArray();
    
    // Default: OAuth bypasses 2FA (current behavior)
    $forceOauth2fa = false;
    
    // STRICTEST SECURITY: If ANY organization requires 2FA, enforce it
    // This prevents users from bypassing 2FA by joining a less secure organization
    foreach ($orgIds as $orgId) {
        if (OrgConfigService::getBool($orgId, 'force_oauth_2fa', false)) {
            $forceOauth2fa = true;
            Log::info('OAuth login: 2FA enforced by organization', [
                'provider' => $provider,
                'user_id' => $user->id,
                'email' => $user->email,
                'org_id' => $orgId,
            ]);
            break; // If any org requires it, enforce 2FA
        }
    }

    // If organization enforces 2FA for OAuth, trigger email verification
    if ($forceOauth2fa) {
        // Store user info in session for 2FA verification
        session([
            'pending_2fa_user_id' => $user->id,
            'pending_2fa_remember' => false, // OAuth logins don't use "remember me"
            'pending_2fa_email' => $user->email,
            'pending_2fa_avatar' => $avatar, // Store avatar for later use
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
            Log::error('Failed to send OAuth 2FA verification email', [
                'provider' => $provider,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            return redirect('login')
                ->with('error', 'Hiba történt az ellenőrző kód küldése során. Kérjük, próbáld újra.');
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

    return $this->finishLogin($request, $user, $avatar, false);
}

    /**
 * POST /attempt-password-login   (Email + password login with account lockout)
 * Modified for 2FA and persistent account lockout
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

    // SECURITY: Check credentials
    if (!$user || empty($user->password) || !Hash::check($data['password'], $user->password)) {
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

    Log::info('login.password_verified_2fa_sent', [
        'user_id' => $user->id,
        'email' => $user->email,
        'ip' => $ipAddress,
    ]);

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
        $avatar = session('pending_2fa_avatar', null); // Get stored avatar for OAuth logins

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