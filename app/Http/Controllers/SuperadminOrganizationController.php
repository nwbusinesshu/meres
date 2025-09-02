<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Enums\UserType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SuperadminOrganizationController extends Controller
{
    public function create()
    {
        return view('superadmin.org.create');
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'organization_name' => 'required|string|max:255',
        'admin_name'        => 'required|string|max:255',
        'admin_email'       => 'required|email|unique:user,email',
    ]);

    DB::transaction(function () use ($validated) {
        // 1) Cég létrehozása
        $org = Organization::create([
            'name'       => $validated['organization_name'],
            'created_at' => now(),
        ]);

        // 2) Admin user létrehozása és hozzárendelése
        $password = Str::random(12); // (később: email értesítés)
        $admin = User::create([
            'name'     => $validated['admin_name'],
            'email'    => $validated['admin_email'],
            'type'     => UserType::ADMIN,
            'password' => Hash::make($password),
        ]);
        $admin->organizations()->attach($org->id, ['role' => 'admin']);

        // 3) Org-config alapértékek — régiek + HYBRID + AI/SUGGESTED policy
        $defaults = [
            // --- mód választó + klasszikus küszöbök ---
            'threshold_mode'        => 'fixed',
            'normal_level_up'       => '85',
            'normal_level_down'     => '70',

            // --- HYBRID / DYNAMIC alapok ---
            'threshold_min_abs_up'  => '70', // HYBRID: alsó fix
            'threshold_top_pct'     => '15', // HYBRID + DYNAMIC: felső X%
            'threshold_bottom_pct'  => '20', // DYNAMIC: alsó Y%

            // --- HYBRID finomhangolás (új) ---
            'threshold_grace_points'=> '0',  // minAbs alá engedés mozgástere (pont)
            'threshold_gap_min'     => '5',  // stagnálási „dead zone” (pont)

            // --- AI/telemetry kapcsolók (új) ---
            'strict_anonymous_mode' => '0',  // ha 1 → ai_telemetry automatikusan OFF
            'ai_telemetry_enabled'  => '1',

            // --- SUGGESTED (AI) policy (új) ---
            // %-os rátákat 0..1 között tároljuk
            'target_promo_rate_max'          => '0.30', // 20%
            'target_demotion_rate_max'       => '0.30', // 10%
            'never_below_abs_min_for_promo'  => null,   // üres = nincs abszolút min.
            'use_telemetry_trust'            => '1',
            'no_forced_demotion_if_high_cohesion' => '1',
        ];

        foreach ($defaults as $key => $val) {
            DB::table('organization_config')->updateOrInsert(
                ['organization_id' => $org->id, 'name' => $key],
                ['value' => $val === null ? null : (string) $val]
            );
        }
    });

    return redirect()
        ->route('admin.home')
        ->with('success', 'Új cég és admin sikeresen létrehozva!');
}
}
