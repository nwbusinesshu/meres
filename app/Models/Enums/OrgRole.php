<?php

namespace App\Models\Enums;

/**
 * Organization Role Enum
 * 
 * Defines roles that users can have within a specific organization.
 * Unlike UserType (which is system-wide), OrgRole is per-organization.
 * 
 * A user can have different roles in different organizations:
 * - Admin in Organization A
 * - Manager in Organization B
 * - Employee in Organization C
 */
class OrgRole
{
    const OWNER = "owner";
    const ADMIN = "admin";
    const MANAGER = "manager";
    const CEO = "ceo";
    const EMPLOYEE = "employee";
    
    /**
     * Get all valid roles
     */
    public static function all(): array
    {
        return [
            self::OWNER,
            self::ADMIN,
            self::MANAGER,
            self::CEO,
            self::EMPLOYEE,
        ];
    }
    
    /**
     * Check if a role value is valid
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all());
    }
    
    /**
     * Get roles that can manage other users
     */
    public static function managementRoles(): array
    {
        return [
            self::OWNER,
            self::ADMIN,
            self::MANAGER,
            self::CEO,
        ];
    }
    
    /**
     * Get role hierarchy level (higher = more permissions)
     */
    public static function getLevel(string $role): int
    {
        return match($role) {
            self::OWNER => 100,
            self::ADMIN => 90,
            self::CEO => 80,
            self::MANAGER => 70,
            self::EMPLOYEE => 10,
            default => 0,
        };
    }
    
    /**
     * Check if roleA has higher or equal permissions than roleB
     */
    public static function hasPermissionLevel(string $roleA, string $roleB): bool
    {
        return self::getLevel($roleA) >= self::getLevel($roleB);
    }
}