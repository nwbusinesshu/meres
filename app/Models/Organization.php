<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $table = 'organization';
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * Fields intentionally EXCLUDED from mass assignment for security:
     * - created_at: Should be set automatically, not by user input
     * - removed_at: Soft delete field - must be controlled by application logic only
     */
    protected $fillable = [
        'name',
        'slug',
    ];
    
    protected $hidden = [];
    
    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function users(){
        return $this->belongsToMany(User::class, 'organization_user', 'organization_id', 'user_id')
            ->withPivot('role');
    }

    public function profile()
    {
        return $this->hasOne(\App\Models\OrganizationProfile::class);
    }
}