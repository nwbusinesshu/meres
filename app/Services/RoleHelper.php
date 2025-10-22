<?php

namespace App\Services;

use App\Models\Enums\OrgRole;
use App\Models\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

/**
 * Role Helper Service
 * 
 * Centralized service for querying and checking user roles within organizations.
 * This service handles the complexity of the user.type + organization_user.role system.
 * 
 * Key concepts:
 * - user.type: System-level classification (superadmin, normal, guest)
 * - organization_user.role: Organization-specific role (admin, manager, ceo, employee)
 */
class RoleHelper
{
    /**
     * Get user's role in a specific organization
     * 
     * @param int $userId
     * @param int $orgId
     * @return string|null Role name or null if not a member
     */
    public static function getUserRole(int $userId, int $orgId): ?string
    {
        return DB::table('organization_user')
            ->where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->value('role');
    }
    
    /**
     * Get user's role in current session organization
     * 
     * @param int $userId
     * @return string|null Role name or null if no org in session
     */
    public static function getCurrentRole(int $userId): ?string
    {
        $orgId = session('org_id');
        if (!$orgId) {
            return null;
        }
        
        return self::getUserRole($userId, $orgId);
    }
    
    /**
     * Check if user has a specific role in organization
     * 
     * Note: Admin role can access everything, so if checking for manager/employee,
     * an admin will also return true (hierarchical permission check).
     * 
     * @param int $userId
     * @param int $orgId
     * @param string $requiredRole
     * @param bool $strict If true, exact match only (no hierarchy)
     * @return bool
     */
    public static function hasRole(int $userId, int $orgId, string $requiredRole, bool $strict = false): bool
    {
        $userRole = self::getUserRole($userId, $orgId);
        
        if (!$userRole) {
            return false;
        }
        
        // Exact match
        if ($userRole === $requiredRole) {
            return true;
        }
        
        // Strict mode: no hierarchy
        if ($strict) {
            return false;
        }
        
        // Hierarchical permission check
        // Admin can access all roles
        if ($userRole === OrgRole::ADMIN) {
            return true;
        }
        
        // CEO can access manager and employee routes
        if ($userRole === OrgRole::CEO && in_array($requiredRole, [OrgRole::MANAGER, OrgRole::EMPLOYEE])) {
            return true;
        }
        
        // Manager can access employee routes
        if ($userRole === OrgRole::MANAGER && $requiredRole === OrgRole::EMPLOYEE) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user has any of the specified roles in organization
     * 
     * @param int $userId
     * @param int $orgId
     * @param array $roles
     * @return bool
     */
    public static function hasAnyRole(int $userId, int $orgId, array $roles): bool
    {
        foreach ($roles as $role) {
            if (self::hasRole($userId, $orgId, $role)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get query builder for users with specific role in org
     * Returns raw query builder (not User models)
     * 
     * @param int $orgId
     * @param string $role
     * @return \Illuminate\Database\Query\Builder
     */
    public static function queryByRole(int $orgId, string $role)
    {
        return DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('ou.role', $role)
            ->whereNull('u.removed_at');
    }
    
    /**
     * Get all users with specific role in organization
     * Returns Eloquent User models
     * 
     * @param int $orgId
     * @param string $role
     * @return Collection
     */
    public static function getUsersByRole(int $orgId, string $role): Collection
    {
        return User::whereHas('organizations', function($q) use ($orgId, $role) {
            $q->where('organization_id', $orgId)
              ->where('role', $role);
        })
        ->whereNull('removed_at')
        ->get();
    }
    
    /**
     * Get users with multiple roles in organization
     * 
     * @param int $orgId
     * @param array $roles
     * @return Collection
     */
    public static function getUsersByRoles(int $orgId, array $roles): Collection
    {
        return User::whereHas('organizations', function($q) use ($orgId, $roles) {
            $q->where('organization_id', $orgId)
              ->whereIn('role', $roles);
        })
        ->whereNull('removed_at')
        ->get();
    }
    
    /**
     * Count users with specific role in organization
     * 
     * @param int $orgId
     * @param string $role
     * @return int
     */
    public static function countByRole(int $orgId, string $role): int
    {
        return self::queryByRole($orgId, $role)->count();
    }
    
    /**
     * Get all roles a user has across all organizations
     * Returns array of ['organization_id' => role]
     * 
     * @param int $userId
     * @return array
     */
    public static function getAllUserRoles(int $userId): array
    {
        return DB::table('organization_user')
            ->where('user_id', $userId)
            ->pluck('role', 'organization_id')
            ->toArray();
    }
    
    /**
     * Check if user is a member of organization (any role)
     * 
     * @param int $userId
     * @param int $orgId
     * @return bool
     */
    public static function isMember(int $userId, int $orgId): bool
    {
        return DB::table('organization_user')
            ->where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->exists();
    }
    
    /**
     * Update user's role in organization
     * 
     * @param int $userId
     * @param int $orgId
     * @param string $newRole
     * @return bool
     */
    public static function updateRole(int $userId, int $orgId, string $newRole): bool
    {
        if (!OrgRole::isValid($newRole)) {
            return false;
        }
        
        $updated = DB::table('organization_user')
            ->where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->update(['role' => $newRole]);
            
        return $updated > 0;
    }
    
    /**
     * Get role distribution in organization
     * Returns array like: ['admin' => 2, 'manager' => 5, 'employee' => 50]
     * 
     * @param int $orgId
     * @return array
     */
    public static function getRoleDistribution(int $orgId): array
    {
        return DB::table('organization_user as ou')
            ->join('user as u', 'u.id', '=', 'ou.user_id')
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->groupBy('ou.role')
            ->pluck(DB::raw('COUNT(*)'), 'ou.role')
            ->toArray();
    }
    
    /**
     * Check if user can manage another user within same organization
     * Based on role hierarchy
     * 
     * @param int $managerId
     * @param int $targetUserId
     * @param int $orgId
     * @return bool
     */
    public static function canManage(int $managerId, int $targetUserId, int $orgId): bool
    {
        $managerRole = self::getUserRole($managerId, $orgId);
        $targetRole = self::getUserRole($targetUserId, $orgId);
        
        if (!$managerRole || !$targetRole) {
            return false;
        }
        
        // Can't manage yourself through role system
        if ($managerId === $targetUserId) {
            return false;
        }
        
        // Check hierarchy
        return OrgRole::hasPermissionLevel($managerRole, $targetRole);
    }
}