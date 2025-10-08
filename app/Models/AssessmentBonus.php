<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentBonus extends Model
{
    protected $table = 'assessment_bonuses';
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    
    protected $fillable = [
        'assessment_id',
        'user_id',
        'bonus_malus_level',
        'net_wage',
        'currency',
        'multiplier',
        'bonus_amount',
        'is_paid',
        'paid_at',
    ];

    protected $casts = [
        'bonus_malus_level' => 'integer',
        'net_wage' => 'decimal:2',
        'multiplier' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}