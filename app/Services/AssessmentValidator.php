<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Enums\UserType;
use App\Models\Enums\OrgRole;

class AssessmentValidator
{
    /**
     * Validate that an organization can create a new assessment
     * 
     * @param int $orgId
     * @throws ValidationException
     */
    public function validateAssessmentCreation(int $orgId): void
    {
        // 1. Check minimum number of users (at least 2 for peer evaluation)
        $this->validateMinimumUsers($orgId, 2);
        
        // 2. Check that users have competencies assigned
        $this->validateUserCompetencies($orgId);
        
        // 3. Check that users have relations defined
        $this->validateUserRelations($orgId);
    }
    
    /**
     * Validate organization has minimum number of active users
     * 
     * @param int $orgId
     * @param int $minUsers
     * @throws ValidationException
     */
    protected function validateMinimumUsers(int $orgId, int $minUsers = 2): void
    {
        // FIXED: Only exclude SUPERADMIN (system-level), not organization admins
        $userCount = User::whereHas('organizations', function($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->whereNull('removed_at')
            ->where('type', '!=', UserType::SUPERADMIN)  // Only exclude superadmins
            ->count();
        
        if ($userCount < $minUsers) {
            throw ValidationException::withMessages([
                'users' => "Legalább {$minUsers} aktív felhasználó szükséges az értékelés indításához. Jelenleg: {$userCount} fő."
            ]);
        }
    }
    
    /**
     * Validate that users have competencies assigned
     * 
     * @param int $orgId
     * @throws ValidationException
     */
    protected function validateUserCompetencies(int $orgId): void
    {
        // Get active users in organization
        $userIds = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->pluck('user_id')
            ->toArray();
        
        // FIXED: Only exclude SUPERADMIN (system-level)
        $activeUserIds = User::whereIn('id', $userIds)
            ->whereNull('removed_at')
            ->where('type', '!=', UserType::SUPERADMIN)  // Only exclude superadmins
            ->pluck('id')
            ->toArray();
        
        if (empty($activeUserIds)) {
            return; // No active users, will be caught by validateMinimumUsers
        }
        
        // Check which users have NO competencies
        $usersWithoutCompetencies = User::whereIn('id', $activeUserIds)
            ->whereDoesntHave('competencies', function($q) use ($orgId) {
                $q->where('user_competency.organization_id', $orgId);
            })
            ->pluck('name')
            ->toArray();
        
        if (!empty($usersWithoutCompetencies)) {
            $userList = implode(', ', array_slice($usersWithoutCompetencies, 0, 5));
            $remaining = count($usersWithoutCompetencies) - 5;
            
            if ($remaining > 0) {
                $userList .= " (és még {$remaining} fő)";
            }
            
            throw ValidationException::withMessages([
                'competencies' => "A következő felhasználóknak nincsenek kompetenciák hozzárendelve: {$userList}. " .
                                  "Minden felhasználónak legalább 1 kompetenciával kell rendelkeznie."
            ]);
        }
    }
    
    /**
     * Validate that users have relations defined
     * 
     * @param int $orgId
     * @throws ValidationException
     */
    protected function validateUserRelations(int $orgId): void
    {
        // Get active users
        $userIds = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->pluck('user_id')
            ->toArray();
        
        // FIXED: Only exclude SUPERADMIN (system-level)
        $activeUserIds = User::whereIn('id', $userIds)
            ->whereNull('removed_at')
            ->where('type', '!=', UserType::SUPERADMIN)  // Only exclude superadmins
            ->pluck('id')
            ->toArray();
        
        if (empty($activeUserIds)) {
            return;
        }
        
        // Check which users have NO relations (excluding self-relations)
        $usersWithoutRelations = DB::table('user as u')
            ->whereIn('u.id', $activeUserIds)
            ->whereNotExists(function($q) use ($orgId) {
                $q->select(DB::raw(1))
                  ->from('user_relation as ur')
                  ->whereColumn('ur.user_id', 'u.id')
                  ->where('ur.organization_id', $orgId)
                  ->where('ur.type', '!=', 'self'); // Exclude self-relations
            })
            ->pluck('u.name')
            ->toArray();
        
        if (!empty($usersWithoutRelations)) {
            $userList = implode(', ', array_slice($usersWithoutRelations, 0, 5));
            $remaining = count($usersWithoutRelations) - 5;
            
            if ($remaining > 0) {
                $userList .= " (és még {$remaining} fő)";
            }
            
            throw ValidationException::withMessages([
                'relations' => "A következő felhasználóknak nincsenek kapcsolatok definiálva: {$userList}. " .
                               "Minden felhasználónak legalább 1 kolléga vagy beosztott kapcsolattal kell rendelkeznie."
            ]);
        }
    }
    
    /**
     * Validate that an assessment is ready to be closed
     * 
     * @param int $assessmentId
     * @throws ValidationException
     */
    public function validateAssessmentReadyToClose(int $assessmentId): void
    {
        // 1. Check all users have been evaluated
        $this->validateAllUsersEvaluated($assessmentId);
        
        // 2. Check minimum scores per user (at least 1 colleague, 1 subordinate if applicable)
        $this->validateMinimumScoresPerUser($assessmentId);
        
        // 3. Check CEO ranks are complete
        $this->validateCeoRanksComplete($assessmentId);
    }
    
    /**
     * Validate all users have submitted their evaluations
     * 
     * @param int $assessmentId
     * @throws ValidationException
     */
    protected function validateAllUsersEvaluated(int $assessmentId): void
    {
        // Get assessment and organization
        $assessment = DB::table('assessment')->find($assessmentId);
        
        if (!$assessment) {
            throw ValidationException::withMessages([
                'assessment' => 'Az értékelés nem található.'
            ]);
        }
        
        $orgId = $assessment->organization_id;
        
        // Get total expected submissions (relations count, excluding self)
        $expectedSubmissions = DB::table('user_relation')
            ->where('organization_id', $orgId)
            ->where('type', '!=', 'self')
            ->count();
        
        // Get actual submissions
        $actualSubmissions = DB::table('user_competency_submit')
            ->where('assessment_id', $assessmentId)
            ->count();
        
        if ($actualSubmissions < $expectedSubmissions) {
            $remaining = $expectedSubmissions - $actualSubmissions;
            $percentage = round(($actualSubmissions / $expectedSubmissions) * 100, 1);
            
            throw ValidationException::withMessages([
                'submissions' => "Még nem minden értékelés lett leadva. " .
                                "Kitöltöttség: {$actualSubmissions}/{$expectedSubmissions} ({$percentage}%). " .
                                "Hiányzó értékelések: {$remaining} db."
            ]);
        }
    }
    
    /**
     * Validate each user has minimum required evaluations
     * 
     * @param int $assessmentId
     * @throws ValidationException
     */
    protected function validateMinimumScoresPerUser(int $assessmentId): void
    {
        // Get all users who should be evaluated
        $assessment = DB::table('assessment')->find($assessmentId);
        $orgId = $assessment->organization_id;
        
        // Get user IDs who are targets in this assessment
        $targetIds = DB::table('user_relation')
            ->where('organization_id', $orgId)
            ->where('type', '!=', 'self')
            ->distinct('target_id')
            ->pluck('target_id')
            ->toArray();
        
        $usersWithIncompleteData = [];
        
        foreach ($targetIds as $targetId) {
            // Count colleague evaluations (excluding self)
            $colleagueCount = DB::table('competency_submit')
                ->where('assessment_id', $assessmentId)
                ->where('target_id', $targetId)
                ->where('type', 'colleague')
                ->count();
            
            // Count subordinate evaluations
            $subordinateCount = DB::table('competency_submit')
                ->where('assessment_id', $assessmentId)
                ->where('target_id', $targetId)
                ->where('type', 'subordinate')
                ->count();
            
            // Get user name
            $userName = DB::table('user')->where('id', $targetId)->value('name');
            
            $issues = [];
            if ($colleagueCount === 0) {
                $issues[] = 'nincs kolléga értékelés';
            }
            
            if (!empty($issues)) {
                $usersWithIncompleteData[] = "{$userName}: " . implode(', ', $issues);
            }
        }
        
        if (!empty($usersWithIncompleteData)) {
            $userList = implode('; ', array_slice($usersWithIncompleteData, 0, 5));
            $remaining = count($usersWithIncompleteData) - 5;
            
            if ($remaining > 0) {
                $userList .= " (és még {$remaining} fő)";
            }
            
            throw ValidationException::withMessages([
                'incomplete_data' => "A következő felhasználók értékelése hiányos: {$userList}"
            ]);
        }
    }
    
    /**
     * Validate CEO rankings are complete
     * 
     * @param int $assessmentId
     * @throws ValidationException
     */
    protected function validateCeoRanksComplete(int $assessmentId): void
    {
        // Get assessment
        $assessment = DB::table('assessment')->find($assessmentId);
        $orgId = $assessment->organization_id;
        
        // FIXED: Count CEOs using organization_user.role instead of user.type
        $ceoCount = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('ou.role', OrgRole::CEO)  // FIXED: Use organization_user.role
            ->whereNull('u.removed_at')
            ->count();
        
        // FIXED: Count managers using organization_user.role
        $managerCount = DB::table('organization_department_managers as odm')
            ->join('user as u', 'u.id', '=', 'odm.manager_id')
            ->where('odm.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->distinct('odm.manager_id')
            ->count('odm.manager_id');
        
        $neededRankers = $ceoCount + $managerCount;
        
        if ($neededRankers === 0) {
            // No CEOs or managers - skip validation
            return;
        }
        
        // Count actual rankings submitted
        $actualRankings = DB::table('user_ceo_rank')
            ->where('assessment_id', $assessmentId)
            ->distinct('ceo_id')
            ->count('ceo_id');
        
        if ($actualRankings < $neededRankers) {
            $remaining = $neededRankers - $actualRankings;
            
            throw ValidationException::withMessages([
                'ceo_ranks' => "Nem minden vezető adta le a rangsorolást. " .
                               "Leadott: {$actualRankings}/{$neededRankers}. " .
                               "Hiányzó rangsorolások: {$remaining} db."
            ]);
        }
    }
}