<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\User;

class AssessmentService
{
  public static function getCurrentAssessment(){
    $orgId = session('org_id');
    return Assessment::where('organization_id', $orgId)
                 ->whereNull('closed_at')
                 ->first();
  }

  public static function isAssessmentRunning(){
    $orgId = session('org_id');
      return Assessment::where('organization_id', $orgId)
                 ->whereNull('closed_at')
                 ->exists();
  }

  public static function calculateNeededCeoRanks(){
    return (User::where('type', '!=', UserType::ADMIN)->count()-1)*User::where('type', UserType::CEO)->count();
  }
  
}
