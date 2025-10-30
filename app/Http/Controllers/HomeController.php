<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CeoRank;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserBonusMalus;
use App\Models\UserCeoRank;
use App\Models\UserRelation;
use App\Services\AssessmentService;
use App\Services\UserService;
use App\Services\WelcomeMessageService;
use App\Services\OrgConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\OrgRole;
use App\Services\RoleHelper; 

class HomeController extends Controller
{
    public function admin(Request $request)
    {
        $orgId = (int) session('org_id');
        $assessment = AssessmentService::getCurrentAssessment();

        // Dolgozók betöltése
        $employees = User::whereNull('removed_at')
            ->whereHas('organizations', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                  ->where('organization_user.role', '!=', OrgRole::ADMIN);
            })
            ->withCount('relations')
            ->get()
            ->map(function ($user) {
                $user['competency_submits_count'] = $user->competencySubmits()->count();
                $user['self_competency_submit_count'] = $user->selfCompetencySubmit()->count() > 0;
                return $user;
            });

        // ✅ FIXED: Calculate needed CEO ranks based on multi-level mode
        $multiLevelEnabled = OrgConfigService::getBool($orgId, 'enable_multi_level', false);
        
        if ($multiLevelEnabled) {
            // Multi-level ON: CEOs + Managers who have employees in their departments
            $ceoCount = RoleHelper::countByRole($orgId, OrgRole::CEO);
            
            // Count managers who have at least one employee in their department(s)
            $managerCount = DB::table('organization_department_managers as odm')
                ->join('organization_user as ou', function ($j) {
                    $j->on('ou.organization_id', '=', 'odm.organization_id')
                      ->on('ou.department_id', '=', 'odm.department_id');
                })
                ->where('odm.organization_id', $orgId)
                ->where('ou.role', '!=', OrgRole::MANAGER)  // Exclude managers from being counted as subordinates
                ->where('ou.role', '!=', OrgRole::ADMIN)    // Exclude admins
                ->where('ou.role', '!=', OrgRole::CEO)      // Exclude CEOs
                ->distinct('odm.manager_id')
                ->count('odm.manager_id');
            
            $neededCeoRanks = $ceoCount + $managerCount;
        } else {
            // Multi-level OFF: Only CEOs rank
            $neededCeoRanks = RoleHelper::countByRole($orgId, OrgRole::CEO);
        }

        // Nyitott fizetések ellenőrzése
        $hasOpenPayments = DB::table('payments')
            ->where('organization_id', $orgId)
            ->whereNull('paid_at')
            ->whereNull('billingo_document_id')
            ->exists();

        // ✅ NEW: Calculate progress percentages for stats tiles
        $assessed = $assessment?->userCompetencySubmits()->count() ?? 0;
        $neededAssessment = UserRelation::where('organization_id', $orgId)->count();
        $ceoRanks = $assessment?->ceoRanks()->distinct('ceo_id')->count() ?? 0;
        
        $assessmentPercent = $neededAssessment > 0 
            ? round(($assessed / $neededAssessment) * 100, 1) 
            : 0;
        
        $ceoRankPercent = $neededCeoRanks > 0 
            ? round(($ceoRanks / $neededCeoRanks) * 100, 1) 
            : 0;

        // ✅ NEW: Calculate employee progress percentages
        $employees = $employees->map(function ($employee) {
            $totalNeeded = $employee->relations_count + 1; // +1 for self-assessment
            $totalDone = $employee->competency_submits_count + ($employee->self_competency_submit_count ? 1 : 0);
            $employee['progressPercent'] = $totalNeeded > 0 
                ? round(($totalDone / $totalNeeded) * 100, 1) 
                : 0;
            return $employee;
        });

