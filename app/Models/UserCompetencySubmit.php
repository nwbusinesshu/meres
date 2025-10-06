<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCompetencySubmit extends Pivot
{
    protected $table = 'user_competency_submit';
    
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * This model tracks that a user has completed their assessment for a target.
     * It also stores telemetry data for AI-based trust scoring.
     * 
     * Mass-assignable fields:
     * - assessment_id: Links to the assessment cycle
     * - user_id: The evaluator completing the assessment
     * - target_id: The person being evaluated
     * - submitted_at: Timestamp of submission
     * - telemetry_raw: Client+server telemetry JSON (set during submission)
     * 
     * Fields intentionally EXCLUDED from mass assignment:
     * - telemetry_ai: CRITICAL - AI-generated trust scores and analysis
     *   This MUST only be set by TelemetryService::scoreAndStoreTelemetryAI()
     *   Never allow user input to set this field as it contains:
     *   - trust_score: Used to weight evaluations
     *   - flags: Detection of suspicious behavior
     *   - AI analysis results
     * 
     * Security note: Database triggers validate that user_id and target_id
     * belong to the assessment's organization.
     */
    protected $fillable = [
        'assessment_id',
        'user_id',
        'target_id',
        'submitted_at',
        'telemetry_raw',
    ];
    
    protected $hidden = [];
    
    protected $casts = [
        'telemetry_raw' => 'array',
        'telemetry_ai' => 'array',
    ];
      
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function target(){
        return $this->belongsTo(User::class, 'target_id', 'id');
    }

    public function assessment(){
        return $this->belongsTo(Assessment::class, 'assessment_id', 'id');
    }
}