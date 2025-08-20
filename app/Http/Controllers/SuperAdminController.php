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

public function update(Request $request, $id)
{
    $org = Organization::findOrFail($id);
    $org->name = $request->org_name;
    $org->save();

    if ($request->admin_remove) {
        $admin = $org->users()->wherePivot('role', 'admin')->first();
        if ($admin) {
            $org->users()->detach($admin->id);

            // Csak akkor töröljük, ha nincs más céges kapcsolódása
            if ($admin->organizations()->count() === 0) {
                $admin->removed_at = now();
                $admin->save();
            }
        }
    } else {
        // ha új admin megadva
        if ($request->filled('admin_email')) {
            $user = User::firstOrCreate(
                ['email' => $request->admin_email],
                [
                    'name' => $request->admin_name,
                    'type' => UserType::ADMIN
                ]
            );
            $org->users()->syncWithoutDetaching([$user->id => ['role' => 'admin']]);
        }
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