<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = null;
        $availableLocales = config('app.available_locales', ['hu' => 'Magyar', 'en' => 'English']);
        $allowed = array_keys($availableLocales);

        // AUTHENTICATED USER: DB locale has highest priority
        if (auth()->check()) {
            // 1) Auth user -> DB value (highest priority for logged-in users)
            $locale = auth()->user()->locale ?? null;
            
            // 2) Session fallback
            if (!$locale) {
                $locale = session('locale');
            }
            
            // 3) Cookie fallback
            if (!$locale) {
                $locale = $request->cookie('app_locale');
            }
            
            // 4) Config fallback
            if (!$locale) {
                $locale = config('app.locale', 'hu');
            }
        } 
        // GUEST USER: URL/Cookie have priority
        else {
            // 1) URL locale (from route parameter - highest priority for guests)
            $urlLocale = $request->route('locale');
            if ($urlLocale && in_array($urlLocale, $allowed, true)) {
                $locale = $urlLocale;
                
                // Store URL locale in session and cookie for persistence
                session(['locale' => $locale]);
                Cookie::queue('app_locale', $locale, 60 * 24 * 365); // 1 year
            }
            
            // 2) Cookie fallback (from previous visit)
            if (!$locale) {
                $locale = $request->cookie('app_locale');
            }
            
            // 3) Session fallback
            if (!$locale) {
                $locale = session('locale');
            }
            
            // 4) Config fallback
            if (!$locale) {
                $locale = config('app.locale', 'hu');
            }
        }

        // Validation: ensure locale is allowed
        if (!in_array($locale, $allowed, true)) {
            $locale = config('app.locale', 'hu');
        }

        // Always update session
        session(['locale' => $locale]);

        // Set application locale
        app()->setLocale($locale);

        return $next($request);
    }
}