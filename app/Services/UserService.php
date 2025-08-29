<?php

namespace App\Services;

use App\Http\Middleware\Auth;
use App\Models\Assessment;
use App\Models\Enums\UserRelationType;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserBonusMalus;

class UserService
{
  const DEFAULT_BM = 5;
  
public static function getUsers() {
    $orgId = session('org_id');

    return User::whereNull('removed_at')
        ->whereNotIn('type', [UserType::ADMIN, UserType::SUPERADMIN])
        ->whereHas('organizations', function ($query) use ($orgId) {
            $query->where('organization_id', $orgId);
        })
        ->orderBy('name')
        ->get();
}


  public static function getCurrentUser(){
    return User::findOrFail(session('uid'));
  }

  public static function calculateUserPoints(User $user, Assessment $assessment){
    if ((int)$assessment->organization_id !== (int) session('org_id')) { 
      return null; 
    }

    //check if assessment is closed
    if(is_null($assessment->closed_at)){
      return null;
    }

    $stats = (object)[
      "selfTotal" => 0,
      "self" => $user->assessedCompetencies($assessment->id, UserRelationType::SELF)->get(),
      "ceoTotal" => 0,
      "ceo" => $user->ceoRanks($assessment->id)->get(),
      "colleagueTotal" => 0,
      "colleague" => $user->assessedCompetencies($assessment->id, UserRelationType::COLLEGAUGE)->get(),
      "managersTotal" => 0,
      "managersCeoTotal" => 0,
      "managersCeo" => $user->assessedCompetencies($assessment->id, UserType::CEO)->get(),
      "managersBasicTotal" => 0,
      "managersBasic" => $user->assessedCompetencies($assessment->id, UserRelationType::SUBORDINATE)->get(),
      "total" => 0
    ];

    if($stats->self->isEmpty()){
      return null;
    }

    $stats->selfTotal = round($stats->self->avg('value') * 0.5);
    $stats->ceoTotal = $stats->ceo->isEmpty() ? 100 : round($stats->ceo->avg('value'));
    $stats->colleagueTotal = $stats->colleague->isEmpty() ? 100 : round($stats->colleague->avg('value'));

    $stats->managersCeoTotal = $stats->managersCeo->isEmpty() ? 100 : round($stats->managersCeo->avg('value'));
    $stats->managersBasicTotal = $stats->managersBasic->isEmpty() ? 100 : round($stats->managersBasic->avg('value'));

    if($stats->managersBasicTotal != 0 && $stats->managersCeoTotal != 0){
      $stats->managersTotal = round($stats->managersBasicTotal * 1.5 + $stats->managersCeoTotal * 0.5);
    }
    else if($stats->managersBasicTotal != 0 && $stats->managersCeoTotal == 0){
      $stats->managersTotal = $stats->managersBasicTotal * 2;
    }
    else if($stats->managersBasicTotal == 0 && $stats->managersCeoTotal != 0){
      $stats->managersTotal = $stats->managersCeoTotal * 2;
    }

    $stats->total = round(($stats->selfTotal+$stats->ceoTotal+$stats->colleagueTotal+$stats->managersTotal) / 4.5);

    return $stats;
  }

  public static function handleNewMonthLevels(){
    User::whereNot('type', UserType::ADMIN)->get()->each(function($user){
      $bonusMalus = $user->bonusMalus()->first();
      if($bonusMalus->month >= date('Y-m-01')){
        return;
      }

      if($user->has_auto_level_up == 1 && $bonusMalus->level < 15){
        $bonusMalus->level++;
      }

      UserBonusMalus::create([
        "user_id" => $user->id,
        "month" => date('Y-m-01'),
        "level" => $bonusMalus->level
      ]);     
    });
  }
}
