<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CompetencySubmit extends Pivot
{
    protected $table = 'competency_submit';
    
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * This model stores individual competency evaluation scores.
     * All fields are used during assessment submissions.
     * 
     * Mass-assignable fields:
     * - assessment_id: Links to the assessment cycle
     * - competency_id: The competency being evaluated
     * - user_id: The evaluator (NULL for anonymous submissions)
     * - target_id: The person being evaluated
     * - value: The evaluation score (0-100)
     * - type: Relationship type (self, colleague, subordinate, ceo)
     * 
     * Security note: Database triggers validate that users belong to the
     * assessment's organization, providing additional protection against
     * cross-organization attacks.
     */
    protected $fillable = [
        'assessment_id',
        'competency_id',
        'user_id',
        'target_id',
        'value',
        'type',
    ];
    
    protected $hidden = [];
    
    public function user(){
        return $this->belongsTo(User::class,'user_id','id');
    }

    public function target(){
        return $this->belongsTo(User::class,'target_id','id');
    }
}