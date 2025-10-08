<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusMalusConfig extends Model
{
    protected $table = 'bonus_malus_config';
    public $timestamps = false;
    
    // Tell Laravel this table doesn't have an auto-incrementing ID
    public $incrementing = false;
    
    // Since we have a composite key, set primaryKey to null
    protected $primaryKey = null;
    
    protected $fillable = [
        'organization_id',
        'level',
        'multiplier',
    ];

    protected $casts = [
        'level' => 'integer',
        'multiplier' => 'float',  // ✅ Changed from 'decimal:2' to 'float'
    ];

    /**
     * Get multiplier for specific level
     * 
     * @param int $orgId
     * @param int $level (1-15)
     * @return float
     */
    public static function getMultiplierForLevel(int $orgId, int $level): float
    {
        $config = self::where('organization_id', $orgId)
            ->where('level', $level)
            ->first();
        
        return $config ? (float) $config->multiplier : 1.0;
    }

    /**
     * Get all multipliers for organization
     * 
     * @param int $orgId
     * @return array [level => multiplier]
     */
    public static function getAllMultipliers(int $orgId): array
    {
        return self::where('organization_id', $orgId)
            ->orderBy('level', 'desc')
            ->get()
            ->keyBy('level')
            ->map(fn($item) => (float) $item->multiplier)
            ->toArray();
    }
}