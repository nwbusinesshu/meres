<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
   public function set(Request $request)
{
    $available = array_keys((array) config('app.available_locales', ['hu' => 'Magyar', 'en' => 'English']));

    $data = $request->validate([
        'locale'   => ['required', 'in:' . implode(',', $available)],
        'redirect' => ['nullable', 'string'],
    ]);

    $locale = $data['locale'];

    // session + app
    session(['locale' => $locale]);
    app()->setLocale($locale);

    // ha be van jelentkezve, frissítsük az adatbázisban is
    if (auth()->check()) {
        auth()->user()->forceFill(['locale' => $locale])->save();
    }

    $redirectTo = $data['redirect'] ?? url()->previous() ?? '/';
    return redirect()->to($redirectTo);
}

}
