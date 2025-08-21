<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Assessment;

class SuperAdminController extends Controller
{
    public function dashboard()
{
    $organizations = Organization::whereNull('removed_at')
    ->orderBy('name')
    ->get();
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

public function update(Request $request)
{
    $request->validate([
        'org_id'       => 'required|exists:organization,id',
        'name'         => 'required|string|max:255',
        'admin_name'   => 'nullable|string|max:255',
        'admin_email'  => 'nullable|email|max:255',
        'admin_remove' => 'nullable|in:0,1',
    ]);

    $org = \App\Models\Organization::findOrFail($request->org_id);
    $org->name = $request->name;
    $org->save();

    // 1. Ha admin törlésre van jelölve
    if ($request->admin_remove == '1') {
        $admin = $org->users()->wherePivot('role', 'admin')->first();
        if ($admin) {
            // Kapcsolat törlése
            $org->users()->detach($admin->id);

            // Ha nincs más szervezeti kapcsolata, akkor soft delete
            if ($admin->organizations()->count() === 0) {
                $admin->removed_at = now();
                $admin->save();
            }
        }
    }

    // 2. Új admin hozzáadása (csak ha van név és email)
    if ($request->filled('admin_name') && $request->filled('admin_email')) {
        $existing = \App\Models\User::where('email', $request->admin_email)->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'admin_email' => ['Ez az e-mail cím már létezik egy másik felhasználónál. Csak új admin regisztrálható.']
                ]
            ], 422);
        }

        $newAdmin = \App\Models\User::create([
            'name'       => $request->admin_name,
            'email'      => $request->admin_email,
            'type'       => \App\Models\Enums\UserType::ADMIN,
            'created_at' => now(),
        ]);

        $org->users()->attach($newAdmin->id, [
            'role' => 'admin',
        ]);
    }

    return response()->json(['success' => true]);
}



public function delete(Request $request)
{
    $request->validate([
        'org_id' => 'required|integer|exists:organization,id',
    ]);

    $org = Organization::findOrFail($request->org_id);

    $users = $org->users;

    foreach ($users as $user) {
        $otherOrgs = $user->organizations()->where('organization_id', '!=', $org->id)->count();

        if ($otherOrgs === 0) {
            // nincs más céges kapcsolódása → soft delete
            $user->update(['removed_at' => now()]);
        } else {
            // csak a kapcsolatot töröljük
            $org->users()->detach($user->id);
        }
    }

    // szervezet soft delete
    $org->update(['removed_at' => now()]);

    return response()->json(['success' => true]);
}

}