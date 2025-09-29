<?php
// app/Models/CompetencyGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CompetencyGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'competency_ids',
        'assigned_users'
    ];

    protected $casts = [
        'competency_ids' => 'array',
        'assigned_users' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the organization that owns the competency group.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the competencies that belong to this group.
     * This will return actual Competency models based on the stored IDs.
     */
    public function competencies()
    {
        if (empty($this->competency_ids)) {
            return collect();
        }

        return Competency::whereIn('id', $this->competency_ids)
                         ->whereNull('removed_at')
                         ->orderBy('name')
                         ->get();
    }

    /**
     * Add a competency to this group.
     */
    public function addCompetency($competencyId)
    {
        $competencyIds = $this->competency_ids ?? [];
        
        if (!in_array($competencyId, $competencyIds)) {
            $competencyIds[] = $competencyId;
            $this->competency_ids = $competencyIds;
            $this->save();
        }
    }

    /**
     * Remove a competency from this group.
     */
    public function removeCompetency($competencyId)
    {
        $competencyIds = $this->competency_ids ?? [];
        $competencyIds = array_values(array_filter($competencyIds, function($id) use ($competencyId) {
            return $id != $competencyId;
        }));
        
        $this->competency_ids = $competencyIds;
        $this->save();
    }

    /**
     * Check if a competency is in this group.
     */
    public function hasCompetency($competencyId)
    {
        return in_array($competencyId, $this->competency_ids ?? []);
    }

    /**
     * Get the count of competencies in this group.
     */
    public function getCompetencyCountAttribute()
    {
        return count($this->competency_ids ?? []);
    }

    // ========== User Assignment Methods ==========

    /**
     * Get the users that are assigned to this group.
     * This will return actual User models based on the stored IDs.
     */
    public function assignedUsers()
    {
        if (empty($this->assigned_users)) {
            return collect();
        }

        return User::whereIn('id', $this->assigned_users)
                   ->whereNull('removed_at')
                   ->orderBy('name')
                   ->get();
    }

    /**
     * Add a user to this group.
     */
    public function addUser($userId)
    {
        $userIds = $this->assigned_users ?? [];
        
        if (!in_array($userId, $userIds)) {
            $userIds[] = $userId;
            $this->assigned_users = $userIds;
            $this->save();
        }
    }

    /**
     * Remove a user from this group.
     */
    public function removeUser($userId)
    {
        $userIds = $this->assigned_users ?? [];
        $userIds = array_values(array_filter($userIds, function($id) use ($userId) {
            return $id != $userId;
        }));
        
        $this->assigned_users = $userIds;
        $this->save();
    }

    /**
     * Check if a user is assigned to this group.
     */
    public function hasUser($userId)
    {
        return in_array($userId, $this->assigned_users ?? []);
    }

    /**
     * Get the count of assigned users in this group.
     */
    public function getAssignedUsersCountAttribute()
    {
        return count($this->assigned_users ?? []);
    }

    /**
     * Set multiple users at once (replaces existing assignments).
     */
    public function setUsers(array $userIds)
    {
        $this->assigned_users = array_values(array_unique($userIds));
        $this->save();
    }

    /**
     * Clear all user assignments from this group.
     */
    public function clearUsers()
    {
        $this->assigned_users = [];
        $this->save();
    }

    // ========== NEW: Competency Synchronization Methods ==========

    /**
     * Sync all competencies from this group to a specific user.
     * Adds competencies to user_competency and tracks in user_competency_sources.
     */
    public function syncUserCompetencies($userId)
    {
        $competencyIds = $this->competency_ids ?? [];
        
        if (empty($competencyIds)) {
            return;
        }

        foreach ($competencyIds as $compId) {
            // Add to user_competency if not exists
            DB::table('user_competency')->insertOrIgnore([
                'user_id' => $userId,
                'competency_id' => $compId,
                'organization_id' => $this->organization_id
            ]);
            
            // Track source
            DB::table('user_competency_sources')->insertOrIgnore([
                'user_id' => $userId,
                'competency_id' => $compId,
                'organization_id' => $this->organization_id,
                'source_type' => 'group',
                'source_id' => $this->id,
                'created_at' => now()
            ]);
        }
    }

    /**
     * Remove competencies from a user that came from this group.
     * Only removes from user_competency if no other sources exist.
     */
    public function removeUserCompetencies($userId)
    {
        // Get competencies that this user has from this specific group
        $groupCompIds = DB::table('user_competency_sources')
            ->where('user_id', $userId)
            ->where('organization_id', $this->organization_id)
            ->where('source_type', 'group')
            ->where('source_id', $this->id)
            ->pluck('competency_id')
            ->toArray();
        
        if (empty($groupCompIds)) {
            return;
        }

        // Remove source records for this group
        DB::table('user_competency_sources')
            ->where('user_id', $userId)
            ->where('organization_id', $this->organization_id)
            ->where('source_type', 'group')
            ->where('source_id', $this->id)
            ->delete();
        
        // For each competency, check if it has other sources
        foreach ($groupCompIds as $compId) {
            $hasOtherSources = DB::table('user_competency_sources')
                ->where('user_id', $userId)
                ->where('competency_id', $compId)
                ->where('organization_id', $this->organization_id)
                ->exists();
            
            // Only remove from user_competency if no other sources exist
            if (!$hasOtherSources) {
                DB::table('user_competency')
                    ->where('user_id', $userId)
                    ->where('competency_id', $compId)
                    ->where('organization_id', $this->organization_id)
                    ->delete();
            }
        }
    }

    /**
     * Sync competencies for all users currently assigned to this group.
     * Useful when competencies are added/removed from the group.
     */
    public function syncAllUsersCompetencies()
    {
        $userIds = $this->assigned_users ?? [];
        
        foreach ($userIds as $userId) {
            $this->syncUserCompetencies($userId);
        }
    }

    /**
     * Remove competencies for all users currently assigned to this group.
     */
    public function removeAllUsersCompetencies()
    {
        $userIds = $this->assigned_users ?? [];
        
        foreach ($userIds as $userId) {
            $this->removeUserCompetencies($userId);
        }
    }
}