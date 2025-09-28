<?php
// app/Models/CompetencyGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetencyGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'competency_ids',
        'assigned_users'  // NEW: Added assigned_users
    ];

    protected $casts = [
        'competency_ids' => 'array',
        'assigned_users' => 'array',  // NEW: Cast assigned_users as array
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

    // ========== NEW: User Assignment Methods ==========

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
}