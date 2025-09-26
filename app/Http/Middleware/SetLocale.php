<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = null;

        // 1) Auth user -> DB érték
        if (auth()->check()) {
            $locale = auth()->user()->locale ?? null;
        }

        // 2) Session fallback
        if (!$locale) {
            $locale = session('locale');
        }

        // 3) Config fallback
        if (!$locale) {
            $locale = config('app.locale', 'hu');
        }

        // FIXED: Get allowed languages from config instead of hardcoding
        $availableLocales = config('app.available_locales', ['hu' => 'Magyar', 'en' => 'English']);
        $allowed = array_keys($availableLocales);

        // validálás
        if (!in_array($locale, $allowed, true)) {
            $locale = config('app.locale', 'hu');
        }

        // sessionbe mindig tegyük
        session(['locale' => $locale]);

        // app-ra is állítsük
        app()->setLocale($locale);

        return $next($request);
    }
}