<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Models\Enums\OrgRole;


class AdminResultsController extends Controller
{
    public function index(Request $request, ?int $assessmentId = null)
    {
        $orgId = (int) session('org_id');

        // Aktuális lezárt mérés (adott ID vagy legutóbbi a szervezetben)
        $assessment = $assessmentId
            ? Assessment::where('organization_id', $orgId)->whereNotNull('closed_at')->find($assessmentId)
            : Assessment::where('organization_id', $orgId)->whereNotNull('closed_at')->orderByDesc('closed_at')->first();

        if (!$assessment) {
            return view('admin.results', [
                'assessment'     => null,
                'users'          => collect(),
                'prevAssessment' => null,
                'nextAssessment' => null,
            ]);
        }

        $showBonusMalus = \App\Services\OrgConfigService::getBool($orgId, 'show_bonus_malus', true);

        // Előző/következő lezárt mérés
        $prevAssessment = Assessment::where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->where('closed_at', '<', $assessment->closed_at)
            ->orderByDesc('closed_at')
            ->first();

        $nextAssessment = Assessment::where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>', $assessment->closed_at)
            ->orderBy('closed_at')
            ->first();

        // Résztvevő userek (admin/szuperadmin out), aztán szűrés arra, hogy VAN statjuk ebben a mérésben
        $users = User::whereNull('removed_at')
            ->where('type', '!=', UserType::SUPERADMIN)  // ✅ Exclude system superadmins
            ->whereHas('organizations', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                  ->where('organization_user.role', '!=', OrgRole::ADMIN);  // ✅ Exclude org admins
            })
            ->orderBy('name')
            ->get()
            ->map(function ($user) use ($assessment) {
                // Get cached results from snapshot
                $cached = UserService::getUserResultsFromSnapshot($assessment->id, $user->id);
                
                if ($cached) {
                    // Use cached data - FAST!
                    $user['stats'] = UserService::snapshotResultToStdClass($cached);
                    $user['bonusMalus'] = $cached['bonus_malus_level'];
                    $user['change'] = $cached['change'];
                    // ✅ NEW: Pass metadata for displaying component status
                    $user['componentsAvailable'] = $cached['components_available'] ?? 0;
                    $user['missingComponents'] = $cached['missing_components'] ?? [];
                    $user['isCeo'] = $cached['is_ceo'] ?? false;
                    $user['complete'] = $cached['complete'] ?? true;
                } else {
                    // No cached data - user has no results
                    $user['stats'] = null;
                    $user['bonusMalus'] = null;
                    $user['change'] = 'none';
                    $user['componentsAvailable'] = 0;
                    $user['missingComponents'] = [];
                    $user['isCeo'] = false;
                    $user['complete'] = false;
                }

                return $user;
            })
            ->filter(function ($user) {
                return !is_null($user->stats);
            })
            ->values();


        $summaryHu = null;
        $summaryDbg = null;

        if (strtolower((string)($assessment->threshold_method ?? '')) === 'suggested') {
            // Parse JSON - suggested_decision is an array with decision objects
            $logs = is_string($assessment->suggested_decision)
                ? json_decode($assessment->suggested_decision, true)
                : $assessment->suggested_decision;
            
            if (is_array($logs) && count($logs) > 0) {
                // Get the first (or last) decision object
                // Based on the SQL dump, the structure is: [{"thresholds":{...}, "decisions":[...], "rates":{...}, "summary_hu":"..."}]
                $decision = end($logs); // Get last decision (most recent)
                
                // Extract summary_hu directly from the decision object
                if (isset($decision['summary_hu'])) {
                    $summaryHu = $decision['summary_hu'];
                }
                
                // For debugging, store the entire decision object
                if (!empty($decision)) {
                    $summaryDbg = json_encode($decision, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
            }
        }



        return view('admin.results', [
    'assessment'     => $assessment,
    'users'          => $users,
    'prevAssessment' => $prevAssessment,
    'nextAssessment' => $nextAssessment,
    'summaryHu'      => $summaryHu,
    'summaryDbg'     => $summaryDbg,
    'showBonusMalus' => $showBonusMalus,
]);
    }
}
