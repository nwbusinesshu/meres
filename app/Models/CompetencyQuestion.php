<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetencyQuestion extends Model
{
    protected $table = 'competency_question';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = [];
    protected $fillable = [
        'organization_id','competency_id','question','question_self',
        'min_label','max_label','max_value'
      ];

    public function competency(){
      return $this->belongsTo(Competency::class, 'competency_id', 'id');
    }
     public function scopeGlobal($q)  { return $q->whereNull('organization_id'); }
    public function scopeForOrg($q, $orgId) { return $q->where('organization_id', $orgId); }
}
