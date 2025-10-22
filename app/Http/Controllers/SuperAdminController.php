<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Assessment;
use Illuminate\Validation\Rule;

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

    $country = strtoupper($request->input('country_code', 'HU'));

    $request->validate([
        'org_name'           => 'required|string|max:255',
        'admin_name'         => 'required|string|max:255',
        'admin_email'        => 'required|email|max:255',

        'country_code'       => 'required|string|size:2',
        'postal_code'        => 'nullable|string|max:16',
        'region'             => 'nullable|string|max:64',
        'city'               => 'nullable|string|max:64',
        'street'             => 'nullable|string|max:128',
        'house_number'       => 'nullable|string|max:32',

        // HU esetén kötelező és mintás, más ország esetén nem kötelező
        'tax_number'         => [
            'nullable','string','max:50',
            Rule::requiredIf($country === 'HU'),
            'regex:/^\d{8}-\d-\d{2}$/'
        ],

        // nem-HU esetén kötelező, HU esetén opcionális
        'eu_vat_number'      => [
            'nullable','string','max:32',
            Rule::requiredIf($country !== 'HU'),
            'regex:/^[A-Z]{2}[A-Za-z0-9]{2,12}$/'
        ],

        'subscription_type'  => 'nullable|in:free,pro',
    ]);

    // 1) Szervezet
    $org = \App\Models\Organization::create([
        'name'       => $request->org_name,
        'created_at' => now(),
    ]);

    // 2) Admin user
    $user = \App\Models\User::firstOrCreate(
    ['email' => $request->admin_email],
    [
        'name' => $request->admin_name,
        'type' => UserType::NORMAL,  // ✅ CORRECT - System level type
    ]
);

// Admin role is properly set via pivot table:
$org->users()->attach($user->id, ['role' => OrgRole::ADMIN]);  // ✅ Org level role

    // 4) Profil
    \App\Models\OrganizationProfile::create([
        'organization_id'   => $org->id,
        'country_code'      => $country,
        'postal_code'       => $request->postal_code,
        'region'            => $request->region,
        'city'              => $request->city,
        'street'            => $request->street,
        'house_number'      => $request->house_number,
        'tax_number'        => $request->tax_number,
        'eu_vat_number'     => strtoupper((string) $request->eu_vat_number),
        'subscription_type' => $request->subscription_type,
        'created_at'        => now(),
    ]);

    // 5) Alap CEO rangok
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

    $country = strtoupper($request->input('country_code', 'HU'));

    $request->validate([
        'org_id'            => 'required|exists:organization,id',
        'org_name'          => 'required|string|max:255',

        'admin_name'        => 'nullable|string|max:255',
        'admin_email'       => 'nullable|email|max:255',
        'admin_remove'      => 'nullable|in:0,1',

        'country_code'      => 'required|string|size:2',
        'postal_code'       => 'nullable|string|max:16',
        'region'            => 'nullable|string|max:64',
        'city'              => 'nullable|string|max:64',
        'street'            => 'nullable|string|max:128',
        'house_number'      => 'nullable|string|max:32',

        'tax_number'        => [
            'nullable','string','max:50',
            Rule::requiredIf($country === 'HU'),
            'regex:/^\d{8}-\d-\d{2}$/'
        ],
        'eu_vat_number'     => [
            'nullable','string','max:32',
            Rule::requiredIf($country !== 'HU'),
            'regex:/^[A-Z]{2}[A-Za-z0-9]{2,12}$/'
        ],

        'subscription_type' => 'nullable|in:free,pro',
    ]);

    $org   = \App\Models\Organization::findOrFail($request->org_id);
    $admin = $org->users()->wherePivot('role', 'admin')->first();

    // szervezet név
    $org->name = $request->org_name;
    $org->save();

    // profil
    $profile = \App\Models\OrganizationProfile::firstOrNew(['organization_id' => $org->id]);
    $profile->country_code      = $country;
    $profile->postal_code       = $request->postal_code;
    $profile->region            = $request->region;
    $profile->city              = $request->city;
    $profile->street            = $request->street;
    $profile->house_number      = $request->house_number;
    $profile->tax_number        = $request->tax_number;
    $profile->eu_vat_number     = strtoupper((string) $request->eu_vat_number);
    $profile->subscription_type = $request->subscription_type;
    $profile->updated_at        = now();
    $profile->save();

    // admin törlés
    if ($request->admin_remove === '1') {
        if ($admin) {
            $org->users()->detach($admin->id);
            if ($admin->organizations()->count() === 0) {
                $admin->removed_at = now();
                $admin->save();
            }
        }
    }

    // admin csere
    if ($request->filled('admin_name') && $request->filled('admin_email')) {
        $existing = \App\Models\User::where('email', $request->admin_email)
            ->where('id', '!=', optional($admin)->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'errors'  => [
                    'admin_email' => ['Ez az e-mail cím már létezik egy másik felhasználónál. Csak új admin regisztrálható.']
                ]
            ], 422);
        }

        $newAdmin = \App\Models\User::create([
            'name'       => $request->admin_name,
            'email'      => $request->admin_email,
            'type'       => UserType::NORMAL,  // ✅ CORRECT - System level type
            'created_at' => now(),
        ]);

        $org->users()->attach($newAdmin->id, ['role' => OrgRole::ADMIN]);  // ✅ Org level role
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
    $org = \App\Models\Organization::where('id', $orgId)->whereNull('removed_at')->firstOrFail();
    $profile = $org->profile; // lehet null
    $admin = $org->users()->wherePivot('role', 'admin')->first();

    return response()->json([
        'org_name'          => $org->name,

        // számlázási adatok (új, struktúrált)
        'country_code'      => $profile?->country_code ?? 'HU',
        'postal_code'       => $profile?->postal_code ?? '',
        'region'            => $profile?->region ?? '',
        'city'              => $profile?->city ?? '',
        'street'            => $profile?->street ?? '',
        'house_number'      => $profile?->house_number ?? '',
        'tax_number'        => $profile?->tax_number ?? '',
        'eu_vat_number'     => $profile?->eu_vat_number ?? '',
        'subscription_type' => $profile?->subscription_type ?? '',

        // admin adatok
        'admin_name'        => $admin?->name ?? '',
        'admin_email'       => $admin?->email ?? '',
    ]);
}



}