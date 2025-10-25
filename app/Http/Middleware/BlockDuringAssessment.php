<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AssessmentService;

class BlockDuringAssessment
{
    /**
     * Handle an incoming request.
     * 
     * Block access to configuration pages while an assessment is running.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (AssessmentService::isAssessmentRunning()) {
            // If AJAX request, return JSON error
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => __('admin/home.assessment-warning')
                ], 403);
            }
            
            // For regular requests, redirect to admin home with error message
            return redirect()
                ->route('admin.home')
                ->with('error', __('admin/home.assessment-warning'));
        }

        return $next($request);
    }
}