        return view('admin.home', [
            'assessment'          => $assessment,
            'employees'           => $employees,
            'neededCeoRanks'      => $neededCeoRanks,
            'ceoRanks'            => $ceoRanks,
            'neededAssessment'    => $neededAssessment,
            'assessed'            => $assessed,
            'hasOpenPayments'     => $hasOpenPayments,
            'assessmentPercent'   => $assessmentPercent,    // ✅ NEW
            'ceoRankPercent'      => $ceoRankPercent,       // ✅ NEW
        ]);
    }

    public function normal(Request $request)
    {
        $orgId = session('org_id');
        $userId = session('uid');
        $orgRole = session('org_role');
        
        $assessment = AssessmentService::getCurrentAssessment();
        $user = UserService::getCurrentUser();
        
        // ✅ FIXED: Check if user has made CEO rank AND has employees to rank
        $madeCeoRank = false;
        $canAccessCeoRank = false;
        
        if ($assessment) {
            // Check if user is CEO or Manager
            if ($orgRole === OrgRole::CEO || $orgRole === OrgRole::MANAGER) {
                // Check if they have employees to rank
                $multiLevelEnabled = OrgConfigService::getBool($orgId, 'enable_multi_level', false);
                $hasEmployeesToRank = $this->checkIfUserHasEmployeesToRank($orgId, $userId, $orgRole, $multiLevelEnabled);
                
                if ($hasEmployeesToRank) {
                    $canAccessCeoRank = true;
                    
                    // Check if they've already completed the ranking
                    $madeCeoRank = UserCeoRank::where('assessment_id', $assessment->id)
                        ->where('ceo_id', $userId)
                        ->exists();
                }
            }
        }
        
        return view('home', [
            "welcomeMessage" => WelcomeMessageService::generate(),
            'assessment' => $assessment,
            'relations' => $user->relations()
                ->whereNotIn('target_id', $user->competencySubmits->map(
                    function($sub){ return $sub->target_id; }))
                ->with('target')->get(),
            'selfAssessed' => $user->selfCompetencySubmit()->count() == 1,
            'madeCeoRank' => $madeCeoRank,  // ✅ FIXED: Only true if they have employees AND completed
            'canAccessCeoRank' => $canAccessCeoRank,  // ✅ NEW: Flag to show/hide the section
        ]);
    }
    
    /**
     * ✅ NEW METHOD: Check if a user (CEO or Manager) has employees to rank
     * 
     * This replicates the logic from CeoRankController::getAllowedTargets()
     * to determine if a user should see the CEO ranking task.
     * 
     * @param int $orgId Organization ID
     * @param int $userId User ID
     * @param string $orgRole User's org role
     * @param bool $multiLevelEnabled Whether multi-level mode is enabled
     * @return bool True if user has employees to rank
     */
    private function checkIfUserHasEmployeesToRank(int $orgId, int $userId, string $orgRole, bool $multiLevelEnabled): bool
    {
        if (!$multiLevelEnabled) {
            // Multi-level OFF: Only CEOs can rank, and they rank all non-admin users
            if ($orgRole !== OrgRole::CEO) {
                return false;
            }
            
            // Check if there are any non-admin users to rank
            $count = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('role', '!=', OrgRole::ADMIN)
                ->where('role', '!=', OrgRole::CEO)
                ->count();
            
            return $count > 0;
        }
        
        // Multi-level ON
        if ($orgRole === OrgRole::CEO) {
            // CEO ranks: managers + unassigned employees
            // Get all managers
            $managerCount = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('role', OrgRole::MANAGER)
                ->count();
            
            // Get unassigned users (excluding CEO, ADMIN, and MANAGER roles)
            $unassignedCount = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->whereNull('department_id')
                ->where('role', '!=', OrgRole::MANAGER)
                ->where('role', '!=', OrgRole::ADMIN)
                ->where('role', '!=', OrgRole::CEO)
                ->count();
            
            return ($managerCount + $unassignedCount) > 0;
        }
        
        if ($orgRole === OrgRole::MANAGER) {
            // Manager ranks: only their department employees
            // Get manager's department IDs
            $deptIds = DB::table('organization_department_managers')
                ->where('organization_id', $orgId)
                ->where('manager_id', $userId)
                ->pluck('department_id')
                ->all();
            
            if (empty($deptIds)) {
                return false;  // Manager has no departments
            }
            
            // Check if there are subordinates in those departments
            $subordinateCount = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->whereIn('department_id', $deptIds)
                ->where('role', '!=', OrgRole::MANAGER)
                ->where('role', '!=', OrgRole::ADMIN)
                ->where('role', '!=', OrgRole::CEO)
                ->count();
            
            return $subordinateCount > 0;
        }
        
        return false;
    }
}