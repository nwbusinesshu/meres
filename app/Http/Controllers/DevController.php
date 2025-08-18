<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CeoRank;
use App\Models\CompetencySubmit;
use App\Models\Enums\UserRelationType;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserBonusMalus;
use App\Models\UserCeoRank;
use App\Models\UserCompetencySubmit;
use App\Models\UserRelation;
use App\Services\AssessmentService;
use App\Services\UserService;
use App\Services\WelcomeMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevController extends Controller
{   
    public function __construct(){
        if(env('APP_ENV') == "production"){
            return abort(403);
        }
    }

    public function makeFullAssessment(Request $request){
        DB::transaction(function(){

        $assessment = Assessment::create([
            "started_at" => date('Y-m-d H:i:s'),
            "due_at" => date('Y-m-d 00:00:00',strtotime('+14 Days'))
        ]);

        User::whereNull('removed_at')->whereNot('type', UserType::ADMIN)->get()
        ->each(function($user) use ($assessment){
            $user->allRelations
            ->each(function($relation) use ($user, $assessment){
                $target = User::find($relation->target_id);
                User::find($relation->target_id)->competencies
                ->each(function($comp) use ($user, $relation, $assessment, $target){
                    CompetencySubmit::create([
                        "assessment_id" => $assessment->id,
                        "competency_id" => $comp->id,
                        "user_id" => $user->id,
                        "target_id" => $target->id,
                        "value" => rand(0,100),
                        "type" => $relation->type == UserRelationType::SUBORDINATE ? ($target->type == UserType::CEO ? UserType::CEO : UserRelationType::SUBORDINATE) : $relation->type,
                    ]);
                });
                UserCompetencySubmit::create([
                    "assessment_id" => $assessment->id,
                    "user_id" => $user->id,
                    "target_id" => $target->id,
                    "submitted_at" => date('Y-m-d H:i:s')
                ]);
            });
        });

        User::whereNull('removed_at')->where('type', UserType::CEO)->get()
        ->each(function($ceo) use ($assessment){
            $ranks = CeoRank::whereNull('removed_at')->get();

            User::whereNull('removed_at')->where('type', UserType::NORMAL)->get()
            ->each(function($target) use ($ceo,$assessment,$ranks){
                UserCeoRank::create([
                    "assessment_id" => $assessment->id,
                    "ceo_id" => $ceo->id,
                    "user_id" => $target->id,
                    "value" => $ranks->random()->value
                ]);
            });
        });

        });
    }

    public function generateBonusMalus(Request $request){
        DB::transaction(function(){

        User::whereNull('removed_at')->whereNot('type', UserType::ADMIN)->get()
        ->each(function($user){
            $months = rand(3,4);
            for($i = 0; $i < $months; $i++){
                $user->bonusMalus()->updateOrCreate([
                    "level" => UserService::DEFAULT_BM,
                    "month" => date('Y-m-01',strtotime("-$i Months"))
                ]);
            }
        });

        });
    }
}

