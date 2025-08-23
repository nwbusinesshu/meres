<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Assessment;

class SuperAdminController extends Controller
{
public function dashboard()
{
    $organizations = \App\Models\Organization::with(['profile', 'users' => function ($q) {
        $q->wherePivot('role', 'admin')->orWherePivot('role', 'employee');
    }])
    ->whereNull('removed_at')
    ->get()
    ->each(function ($org) {
        $admin = $org->users->firstWhere('pivot.role', 'admin');
        $employees = $org->users->where('pivot.role', 'employee')->count();

        // dinamikus attribútum hozzáadása
        $org->admin_name = $admin?->name;
        $org->admin_email = $admin?->email;
        $org->employee_count = $employees;
    });

    return view('superadmin.dashboard', compact('organizations'));
}



public function store(Request $request)
{
    $request->validate([
        'org_name'           => 'required|string|max:255',
        'admin_name'         => 'required|string|max:255',
        'admin_email'        => 'required|email|max:255',
        'tax_number'         => 'nullable|string|max:50',
        'billing_address'    => 'nullable|string|max:255',
        'subscription_type'  => 'nullable|string|max:50',
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

    // 4. OrganizationProfile létrehozása
    \App\Models\OrganizationProfile::create([
        'organization_id'   => $org->id,
        'tax_number'        => $request->tax_number,
        'billing_address'   => $request->billing_address,
        'subscription_type' => $request->subscription_type,
        'created_at'        => now(),
    ]);

    // 5. Alap CEO rangok másolása az új céghez
    $defaultRanks = \App\Models\CeoRank::whereNull('organization_id')->get();

    foreach ($defaultRanks as $rank) {
        \App\Models\CeoRank::create([
            'organization_id' => $org->id,
            'name'            => $rank->name,
            'value'           => $rank->value,
            'min'             => $rank->min,
            'max'             => $rank->max,
            'removed_at'      => null,
        ]);
    }

    return response()->json(['success' => true]);
}

public function update(Request $request)
{
    \Log::info('Update route hit with method: ' . $request->method());

    $request->validate([
        'org_id'            => 'required|exists:organization,id',
        'org_name'          => 'required|string|max:255',
        'admin_name'        => 'nullable|string|max:255',
        'admin_email'       => 'nullable|email|max:255',
        'admin_remove'      => 'nullable|in:0,1',
        'tax_number'        => 'nullable|string|max:50',
        'billing_address'   => 'nullable|string|max:255',
        'subscription_type' => 'nullable|in:free,pro',
    ]);

    $org = \App\Models\Organization::findOrFail($request->org_id);
    $admin = $org->users()->wherePivot('role', 'admin')->first();

    // Szervezet neve frissítése
    $org->name = $request->org_name;
    $org->save();

    // Profil frissítése vagy létrehozása
    $profile = \App\Models\OrganizationProfile::firstOrNew(['organization_id' => $org->id]);
    $profile->tax_number = $request->tax_number;
    $profile->billing_address = $request->billing_address;
    $profile->subscription_type = $request->subscription_type;
    $profile->updated_at = now();
    $profile->save();

    // Admin törlés logika
    if ($request->admin_remove == '1') {
        if ($admin) {
            $org->users()->detach($admin->id);

            if ($admin->organizations()->count() === 0) {
                $admin->removed_at = now();
                $admin->save();
            }
        }
    }

    // Új admin hozzáadása
    if ($request->filled('admin_name') && $request->filled('admin_email')) {
        $existing = \App\Models\User::where('email', $request->admin_email)
            ->where('id', '!=', optional($admin)->id)
            ->first();

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

    // Felhasználók kezelése
    foreach ($org->users as $user) {
        $otherOrgs = $user->organizations()->where('organization_id', '!=', $org->id)->count();

        if ($otherOrgs === 0) {
            $user->update(['removed_at' => now()]);
        } else {
            $org->users()->detach($user->id);
        }
    }

    // Szervezet soft delete
    $org->update(['removed_at' => now()]);

    // Profil soft delete
    \App\Models\OrganizationProfile::where('organization_id', $org->id)->update(['removed_at' => now()]);

    // CeoRank soft delete
    \App\Models\CeoRank::where('organization_id', $org->id)->update(['removed_at' => now()]);

    return response()->json(['success' => true]);
}



public function exitCompany(Request $request)
{
    $request->session()->forget('org_id');
    return redirect()->route('superadmin.dashboard')->with('info', 'Kiléptél a cégből.');
}

public function getOrgData($orgId)
{
    $org = Organization::where('id', $orgId)->whereNull('removed_at')->firstOrFail();

    $profile = $org->profile; // lehet null

    $admin = $org->users()->wherePivot('role', 'admin')->first();

    return response()->json([
        'org_name' => $org->name,
        'tax_number' => $profile?->tax_number ?? '',
        'billing_address' => $profile?->billing_address ?? '',
        'subscription_type' => $profile?->subscription_type ?? '',
        'admin_name' => $admin?->name ?? '',
        'admin_email' => $admin?->email ?? '',
    ]);
}


}