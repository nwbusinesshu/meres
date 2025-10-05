<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
   * 
   * SECURITY FIX: Admins now require 'admin' role in the current organization
   */
  public static function isAuthorized($utype)
  {
    if (!session()->has('utype')) {
      return false;
    }

    $current = session('utype');
    $uid = session('uid');
    $orgId = session('org_id');

    // ========================================
    // SUPERADMIN: Full system access (developers)
    // ========================================
    if ($current === UserType::SUPERADMIN) {
      return true;
    }

    // ========================================
    // ADMIN: Organization-level authorization
    // ========================================
    if ($current === UserType::ADMIN) {
      // Admin routes require both:
      // 1. User type = 'admin'
      // 2. Role = 'admin' in current organization
      
      if ($utype === UserType::ADMIN) {
        // Check if user has admin role in the current organization
        if (!$orgId) {
          Log::warning('Auth: Admin user without org_id in session', [
            'user_id' => $uid,
            'utype' => $current
          ]);
          return false;
        }

        $hasAdminRole = DB::table('organization_user')
          ->where('organization_id', $orgId)
          ->where('user_id', $uid)
          ->where('role', 'admin')
          ->exists();

        if (!$hasAdminRole) {
          Log::warning('Auth: Admin user lacks admin role in current org', [
            'user_id' => $uid,
            'org_id' => $orgId,
            'requested_route_type' => $utype
          ]);
          return false;
        }

        return true;
      }

      // Admins can also access NORMAL routes (for viewing assessments, results, etc.)
      if ($utype === UserType::NORMAL) {
        return true;
      }

      // Admins cannot access other route types (CEO-only, MANAGER-only)
      return false;
    }

    // ========================================
    // Regular users: Standard authorization
    // ========================================
    
    // Pontos egyezés
    if ($utype === $current) {
      return true;
    }

    // CEO használhat "normal" felületet is
    if ($utype === UserType::NORMAL && $current === UserType::CEO) {
      return true;
    }

    // MANAGER használhat "normal" felületet is
    if ($utype === UserType::NORMAL && $current === UserType::MANAGER) {
      return true;
    }

    return false;
  }
}