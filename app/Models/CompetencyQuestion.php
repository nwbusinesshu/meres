<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetencyQuestion extends Model
{
    protected $table = 'competency_question';
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed contradictory $guarded = []
     * 
     * Mass-assignable fields for competency questions:
     * - organization_id: NULL for global questions, set for org-specific questions
     * - competency_id: Links question to its competency
     * - question: The question text for others to answer
     * - question_self: The question text for self-assessment
     * - min_label: Label for minimum scale value
     * - max_label: Label for maximum scale value  
     * - max_value: Maximum value on the scale
     * 
     * Fields intentionally EXCLUDED:
     * - id: Auto-increment primary key
     * - removed_at: Soft delete field, controlled by application logic only
     */
    protected $fillable = [
        'organization_id',
        'competency_id',
        'question',
        'question_self',
        'min_label',
        'max_label',
        'max_value',
    ];
    
    protected $hidden = [];

    public function competency(){
        return $this->belongsTo(Competency::class, 'competency_id', 'id');
    }
    
    public function scopeGlobal($q)  { 
        return $q->whereNull('organization_id'); 
    }
    
    public function scopeForOrg($q, $orgId) { 
        return $q->where('organization_id', $orgId); 
    }
}