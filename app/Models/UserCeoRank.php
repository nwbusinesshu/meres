<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCeoRank extends Pivot
{
  protected $table = 'user_ceo_rank';
  
  public $timestamps = false;
  protected $guarded = [];
  protected $hidden = [];
      
  public static function ceo(){
    return $this->belongsTo(User::class, 'ceo_id', 'id');
  }

  public static function user(){
    return $this->belongsTo(User::class, 'user_id', 'id');
  }

  public static function assessment(){
    return $this->belongsTo(Assessment::class, 'assessment_id', 'id');
  }
}
