<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\Organization;

class SetOrganization
{
    public function handle(Request $request, Closure $next)
    {
        $uid = session('uid');
        /** @var User $user */
        $user = $uid ? User::find($uid) : null;

        if (!$user) {
            return redirect()->route('login');
        }

        $isSuperAdmin = (session('utype') === UserType::SUPERADMIN);
        $orgId = session('org_id');
        $userOrgIds = $user->organizations()->pluck('organization.id')->toArray();

        if (!$orgId) {
            if ($isSuperAdmin) {
                return redirect()->route('org.select');
            }
            if (count($userOrgIds) === 1) {
                session(['org_id' => $userOrgIds[0]]);
            } elseif (count($userOrgIds) > 1) {
                return redirect()->route('org.select');
            } else {
                abort(403, __('auth.no-organization'));
            }
        } else {
            if (!$isSuperAdmin && !in_array($orgId, $userOrgIds)) {
                session()->forget('org_id');
                return redirect()->route('org.select');
            }
        }

        return $next($request);
    }
}
