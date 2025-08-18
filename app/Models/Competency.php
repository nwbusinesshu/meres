<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competency extends Model
{
    protected $table = 'competency';
	public $timestamps = false;
	protected $guarded = [];
	protected $hidden = [];

    public function questions(){
        return $this->hasMany(CompetencyQuestion::class,'competency_id','id')->whereNull('removed_at');
    }

    public function users(){
        return $this->belongsToMany(User::class,'user_competency','competency_id','user_id','id','id');
    }   
}
