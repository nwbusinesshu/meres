<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCeoRank extends Pivot
{
    protected $table = 'user_ceo_rank';

    public $timestamps   = false;
    public $incrementing = false; // no 'id' column - composite primary key
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * This model stores CEO rankings of employees within an assessment.
     * 
     * Mass-assignable fields:
     * - assessment_id: Links to the assessment cycle
     * - ceo_id: The CEO providing the ranking
     * - user_id: The employee being ranked
     * - value: The ranking value (scale varies by system config)
     * 
     * Security note: Database triggers validate that both ceo_id and user_id
     * are members of the assessment's organization, providing additional
     * protection against cross-organization manipulation.
     */
    protected $fillable = [
        'assessment_id',
        'ceo_id',
        'user_id',
        'value',
    ];
    
    protected $casts = [
        'assessment_id' => 'int',
        'ceo_id'        => 'int',
        'user_id'       => 'int',
        'value'         => 'int',
    ];

    public function ceo()
    {
        return $this->belongsTo(User::class, 'ceo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    // Convenience scopes
    public function scopeForAssessment($q, $assessmentId)
    {
        return $q->where('assessment_id', $assessmentId);
    }

    public function scopeByCeo($q, $ceoId)
    {
        return $q->where('ceo_id', $ceoId);
    }

    public function scopeByUser($q, $userId)
    {
        return $q->where('user_id', $userId);
    }
}