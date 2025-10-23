<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

class UserBonusMalus extends Pivot
{
    protected $table = 'user_bonus_malus';
    
    public $timestamps = false;
    
    /**
     * ✅ FIX: Composite primary key configuration
     * Table has composite PK: (user_id, month, organization_id)
     * Laravel doesn't handle composite keys well with Eloquent, so we disable incrementing
     */
    public $incrementing = false;
    protected $primaryKey = null;
    
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
    
    /**
     * ✅ OVERRIDE save() to handle composite primary key properly
     * 
     * Since Laravel doesn't support composite keys, we use updateOrInsert
     * which explicitly specifies all key columns in the WHERE clause
     */
    public function save(array $options = [])
    {
        // If we have all the key values, use updateOrInsert
        if ($this->user_id && $this->organization_id && $this->month) {
            return DB::table($this->table)->updateOrInsert(
                [
                    'user_id' => $this->user_id,
                    'organization_id' => $this->organization_id,
                    'month' => $this->month,
                ],
                [
                    'level' => $this->level,
                ]
            );
        }
        
        // Fallback to parent (shouldn't happen in normal flow)
        return parent::save($options);
    }
      
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function name(){
        return __('global.bonus-malus.'.$this->level);
    }
}