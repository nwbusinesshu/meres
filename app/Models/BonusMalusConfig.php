<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BonusMalusConfig extends Model
{
    protected $table = 'bonus_malus_config';
    public $timestamps = false;
    
    // Composite primary key - Laravel doesn't handle this well with updateOrCreate
    public $incrementing = false;
    protected $primaryKey = null;
    
    protected $fillable = [
        'organization_id',
        'level',
        'multiplier',
    ];

    protected $casts = [
        'level' => 'integer',
        'multiplier' => 'float',
    ];

    /**
     * Custom save method for composite key table
     * DO NOT USE updateOrCreate() - it breaks the WHERE clause!
     */
    public static function saveConfig(int $orgId, int $level, float $multiplier)
    {
        return DB::table('bonus_malus_config')
            ->where('organization_id', $orgId)
            ->where('level', $level)
            ->update(['multiplier' => $multiplier]);
    }

    /**
     * Get multiplier for specific level
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