<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Services\UserService;
use App\Services\OrgConfigService;
use Illuminate\Http\Request;
use App\Models\Enums\OrgRole;
use Illuminate\Support\Facades\DB;

class AdminResultsController extends Controller
{
    public function index(Request $request, ?int $assessmentId = null)
    {
        $orgId = (int) session('org_id');

        // Get current closed assessment (specific ID or latest in organization)
        $assessment = $assessmentId
            ? Assessment::where('organization_id', $orgId)->whereNotNull('closed_at')->find($assessmentId)
            : Assessment::where('organization_id', $orgId)->whereNotNull('closed_at')->orderByDesc('closed_at')->first();

        if (!$assessment) {
            return view('admin.results', [
                'assessment'        => null,
                'users'             => collect(),
                'departments'       => collect(),
                'prevAssessment'    => null,
                'nextAssessment'    => null,
                'enableMultiLevel'  => false,
                'showBonusMalus'    => false,
            ]);
        }

        $enableMultiLevel = OrgConfigService::getBool($orgId, 'enable_multi_level', false);
        $showBonusMalus = OrgConfigService::getBool($orgId, 'show_bonus_malus', true);

        // Previous/next closed assessment
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

        // Participant users (admin/superadmin out), then filter for those who have stats in this assessment
        $users = User::whereNull('removed_at')
            ->where('type', '!=', UserType::SUPERADMIN)
            ->whereHas('organizations', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                  ->where('organization_user.role', '!=', OrgRole::ADMIN);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($user) use ($assessment, $orgId) {
                // Get cached results from snapshot
                $cached = UserService::getUserResultsFromSnapshot($assessment->id, $user->id);
                
                if ($cached) {
                    // Use cached data - FAST!
                    $user['stats'] = UserService::snapshotResultToStdClass($cached);
                    $user['bonusMalus'] = $cached['bonus_malus_level'];
                    $user['change'] = $cached['change'];
                    $user['componentsAvailable'] = $cached['components_available'] ?? 0;
                    $user['missingComponents'] = $cached['missing_components'] ?? [];
                    $user['isCeo'] = $cached['is_ceo'] ?? false;
                } else {
                    $user['stats'] = null;
                    $user['bonusMalus'] = null;
                    $user['change'] = 'stable';
                    $user['componentsAvailable'] = 0;
                    $user['missingComponents'] = [];
                    $user['isCeo'] = false;
                }

                // Get position and email from organization_user
                $orgUser = DB::table('organization_user')
                    ->where('organization_id', $orgId)
                    ->where('user_id', $user->id)
                    ->first(['position', 'department_id', 'role']);

                $user['position'] = $orgUser->position ?? null;
                $user['department_id'] = $orgUser->department_id ?? null;
                $user['role'] = $orgUser->role ?? null;

                return $user;
            })
            ->filter(fn ($u) => $u['stats'] !== null);

        // Get departments if multi-level is enabled
        $departments = collect();
        
        if ($enableMultiLevel) {
            $deptList = DB::table('organization_departments')
                ->where('organization_id', $orgId)
                ->whereNull('removed_at')
                ->orderBy('department_name')
                ->get(['id', 'department_name']);

            $departments = $deptList->map(function ($dept) use ($users) {
                $deptUsers = $users->filter(fn($u) => $u['department_id'] == $dept->id);
                
                return (object) [
                    'id' => $dept->id,
                    'department_name' => $dept->department_name,
                    'users' => $deptUsers,
                ];
            });

            // Add CEO and unassigned users at the top
            $ceosAndUnassigned = $users->filter(function($u) {
                return $u['role'] === OrgRole::CEO || is_null($u['department_id']);
            });

            if ($ceosAndUnassigned->isNotEmpty()) {
                $departments->prepend((object) [
                    'id' => null,
                    'department_name' => __('admin/employees.ceo-and-unassigned'),
                    'users' => $ceosAndUnassigned,
                ]);
            }

            // Remove these users from the main users list (they're now in departments)
            $users = collect();
        }

        // Get AI summary if threshold method is 'suggested'
        $summaryHu = null;
        $summaryDbg = null;
        
        if (strtolower((string)($assessment->threshold_method ?? '')) === 'suggested') {
            $decision = $assessment->suggested_decision;
            if ($decision && isset($decision['summary_hu'])) {
                $summaryHu = $decision['summary_hu'];
            } else {
                $summaryDbg = 'AI summary not available';
            }
        }

        return view('admin.results', [
            'assessment'        => $assessment,
            'users'             => $users,
            'departments'       => $departments,
            'prevAssessment'    => $prevAssessment,
            'nextAssessment'    => $nextAssessment,
            'summaryHu'         => $summaryHu,
            'summaryDbg'        => $summaryDbg,
            'showBonusMalus'    => $showBonusMalus,
            'enableMultiLevel'  => $enableMultiLevel,
        ]);
    }
}