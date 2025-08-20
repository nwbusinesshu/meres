<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Assessment;

class SuperAdminController extends Controller
{
    public function dashboard()
{
    $organizations = Organization::orderBy('name')->get();
    return view('superadmin.dashboard', compact('organizations'));
}
public function store(Request $request)
{
    $request->validate([
        'org_name'     => 'required|string|max:255',
        'admin_name'   => 'required|string|max:255',
        'admin_email'  => 'required|email|max:255',
    ]);

    // 1. Szervezet létrehozása
    $org = \App\Models\Organization::create([
        'name' => $request->org_name,
        'created_at' => now(),
    ]);

    // 2. Admin user létrehozása vagy visszakeresése
    $user = \App\Models\User::firstOrCreate(
        ['email' => $request->admin_email],
        [
            'name' => $request->admin_name,
            'type' => \App\Models\Enums\UserType::ADMIN,
        ]
    );

    // 3. Kapcsolat létrehozása a szervezet és user között
    $org->users()->attach($user->id, [
        'role' => 'admin',
    ]);

    return response()->json(['success' => true]);
}



}
