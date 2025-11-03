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
        $userCount = User::whereHas('organizations', function($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                  ->where('role', '!=', OrgRole::ADMIN);
            })
            ->whereNull('removed_at')
            ->where('type', '!=', UserType::SUPERADMIN)
            ->count();
        
        if ($userCount < $minUsers) {
            throw ValidationException::withMessages([
                'users' => __('assessment.min-users-required', ['min' => $minUsers, 'count' => $userCount])
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
        $userIds = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('role', '!=', OrgRole::ADMIN)
            ->pluck('user_id')
            ->toArray();
        
        $activeUserIds = User::whereIn('id', $userIds)
            ->whereNull('removed_at')
            ->where('type', '!=', UserType::SUPERADMIN)
            ->pluck('id')
            ->toArray();
        
        if (empty($activeUserIds)) {
            return;
        }
        
        $usersWithoutCompetencies = DB::table('user as u')
            ->whereIn('u.id', $activeUserIds)
            ->whereNotExists(function($q) use ($orgId) {
                $q->select(DB::raw(1))
                  ->from('user_competency as uc')
                  ->whereColumn('uc.user_id', 'u.id')
                  ->where('uc.organization_id', $orgId);
            })
            ->pluck('u.name')
            ->toArray();
        
        if (!empty($usersWithoutCompetencies)) {
            $userList = implode(', ', array_slice($usersWithoutCompetencies, 0, 5));
            $remaining = count($usersWithoutCompetencies) - 5;
            
            if ($remaining > 0) {
                $userList .= ' ' . __('assessment.and-more', ['count' => $remaining]);
            }

            
            throw ValidationException::withMessages([
                'competencies' => __('assessment.no-competencies', ['users' => $userList])
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
        $userIds = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('role', '!=', OrgRole::ADMIN)
            ->pluck('user_id')
            ->toArray();
        
        $activeUserIds = User::whereIn('id', $userIds)
            ->whereNull('removed_at')
            ->where('type', '!=', UserType::SUPERADMIN)
            ->pluck('id')
            ->toArray();
        
        if (empty($activeUserIds)) {
            return;
        }
        
        $usersWithoutRelations = DB::table('user as u')
            ->whereIn('u.id', $activeUserIds)
            ->whereNotExists(function($q) use ($orgId) {
                $q->select(DB::raw(1))
                  ->from('user_relation as ur')
                  ->whereColumn('ur.user_id', 'u.id')
                  ->where('ur.organization_id', $orgId)
                  ->where('ur.type', '!=', 'self');
            })
            ->pluck('u.name')
            ->toArray();
        
        if (!empty($usersWithoutRelations)) {
            $userList = implode(', ', array_slice($usersWithoutRelations, 0, 5));
            $remaining = count($usersWithoutRelations) - 5;
            
            if ($remaining > 0) {
                $userList .= ' ' . __('assessment.and-more', ['count' => $remaining]);
            }

            throw ValidationException::withMessages([
                'relations' => __('assessment.no-relations', ['users' => $userList])
            ]);
        }
    }
    
    /**
     * ========================================================================
     * NEW VALIDATION LOGIC - Assessment Ready to Close
     * ========================================================================
     * 
     * Validate that an assessment is ready to be closed with NEW rules:
     * 1. Every user must have self-evaluation
     * 2. Every non-CEO must have CEO rank
     * 3. Every CEO must have direct reports feedback
     * 4. Every user must have external feedback
     * 
     * @param int $assessmentId
     * @throws ValidationException
     */
    public function validateAssessmentReadyToClose(int $assessmentId): void
    {
        // 1. Every user must have self-evaluation
        $this->validateAllUsersHaveSelfEvaluation($assessmentId);
        
        // 2. Every non-CEO must have CEO rank
        $this->validateNonCeosHaveCeoRank($assessmentId);
        
        // 3. Every CEO must have direct reports feedback
        $this->validateCeosHaveDirectReports($assessmentId);
        
        // 4. Every user must have external feedback
        $this->validateUsersHaveExternalFeedback($assessmentId);
    }
    
    /**
     * NEW RULE 1: Validate all users have completed self-evaluation
     * 
     * @param int $assessmentId
     * @throws ValidationException
     */
    protected function validateAllUsersHaveSelfEvaluation(int $assessmentId): void
    {
        $assessment = DB::table('assessment')->find($assessmentId);
        
        if (!$assessment) {
            throw ValidationException::withMessages([
                'assessment' => __('assessment.not-found')
            ]);
        }
        
        $snapshot = json_decode($assessment->org_snapshot, true);
        if (!$snapshot) {
            throw ValidationException::withMessages([
                'snapshot' => __('assessment.no-snapshot')
            ]);
        }
        
        // Get all user IDs from snapshot
        $allUserIds = array_keys($snapshot['_index']['user_ids'] ?? []);
        
        if (empty($allUserIds)) {
            return; // No users to validate
        }
        
        $usersWithoutSelf = [];
        
        foreach ($allUserIds as $userId) {
            // Check if user has self-evaluation (user_competency_submit table)
            $hasSelf = DB::table('user_competency_submit')
                ->where('assessment_id', $assessmentId)
                ->where('user_id', $userId)
                ->where('target_id', $userId)
                ->exists();
            
            if (!$hasSelf) {
                // Get user name from snapshot
                $userName = null;
                foreach ($snapshot['users'] ?? [] as $u) {
                    if ((int)($u['id'] ?? 0) === $userId) {
                        $userName = $u['name'] ?? null;
                        break;
                    }
                }
                
                if (!$userName) {
                    $user = DB::table('user')->find($userId);
                    $userName = $user->name ?? "User ID {$userId}";
                }
                
                $usersWithoutSelf[] = $userName;
            }
        }
        
        if (!empty($usersWithoutSelf)) {
            $userList = implode(', ', array_slice($usersWithoutSelf, 0, 10));
            $remaining = count($usersWithoutSelf) - 10;
            
            if ($remaining > 0) {
                $userList .= ' ' . __('assessment.and-more', ['count' => $remaining]);
            }

            throw ValidationException::withMessages([
                'self_evaluation' => __('assessment.no-self-evaluation', ['users' => $userList])
            ]);
        }
    }
    
    /**
     * NEW RULE 2: Validate all non-CEOs have CEO rank
     * 
     * @param int $assessmentId
     * @throws ValidationException
     */
    protected function validateNonCeosHaveCeoRank(int $assessmentId): void
    {
        $assessment = DB::table('assessment')->find($assessmentId);
        $snapshot = json_decode($assessment->org_snapshot, true);
        
        if (!$snapshot) {
            throw ValidationException::withMessages([
                'snapshot' => __('assessment.no-snapshot')
            ]);
        }
        
        $ceoIds = array_keys($snapshot['_index']['ceo_ids'] ?? []);
        $allUserIds = array_keys($snapshot['_index']['user_ids'] ?? []);
        $nonCeoIds = array_diff($allUserIds, $ceoIds);
        
        if (empty($nonCeoIds)) {
            return; // No non-CEOs to validate
        }
        
        $usersWithoutCeoRank = [];
        
        foreach ($nonCeoIds as $userId) {
            // Check if user has been ranked by any CEO
            $hasCeoRank = DB::table('user_ceo_rank')
                ->where('assessment_id', $assessmentId)
                ->where('user_id', $userId)
                ->exists();
            
            if (!$hasCeoRank) {
                // Get user name from snapshot
                $userName = null;
                foreach ($snapshot['users'] ?? [] as $u) {
                    if ((int)($u['id'] ?? 0) === $userId) {
                        $userName = $u['name'] ?? null;
                        break;
                    }
                }
                
                if (!$userName) {
                    $user = DB::table('user')->find($userId);
                    $userName = $user->name ?? "User ID {$userId}";
                }
                
                $usersWithoutCeoRank[] = $userName;
            }
        }
        
        if (!empty($usersWithoutCeoRank)) {
            $userList = implode(', ', array_slice($usersWithoutCeoRank, 0, 10));
            $remaining = count($usersWithoutCeoRank) - 10;
            
            if ($remaining > 0) {
                $userList .= ' ' . __('assessment.and-more', ['count' => $remaining]);
            }

            throw ValidationException::withMessages([
                'ceo_rank' => __('assessment.no-ceo-rank', ['users' => $userList])
            ]);
        }
    }
    
    /**
     * NEW RULE 3: Validate all CEOs have direct reports feedback
     * 
     * @param int $assessmentId
     * @throws ValidationException
     */
    protected function validateCeosHaveDirectReports(int $assessmentId): void
    {
        $assessment = DB::table('assessment')->find($assessmentId);
        $snapshot = json_decode($assessment->org_snapshot, true);
        
        if (!$snapshot) {
            throw ValidationException::withMessages([
                'snapshot' => __('assessment.no-snapshot')
            ]);
        }
        
        $ceoIds = array_keys($snapshot['_index']['ceo_ids'] ?? []);
        
        if (empty($ceoIds)) {
            return; // No CEOs to validate
        }
        
        $ceosWithoutFeedback = [];
        
        foreach ($ceoIds as $ceoId) {
            // Check if CEO has feedback from direct reports (type='superior' in competency_submit)
            $hasDirectReports = DB::table('competency_submit')
                ->where('assessment_id', $assessmentId)
                ->where('target_id', $ceoId)
                ->where('type', 'superior')
                ->exists();
            
            if (!$hasDirectReports) {
                // Get CEO name from snapshot
                $ceoName = null;
                foreach ($snapshot['users'] ?? [] as $u) {
                    if ((int)($u['id'] ?? 0) === $ceoId) {
                        $ceoName = $u['name'] ?? null;
                        break;
                    }
                }
                
                if (!$ceoName) {
                    $user = DB::table('user')->find($ceoId);
                    $ceoName = $user->name ?? "CEO ID {$ceoId}";
                }
                
                $ceosWithoutFeedback[] = $ceoName;
            }
        }
        
        if (!empty($ceosWithoutFeedback)) {
            $userList = implode(', ', array_slice($ceosWithoutFeedback, 0, 10));
            $remaining = count($ceosWithoutFeedback) - 10;
            
            if ($remaining > 0) {
                $userList .= ' ' . __('assessment.and-more', ['count' => $remaining]);
            }

            throw ValidationException::withMessages([
                'ceo_feedback' => __('assessment.ceo-no-feedback', ['users' => $userList])
            ]);
        }
    }
    
    /**
     * NEW RULE 4: Validate all users have external feedback
     * 
     * External feedback means any evaluation where user_id != target_id
     * 
     * @param int $assessmentId
     * @throws ValidationException
     */
    protected function validateUsersHaveExternalFeedback(int $assessmentId): void
    {
        $assessment = DB::table('assessment')->find($assessmentId);
        $snapshot = json_decode($assessment->org_snapshot, true);
        
        if (!$snapshot) {
            throw ValidationException::withMessages([
                'snapshot' => __('assessment.no-snapshot')
            ]);
        }
        
        $allUserIds = array_keys($snapshot['_index']['user_ids'] ?? []);
        
        if (empty($allUserIds)) {
            return; // No users to validate
        }
        
        $usersWithoutExternal = [];
        
        foreach ($allUserIds as $userId) {
            // Check if user has been evaluated by others (user_competency_submit where user_id != target_id)
            $hasExternal = DB::table('user_competency_submit')
                ->where('assessment_id', $assessmentId)
                ->where('target_id', $userId)
                ->where('user_id', '!=', $userId)
                ->exists();
            
            if (!$hasExternal) {
                // Get user name from snapshot
                $userName = null;
                foreach ($snapshot['users'] ?? [] as $u) {
                    if ((int)($u['id'] ?? 0) === $userId) {
                        $userName = $u['name'] ?? null;
                        break;
                    }
                }
                
                if (!$userName) {
                    $user = DB::table('user')->find($userId);
                    $userName = $user->name ?? "User ID {$userId}";
                }
                
                $usersWithoutExternal[] = $userName;
            }
        }
        
        if (!empty($usersWithoutExternal)) {
            $userList = implode(', ', array_slice($usersWithoutExternal, 0, 10));
            $remaining = count($usersWithoutExternal) - 10;
            
            if ($remaining > 0) {
                $userList .= ' ' . __('assessment.and-more', ['count' => $remaining]);
            }

            throw ValidationException::withMessages([
                'external_feedback' => __('assessment.no-external-feedback', ['users' => $userList])
            ]);
        }
    }
}