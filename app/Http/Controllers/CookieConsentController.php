<?php

namespace App\Http\Controllers;

use App\Models\CookieConsent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CookieConsentController extends Controller
{
    /**
     * Store user's cookie consent preferences
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'necessary' => 'boolean',
            'analytics' => 'boolean',
        ]);

        // Necessary cookies are always true
        $validated['necessary'] = true;

        $consent = CookieConsent::create([
            'session_id' => session()->getId(),
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'necessary' => $validated['necessary'],
            'analytics' => $validated['analytics'] ?? false,
            'marketing' => false, // Always false now
            'preferences' => false, // Always false now
            'consent_date' => now(),
            'consent_version' => config('cookie-consent.version', '1.0'),
            'user_agent' => [
                'browser' => $request->userAgent(),
                'platform' => $request->header('sec-ch-ua-platform'),
            ],
        ]);

        // Set consent cookie for frontend
        cookie()->queue(
            'cookie_consent',
            json_encode([
                'necessary' => $consent->necessary,
                'analytics' => $consent->analytics,
                'version' => $consent->consent_version,
                'timestamp' => $consent->consent_date->timestamp,
            ]),
            60 * 24 * 365 // 1 year
        );

        return response()->json([
            'success' => true,
            'message' => __('global.cookie_preferences_saved'),
            'consent' => $consent->getConsentedTypes(),
        ]);
    }

    /**
     * Accept all cookies (necessary + analytics)
     */
    public function acceptAll(Request $request): JsonResponse
    {
        return $this->store($request->merge([
            'necessary' => true,
            'analytics' => true,
        ]));
    }

    /**
     * Accept only necessary cookies
     */
    public function acceptNecessary(Request $request): JsonResponse
    {
        return $this->store($request->merge([
            'necessary' => true,
            'analytics' => false,
        ]));
    }

    /**
     * Get current consent status
     */
    public function status(Request $request): JsonResponse
    {
        $consent = CookieConsent::getLatestConsent(
            session()->getId(),
            Auth::id()
        );

        if (!$consent) {
            return response()->json([
                'has_consent' => false,
                'consent' => null,
            ]);
        }

        return response()->json([
            'has_consent' => true,
            'consent' => [
                'necessary' => $consent->necessary,
                'analytics' => $consent->analytics,
                'version' => $consent->consent_version,
                'date' => $consent->consent_date->toISOString(),
            ],
        ]);
    }

    /**
     * Show cookie preferences page (for manual management)
     */
    public function preferences()
    {
        $consent = CookieConsent::getLatestConsent(
            session()->getId(),
            Auth::id()
        );

        return view('cookie-consent.preferences', compact('consent'));
    }
}