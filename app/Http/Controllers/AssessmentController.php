<?php

namespace App\Http\Controllers;

use App\Models\CompetencyQuestion;
use App\Models\CompetencySubmit;
use App\Models\Enums\UserRelationType;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserCompetencySubmit;
use App\Models\UserRelation;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use App\Services\UserService;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function index(Request $request){
        // getting user
        $user = UserService::getCurrentUser();

        // getting target
        $target = User::findOrFail($request->targetId);
        
        // checking if an assessment is running
        if(is_null(($assessment = AssessmentService::getCurrentAssessment()))){
            return abort(403);
        }

        // checking if target is in realations
        if($user->relations()->where('target_id', $target->id)->count() == 0 && session('uid') != $target->id){
            return abort(403);
        }

        // checking if target is already assessed
        if($user->competencySubmits()->where('target_id', $target->id)->count() != 0){
            return abort(403);
        }

        return view('assessment',[
            "target" => $target,
            "relation" => UserRelation::where('user_id', $user->id)->where('target_id', $target->id)->first(),
            "assessment" => $assessment,
            "questions" => $target->competencyQuestions()->with('competency')->get()->groupBy('competency.name'),
            "questionsCount" => $target->competencyQuestions()->count()
        ]);
    }

    public function submitAssessment(Request $request){
        $assessment = AssessmentService::getCurrentAssessment();
        if(is_null($assessment)){
            return abort(403);
        }

        // get target
        $target = User::findOrFail($request->target);

        // check if target is assessed already
        $user = UserService::getCurrentUser();
        // checking if target is already assessed
        if($user->competencySubmits()->where('target_id', $target->id)->count() != 0){
            return abort(403);
        }

        // get competencyQuestions
        $questions = $target->competencyQuestions;

        // check if all of them are present
        if($questions->count() != count($request?->answers)){
            return abort(403);
        }

        AjaxService::DBTransaction(function() use ($request, &$user, &$questions, $target, $assessment){
            $questions->groupBy('competency_id')->each(function($item, $key) use ($request, &$user, $target, $assessment){
                $competencyId = $key;
                $sum = 0;
                $item->each(function($question) use ($request, &$sum){
                    $max = $question->max_value;
                    $value = collect($request->answers)->filter(function($answer) use ($question){
                        return $answer['questionId'] == $question->id;
                    })->first()['value'];
                    $value = round($value / $max * 100);
                    $sum = $sum == 0 ? $value : round(($sum + $value) / 2);
                });
                
                $type = $user->id == $target->id ? UserRelationType::SELF : $user->relations()->where('target_id', $target->id)->first()['type'];

                if($type == UserRelationType::SUBORDINATE && session('utype') == UserType::CEO){
                    $type = UserType::CEO;
                }

                CompetencySubmit::create([
                    "assessment_id" => $assessment->id,
                    "user_id" => $user->id,
                    "target_id" => $target->id,
                    "competency_id" => $competencyId,
                    "value" => $sum,
                    "type" => $type
                ]);
            });
            UserCompetencySubmit::create([
                "assessment_id" => $assessment->id,
                "user_id" => $user->id,
                "target_id" => $target->id,
                "submitted_at" => date('Y-m-d H:i:s')
            ]);
        });
    }
}

