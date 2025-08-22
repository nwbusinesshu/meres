<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\AjaxService;
use Illuminate\Http\Request;

class GlobalCompetencyController extends Controller
{
    public function index(Request $request)
    {
        // Csak globális kompetenciák és azok globális kérdései
        $globals = Competency::query()
            ->whereNull('removed_at')
            ->whereNull('organization_id')
            ->with(['questions' => function ($q) {
                $q->whereNull('removed_at')->whereNull('organization_id');
            }])
            ->orderBy('name')
            ->get();

        return view('superadmin.global-competencies', [
            'globals' => $globals,
        ]);
    }

    public function saveCompetency(Request $request)
    {
        $comp = Competency::find($request->id);

        $request->validate(['name' => ['required', 'string', 'max:255']]);

        AjaxService::DBTransaction(function () use ($request, &$comp) {
            if (is_null($comp)) {
                // ÚJ: kifejezetten GLOBÁLIS
                Competency::create([
                    'name' => $request->name,
                    'organization_id' => null,
                ]);
            } else {
                // csak globális szerkeszthető ezen az oldalon
                if (!is_null($comp->organization_id)) {
                    abort(403);
                }
                $comp->name = $request->name;
                $comp->save();
            }
        });
    }

    public function removeCompetency(Request $request)
    {
        $comp = Competency::findOrFail($request->id);

        if (!is_null($comp->organization_id)) {
            abort(403);
        }

        AjaxService::DBTransaction(function () use (&$comp) {
            // Csak logikai törlés (marad a viszony)
            $comp->removed_at = now();
            $comp->save();
        });
    }

    public function getCompetencyQuestion(Request $request)
    {
        $q = CompetencyQuestion::findOrFail($request->id);
        if (!is_null($q->organization_id)) {
            abort(403);
        }
        return $q;
    }

    public function saveCompetencyQuestion(Request $request)
    {
        $question = CompetencyQuestion::find($request->id);

        $request->validate([
            'compId'       => ['required', 'integer', 'exists:competency,id'],
            'question'     => ['required', 'string'],
            'questionSelf' => ['required', 'string'],
            'minLabel'     => ['required', 'string'],
            'maxLabel'     => ['required', 'string'],
            'scale'        => ['required', 'integer', 'min:2'],
        ]);

        AjaxService::DBTransaction(function () use ($request, &$question) {
            $comp = Competency::findOrFail($request->compId);

            // csak GLOBÁLIS kompetenciához engedünk kérdést ezen az oldalon
            if (!is_null($comp->organization_id)) {
                abort(403);
            }

            if (is_null($question)) {
                $comp->questions()->create([
                    'organization_id' => null, // GLOBÁLIS
                    'question'        => $request->question,
                    'question_self'   => $request->questionSelf,
                    'min_label'       => $request->minLabel,
                    'max_label'       => $request->maxLabel,
                    'max_value'       => $request->scale,
                ]);
            } else {
                if (!is_null($question->organization_id)) {
                    abort(403);
                }
                $question->question      = $request->question;
                $question->question_self = $request->questionSelf;
                $question->min_label     = $request->minLabel;
                $question->max_label     = $request->maxLabel;
                $question->max_value     = $request->scale;
                // marad global
                $question->save();
            }
        });
    }

    public function removeCompetencyQuestion(Request $request)
    {
        $q = CompetencyQuestion::findOrFail($request->id);

        if (!is_null($q->organization_id)) {
            abort(403);
        }

        AjaxService::DBTransaction(function () use (&$q) {
            $q->removed_at = now();
            $q->save();
        });
    }
}
