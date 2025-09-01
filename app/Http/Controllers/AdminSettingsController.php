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

        $hasClosed = DB::table('assessment')
    ->where('organization_id', $orgId)
    ->whereNotNull('closed_at')   // vagy a te státusz/flag meződ, ami a lezárást jelzi
    ->exists();

        return view('admin.settings', [
            'strictAnon'            => $strictAnon,
            'aiTelemetry'           => $aiTelemetry,
            // módválasztó adatok
            'threshold_mode'        => $thresholdMode,
            'threshold_min_abs_up'  => $thresholdMinAbsUp,
            'threshold_top_pct'     => $thresholdTopPct,
            'threshold_bottom_pct'  => $thresholdBottomPct,
            'normal_level_up'       => $normalLevelUp,
            'normal_level_down'     => $normalLevelDown,
            'hasClosedAssessment' => $hasClosed,
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'key' => 'required|in:strict_anonymous_mode,ai_telemetry_enabled',
            'value' => 'required|boolean',
        ]);

        $orgId = (int) session('org_id');
        $key   = $request->string('key');
        $val   = (bool) $request->boolean('value');

        // üzleti szabály: ha strict anon-ON, akkor ai_telemetry OFF
        if ($key === OrgConfigService::STRICT_ANON_KEY) {
            OrgConfigService::setBool($orgId, OrgConfigService::STRICT_ANON_KEY, $val);
            if ($val === true) {
                OrgConfigService::setBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, false);
            }
        } else {
            // ai_telemetry toggling csak akkor, ha nincs strict anon
            $strictAnon = OrgConfigService::getBool($orgId, OrgConfigService::STRICT_ANON_KEY, false);
            if ($strictAnon && $val === true) {
                $val = false;
            }
            OrgConfigService::setBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, $val);
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

        return redirect()->back()->with('success', 'Ponthatár mód és beállítások mentve.');
    }
}
