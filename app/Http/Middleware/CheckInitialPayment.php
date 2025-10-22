<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\OrgRole;


class CheckInitialPayment
{
    /**
     * Handle an incoming request.
     * 
     * If admin has an unpaid initial payment (assessment_id is null and status is not 'paid'),
     * redirect to payments page.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only check for admin users
        $orgRole = session('org_role');
if ($orgRole !== \App\Models\Enums\OrgRole::ADMIN) {
    return $next($request);
}

        // Get organization ID from session
        $orgId = session('org_id');
        if (!$orgId) {
            return $next($request);
        }

        // Check if there's an unpaid initial payment (assessment_id is null and not paid)
        $hasUnpaidInitialPayment = DB::table('payments')
            ->where('organization_id', $orgId)
            ->whereNull('assessment_id')
            ->where('status', '!=', 'paid')
            ->exists();

        // If no unpaid initial payment, proceed normally
        if (!$hasUnpaidInitialPayment) {
            return $next($request);
        }

        // Allow access to payments routes
        if ($request->routeIs('admin.payments.*')) {
            return $next($request);
        }

        // Allow access to logout
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        // Allow access to locale switching
        if ($request->routeIs('locale.set')) {
            return $next($request);
        }

        // Allow AJAX requests for payment operations
        if ($request->ajax() && $request->routeIs('admin.payments.*')) {
            return $next($request);
        }

        // Redirect to payments page with a message
        return redirect()
            ->route('admin.payments.index')
            ->with('warning', 'Kérjük, rendezze az első fizetést a rendszer használatához.');
    }
}