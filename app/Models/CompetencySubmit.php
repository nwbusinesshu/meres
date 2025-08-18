<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CompetencySubmit extends Pivot
{
  protected $table = 'competency_submit';
  
  public $timestamps = false;
  protected $guarded = [];
  protected $hidden = [];
  
  public function user(){
    return $this->belongsTo(User::class,'user_id','id');
  }

  public function target(){
    return $this->belongsTo(User::class,'target_id','id');
  }
}
