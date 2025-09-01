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
use App\Services\OrgConfigService;


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

    public function submitAssessment(Request $request)
{
    $assessment = AssessmentService::getCurrentAssessment();
    if (is_null($assessment)) {
        return abort(403);
    }

    // --- Org beállítások kiolvasása (kizárólagossággal) ---
    $orgId       = (int) $assessment->organization_id; // biztosan az aktuális assessment szervezete
    $strictAnon  = OrgConfigService::getBool($orgId, OrgConfigService::STRICT_ANON_KEY, false);
    $aiTelemetry = OrgConfigService::getBool($orgId, OrgConfigService::AI_TELEMETRY_KEY, true);
    if ($strictAnon) {
        $aiTelemetry = false; // szabály: strict anon => AI OFF
    }

    // get target
    $target = User::findOrFail($request->target);

    // check if target is assessed already
    $user = \App\Services\UserService::getCurrentUser();
    $already = \App\Models\UserCompetencySubmit::where('assessment_id', $assessment->id)
    ->where('user_id', $user->id)
    ->where('target_id', $target->id)
    ->exists();

if ($already) {
    return abort(403);
}
    // get competencyQuestions
    $questions = $target->competencyQuestions;

    // check if all of them are present
    if ($questions->count() != count($request->input('answers', []))) {
        return abort(403);
    }

    // Kliens-telemetria biztonságos beolvasása (ha van és ha engedélyezett)
    $clientTelemetry = null;
    if ($aiTelemetry && $request->filled('telemetry_raw')) {
        try {
            $clientTelemetry = json_decode($request->input('telemetry_raw'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $clientTelemetry = null; // hibás JSON-t figyelmen kívül hagyjuk
        }
    }

    AjaxService::DBTransaction(function () use ($request, $user, $questions, $target, $assessment, $clientTelemetry, $strictAnon, $aiTelemetry) {

        // --- Kompetencia-aggregálás és mentés ---
        $questions->groupBy('competency_id')->each(function ($item, $key) use ($request, $user, $target, $assessment, $strictAnon) {
            $competencyId = $key;
            $sum = 0;

            $item->each(function ($question) use ($request, &$sum) {
                $max = $question->max_value;
                $value = collect($request->answers)
                    ->firstWhere('questionId', $question->id)['value'] ?? 0;

                $value = round($value / $max * 100);
                $sum = $sum == 0 ? $value : round(($sum + $value) / 2);
            });

            $type = $user->id == $target->id
                ? UserRelationType::SELF
                : optional($user->relations()->where('target_id', $target->id)->first())['type'];

            if ($type == UserRelationType::SUBORDINATE && session('utype') == UserType::CEO) {
                $type = UserType::CEO;
            }

            CompetencySubmit::create([
                'assessment_id' => $assessment->id,
                'user_id'       => $strictAnon ? null : $user->id, // <<-- SZIGORÚ ANONÍMIA
                'target_id'     => $target->id,
                'competency_id' => $competencyId,
                'value'         => $sum,
                'type'          => $type,
            ]);
        });

        // --- telemetry_raw összeállítása csak ha AI telemetria engedélyezett ---
        $telemetryRaw = null;
        if ($aiTelemetry) {
            $telemetryRaw = TelemetryService::makeTelemetryRaw(
                $clientTelemetry,
                $assessment,
                $user,
                $target,
                $questions,
                $request->input('answers', [])
            );
        }

        // Mentés user_competency_submit-be
        $ucs = UserCompetencySubmit::create([
            'assessment_id' => $assessment->id,
            'user_id'       => $user->id,
            'target_id'     => $target->id,
            'submitted_at'  => date('Y-m-d H:i:s'),
            'telemetry_raw' => $aiTelemetry
                ? json_encode($telemetryRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null, // <<-- AI OFF esetén NULL
            // 'telemetry_ai' itt NEM kerül kitöltésre; AI OFF esetén később is NULL marad
        ]);

        // AI hívás csak akkor, ha engedélyezett
        if ($aiTelemetry) {
            \DB::afterCommit(function () use ($assessment, $user, $target) {
                try {
                    \Log::info('[AI] afterCommit: start', [
                        'assessmentId' => $assessment->id,
                        'userId'       => $user->id,
                        'targetId'     => $target->id,
                    ]);

                    $ai = \App\Services\TelemetryService::scoreAndStoreTelemetryAI(
                        $assessment->id, $user->id, $target->id
                    );

                    \Log::info('[AI] afterCommit: done', [
                        'ok'          => (bool) $ai,
                        'trust_score' => $ai['trust_score'] ?? null,
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('[AI] afterCommit: exception', ['msg' => $e->getMessage()]);
                }
            });
        }
        // AI OFF esetén nincs afterCommit callback -> telemetry_ai NULL marad

    }); // DBTransaction vége

    return response()->json(['ok' => true]);
}

}

