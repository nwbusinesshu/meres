<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCompetencySubmit extends Pivot
{
  protected $table = 'user_competency_submit';
  
  public $timestamps = false;
  protected $guarded = [];
  protected $hidden = [];
  protected $casts = [
    'telemetry_raw' => 'array',
    'telemetry_ai' => 'array',
  ];
      
  public static function user(){
    return $this->belongsTo(User::class, 'user_id', 'id');
  }

  public static function target(){
    return $this->belongsTo(User::class, 'target_id', 'id');
  }

  public static function assessment(){
    return $this->belongsTo(Assessment::class, 'assessment_id', 'id');
  }
}
