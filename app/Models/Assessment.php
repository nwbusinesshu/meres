<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $table = 'assessment';
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * Fields intentionally EXCLUDED from mass assignment for security:
     * - organization_id: CRITICAL - prevents cross-organization attacks
     * - started_at: Must be controlled by business logic
     * - closed_at: CRITICAL - controls assessment lifecycle, must not be manipulated
     * - org_snapshot: System-generated snapshot, must not be user-editable
     * - org_snapshot_version: System version control
     * - suggested_decision: AI/system-generated, must not be user-editable
     * 
     * Safe for mass assignment (in controlled contexts):
     * - due_at: Can be updated by admins
     * - threshold_method, normal_level_up, normal_level_down, monthly_level_down:
     *   Threshold configuration values that can be set during creation
     */
    protected $fillable = [
        'due_at',
        'threshold_method',
        'normal_level_up',
        'normal_level_down',
        'monthly_level_down',
    ];
    
    protected $hidden = [];
    
    protected $casts = [
        'suggested_decision' => 'array',
        'telemetry_ai' => 'array',
    ];
    
    public function userCompetencySubmits(){
        return $this->hasMany(UserCompetencySubmit::class, 'assessment_id', 'id');
    }

    public function ceoRanks(){
        return $this->hasMany(UserCeoRank::class, 'assessment_id', 'id');
    }
}