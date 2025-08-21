<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserRelation extends Pivot
{
  protected $table = 'user_relation';
  
  public $timestamps = false;
  protected $guarded = [];
  protected $hidden = [];
      
  public function user(){
    return $this->belongsTo(User::class, 'user_id', 'id');
  }

  public function target(){
    return $this->belongsTo(User::class, 'target_id', 'id');
  }


  public function assessee()
  {
    return $this->belongsTo(User::class, 'assessee_id');
  }

}
