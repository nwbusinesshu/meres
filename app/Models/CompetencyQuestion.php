<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetencyQuestion extends Model
{
    protected $table = 'competency_question';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = [];

    public function competency(){
      return $this->belongsTo(Competency::class, 'competency_id', 'id');
    }
}
