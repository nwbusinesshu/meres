<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserBonusMalus extends Pivot
{
    protected $table = 'user_bonus_malus';
    
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * This model stores employee performance levels per month.
     * 
     * Mass-assignable fields:
     * - user_id: The employee being evaluated
     * - organization_id: Organization context (validated by DB trigger)
     * - level: The bonus/malus level (1-15 scale)
     * - month: The month this level applies to (DATE format: Y-m-01)
     * 
     * Security note: Database triggers validate that user_id is a member
     * of the specified organization_id, providing additional protection.
     */
    protected $fillable = [
        'user_id',
        'organization_id',
        'level',
        'month',
    ];
    
    protected $hidden = [];
      
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function name(){
        return __('global.bonus-malus.'.$this->level);
    }
}