<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCompetency extends Pivot
{
    protected $table = 'user_competency';
    
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * This model assigns competencies to users for evaluation.
     * Determines which competencies a user will be evaluated on.
     * 
     * Mass-assignable fields:
     * - user_id: The user being assigned competencies
     * - organization_id: Organization context
     * - competency_id: The competency being assigned
     * 
     * Security note: Database triggers validate that the user belongs to
     * the organization and that the competency is valid.
     */
    protected $fillable = [
        'user_id',
        'organization_id',
        'competency_id',
    ];
    
    protected $hidden = [];
      
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}