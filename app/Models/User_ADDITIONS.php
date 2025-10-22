<?php
/**
 * ADD THESE METHODS TO app/Models/User.php
 * 
 * Location: Add these methods after the existing bonusMalus() and related methods
 * (around line 100-150, after the relationship methods)
 */

// ==============================================================================
// ORGANIZATION ROLE METHODS (NEW - for migration)
// ==============================================================================

/**
 * Get user's role in a specific organization
 * 
 * @param int $orgId Organization ID
 * @return string|null Role name or null if not a member
 * 
 * Example:
 *   $user->getRoleInOrg(26) // Returns: 'admin', 'ceo', 'manager', 'employee', or null
 */
public function getRoleInOrg(int $orgId): ?string
{
    return \App\Services\RoleHelper::getUserRole($this->id, $orgId);
}

/**
 * Get user's role in current session organization
 * 
 * @return string|null Role name or null if no org in session
 * 
 * Example:
 *   $user->getCurrentRole() // Returns role in session('org_id')
 */
public function getCurrentRole(): ?string
{
    return \App\Services\RoleHelper::getCurrentRole($this->id);
}

/**
 * Check if user has a specific role in organization
 * 
 * @param int $orgId Organization ID
 * @param string $role Role to check (use OrgRole constants)
 * @param bool $strict If true, exact match only (no hierarchy)
 * @return bool
 * 
 * Example:
 *   $user->hasRoleInOrg(26, \App\Models\Enums\OrgRole::ADMIN)
 *   $user->hasRoleInOrg(26, 'manager', true) // Strict = exact match only
 */
public function hasRoleInOrg(int $orgId, string $role, bool $strict = false): bool
{
    return \App\Services\RoleHelper::hasRole($this->id, $orgId, $role, $strict);
}

/**
 * Check if user is admin in organization
 * 
 * @param int $orgId Organization ID
 * @return bool
 * 
 * Example:
 *   if ($user->isAdminInOrg(26)) { ... }
 */
public function isAdminInOrg(int $orgId): bool
{
    return $this->getRoleInOrg($orgId) === \App\Models\Enums\OrgRole::ADMIN;
}

/**
 * Check if user is CEO in organization
 * 
 * @param int $orgId Organization ID
 * @return bool
 * 
 * Example:
 *   if ($user->isCeoInOrg(26)) { ... }
 */
public function isCeoInOrg(int $orgId): bool
{
    return $this->getRoleInOrg($orgId) === \App\Models\Enums\OrgRole::CEO;
}

/**
 * Check if user is Manager in organization
 * 
 * @param int $orgId Organization ID
 * @return bool
 * 
 * Example:
 *   if ($user->isManagerInOrg(26)) { ... }
 */
public function isManagerInOrg(int $orgId): bool
{
    return $this->getRoleInOrg($orgId) === \App\Models\Enums\OrgRole::MANAGER;
}

/**
 * Check if user is admin in current session organization
 * 
 * @return bool
 * 
 * Example:
 *   if ($user->isCurrentAdmin()) { ... }
 */
public function isCurrentAdmin(): bool
{
    $orgId = session('org_id');
    return $orgId ? $this->isAdminInOrg($orgId) : false;
}

/**
 * Check if user is CEO in current session organization
 * 
 * @return bool
 */
public function isCurrentCeo(): bool
{
    $orgId = session('org_id');
    return $orgId ? $this->isCeoInOrg($orgId) : false;
}

/**
 * Check if user is Manager in current session organization
 * 
 * @return bool
 */
public function isCurrentManager(): bool
{
    $orgId = session('org_id');
    return $orgId ? $this->isManagerInOrg($orgId) : false;
}

/**
 * Get all roles this user has across all organizations
 * 
 * @return array ['organization_id' => 'role']
 * 
 * Example:
 *   $roles = $user->getAllRoles();
 *   // Returns: [26 => 'admin', 27 => 'employee', 28 => 'manager']
 */
public function getAllRoles(): array
{
    return \App\Services\RoleHelper::getAllUserRoles($this->id);
}

/**
 * Check if user can manage another user in organization
 * Based on role hierarchy
 * 
 * @param User $targetUser User to check if can be managed
 * @param int $orgId Organization ID
 * @return bool
 * 
 * Example:
 *   if ($adminUser->canManage($employeeUser, 26)) { ... }
 */
public function canManage(User $targetUser, int $orgId): bool
{
    return \App\Services\RoleHelper::canManage($this->id, $targetUser->id, $orgId);
}

// ==============================================================================
// USAGE EXAMPLES
// ==============================================================================

/*

// 1. Check user's role
$user = User::find(94);
$role = $user->getRoleInOrg(26); // 'admin'

// 2. Quick role checks
if ($user->isAdminInOrg(26)) {
    // Do admin stuff
}

if ($user->isCeoInOrg(26)) {
    // Do CEO stuff
}

// 3. Check current session role
if ($user->isCurrentAdmin()) {
    // User is admin in current organization
}

// 4. Get all roles across organizations
$allRoles = $user->getAllRoles();
// [26 => 'admin', 27 => 'employee', 28 => 'ceo']

// 5. Check if can manage another user
$admin = User::find(94);
$employee = User::find(95);
if ($admin->canManage($employee, 26)) {
    // Admin can manage this employee
}

// 6. Hierarchical permission check (default)
$manager = User::find(100); // role = 'manager'
$manager->hasRoleInOrg(26, OrgRole::EMPLOYEE); // true (manager can access employee routes)

// 7. Strict role check (exact match only)
$manager->hasRoleInOrg(26, OrgRole::EMPLOYEE, true); // false (strict = no hierarchy)

*/