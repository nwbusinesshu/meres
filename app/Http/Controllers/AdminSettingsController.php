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
        $showBonusMalus = OrgConfigService::getBool($orgId, 'show_bonus_malus', true); // NEW: Default to true

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
        $absMinPromo  = OrgConfigService::get($orgId, 'never_below_abs_min_for_promo', null);
        $useTrust     = OrgConfigService::getBool($orgId, 'use_telemetry_trust', true);
        $noForcedDemo = OrgConfigService::getBool($orgId, 'no_forced_demotion_if_high_cohesion', true);

        $multi = \DB::table('organization_config')
            ->where('organization_id', $orgId)
            ->where('name', 'enable_multi_level')
            ->value('value');

        $enableMultiLevel = $multi === '1';

        $hasClosed = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->exists();

        return view('admin.settings', [
            'strictAnon'            => $strictAnon,
            'aiTelemetry'           => $aiTelemetry,
            'showBonusMalus'        => $showBonusMalus, // NEW: Pass to view

            'threshold_mode'        => $thresholdMode,
            'threshold_min_abs_up'  => $thresholdMinAbsUp,
            'threshold_top_pct'     => $thresholdTopPct,
            'threshold_bottom_pct'  => $thresholdBottomPct,
            'normal_level_up'       => $normalLevelUp,
            'normal_level_down'     => $normalLevelDown,

            // ÚJ – HYBRID
            'threshold_grace_points'=> $thresholdGrace,
            'threshold_gap_min'     => $thresholdGapMin,

            // ÚJ – SUGGESTED (UI %-ban mutatjuk)
            'target_promo_rate_max_pct'    => (int) round($promoRateMax * 100),
            'target_demotion_rate_max_pct' => (int) round($demoRateMax  * 100),
            'never_below_abs_min_for_promo'=> $absMinPromo,
            'use_telemetry_trust'          => $useTrust,
            'no_forced_demotion_if_high_cohesion' => $noForcedDemo,

            'hasClosedAssessment'   => $hasClosed,
            'enableMultiLevel'      => $enableMultiLevel,
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'key'   => 'required|in:strict_anonymous_mode,ai_telemetry_enabled,enable_multi_level,show_bonus_malus', // NEW: Add show_bonus_malus
            'value' => 'required|boolean',
        ]);

        $orgId = (int) session('org_id');
        $key   = (string) $request->input('key');
        $val   = (bool) $request->boolean('value');

        // NEW: Handle bonus/malus toggle
        if ($key === 'show_bonus_malus') {
            OrgConfigService::setBool($orgId, 'show_bonus_malus', $val);
            return response()->json(['ok' => true]);
        }

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
            if ($strictAnon && $val === true) {
                $val = false;
            }
            \App\Services\OrgConfigService::setBool($orgId, $key, $val);
        }

        return response()->json(['ok' => true]);
    }

    // === ÚJ: ponthatár-mód és értékek mentése ===
    public function saveThresholds(Request $request)
    {
        $orgId = (int) session('org_id');

        $validated = $request->validate([
            'threshold_mode'       => ['required', Rule::in(['fixed','hybrid','dynamic','suggested'])],
            'threshold_min_abs_up' => ['nullable','integer','min:0','max:100'],
            'threshold_bottom_pct' => ['nullable','integer','min:0','max:100'],
            'threshold_top_pct'    => ['nullable','integer','min:0','max:100'],
            'normal_level_up'      => ['nullable','integer','min:0','max:100'],
            'normal_level_down'    => ['nullable','integer','min:0','max:100'],
            // ÚJ – HYBRID finomhangolás
            'threshold_grace_points' => ['nullable','integer','min:0','max:20'],
            'threshold_gap_min'      => ['nullable','integer','min:0','max:10'],

            // ÚJ – SUGGESTED (UI %-ok + kapcsolók)
            'target_promo_rate_max_pct'    => ['nullable','integer','min:0','max:100'],
            'target_demotion_rate_max_pct' => ['nullable','integer','min:0','max:100'],
            'never_below_abs_min_for_promo'=> ['nullable','integer','min:0','max:100'],
            'use_telemetry_trust'          => ['nullable','boolean'],
            'no_forced_demotion_if_high_cohesion' => ['nullable','boolean'],
    ]);

        $mode = $validated['threshold_mode'];

        // Üzleti ellenőrzések
        if ($mode === 'hybrid') {
            if (!$request->filled('threshold_min_abs_up')) {
                return back()->withErrors(['threshold_min_abs_up' => 'Adj meg alsó fix ponthatárt (0–100) a Hybrid módhoz.'])->withInput();
            }
            if (!$request->filled('threshold_top_pct')) {
                return back()->withErrors(['threshold_top_pct' => 'Adj meg felső százalékot (0–100) a Hybrid módhoz.'])->withInput();
            }
        } elseif ($mode === 'dynamic') {
            if (!$request->filled('threshold_bottom_pct')) {
                return back()->withErrors(['threshold_bottom_pct' => 'Adj meg alsó százalékot (0–100) a Dynamic módhoz.'])->withInput();
            }
            if (!$request->filled('threshold_top_pct')) {
                return back()->withErrors(['threshold_top_pct' => 'Adj meg felső százalékot (0–100) a Dynamic módhoz.'])->withInput();
            }
        }
        // suggested (AI) módban most nincs plusz input

        // Mentés (idempotens org-config kulcsok)
        OrgConfigService::set($orgId, 'threshold_mode', $mode);

        // Ezeket akkor is eltároljuk, ha épp nem aktív a mód – később jól jön:
        if ($request->filled('threshold_min_abs_up')) {
            OrgConfigService::set($orgId, 'threshold_min_abs_up', (int) $request->input('threshold_min_abs_up'));
        }
        if ($request->filled('threshold_bottom_pct')) {
            OrgConfigService::set($orgId, 'threshold_bottom_pct', (int) $request->input('threshold_bottom_pct'));
        }
        if ($request->filled('threshold_top_pct')) {
            OrgConfigService::set($orgId, 'threshold_top_pct', (int) $request->input('threshold_top_pct'));
        }

        // ÚJ: FIX ponthatárok – ha küldi, elmentjük (elsősorban FIXED módban jelenik meg az UI-ban)
        if ($request->filled('normal_level_up')) {
            OrgConfigService::set($orgId, 'normal_level_up', (int) $request->input('normal_level_up'));
        }
        if ($request->filled('normal_level_down')) {
            OrgConfigService::set($orgId, 'normal_level_down', (int) $request->input('normal_level_down'));
        }

        // ÚJ – HYBRID finomhangolás mentése (ha küldték)
        if ($request->filled('threshold_grace_points')) {
            OrgConfigService::set($orgId, 'threshold_grace_points', (int) $request->input('threshold_grace_points'));
        }
        if ($request->filled('threshold_gap_min')) {
            OrgConfigService::set($orgId, 'threshold_gap_min', (int) $request->input('threshold_gap_min'));
        }

        // ÚJ – SUGGESTED policy mentése
        // %-os mezők → 0..1 float tárolás
        if ($request->filled('target_promo_rate_max_pct')) {
            $v = max(0, min(100, (int)$request->input('target_promo_rate_max_pct')));
            OrgConfigService::set($orgId, 'target_promo_rate_max', $v / 100.0);
        }
        if ($request->filled('target_demotion_rate_max_pct')) {
            $v = max(0, min(100, (int)$request->input('target_demotion_rate_max_pct')));
            OrgConfigService::set($orgId, 'target_demotion_rate_max', $v / 100.0);
        }
        if ($request->exists('never_below_abs_min_for_promo')) {
            // üres → töröljük (NULL)
            $raw = $request->input('never_below_abs_min_for_promo');
            if ($raw === null || $raw === '') {
                OrgConfigService::set($orgId, 'never_below_abs_min_for_promo', null);
            } else {
                OrgConfigService::set($orgId, 'never_below_abs_min_for_promo', (int) $raw);
            }
        }
        // checkboxok/kapcsolók
        $useTrust = $request->boolean('use_telemetry_trust'); // 0/1-ből bool lesz
        $noForced = $request->boolean('no_forced_demotion_if_high_cohesion');

        OrgConfigService::setBool($orgId, 'use_telemetry_trust', $useTrust);
        OrgConfigService::setBool($orgId, 'no_forced_demotion_if_high_cohesion', $noForced);

        return redirect()->back()->with('success', 'Ponthatár mód és beállítások mentve.');
    }
}
