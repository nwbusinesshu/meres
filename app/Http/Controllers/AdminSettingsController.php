<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrgConfigService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AdminSettingsController extends Controller
{
    public function index(Request $request)
    {
        $orgId = (int) session('org_id');

        $strictAnon = OrgConfigService::getBool($orgId, OrgConfigService::STRICT_ANON_KEY, false);
        $aiTelemetry = OrgConfigService::getBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, true);
        $showBonusMalus = OrgConfigService::getBool($orgId, 'show_bonus_malus', true);
        $easyRelationSetup = OrgConfigService::getBool($orgId, 'easy_relation_setup', false);
        $forceOauth2fa = OrgConfigService::getBool($orgId, 'force_oauth_2fa', false);
        
        // ✅ NEW: Bonuses visibility setting
        $employeesSeeBonuses = OrgConfigService::getBool($orgId, 'employees_see_bonuses', false);

        // kizárólagosság biztosítása (ha strict anon ON, akkor AI OFF)
        if ($strictAnon && $aiTelemetry) {
            $aiTelemetry = false;
            OrgConfigService::setBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, false);
        }

        // === ÚJ: ponthatár-mód és kapcsolódó értékek betöltése ===
        $thresholdMode       = OrgConfigService::get($orgId, 'threshold_mode', 'fixed');
        $thresholdMinAbsUp   = (int) OrgConfigService::get($orgId, 'threshold_min_abs_up', 80);
        $thresholdTopPct     = (int) OrgConfigService::get($orgId, 'threshold_top_pct', 15);
        $thresholdBottomPct  = (int) OrgConfigService::get($orgId, 'threshold_bottom_pct', 20);
        $normalLevelUp   = (int) OrgConfigService::get($orgId, 'normal_level_up', 85);
        $normalLevelDown = (int) OrgConfigService::get($orgId, 'normal_level_down', 70);
        $thresholdGrace      = (int) OrgConfigService::get($orgId, 'threshold_grace_points', 5);
        $thresholdGapMin     = (int) OrgConfigService::get($orgId, 'threshold_gap_min', 2);

        // ÚJ – SUGGESTED policy (belsőleg 0..1, UI %-ban)
        $promoRateMax = (float) OrgConfigService::get($orgId, 'target_promo_rate_max', 0.20);
        $demoRateMax  = (float) OrgConfigService::get($orgId, 'target_demotion_rate_max', 0.10);
        $neverBelowAbsMin = OrgConfigService::get($orgId, 'never_below_abs_min_for_promo', null);
        $useTrust     = OrgConfigService::getBool($orgId, 'use_telemetry_trust', true);
        $noForcedDemotion = OrgConfigService::getBool($orgId, 'no_forced_demotion_if_high_cohesion', true);

        // multi-level & érvényes SUGGESTED?
        $enableMultiLevel = OrgConfigService::getBool($orgId, 'enable_multi_level', false);
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
            'employeesSeeBonuses', // ✅ NEW
            'thresholdMode',
            'thresholdMinAbsUp',
            'thresholdTopPct',
            'thresholdBottomPct',
            'normalLevelUp',
            'normalLevelDown',
            'thresholdGrace',
            'thresholdGapMin',
            'promoRateMax',
            'demoRateMax',
            'neverBelowAbsMin',
            'useTrust',
            'noForcedDemotion',
            'enableMultiLevel',
            'hasClosedAssessment',
            'aiTelemetry'
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

        // ✅ NEW: Handle employees_see_bonuses toggle
        if ($key === 'employees_see_bonuses') {
            \App\Services\OrgConfigService::setBool($orgId, 'employees_see_bonuses', $val);
            return response()->json(['ok' => true]);
        }

        // Handle show_bonus_malus toggle
        if ($key === 'show_bonus_malus') {
            \App\Services\OrgConfigService::setBool($orgId, 'show_bonus_malus', $val);
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

            if ($current) {
                return response()->json(['ok' => true, 'already_on' => true]);
            }

            if ($val === true) {
                \App\Services\OrgConfigService::setBool($orgId, 'enable_multi_level', true);
                return response()->json(['ok' => true, 'enabled' => true]);
            } else {
                return response()->json(['ok' => true, 'noop' => true]);
            }
        }

        // --- EDDIGI KÉT KAPCSOLÓ VÁLTOZATLANUL ---
        if ($key === \App\Services\OrgConfigService::STRICT_ANON_KEY) {
            $strict = $val === true;
            \App\Services\OrgConfigService::setBool($orgId, $key, $strict);
            if ($strict) {
                \App\Services\OrgConfigService::setBool($orgId, \App\Services\OrgConfigService::AI_TELEMETRY_KEY, false);
            }
        } else { // AI_TELEMETRY_KEY
            $strictAnon = \App\Services\OrgConfigService::getBool($orgId, \App\Services\OrgConfigService::STRICT_ANON_KEY, false);
            if ($strictAnon) {
                return response()->json([
                    'ok' => false,
                    'message' => 'AI telemetria nem engedélyezhető strict anonymous üzemmódban.'
                ], 422);
            }
            \App\Services\OrgConfigService::setBool($orgId, $key, $val);
        }

        return response()->json(['ok' => true]);
    }

    public function saveThresholds(Request $request)
    {
        $orgId = (int) session('org_id');

        // === 1) A mód mentése ===
        $mode = $request->input('threshold_mode', 'fixed');
        if (!in_array($mode, ['fixed','hybrid','dynamic','suggested'], true)) {
            $mode = 'fixed';
        }
        OrgConfigService::set($orgId, 'threshold_mode', $mode);

        // === 2) FIXED mód küszöbök ===
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
        if ($request->has('use_telemetry_trust')) {
            $bool = $request->boolean('use_telemetry_trust');
            OrgConfigService::setBool($orgId, 'use_telemetry_trust', $bool);
        }
        if ($request->has('no_forced_demotion_if_high_cohesion')) {
            $bool = $request->boolean('no_forced_demotion_if_high_cohesion');
            OrgConfigService::setBool($orgId, 'no_forced_demotion_if_high_cohesion', $bool);
        }

        return redirect()->route('admin.settings.index')->with('success', 'Beállítások elmentve!');
    }
}