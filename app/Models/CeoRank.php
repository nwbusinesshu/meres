<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CeoRank extends Model
{
    protected $table = 'ceo_rank';
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * This model defines CEO ranking categories/options.
     * Can be global (organization_id = NULL) or organization-specific.
     * 
     * Mass-assignable fields:
     * - organization_id: NULL for global ranks, set for org-specific ranks
     * - name: The ranking category name (e.g., "Kiválóan teljesített")
     * - name_json: Translations of the name in multiple languages
     * - original_language: The language the original name was written in
     * - value: The numerical value associated with this rank (0-100)
     * - min: Minimum percentage of employees that should be in this rank
     * - max: Maximum percentage of employees that should be in this rank
     * 
     * Fields intentionally EXCLUDED:
     * - id: Auto-increment primary key
     * - removed_at: Soft delete field, controlled by application logic only
     */
    protected $fillable = [
        'organization_id',
        'name',
        'name_json',
        'original_language',
        'value',
        'min',
        'max',
    ];
    
    protected $hidden = [];
}