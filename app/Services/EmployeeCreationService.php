<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRelation;
use App\Models\Enums\UserRelationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeCreationService
{
    /**
     * Create a new employee with all required setup
     * 
     * @param array $data Employee data (name, email, type, position, wage, currency)
     * @param int $orgId Organization ID
     * @return User Created user
     * @throws \Exception
     */
    public static function createEmployee(array $data, int $orgId): User
    {
        // Normalize data
        $email = strtolower(trim($data['email']));
        $name = trim($data['name']);
        $type = strtolower(trim($data['type']));
        $position = isset($data['position']) ? trim($data['position']) : null;
        $wage = isset($data['wage']) ? (float) $data['wage'] : null;
        $currency = isset($data['currency']) ? strtoupper(trim($data['currency'])) : 'HUF';
        
        // Double-check email doesn't exist
        $existingUser = User::where('email', $email)
            ->whereNull('removed_at')
            ->first();
            
        if ($existingUser) {
            throw new \Exception("Email already exists: {$email}");
        }
        
        // 1) Create User
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'type' => $type,
            'has_auto_level_up' => 0, // deprecated field but required
        ]);
        
        Log::info('employee.create.user', [
            'user_id' => $user->id,
            'email' => $email,
            'org_id' => $orgId
        ]);
        
        // 2) Attach to organization (use syncWithoutDetaching for idempotency like old code)
        $user->organizations()->syncWithoutDetaching([$orgId]);
        
        // 3) Set position in organization_user (use updateOrInsert like old code)
        $positionValue = ($position && $position !== '') ? $position : null;
        DB::table('organization_user')->updateOrInsert(
            ['organization_id' => $orgId, 'user_id' => $user->id],
            ['position' => $positionValue]
        );
        
        Log::info('employee.create.org_attached', [
            'user_id' => $user->id,
            'org_id' => $orgId,
            'position' => $position
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
                'level' => UserService::DEFAULT_BM,
            ]
        );
        
        Log::info('employee.create.bonus_malus', [
            'user_id' => $user->id,
            'org_id' => $orgId,
            'level' => UserService::DEFAULT_BM
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
     * @param User $user
     * @param int $orgId
     * @param int $departmentId
     * @param string $type User type (normal/manager/ceo)
     */
    public static function assignToDepartment(User $user, int $orgId, int $departmentId, string $type): void
    {
        if ($type === 'normal') {
            // Normal user = department member
            DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('user_id', $user->id)
                ->update(['department_id' => $departmentId]);
                
            Log::info('employee.assign.member', [
                'user_id' => $user->id,
                'dept_id' => $departmentId,
                'org_id' => $orgId
            ]);
            
        } elseif ($type === 'manager') {
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
        // CEO type ignores departments
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