<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCompetency extends Pivot
{
  protected $table = 'user_competency';
  
  public $timestamps = false;
  protected $guarded = [];
  protected $hidden = [];
      
  public static function user(){
    return $this->belongsTo(User::class, 'user_id', 'id');
  }
}
