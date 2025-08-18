<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\User;

class AssessmentService
{
  public static function getCurrentAssessment(){
    return Assessment::whereNull('closed_at')->first();
  }

  public static function isAssessmentRunning(){
    return Assessment::whereNull('closed_at')->count() > 0;
  }

  public static function calculateNeededCeoRanks(){
    return (User::where('type', '!=', UserType::ADMIN)->count()-1)*User::where('type', UserType::CEO)->count();
  }
  
}
