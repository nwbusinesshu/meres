<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SetOrganization
{
    /**
     * Handle an incoming request.
     * 
     * This middleware ensures users can only access organizations they belong to.
     * 
     * SECURITY NOTE: We do NOT regenerate sessions on org validation failures because:
     * - The user is already authenticated (identity hasn't changed)
     * - Authorization check is sufficient protection
     * - Session regeneration should ONLY happen during login/logout/password change
     * - Regeneration causes UX issues (race conditions, broken AJAX, multi-tab conflicts)
     * 
     * Instead, we rely on:
     * - Strict authorization checks
     * - Comprehensive audit logging
     * - Rate limiting (handled at route level)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $uid = session('uid');

        /** @var User|null $user */
        $user = $uid ? User::find($uid) : null;

        // No user session? Redirect to login
        if (!$user) {
            Log::warning('SetOrganization: No user found in session', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);
            return redirect()->route('login');
        }

        $isSuperAdmin = (session('utype') === UserType::SUPERADMIN);
        $orgId        = session('org_id');
        $userOrgIds   = $user->organizations()->pluck('organization.id')->toArray();

        // ========================================
        // CASE 1: No org_id in session
        // ========================================
        if (!$orgId) {
            if ($isSuperAdmin) {
                // SuperAdmins need to select an org (unless they're in the superadmin section)
                if (!$request->routeIs('superadmin.*')) {
                    Log::info('SetOrganization: SuperAdmin needs to select org', [
                        'user_id' => $uid,
                        'url' => $request->fullUrl(),
                    ]);
                    return redirect()->route('org.select');
                }
                // SuperAdmin in superadmin section - allow without org
            } else {
                // Regular users must have at least one organization
                if (count($userOrgIds) === 1) {
                    // Automatically set the only org they belong to
                    session(['org_id' => $userOrgIds[0]]);
                    $request->attributes->set('org_id', $userOrgIds[0]);
                    
                    Log::info('SetOrganization: Auto-assigned single org', [
                        'user_id' => $uid,
                        'org_id' => $userOrgIds[0],
                    ]);
                } elseif (count($userOrgIds) > 1) {
                    // Multiple orgs - user needs to select one
                    Log::info('SetOrganization: User needs to select org (multiple available)', [
                        'user_id' => $uid,
                        'available_orgs' => count($userOrgIds),
                    ]);
                    return redirect()->route('org.select');
                } else {
                    // No organizations assigned - access denied
                    Log::warning('SetOrganization: User has no organizations', [
                        'user_id' => $uid,
                        'user_email' => $user->email,
                    ]);
                    abort(403, __('auth.no-organization'));
                }
            }
        } else {
            // ========================================
            // CASE 2: org_id exists in session - validate it
            // ========================================
            
            // SuperAdmins can access any org (no validation needed)
            if ($isSuperAdmin) {
                $request->attributes->set('org_id', $orgId);
            } else {
                // Regular users: verify they have access to this org
                if (!in_array($orgId, $userOrgIds, true)) {
                    // SECURITY: Unauthorized organization access attempt detected
                    Log::warning('SetOrganization: UNAUTHORIZED ORG ACCESS ATTEMPT', [
                        'user_id' => $uid,
                        'user_email' => $user->email,
                        'user_type' => session('utype'),
                        'attempted_org_id' => $orgId,
                        'user_org_ids' => $userOrgIds,
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'url' => $request->fullUrl(),
                        'referer' => $request->header('referer'),
                    ]);
                    
                    // Clear the invalid org_id from session
                    session()->forget('org_id');
                    
                    // SECURITY FIX: We do NOT regenerate the session here
                    // Reason: User is still authenticated, just accessing wrong org
                    // The authorization check (redirect) is sufficient protection
                    
                    return redirect()
                        ->route('org.select')
                        ->with('error', 'Nincs jogosultságod ehhez a szervezethez. Kérjük, válassz egy másikat.');
                }
                
                // User has valid access to this org
                $request->attributes->set('org_id', $orgId);
            }
        }

        return $next($request);
    }
}