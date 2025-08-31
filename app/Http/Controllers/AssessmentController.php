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
use App\Services\TelemetryService;


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
    if (is_null($assessment)) {
        return abort(403);
    }

    // get target
    $target = User::findOrFail($request->target);

    // check if target is assessed already
    $user = UserService::getCurrentUser();
    if ($user->competencySubmits()->where('target_id', $target->id)->count() != 0) {
        return abort(403);
    }

    // get competencyQuestions
    $questions = $target->competencyQuestions;

    // check if all of them are present
    if ($questions->count() != count($request->input('answers', []))) {
        return abort(403);
    }

    // Kliens-telemetria biztonságos beolvasása (ha van)
    $clientTelemetry = null;
    if ($request->filled('telemetry_raw')) {
        try {
            $clientTelemetry = json_decode($request->input('telemetry_raw'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $clientTelemetry = null; // hibás JSON-t figyelmen kívül hagyjuk
        }
    }

    AjaxService::DBTransaction(function() use ($request, &$user, &$questions, $target, $assessment, $clientTelemetry){

        // --- Meglévő logika: kompetencia-aggregálás és mentés ---
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

            $type = $user->id == $target->id
                ? UserRelationType::SELF
                : $user->relations()->where('target_id', $target->id)->first()['type'];

            if ($type == UserRelationType::SUBORDINATE && session('utype') == UserType::CEO) {
                $type = UserType::CEO;
            }

            CompetencySubmit::create([
                "assessment_id" => $assessment->id,
                "user_id"       => $user->id,
                "target_id"     => $target->id,
                "competency_id" => $competencyId,
                "value"         => $sum,
                "type"          => $type
            ]);
        });

        // --- ÚJ: telemetry_raw összeállítása a Service segítségével ---
        $telemetryRaw = TelemetryService::makeTelemetryRaw(
            $clientTelemetry,
            $assessment,
            $user,
            $target,
            $questions,
            $request->input('answers', [])
        );

        // Mentés
        UserCompetencySubmit::create([
            "assessment_id" => $assessment->id,
            "user_id"       => $user->id,
            "target_id"     => $target->id,
            "submitted_at"  => date('Y-m-d H:i:s'),
            "telemetry_raw" => json_encode($telemetryRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        TelemetryService::scoreAndStoreTelemetryAI($assessment->id, $user->id, $target->id);
    });
}
}

