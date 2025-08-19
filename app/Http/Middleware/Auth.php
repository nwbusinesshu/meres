<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

use App\Exceptions\AuthFailException;
use App\Models\Enums\UserType;
use App\Models\User;

class Auth
{
  /**
   * Authenticates and authorizes the user.
   *
   * @param  Request  $request
   * @param  Closure  $next
   * @param  string   $utype  required user type for the route
   */
  public function handle(Request $request, Closure $next, $utype)
  {
    if ($utype != UserType::GUEST) {
      try {
        // 1) be van-e jelentkezve?
        if (!$this->isLoggedIn()) {
          throw new AuthFailException(__('auth.login-first'), 'login');
        }

        // 2) másik eszközön újabb login?
        $lastLogin = User::find(session('uid'))->getLastLogin();
        if (!$lastLogin || session()->getId() != $lastLogin->token) {
          $request->session()->flush();
          return redirect('login')->with('warning', __('login.other-device-logout'));
        }

        // 3) jogosultság ellenőrzés
        if (!$this->isAuthorized($utype)) {
          throw new AuthFailException(__('auth.access-denied'), 'home-redirect');
        }
      } catch (AuthFailException $e) {
        return !$request->expectsJson()
          ? redirect($e->getFallback())->with('error', $e->getMessage())
          : abort(403, $e->getMessage());
      }
    } else {
      // guest route → ha már be van lépve, irány haza
      try {
        if (Auth::isLoggedIn()) {
          return redirect('home-redirect');
        }
      } catch (AuthFailException $e) {
        return !$request->expectsJson()
          ? redirect($e->getFallback())->with('error', $e->getMessage())
          : abort(403, $e->getMessage());
      }
    }

    return $next($request);
  }

  /** Be van-e jelentkezve */
  public static function isLoggedIn()
  {
    return session('uid') ? true : false;
  }

  /**
   * Jogosultság ellenőrzés az adott route-hoz kért $utype alapján
   */
  public static function isAuthorized($utype)
  {
    if (!session()->has('utype')) {
      return false;
    }

    $current = session('utype');

    // ⬇⬇ Globális felülbírálók
    if ($current === UserType::SUPERADMIN) {
      return true; // superadmin mindenhova bemehet
    }
    if ($current === UserType::ADMIN) {
      return true; // admin is mindenhova bemehet (megtartva a régi viselkedést)
    }

    // Pontos egyezés
    if ($utype === $current) {
      return true;
    }

    // CEO használhat "normal" felületet is
    if ($utype === UserType::NORMAL && $current === UserType::CEO) {
      return true;
    }

    return false;
  }
}
