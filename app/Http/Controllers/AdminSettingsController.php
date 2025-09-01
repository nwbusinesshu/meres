<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrgConfigService;

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

        return view('admin.settings', compact('strictAnon', 'aiTelemetry'));
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
                // nem engedélyezhető — kényszerítsük false-ra
                $val = false;
            }
            OrgConfigService::setBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, $val);
        }

        return response()->json(['ok' => true]);
    }
}
