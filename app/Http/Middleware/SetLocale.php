<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    private array $allowed = ['hu', 'en'];

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

        // validálás
        if (!in_array($locale, $this->allowed, true)) {
            $locale = 'hu';
        }

        // sessionbe mindig tegyük
        session(['locale' => $locale]);

        // app-ra is állítsuk
        app()->setLocale($locale);

        return $next($request);
    }
}
