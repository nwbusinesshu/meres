<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCeoRank extends Pivot
{
    protected $table = 'user_ceo_rank';

    public $timestamps   = false;
    public $incrementing = false; // nincs 'id' oszlop
    protected $guarded   = [];
    protected $casts     = [
        'assessment_id' => 'int',
        'ceo_id'        => 'int',
        'user_id'       => 'int',
        'value'         => 'int',
    ];

    public function ceo()
    {
        return $this->belongsTo(User::class, 'ceo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    // kényelmi scope-ok (opcionális)
    public function scopeForAssessment($q, $assessmentId)
    {
        return $q->where('assessment_id', $assessmentId);
    }

    public function scopeByCeo($q, $ceoId)
    {
        return $q->where('ceo_id', $ceoId);
    }

    public function scopeByUser($q, $userId)
    {
        return $q->where('user_id', $userId);
    }
}
