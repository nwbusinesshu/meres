<?php
// app/Models/CompetencyGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetencyGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'competency_ids'
    ];

    protected $casts = [
        'competency_ids' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the organization that owns the competency group.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the competencies that belong to this group.
     * This will return actual Competency models based on the stored IDs.
     */
    public function competencies()
    {
        if (empty($this->competency_ids)) {
            return collect();
        }

        return Competency::whereIn('id', $this->competency_ids)
                         ->whereNull('removed_at')
                         ->orderBy('name')
                         ->get();
    }

    /**
     * Add a competency to this group.
     */
    public function addCompetency($competencyId)
    {
        $competencyIds = $this->competency_ids ?? [];
        
        if (!in_array($competencyId, $competencyIds)) {
            $competencyIds[] = $competencyId;
            $this->competency_ids = $competencyIds;
            $this->save();
        }
    }

    /**
     * Remove a competency from this group.
     */
    public function removeCompetency($competencyId)
    {
        $competencyIds = $this->competency_ids ?? [];
        $competencyIds = array_values(array_filter($competencyIds, function($id) use ($competencyId) {
            return $id != $competencyId;
        }));
        
        $this->competency_ids = $competencyIds;
        $this->save();
    }

    /**
     * Check if a competency is in this group.
     */
    public function hasCompetency($competencyId)
    {
        return in_array($competencyId, $this->competency_ids ?? []);
    }

    /**
     * Get the count of competencies in this group.
     */
    public function getCompetencyCountAttribute()
    {
        return count($this->competency_ids ?? []);
    }
}