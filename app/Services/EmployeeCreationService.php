<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRelation;
use App\Models\Enums\UserRelationType;
use App\Models\Enums\OrgRole;  // ✅ ADDED
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeCreationService
{
    /**
     * Create a new employee with all required setup
     * 
     * ✅ FIXED: Now properly handles user.type vs organization_user.role
     * 
     * @param array $data Employee data (name, email, type, position, wage, currency)
     *                    Note: 'type' parameter is actually the org role (admin/manager/ceo/employee)
     * @param int $orgId Organization ID
     * @return User Created user
     * @throws \Exception
     */
    public static function createEmployee(array $data, int $orgId): User
    {
        // Normalize data
        $email = strtolower(trim($data['email']));
        $name = trim($data['name']);
        $role = strtolower(trim($data['type']));  // ✅ RENAMED: This is the ORG ROLE, not user type!
        $position = isset($data['position']) ? trim($data['position']) : null;
        $wage = isset($data['wage']) ? (float) $data['wage'] : null;
        $currency = isset($data['currency']) ? strtoupper(trim($data['currency'])) : 'HUF';
        
        // Validate role
        if (!OrgRole::isValid($role)) {
            throw new \Exception("Invalid role: {$role}");
        }
        
        // Double-check email doesn't exist
        $existingUser = User::where('email', $email)
            ->whereNull('removed_at')
            ->first();
            
        if ($existingUser) {
            throw new \Exception("Email already exists: {$email}");
        }
        
        // ✅ FIX #1: Create User with type='normal' ALWAYS
        // user.type should only be 'superadmin', 'normal', or 'guest'
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'type' => 'normal',  // ✅ FIXED: Always 'normal' for org members
            'has_auto_level_up' => 0, // deprecated field but required
        ]);
        
        Log::info('employee.create.user', [
            'user_id' => $user->id,
            'email' => $email,
            'org_id' => $orgId,
            'user_type' => 'normal',
            'org_role' => $role
        ]);

        ProfilePicService::assignRandomMonster($user);
        
        // 2) Attach to organization (use syncWithoutDetaching for idempotency)
        $user->organizations()->syncWithoutDetaching([$orgId]);
        
        // ✅ FIX #2: Set BOTH position AND role in organization_user
        $positionValue = ($position && $position !== '') ? $position : null;
        DB::table('organization_user')->updateOrInsert(
            ['organization_id' => $orgId, 'user_id' => $user->id],
            [
                'position' => $positionValue,
                'role' => $role  // ✅ ADDED: Set the org-specific role here!
            ]
        );
        
        Log::info('employee.create.org_attached', [
            'user_id' => $user->id,
            'org_id' => $orgId,
            'position' => $position,
            'role' => $role  // ✅ ADDED: Log the role
        ]);
        
        // 4) Create SELF relation (CRITICAL - required for system)
        UserRelation::updateOrCreate(
            [
                'organization_id' => $orgId,
                'user_id' => $user->id,
                'target_id' => $user->id,
            ],
            [
                'type' => UserRelationType::SELF,
            ]
        );
        
        Log::info('employee.create.self_relation', [
            'user_id' => $user->id,
            'org_id' => $orgId
        ]);
        
        // 5) Initialize Bonus/Malus (CRITICAL - required for system)
        $user->bonusMalus()->updateOrCreate(
            [
                'month' => date('Y-m-01'),
                'organization_id' => $orgId,
            ],
            [
                'level' => \App\Services\UserService::DEFAULT_BM,
            ]
        );
        
        Log::info('employee.create.bonus_malus', [
            'user_id' => $user->id,
            'org_id' => $orgId,
            'level' => \App\Services\UserService::DEFAULT_BM
        ]);
        
        // 6) Set wage if provided
        if ($wage && $wage > 0) {
            DB::table('user_wages')->insert([
                'user_id' => $user->id,
                'organization_id' => $orgId,
                'net_wage' => $wage,
                'currency' => $currency,
            ]);
            
            Log::info('employee.create.wage', [
                'user_id' => $user->id,
                'org_id' => $orgId,
                'wage' => $wage,
                'currency' => $currency
            ]);
        }
        
        return $user;
    }
    
    /**
     * Assign employee to department
     * Handles both normal members and managers
     * 
     * BUGFIX: Preserve the position when assigning to department
     * 
     * @param User $user
     * @param int $orgId
     * @param int $departmentId
     * @param string $role User role (employee/manager/ceo) - ✅ RENAMED from $type
     */
    public static function assignToDepartment(User $user, int $orgId, int $departmentId, string $role): void
    {
        // ✅ UPDATED: Check role instead of type
        if ($role === OrgRole::EMPLOYEE || $role === 'normal') {
            // BUGFIX: Get current position to preserve it
            $currentData = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('user_id', $user->id)
                ->first();
            
            $currentPosition = $currentData ? $currentData->position : null;
            
            // Normal user = department member
            // Update department_id while preserving position
            DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('user_id', $user->id)
                ->update([
                    'department_id' => $departmentId,
                    'position' => $currentPosition  // Explicitly preserve position
                ]);
                
            Log::info('employee.assign.member', [
                'user_id' => $user->id,
                'dept_id' => $departmentId,
                'org_id' => $orgId,
                'position_preserved' => $currentPosition
            ]);
            
        } elseif ($role === OrgRole::MANAGER || $role === 'manager') {
            // Manager = department manager (not member)
            DB::table('organization_department_managers')->insert([
                'organization_id' => $orgId,
                'department_id' => $departmentId,
                'manager_id' => $user->id,
                'created_at' => now(),
            ]);
            
            Log::info('employee.assign.manager', [
                'user_id' => $user->id,
                'dept_id' => $departmentId,
                'org_id' => $orgId
            ]);
        }
        // CEO role ignores departments
    }
    
    /**
     * Get or create department by name (case-insensitive)
     * 
     * @param string $departmentName
     * @param int $orgId
     * @return int Department ID
     */
    public static function getOrCreateDepartment(string $departmentName, int $orgId): int
    {
        $deptLower = strtolower(trim($departmentName));
        
        // Check if department exists (case-insensitive)
        $dept = DB::table('organization_departments')
            ->where('organization_id', $orgId)
            ->whereRaw('LOWER(department_name) = ?', [$deptLower])
            ->whereNull('removed_at')
            ->first();
        
        if ($dept) {
            return $dept->id;
        }
        
        // Create new department
        $departmentId = DB::table('organization_departments')->insertGetId([
            'organization_id' => $orgId,
            'department_name' => $departmentName, // Keep original case
            'created_at' => now(),
        ]);
        
        Log::info('employee.create.department', [
            'dept_id' => $departmentId,
            'dept_name' => $departmentName,
            'org_id' => $orgId
        ]);
        
        return $departmentId;
    }
}