<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Enums\UserType;
use App\Models\User;

class SetOrganization
{
    public function handle(Request $request, Closure $next)
    {
        $uid = session('uid');

        /** @var User|null $user */
        $user = $uid ? User::find($uid) : null;

        if (!$user) {
            return redirect()->route('login');
        }

        $isSuperAdmin = (session('utype') === UserType::SUPERADMIN);
        $orgId        = session('org_id');
        $userOrgIds   = $user->organizations()->pluck('organization.id')->toArray();

        if (!$orgId) {
            if ($isSuperAdmin) {
                // Nincs org_id beállítva – ha nem a superadmin szekcióban jár, menjen org választóra
                if (!$request->routeIs('superadmin.*')) {
                    return redirect()->route('org.select');
                }
            } else {
                if (count($userOrgIds) === 1) {
                    // Egyetlen org – automatikusan beállítjuk, és session ID-t regenerálunk
                    $request->session()->regenerate();
                    session(['org_id' => $userOrgIds[0]]);
                    // (opcionális) tegyük a request attribútumai közé is
                    $request->attributes->set('org_id', $userOrgIds[0]);
                } elseif (count($userOrgIds) > 1) {
                    return redirect()->route('org.select');
                } else {
                    abort(403, __('auth.no-organization'));
                }
            }
        } else {
            // Van org_id – ellenőrizzük, hogy tényleg a user orgjai között van
            if (!$isSuperAdmin && !in_array($orgId, $userOrgIds, true)) {
                $request->session()->regenerate();
                session()->forget('org_id');
                return redirect()->route('org.select');
            }
            // (opcionális) a kérést is címkézzük
            $request->attributes->set('org_id', $orgId);
        }

        return $next($request);
    }
}
