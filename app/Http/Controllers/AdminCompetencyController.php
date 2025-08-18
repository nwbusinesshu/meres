<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use Illuminate\Http\Request;

class AdminCompetencyController extends Controller
{
    public function __construct(){
        if(AssessmentService::isAssessmentRunning()){
            return abort(403);
        }
    }

    public function index(Request $request){
        return view('admin.competencies',[
            "competencies" => Competency::whereNull('removed_at')->with('questions')->get(),
        ]);
    }

    public function getAllCompetency(Request $request){
        return Competency::whereNull('removed_at')->orderBy('name')->get();
    }

    public function saveCompetency(Request $request){
        $comp = Competency::find($request->id);

        $rules = [
            "name" => ['required'],
        ];

        $attributes = [
            "name" => __('global.name'),
        ];
    
        $this->validate(
            request: $request,
            rules: $rules,
            customAttributes: $attributes,
        ); 

        AjaxService::DBTransaction(function() use ($request, &$comp){
            if(is_null($comp)){
                Competency::create([
                    "name" => $request->name
                ]);
            }else{
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

        $attributes = [
            "question" => __('admin/competencies.question'),
            "questionSelf" => __('admin/competencies.question-self'),
            "minLabel" => __('admin/competencies.min-label'),
            "maxLabel" => __('admin/competencies.max-label'),
            "scale" => __('admin/competencies.scale'),
        ];
    
        $this->validate(
            request: $request,
            rules: $rules,
            customAttributes: $attributes,
        ); 

        AjaxService::DBTransaction(function() use ($request, &$question){
            if(is_null($question)){
                $comp = Competency::findOrFail($request->compId);

                $comp->questions()->create([
                    "question" => $request->question,
                    "question_self" => $request->questionSelf,
                    "min_label" => $request->minLabel,
                    "max_label" => $request->maxLabel,
                    "max_value" => $request->scale,
                ]);
            }else{
                $question->question = $request->question;
                $question->question_self = $request->questionSelf;
                $question->min_label = $request->minLabel;
                $question->max_label = $request->maxLabel;
                $question->max_value = $request->scale;
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

