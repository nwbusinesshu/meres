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

        // kizárólagosság biztosítása (ha strict anon ON, akkor AI OFF)
        if ($strictAnon && $aiTelemetry) {
            $aiTelemetry = false;
            OrgConfigService::setBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, false);
        }

        // === ÚJ: ponthatár-mód és kapcsolódó értékek betöltése ===
        // Ha nincs külön helper, OrgConfigService::get() használható; ha nincs, írhatsz rá egyet.
        $thresholdMode       = OrgConfigService::get($orgId, 'threshold_mode', 'fixed'); // fixed | hybrid | dynamic | suggested
        $thresholdMinAbsUp   = (int) OrgConfigService::get($orgId, 'threshold_min_abs_up', 80); // HYBRID: alsó fix
        $thresholdTopPct     = (int) OrgConfigService::get($orgId, 'threshold_top_pct', 15);    // HYBRID/DYNAMIC: felső X%
        $thresholdBottomPct  = (int) OrgConfigService::get($orgId, 'threshold_bottom_pct', 20); // DYNAMIC: alsó Y%
        $normalLevelUp   = (int) OrgConfigService::get($orgId, 'normal_level_up', 85);
        $normalLevelDown = (int) OrgConfigService::get($orgId, 'normal_level_down', 70);
         // ÚJ – HYBRID finomhangolás
        $thresholdGrace      = (int) OrgConfigService::get($orgId, 'threshold_grace_points', 5);
        $thresholdGapMin     = (int) OrgConfigService::get($orgId, 'threshold_gap_min', 2);

        // ÚJ – SUGGESTED policy (belsőleg 0..1, UI %-ban)
        $promoRateMax = (float) OrgConfigService::get($orgId, 'target_promo_rate_max', 0.20);
        $demoRateMax  = (float) OrgConfigService::get($orgId, 'target_demotion_rate_max', 0.10);
        $absMinPromo  = OrgConfigService::get($orgId, 'never_below_abs_min_for_promo', null);
        $useTrust     = OrgConfigService::getBool($orgId, 'use_telemetry_trust', true);
        $noForcedDemo = OrgConfigService::getBool($orgId, 'no_forced_demotion_if_high_cohesion', true);


        $hasClosed = DB::table('assessment')
    ->where('organization_id', $orgId)
    ->whereNotNull('closed_at')   // vagy a te státusz/flag meződ, ami a lezárást jelzi
    ->exists();

        return view('admin.settings', [
        'strictAnon'            => $strictAnon,
        'aiTelemetry'           => $aiTelemetry,

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
        ]);
    }

    public function toggle(Request $request)
{
    $request->validate([
        'key' => 'required|in:strict_anonymous_mode,ai_telemetry_enabled',
        'value' => 'required|boolean',
    ]);

    $orgId = (int) session('org_id');
    $key = $request->string('key');
    $val = (bool) $request->boolean('value');

    // Üzleti szabály: ha strict anon-ON, akkor ai_telemetry OFF
    if ($key === OrgConfigService::STRICT_ANON_KEY) {
        // Mindig beállítjuk az anonim módot
        OrgConfigService::setBool($orgId, $key, $val);

        // Ha az anonim mód be van kapcsolva, kikapcsoljuk a telemetry-t
        if ($val === true) {
            OrgConfigService::setBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, false);
        }
        // Ha az anonim mód ki van kapcsolva, a telemetry-vel nem csinálunk semmit
    } else { // key === OrgConfigService::AI_TELEMETRY_KEY
        // ai_telemetry toggling csak akkor, ha nincs strict anon
        $strictAnon = OrgConfigService::getBool($orgId, OrgConfigService::STRICT_ANON_KEY, false);

        // Ha a telemetry bekapcsolása a cél, de az anonim mód be van kapcsolva, akkor kikapcsolva marad
        if ($strictAnon && $val === true) {
            $val = false;
        }

        OrgConfigService::setBool($orgId, $key, $val);
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
