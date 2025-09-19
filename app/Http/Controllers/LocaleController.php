<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function set(Request $request)
{
    $data = $request->validate([
        'locale' => ['required', 'in:hu,en'],
        'redirect' => ['nullable','string'],
    ]);

    $locale = $data['locale'];

    // sessionbe
    session(['locale' => $locale]);

    // DB-be ha be van jelentkezve
    if (auth()->check()) {
        auth()->user()->forceFill(['locale' => $locale])->save();
    }

    return redirect()->to($data['redirect'] ?? url()->previous() ?? '/');
}
}
