<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CeoRank;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Services\AssessmentService;
use App\Services\UserService;
use Illuminate\Http\Request;

class AdminResultsController extends Controller
{
    public function index(Request $request){
        $assessment = Assessment::whereNotNull('closed_at')->orderByDesc('closed_at')->first();
        return view('admin.results',[
            "assessment" => $assessment,
            "users" => is_null($assessment) ? null : User::whereNull('removed_at')->whereNot('type', UserType::ADMIN)->orderBy('name')->get()->map(function($user) use ($assessment){
                $user['stats'] = UserService::calculateUserPoints($user, $assessment);
                $user['bonusMalus'] = $user->getBonusMalusInMonth(date('Y-m-01',strtotime($assessment->closed_at)))->level;
                $user['change'] = 'none';
                if(!is_null($user->stats)){
                    if($user->has_auto_level_up == 1){
                        if($user->stats->total < $assessment->monthly_level_down){
                            $user->change = "down";
                        }
                    }else{
                        if($user->stats->total < $assessment->normal_level_down){
                            $user->change = "down";
                        }else if($user->stats->total >  $assessment->normal_level_up){
                            $user->change = "up";
                        }
                    }
                }   
                return $user;
            }),
        ]);
    }
}

