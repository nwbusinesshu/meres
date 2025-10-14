<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWage extends Model
{
    protected $table = 'user_wages';
    public $timestamps = false;
    const UPDATED_AT = 'updated_at';
    
    // ✅ FIX: Composite primary key configuration
    // Tell Laravel this table doesn't have a single auto-incrementing 'id' column
    public $incrementing = false;
    protected $primaryKey = null;
    
    protected $fillable = [
        'user_id',
        'organization_id',
        'net_wage',
        'currency',
    ];

    protected $casts = [
        'net_wage' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get current wage for user in organization
     * 
     * @param int $userId
     * @param int $orgId
     * @return array|null ['net_wage' => float, 'currency' => string]
     */
    public static function getCurrentWage(int $userId, int $orgId): ?array
    {
        $wage = self::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->first();
        
        return $wage ? [
            'net_wage' => $wage->net_wage,
            'currency' => $wage->currency,
        ] : null;
    }
}