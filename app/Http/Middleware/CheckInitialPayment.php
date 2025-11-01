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
     * If admin has an unpaid initial payment (assessment_id is null and status is not 'paid'):
     * - Within 5 days: Allow access, block only assessment creation
     * - After 5 days: Block all access except payments page
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
        $unpaidInitialPayment = DB::table('payments')
            ->where('organization_id', $orgId)
            ->whereNull('assessment_id')
            ->where('status', '!=', 'paid')
            ->first();

        // If no unpaid initial payment, proceed normally
        if (!$unpaidInitialPayment) {
            return $next($request);
        }

        // Calculate trial period (5 days from payment creation)
        $paymentCreatedAt = \Carbon\Carbon::parse($unpaidInitialPayment->created_at);
        $trialEndsAt = $paymentCreatedAt->copy()->addDays(5);
        $isWithinTrial = now()->lessThan($trialEndsAt);

        // If within trial period, allow access (assessment blocking is handled in AdminAssessmentController)
        if ($isWithinTrial) {
            // Store trial info in session for views to display
            session(['trial_ends_at' => $trialEndsAt->toDateTimeString()]);
            return $next($request);
        }

        // Trial expired - block access except for specific routes
        
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
            ->with('warning', 'A próbaidőszak lejárt. Kérjük, rendezze az első fizetést a rendszer használatához.');
    }
}