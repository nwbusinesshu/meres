<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrgConfigService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AdminSettingsController extends Controller
{
    public function index()
{
    $orgId = (int) session('org_id');

    // Toggle settings
    $strictAnon          = OrgConfigService::getBool($orgId, 'strict_anonymous_mode', false);
    $aiTelemetry         = OrgConfigService::getBool($orgId, 'ai_telemetry_enabled', true);
    $showBonusMalus      = OrgConfigService::getBool($orgId, 'show_bonus_malus', true);
    $easyRelationSetup   = OrgConfigService::getBool($orgId, 'easy_relation_setup', false);
    $forceOauth2fa       = OrgConfigService::getBool($orgId, 'force_oauth_2fa', false);
    $employeesSeeBonuses = OrgConfigService::getBool($orgId, 'employees_see_bonuses', false);
    $enableBonusCalculation = OrgConfigService::getBool($orgId, 'enable_bonus_calculation', false);

    // Threshold mode & config
    $threshold_mode = OrgConfigService::get($orgId, 'threshold_mode', 'fixed');
    
    // All threshold config values with underscore naming to match blade
    $threshold_min_abs_up     = (int) OrgConfigService::get($orgId, 'threshold_min_abs_up', 80);
    $threshold_top_pct        = (int) OrgConfigService::get($orgId, 'threshold_top_pct', 15);
    $threshold_bottom_pct     = (int) OrgConfigService::get($orgId, 'threshold_bottom_pct', 20);
    $normal_level_up          = (int) OrgConfigService::get($orgId, 'normal_level_up', 85);
    $normal_level_down        = (int) OrgConfigService::get($orgId, 'normal_level_down', 70);
    $threshold_grace_points   = (int) OrgConfigService::get($orgId, 'threshold_grace_points', 5);
    $threshold_gap_min        = (int) OrgConfigService::get($orgId, 'threshold_gap_min', 2);

    // SUGGESTED policy (internally 0..1, UI in %)
    $promoRate = (float) OrgConfigService::get($orgId, 'target_promo_rate_max', 0.20);
    $demoRate  = (float) OrgConfigService::get($orgId, 'target_demotion_rate_max', 0.10);
    $target_promo_rate_max_pct    = (int)($promoRate * 100);
    $target_demotion_rate_max_pct = (int)($demoRate * 100);
    
    $never_below_abs_min_for_promo        = OrgConfigService::get($orgId, 'never_below_abs_min_for_promo', null);
    $use_telemetry_trust                  = OrgConfigService::getBool($orgId, 'use_telemetry_trust', true);
    $no_forced_demotion_if_high_cohesion  = OrgConfigService::getBool($orgId, 'no_forced_demotion_if_high_cohesion', true);

    // Multi-level & valid SUGGESTED check
    $enableMultiLevel     = OrgConfigService::getBool($orgId, 'enable_multi_level', false);
    $hasClosedAssessment = DB::table('assessment')
        ->where('organization_id', $orgId)
        ->whereNotNull('closed_at')
        ->exists();

    return view('admin.settings', compact(
        'strictAnon',
        'aiTelemetry',
        'showBonusMalus',
        'easyRelationSetup',
        'forceOauth2fa',
        'employeesSeeBonuses',
        'enableBonusCalculation',
        'threshold_mode',
        'threshold_min_abs_up',
        'threshold_top_pct',
        'threshold_bottom_pct',
        'normal_level_up',
        'normal_level_down',
        'threshold_grace_points',
        'threshold_gap_min',
        'target_promo_rate_max_pct',
        'target_demotion_rate_max_pct',
        'never_below_abs_min_for_promo',
        'use_telemetry_trust',
        'no_forced_demotion_if_high_cohesion',
        'enableMultiLevel',
        'hasClosedAssessment'
    ));
}

    public function toggle(Request $request)
    {
        $orgId = (int) session('org_id');

        $this->validate($request, [
            'key' => ['required','string'],
            'value' => ['required','boolean'],
        ]);

        $key = $request->key;
        $val = $request->value;

        // ✅ CASCADING RULE: show_bonus_malus
        if ($key === 'show_bonus_malus') {
            OrgConfigService::setBool($orgId, 'show_bonus_malus', $val);
            
            // If turning OFF, cascade disable all child settings
            if (!$val) {
                OrgConfigService::setBool($orgId, 'enable_bonus_calculation', false);
                OrgConfigService::setBool($orgId, 'employees_see_bonuses', false);
            }
            
            return response()->json(['ok' => true]);
        }

        // ✅ CASCADING RULE: enable_bonus_calculation
        if ($key === 'enable_bonus_calculation') {
            // Check if parent is enabled
            $showBonusMalus = OrgConfigService::getBool($orgId, 'show_bonus_malus', true);
            
            if (!$showBonusMalus) {
                return response()->json([
                    'ok' => false,
                    'error' => 'A Bonus/Malus megjelenítés ki van kapcsolva.'
                ], 422);
            }
            
            OrgConfigService::setBool($orgId, 'enable_bonus_calculation', $val);
            
            // If turning OFF, cascade disable employees_see_bonuses
            if (!$val) {
                OrgConfigService::setBool($orgId, 'employees_see_bonuses', false);
            }
            
            return response()->json(['ok' => true, 'reload' => true]); // Reload to update navbar
        }

        // ✅ CASCADING RULE: employees_see_bonuses
        if ($key === 'employees_see_bonuses') {
            // Check if parents are enabled
            $showBonusMalus = OrgConfigService::getBool($orgId, 'show_bonus_malus', true);
            $enableBonusCalculation = OrgConfigService::getBool($orgId, 'enable_bonus_calculation', false);
            
            if (!$showBonusMalus || !$enableBonusCalculation) {
                return response()->json([
                    'ok' => false,
                    'error' => 'A szülő beállítások (Bonus/Malus megjelenítés és Bónusz számítás) ki vannak kapcsolva.'
                ], 422);
            }
            
            OrgConfigService::setBool($orgId, 'employees_see_bonuses', $val);
            return response()->json(['ok' => true]);
        }

        // Handle easy_relation_setup toggle
        if ($key === 'easy_relation_setup') {
            \App\Services\OrgConfigService::setBool($orgId, 'easy_relation_setup', $val);
            return response()->json(['ok' => true]);
        }

        // Handle force_oauth_2fa toggle
        if ($key === 'force_oauth_2fa') {
            \App\Services\OrgConfigService::setBool($orgId, 'force_oauth_2fa', $val);
            return response()->json(['ok' => true]);
        }

        // Handle enable_multi_level toggle
        if ($key === 'enable_multi_level') {
            $current = \App\Services\OrgConfigService::getBool($orgId, 'enable_multi_level', false);
            if (!$current && $val) {
                \App\Services\OrgConfigService::setBool($orgId, 'enable_multi_level', true);
                return response()->json(['ok' => true, 'reload' => true]);
            }
            return response()->json(['ok' => true]);
        }

        // Handle AI toggle + strict anon exclusivity
        if ($key === OrgConfigService::AI_TELEMETRY_KEY) {
            if ($val === true) {
                $strict = \App\Services\OrgConfigService::getBool($orgId, OrgConfigService::STRICT_ANON_KEY, false);
                if ($strict) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'A Szigorú anonimizálás be van kapcsolva, így az AI telemetria nem engedélyezhető.'
                    ], 422);
                }
            }
            \App\Services\OrgConfigService::setBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, $val);
            return response()->json(['ok' => true]);
        }

        // Handle Strict Anon toggle + disabling AI
        if ($key === OrgConfigService::STRICT_ANON_KEY) {
            if ($val === true) {
                \App\Services\OrgConfigService::setBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, false);
            }
            \App\Services\OrgConfigService::setBool($orgId, OrgConfigService::STRICT_ANON_KEY, $val);
            return response()->json(['ok' => true, 'reload' => true]);
        }

        return response()->json([
            'ok' => false,
            'error' => 'Ismeretlen beállítás.'
        ], 400);
    }

    public function save(Request $request)
    {
        $orgId = (int) session('org_id');

        // === 1) threshold_mode validáció ===
        $this->validate($request, [
            'threshold_mode' => ['required', Rule::in(['fixed', 'hybrid', 'dynamic', 'suggested'])],
        ]);

        $mode = $request->input('threshold_mode');
        OrgConfigService::set($orgId, 'threshold_mode', $mode);

        // === 2) FIXED/NORMAL módhoz: normal_level_up, normal_level_down ===
        if ($request->exists('normal_level_up')) {
            $up = max(0, min(100, (int)$request->input('normal_level_up')));
            OrgConfigService::set($orgId, 'normal_level_up', $up);
        }
        if ($request->exists('normal_level_down')) {
            $down = max(0, min(100, (int)$request->input('normal_level_down')));
            OrgConfigService::set($orgId, 'normal_level_down', $down);
        }

        // === 3) HYBRID / DYNAMIC közös beállítások ===
        if ($request->exists('threshold_min_abs_up')) {
            $minAbs = max(0, min(100, (int)$request->input('threshold_min_abs_up')));
            OrgConfigService::set($orgId, 'threshold_min_abs_up', $minAbs);
        }
        if ($request->exists('threshold_top_pct')) {
            $topPct = max(1, min(50, (int)$request->input('threshold_top_pct')));
            OrgConfigService::set($orgId, 'threshold_top_pct', $topPct);
        }
        if ($request->exists('threshold_bottom_pct')) {
            $bottomPct = max(1, min(50, (int)$request->input('threshold_bottom_pct')));
            OrgConfigService::set($orgId, 'threshold_bottom_pct', $bottomPct);
        }
        if ($request->exists('threshold_grace_points')) {
            $grace = max(0, min(20, (int)$request->input('threshold_grace_points')));
            OrgConfigService::set($orgId, 'threshold_grace_points', $grace);
        }
        if ($request->exists('threshold_gap_min')) {
            $gap = max(0, min(20, (int)$request->input('threshold_gap_min')));
            OrgConfigService::set($orgId, 'threshold_gap_min', $gap);
        }

        // === 4) SUGGESTED policy beállítások ===
        if ($request->exists('target_promo_rate_max_pct')) {
            $pct = max(0, min(100, (int)$request->input('target_promo_rate_max_pct')));
            OrgConfigService::set($orgId, 'target_promo_rate_max', $pct / 100.0);
        }
        if ($request->exists('target_demotion_rate_max_pct')) {
            $pct = max(0, min(100, (int)$request->input('target_demotion_rate_max_pct')));
            OrgConfigService::set($orgId, 'target_demotion_rate_max', $pct / 100.0);
        }
        if ($request->has('never_below_abs_min_for_promo')) {
            $val = $request->input('never_below_abs_min_for_promo');
            OrgConfigService::set($orgId, 'never_below_abs_min_for_promo', $val === '' ? null : $val);
        }
        // Always save checkbox state - $request->boolean() treats missing as false
        $use_telemetry_trust = $request->boolean('use_telemetry_trust');
            OrgConfigService::setBool($orgId, 'use_telemetry_trust', $use_telemetry_trust);

        $no_forced_demotion = $request->boolean('no_forced_demotion_if_high_cohesion');
            OrgConfigService::setBool($orgId, 'no_forced_demotion_if_high_cohesion', $no_forced_demotion);

        return redirect()->route('admin.settings.index')->with('success', 'Beállítások elmentve!');
    }
}