<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserLogin extends Pivot
{
  protected $table = 'user_login';
  
  public $timestamps = false;
  protected $guarded = [];
  protected $hidden = [];
      
  public static function user(){
    return $this->belongsTo(User::class, 'user_id', 'id');
  }
}
