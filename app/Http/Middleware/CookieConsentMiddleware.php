<?php

namespace App\Http\Middleware;

use App\Models\CookieConsent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class CookieConsentMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for API routes, AJAX requests, and admin routes
        if ($request->is('api/*') || 
            $request->ajax() || 
            $request->is('admin/*') ||
            $request->is('cookie-consent/*')) {
            return $next($request);
        }

        // Check for existing consent
        $consent = $this->getExistingConsent($request);
        $showBanner = !$consent || $this->needsConsentUpdate($consent);

        // Share consent data with all views
        View::share('cookieConsent', [
            'showBanner' => $showBanner,
            'hasConsent' => !!$consent,
            'consent' => $consent,
        ]);

        return $next($request);
    }

    /**
     * Get existing consent from database or cookie
     */
    private function getExistingConsent(Request $request): ?CookieConsent
    {
        // First check database
        $consent = CookieConsent::getLatestConsent(
            session()->getId(),
            Auth::id()
        );

        if ($consent) {
            return $consent;
        }

        // Fallback to cookie if no database record
        $cookieConsent = $request->cookie('cookie_consent');
        if ($cookieConsent) {
            try {
                $data = json_decode($cookieConsent, true);
                if (is_array($data) && isset($data['version'])) {
                    // Create a temporary model for consistency
                    return new CookieConsent([
                        'necessary' => $data['necessary'] ?? true,
                        'analytics' => $data['analytics'] ?? false,
                        'marketing' => $data['marketing'] ?? false,
                        'preferences' => $data['preferences'] ?? false,
                        'consent_version' => $data['version'] ?? '1.0',
                        'consent_date' => isset($data['timestamp']) 
                            ? \Carbon\Carbon::createFromTimestamp($data['timestamp']) 
                            : now(),
                    ]);
                }
            } catch (\Exception $e) {
                // Invalid cookie data, treat as no consent
            }
        }

        return null;
    }

    /**
     * Check if consent needs to be updated (version changed, expired, etc.)
     */
    private function needsConsentUpdate(?CookieConsent $consent): bool
    {
        if (!$consent) {
            return true;
        }

        $currentVersion = config('cookie-consent.version', '1.0');
        $maxAge = config('cookie-consent.max_age_days', 365);

        // Check version
        if ($consent->consent_version !== $currentVersion) {
            return true;
        }

        // Check age
        if ($consent->consent_date->addDays($maxAge)->isPast()) {
            return true;
        }

        return false;
    }
}