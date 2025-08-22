<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminCompetencyController extends Controller
{
    public function __construct(){
        if(AssessmentService::isAssessmentRunning()){
            return abort(403);
        }
    }

    public function index(Request $request){
        $orgId = session('org_id');

        $comps = Competency::whereNull('removed_at')
            ->where(function($q) use ($orgId){
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $orgId);
            })
            ->with(['questions' => function($q) use ($orgId){
                $q->whereNull('removed_at')
                  ->where(function($q2) use ($orgId){
                      $q2->whereNull('organization_id')
                         ->orWhere('organization_id', $orgId);
                  });
            }])
            ->orderBy('name')
            ->get();

        return view('admin.competencies', ["competencies" => $comps]);
    }


    public function getAllCompetency(Request $request){
    $orgId = session('org_id');

    return Competency::whereNull('removed_at')
        ->where(function($q) use ($orgId){
            $q->whereNull('organization_id')
              ->orWhere('organization_id', $orgId);
        })
        ->orderBy('name')
        ->get();
    }

    public function saveCompetency(Request $request){
        $comp = Competency::find($request->id);
        $this->validate($request, ['name' => ['required']], [], ['name' => __('global.name')]);

        $orgId = session('org_id');

        AjaxService::DBTransaction(function() use ($request, &$comp, $orgId){
            if (is_null($comp)){
                $comp = Competency::create([
                    'name' => $request->name,
                    'organization_id' => $orgId, // ← fontos
                ]);
            } else {
                $comp->name = $request->name;
                $comp->save();
            }
        });
    }

    public function saveCompetencyQuestion(Request $request){
        $question = CompetencyQuestion::find($request->id);

        $rules = [
            "question" => ['required'],
            "questionSelf" => ['required'],
            "minLabel" => ['required'],
            "maxLabel" => ['required'],
            "scale" => ['required', 'numeric'],
        ];
        $this->validate($request, $rules, [], [
            "question" => __('admin/competencies.question'),
            "questionSelf" => __('admin/competencies.question-self'),
            "minLabel" => __('admin/competencies.min-label'),
            "maxLabel" => __('admin/competencies.max-label'),
            "scale" => __('admin/competencies.scale'),
        ]);

        AjaxService::DBTransaction(function() use ($request, &$question){
            $comp = Competency::findOrFail($request->compId);

            if (is_null($question)){
                $question = $comp->questions()->create([
                    "question" => $request->question,
                    "question_self" => $request->questionSelf,
                    "min_label" => $request->minLabel,
                    "max_label" => $request->maxLabel,
                    "max_value" => $request->scale,
                    "organization_id" => $comp->organization_id, // ← kulcs a trigger miatt
                ]);
            } else {
                $question->question = $request->question;
                $question->question_self = $request->questionSelf;
                $question->min_label = $request->minLabel;
                $question->max_label = $request->maxLabel;
                $question->max_value = $request->scale;
                $question->organization_id = $comp->organization_id; // ← kulcs
                $question->competency_id = $comp->id;                // ha comp váltás megengedett
                $question->save();
            }
        });
    }


    public function getCompetencyQuestion(Request $request){
        return CompetencyQuestion::findOrFail($request->id);
    }

    public function removeCompetency(Request $request){
        $comp = Competency::findOrFail($request->id);
        AjaxService::DBTransaction(function() use(&$comp) {
            $comp->users()->detach();
            $comp->removed_at = date('Y-m-d H:i:s');
            $comp->save();
        });
    }

    public function removeCompetencyQuestion(Request $request){
        $q = CompetencyQuestion::findOrFail($request->id);
        AjaxService::DBTransaction(function() use(&$q) {
            $q->removed_at = date('Y-m-d H:i:s');
            $q->save();
        });
    }
}

