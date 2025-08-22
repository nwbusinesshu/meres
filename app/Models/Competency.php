<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competency extends Model
{
    protected $table = 'competency';
	public $timestamps = false;
	protected $guarded = [];
	protected $hidden = [];
    protected $fillable = ['name','organization_id'];

    public function questions(){
         return $this->hasMany(CompetencyQuestion::class, 'competency_id')
            ->whereNull('competency_question.removed_at');
    }

    public function users(){
        return $this->belongsToMany(User::class,'user_competency','competency_id','user_id','id','id');
    }   
    public function scopeGlobal($q)  { return $q->whereNull('organization_id'); }
    public function scopeForOrg($q, $orgId) { return $q->where('organization_id', $orgId); }
}
