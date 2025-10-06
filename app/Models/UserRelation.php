<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserRelation extends Pivot
{
    protected $table = 'user_relation';
    
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * This model defines relationships between users (who can evaluate whom).
     * CRITICAL for access control - determines who can see/evaluate other users.
     * 
     * Mass-assignable fields:
     * - user_id: The user who has access
     * - target_id: The user being accessed/evaluated
     * - type: Relationship type (self, colleague, subordinate, superior)
     * - organization_id: Organization context
     * 
     * Security note: Database triggers validate that both users are members
     * of the same organization, providing additional protection.
     */
    protected $fillable = [
        'user_id',
        'target_id',
        'type',
        'organization_id',
    ];
    
    protected $hidden = [];
      
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function target(){
        return $this->belongsTo(User::class, 'target_id', 'id');
    }
}