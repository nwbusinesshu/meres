<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserBonusMalus extends Pivot
{
  protected $table = 'user_bonus_malus';
  
  public $timestamps = false;
  protected $guarded = [];
  protected $hidden = [];
      
  public function user(){
    return $this->belongsTo(User::class, 'user_id', 'id');
  }

  public function name(){
    return __('global.bonus-malus.'.$this->level);
  }
}
