<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Assessment;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\UserType;
use App\Models\Enums\OrgRole;


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
    $subscriptionType = $request->input('subscription_type', 'pro');

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

        'tax_number'         => [
            'nullable','string','max:50',
            Rule::requiredIf($country === 'HU'),
            'regex:/^\d{8}-\d-\d{2}$/'
        ],

        'eu_vat_number'      => [
            'nullable','string','max:32',
            Rule::requiredIf($country !== 'HU'),
            'regex:/^[A-Z]{2}[A-Za-z0-9]{2,12}$/'
        ],

        'subscription_type'  => 'nullable|in:free,pro',
        'employee_limit'     => [
            'nullable',
            'integer',
            'min:1',
            Rule::requiredIf($subscriptionType === 'pro'),
        ],
    ]);

    return DB::transaction(function() use ($request, $country, $subscriptionType) {
        // 1) Organization
        $org = \App\Models\Organization::create([
            'name'       => $request->org_name,
            'created_at' => now(),
        ]);

        // 2) Admin user - check for existing or create new
        $user = \App\Models\User::firstOrCreate(
            ['email' => $request->admin_email],
            [
                'name'       => $request->admin_name,
                'type'       => UserType::NORMAL,
                'created_at' => now(),
            ]
        );

        // 3) Pivot table - Admin role
        $org->users()->attach($user->id, ['role' => OrgRole::ADMIN]);

        // 4) Organization Profile with employee_limit
        // For FREE plan: no employee_limit (unlimited)
        // For PRO plan: use the provided employee_limit
        $employeeLimit = null;
        if ($subscriptionType === 'pro') {
            $employeeLimit = (int) $request->input('employee_limit', 50);
        }

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
            'subscription_type' => $subscriptionType,
            'employee_limit'    => $employeeLimit, // null for free, integer for pro
            'created_at'        => now(),
        ]);

        // 5) Copy default CEO ranks
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

        // 6) SET USER PRICE BASED ON COUNTRY
        $hufPrice = DB::table('config')->where('name', 'global_price_huf')->value('value') ?? '950';
        $eurPrice = DB::table('config')->where('name', 'global_price_eur')->value('value') ?? '2.5';
        $userPrice = ($country === 'HU') ? $hufPrice : $eurPrice;

        \Log::info('superadmin.org.user_price_set', [
            'org_id' => $org->id,
            'country' => $country,
            'price' => $userPrice,
            'currency' => ($country === 'HU') ? 'HUF' : 'EUR'
        ]);

        // 7) Complete organization config defaults
        $defaults = [
            // User price
            'user_price'            => $userPrice,
            
            // Threshold mode + classic thresholds
            'threshold_mode'        => 'fixed',
            'normal_level_up'       => '85',
            'normal_level_down'     => '70',
            'monthly_level_down'    => '70',

            // HYBRID / DYNAMIC base settings
            'threshold_min_abs_up'  => '70',
            'threshold_top_pct'     => '15',
            'threshold_bottom_pct'  => '20',

            // HYBRID fine-tuning
            'threshold_grace_points'=> '0',
            'threshold_gap_min'     => '5',

            // AI/telemetry toggles
            'strict_anonymous_mode' => '0',
            'ai_telemetry_enabled'  => '1',

            // SUGGESTED (AI) policy
            'target_promo_rate_max'          => '0.30',
            'target_demotion_rate_max'       => '0.30',
            'never_below_abs_min_for_promo'  => '60',
            'use_telemetry_trust'            => '1',
            'no_forced_demotion_if_high_cohesion' => '1',

            // Other UI/behavior settings
            'enable_multi_level'    => '0',
            'show_bonus_malus'      => '1',
            'easy_relation_setup'   => '1',
            'force_oauth_2fa'       => '0',
            'employees_see_bonuses' => '0',
            'enable_bonus_calculation' => '0',
            'api_enabled' => '1',
            'api_rate_limit' => '60',
        ];

        foreach ($defaults as $key => $val) {
            DB::table('organization_config')->updateOrInsert(
                ['organization_id' => $org->id, 'name' => $key],
                ['value' => $val === null ? null : (string) $val]
            );
        }

        // 8) Default Bonus/Malus multipliers
        $defaultMultipliers = [
            1 => 0.00, 2 => 0.40, 3 => 0.70, 4 => 0.90, 5 => 1.00,
            6 => 1.50, 7 => 2.00, 8 => 2.75, 9 => 3.50, 10 => 4.25,
            11 => 5.25, 12 => 6.25, 13 => 7.25, 14 => 8.25, 15 => 10.00
        ];

        $bonusConfigData = [];
        foreach ($defaultMultipliers as $level => $multiplier) {
            $bonusConfigData[] = [
                'organization_id' => $org->id,
                'level' => $level,
                'multiplier' => $multiplier
            ];
        }
        DB::table('bonus_malus_config')->insert($bonusConfigData);

        // 9) INITIAL PAYMENT CREATION - Only for PRO plans with employee_limit > 0
        if ($subscriptionType === 'pro' && $employeeLimit > 0) {
            // Calculate payment amounts using PaymentHelper
            $paymentAmounts = \App\Services\PaymentHelper::calculatePaymentAmounts($org->id, $employeeLimit);
            
            DB::table('payments')->insert([
                'organization_id' => $org->id,
                'assessment_id'   => null,
                'currency'        => $paymentAmounts['currency'],
                'net_amount'      => $paymentAmounts['net_amount'],
                'vat_rate'        => $paymentAmounts['vat_rate'],
                'vat_amount'      => $paymentAmounts['vat_amount'],
                'gross_amount'    => $paymentAmounts['gross_amount'],
                'status'          => 'initial',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            
            \Log::info('superadmin.payment.created', [
                'org_id' => $org->id,
                'subscription_type' => $subscriptionType,
                'employee_limit' => $employeeLimit,
                'currency' => $paymentAmounts['currency'],
                'gross_amount' => $paymentAmounts['gross_amount'],
            ]);
        } else {
            \Log::info('superadmin.payment.skipped', [
                'org_id' => $org->id,
                'subscription_type' => $subscriptionType,
                'employee_limit' => $employeeLimit,
                'reason' => $subscriptionType === 'free' ? 'Free subscription - unlimited access' : 'No employee limit set'
            ]);
        }

        // 10) Password-setup email
        \App\Services\PasswordSetupService::createAndSend($org->id, $user->id, null);

        return response()->json(['success' => true]);
    });
}


