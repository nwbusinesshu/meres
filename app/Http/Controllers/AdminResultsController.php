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
                $user['email'] = $user->email ?? '';

                return $user;
            })
            ->filter(fn ($u) => $u['stats'] !== null);

        // Get departments if multi-level is enabled
        $departments = collect();
        
        if ($enableMultiLevel) {
            // First, collect ALL manager IDs across all departments
            $allManagerIds = DB::table('organization_department_managers')
                ->where('organization_id', $orgId)
                ->pluck('manager_id')
                ->toArray();

            $deptList = DB::table('organization_departments')
                ->where('organization_id', $orgId)
                ->whereNull('removed_at')
                ->orderBy('department_name')
                ->get(['id', 'department_name']);

            $departments = $deptList->map(function ($dept) use ($users, $orgId, $assessment, $allManagerIds) {
                // Get managers for this department from organization_department_managers
                $managerData = DB::table('organization_department_managers as odm')
                    ->join('user as u', 'u.id', '=', 'odm.manager_id')
                    ->join('organization_user as ou', function($join) use ($orgId) {
                        $join->on('ou.user_id', '=', 'u.id')
                             ->where('ou.organization_id', '=', $orgId);
                    })
                    ->where('odm.organization_id', $orgId)
                    ->where('odm.department_id', $dept->id)
                    ->whereNull('u.removed_at')
                    ->select('u.id', 'u.name', 'u.email', 'ou.position', 'ou.role')
                    ->get();

                // Map managers with their results
                $managers = $managerData->map(function($manager) use ($assessment, $users) {
                    // Find this manager in the users collection to get their stats
                    $userWithStats = $users->firstWhere('id', $manager->id);
                    
                    if ($userWithStats) {
                        $manager->stats = $userWithStats['stats'];
                        $manager->bonusMalus = $userWithStats['bonusMalus'];
                        $manager->change = $userWithStats['change'];
                        $manager->componentsAvailable = $userWithStats['componentsAvailable'];
                        $manager->missingComponents = $userWithStats['missingComponents'];
                        $manager->isCeo = false; // Managers are not CEOs
                        $manager->isManager = true; // Mark as manager
                    } else {
                        $manager->stats = null;
                        $manager->bonusMalus = null;
                        $manager->change = 'stable';
                        $manager->componentsAvailable = 0;
                        $manager->missingComponents = [];
                        $manager->isCeo = false;
                        $manager->isManager = true;
                    }
                    
                    return $manager;
                })->filter(fn($m) => $m->stats !== null);

                // Get regular department members (excluding managers)
                $managerIds = $managers->pluck('id')->toArray();
                $deptUsers = $users->filter(function($u) use ($dept, $managerIds) {
                    return $u['department_id'] == $dept->id && !in_array($u->id, $managerIds);
                });
                
                return (object) [
                    'id' => $dept->id,
                    'department_name' => $dept->department_name,
                    'managers' => $managers,
                    'users' => $deptUsers,
                ];
            });

            // Add CEO and unassigned users at the top (EXCLUDING managers)
            $ceosAndUnassigned = $users->filter(function($u) use ($allManagerIds) {
                // Only include if: CEO OR (unassigned AND not a manager)
                return $u['role'] === OrgRole::CEO || 
                       (is_null($u['department_id']) && !in_array($u->id, $allManagerIds));
            });

            if ($ceosAndUnassigned->isNotEmpty()) {
                $departments->prepend((object) [
                    'id' => null,
                    'department_name' => __('admin/employees.ceo-and-unassigned'),
                    'managers' => collect(),
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