<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
	protected $table = 'assessment';
	public $timestamps = false;
	protected $guarded = [];
	protected $hidden = [];
	
	public function userCompetencySubmits(){
		return $this->hasMany(UserCompetencySubmit::class, 'assessment_id', 'id');
	}

	public function ceoRanks(){
		return $this->hasMany(UserCeoRank::class, 'assessment_id', 'id');
	}
}