public function update(Request $request)
{

    \Log::info('Update route hit with method: ' . $request->method(), [
        'all' => $request->all(),
    ]);

    $country = strtoupper($request->input('country_code', 'HU'));

    $request->validate([
        'org_id'             => 'required|integer|exists:organization,id',
        'org_name'           => 'required|string|max:255',
        'admin_name'         => 'nullable|string|max:255',
        'admin_email'        => 'nullable|email|max:255',

        'country_code'       => 'required|string|size:2',
        'postal_code'        => 'nullable|string|max:16',
        'region'             => 'nullable|string|max:64',
        'city'               => 'nullable|string|max:64',
        'street'             => 'nullable|string|max:128',
        'house_number'       => 'nullable|string|max:32',

        'tax_number'         => [
            'nullable','string','max:50',
            Rule::requiredIf($country === 'HU'),
            'regex:/^\d{8}-\d-\d{2}$/'
        ],

        'eu_vat_number'      => [
            'nullable','string','max:32',
            Rule::requiredIf($country !== 'HU'),
            'regex:/^[A-Z]{2}[A-Za-z0-9]{2,12}$/'
        ],

        'subscription_type'  => 'nullable|in:free,pro',
        'admin_remove'       => 'required|boolean',
    ]);

    return DB::transaction(function() use ($request, $country) {
        $orgId = (int) $request->org_id;

        // 1) Update organization name
        DB::table('organization')->where('id', $orgId)->update(['name' => $request->org_name]);

        // 2) Update profile
        DB::table('organization_profiles')->where('organization_id', $orgId)->update([
            'country_code'      => $country,
            'postal_code'       => $request->postal_code,
            'region'            => $request->region,
            'city'              => $request->city,
            'street'            => $request->street,
            'house_number'      => $request->house_number,
            'tax_number'        => $request->tax_number,
            'eu_vat_number'     => strtoupper((string) $request->eu_vat_number),
            'subscription_type' => $request->subscription_type ?? 'pro',
        ]);

        // 3) Admin handling
        $org = Organization::findOrFail($orgId);

        if ($request->admin_remove == 1) {
            // Remove current admin
            $currentAdmin = $org->users()->wherePivot('role', 'admin')->first();
            if ($currentAdmin) {
                $org->users()->detach($currentAdmin->id);
            }
        }

        // If new admin provided
        if ($request->filled('admin_name') && $request->filled('admin_email')) {
            $existingAdmin = $org->users()->wherePivot('role', 'admin')->first();
            if ($existingAdmin) {
                return response()->json([
                    'success' => false,
                    'errors'  => ['admin' => 'Már van admin, törölni kell először. Csak új admin regisztrálható.']
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
    });
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
/**
 * Get global pricing configuration
 */
public function getPricing()
{
    $hufPrice = DB::table('config')->where('name', 'global_price_huf')->value('value') ?? '950';
    $eurPrice = DB::table('config')->where('name', 'global_price_eur')->value('value') ?? '2.5';
    
    return response()->json([
        'success' => true,
        'data' => [
            'global_price_huf' => $hufPrice,
            'global_price_eur' => $eurPrice,
        ]
    ]);
}

/**
 * Update global pricing configuration
 */
public function updatePricing(Request $request)
{
    $request->validate([
        'global_price_huf' => 'required|numeric|min:0',
        'global_price_eur' => 'required|numeric|min:0',
    ]);

    DB::table('config')->updateOrInsert(
        ['name' => 'global_price_huf'],
        ['value' => $request->global_price_huf]
    );

    DB::table('config')->updateOrInsert(
        ['name' => 'global_price_eur'],
        ['value' => $request->global_price_eur]
    );

    \Log::info('superadmin.pricing.updated', [
        'huf' => $request->global_price_huf,
        'eur' => $request->global_price_eur,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Árak sikeresen frissítve!'
    ]);
}

/**
 * Toggle maintenance mode on/off
 */
public function toggleMaintenance(Request $request)
{
    try {
        $enable = $request->input('enable', false);
        
        if ($enable) {
            // Enable maintenance mode
            Artisan::call('down', [
                '--retry' => 60,
                '--refresh' => 15,
                '--secret' => config('app.key'),
            ]);
            
            return response()->json([
                'ok' => true,
                'message' => __('superadmin/dashboard.maintenance-enabled'),
                'enabled' => true
            ]);
        } else {
            // Disable maintenance mode
            Artisan::call('up');
            
            return response()->json([
                'ok' => true,
                'message' => __('superadmin/dashboard.maintenance-disabled'),
                'enabled' => false
            ]);
        }
    } catch (\Exception $e) {
        \Log::error('Maintenance mode toggle failed', [
            'error' => $e->getMessage(),
            'user_id' => session('uid')
        ]);
        
        return response()->json([
            'ok' => false,
            'message' => __('global.error-occurred')
        ], 500);
    }
}

/**
 * Get current maintenance mode status
 */
public function getMaintenanceStatus()
{
    return response()->json([
        'ok' => true,
        'enabled' => app()->isDownForMaintenance()
    ]);
}





}