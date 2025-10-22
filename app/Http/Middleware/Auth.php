<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Exceptions\AuthFailException;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\Enums\OrgRole;


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
   * 
   * ✅ UPDATED: Now explicitly supports OrgRole enum values
   * 
   * @param string $utype UserType or OrgRole constant
   * @return bool
   */
  public static function isAuthorized($utype)
{
    if (!session()->has('utype')) {
        return false;
    }

    $current = session('utype');     // 'superadmin' or 'normal'
    $orgRole = session('org_role');  // 'admin', 'manager', 'ceo', 'employee'
    $uid = session('uid');
    $orgId = session('org_id');

    // SUPERADMIN: Full system access
    if ($current === UserType::SUPERADMIN) {
        return true;
    }

    // NORMAL users: Check organization role
    if ($current === UserType::NORMAL) {
        
        // ✅ Route requires ADMIN (supports both deprecated UserType and new OrgRole)
        if ($utype === OrgRole::ADMIN || $utype === UserType::ADMIN || $utype === 'admin') {
            return $orgRole === OrgRole::ADMIN;
        }
        
        // ✅ Route requires CEO (supports both deprecated UserType and new OrgRole)
        if ($utype === OrgRole::CEO || $utype === UserType::CEO || $utype === 'ceo') {
            // Admin can access CEO routes too (hierarchical)
            return in_array($orgRole, [OrgRole::ADMIN, OrgRole::CEO]);
        }
        
        // ✅ Route requires MANAGER (supports both deprecated UserType and new OrgRole)
        if ($utype === OrgRole::MANAGER || $utype === UserType::MANAGER || $utype === 'manager') {
            // Admin can access manager routes too (hierarchical)
            return in_array($orgRole, [OrgRole::ADMIN, OrgRole::MANAGER]);
        }
        
        // ✅ Route requires EMPLOYEE (new - only OrgRole)
        if ($utype === OrgRole::EMPLOYEE || $utype === 'employee') {
            // All organization members can access employee routes (hierarchical)
            return in_array($orgRole, [OrgRole::ADMIN, OrgRole::CEO, OrgRole::MANAGER, OrgRole::EMPLOYEE]);
        }
        
        // Route requires NORMAL (any logged-in user)
        if ($utype === UserType::NORMAL || $utype === 'normal') {
            return true; // All roles can access normal routes
        }
    }

    // GUEST routes
    if ($utype === UserType::GUEST) {
        return $current === UserType::GUEST;
    }

    return false;
}
}