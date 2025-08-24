<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        // Közös lezárás: auth + session + napló + org routing
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

        return $this->finishLogin($request, $user, $u->getAvatar(), false);
    }

    /**
     * POST /attempt-password-login   (Email + jelszó belépés)
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

        $remember = (bool)($data['remember'] ?? false);

        // Közös lezárás: auth + session + napló + org routing
        return $this->finishLogin($request, $user, null, $remember);
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
            'user_agent'   => substr($request->userAgent(),0,255),

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
