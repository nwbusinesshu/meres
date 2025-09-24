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

        // UPDATED: Provide all available languages by default for global competencies
        $availableLanguages = config('app.available_locales', []);
        $allLanguageKeys = array_keys($availableLanguages);

        return view('superadmin.global-competencies', [
            'globals' => $globals,
            'selectedLanguages' => $allLanguageKeys, // All languages available
            'availableLanguages' => $availableLanguages
        ]);
    }

    public function saveCompetency(Request $request)
    {
        $comp = Competency::find($request->id);

        $request->validate(['name' => ['required', 'string', 'max:255']]);

        AjaxService::DBTransaction(function () use ($request, &$comp) {
            if (is_null($comp)) {
                // NEW: GLOBAL with translation support
                $comp = new Competency();
                $comp->name = $request->name;
                $comp->organization_id = null;
                $comp->original_language = $request->original_language ?? auth()->user()->locale ?? config('app.locale', 'hu');
            } else {
                if (!is_null($comp->organization_id)) {
                    abort(403);
                }
                $comp->name = $request->name;
                if ($request->has('original_language')) {
                    $comp->original_language = $request->original_language;
                }
            }

            // UPDATED: Handle translations for global competencies
            if ($request->has('translations') && is_array($request->translations)) {
                $translations = array_filter($request->translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $comp->name_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                    
                    // Update available languages
                    $comp->available_languages = json_encode(array_keys($translations), JSON_UNESCAPED_UNICODE);
                }
            } else {
                // Store original name in JSON format
                $originalLang = $comp->original_language;
                $comp->name_json = json_encode([$originalLang => $comp->name], JSON_UNESCAPED_UNICODE);
                $comp->available_languages = json_encode([$originalLang], JSON_UNESCAPED_UNICODE);
            }

            $comp->save();
        });

        return response()->json(['ok' => true, 'id' => $comp->id ?? null]);
    }

    public function removeCompetency(Request $request)
    {
        $comp = Competency::findOrFail($request->id);

        if (!is_null($comp->organization_id)) {
            abort(403);
        }

        AjaxService::DBTransaction(function () use (&$comp) {
            $comp->removed_at = now();
            $comp->save();
        });

        return response()->json(['ok' => true]);
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

            if (!is_null($comp->organization_id)) {
                abort(403);
            }

            if (is_null($question)) {
                $question = new CompetencyQuestion();
                $question->competency_id = $request->compId;
                $question->organization_id = null;
                $question->original_language = $request->original_language ?? auth()->user()->locale ?? config('app.locale', 'hu');
            } else {
                if (!is_null($question->organization_id)) {
                    abort(403);
                }
                if ($request->has('original_language')) {
                    $question->original_language = $request->original_language;
                }
            }

            // Set the main values
            $question->question = $request->question;
            $question->question_self = $request->questionSelf;
            $question->min_label = $request->minLabel;
            $question->max_label = $request->maxLabel;
            $question->max_value = $request->scale;

            // UPDATED: Handle question translations
            if ($request->has('question_translations') && is_array($request->question_translations)) {
                $translations = array_filter($request->question_translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $question->question_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                }
            } else {
                // Store original question in JSON format
                $originalLang = $question->original_language;
                $question->question_json = json_encode([$originalLang => $question->question], JSON_UNESCAPED_UNICODE);
            }

            // Handle question_self translations
            if ($request->has('question_self_translations') && is_array($request->question_self_translations)) {
                $translations = array_filter($request->question_self_translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $question->question_self_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $originalLang = $question->original_language;
                $question->question_self_json = json_encode([$originalLang => $question->question_self], JSON_UNESCAPED_UNICODE);
            }

            // Handle min_label translations
            if ($request->has('min_label_translations') && is_array($request->min_label_translations)) {
                $translations = array_filter($request->min_label_translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $question->min_label_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $originalLang = $question->original_language;
                $question->min_label_json = json_encode([$originalLang => $question->min_label], JSON_UNESCAPED_UNICODE);
            }

            // Handle max_label translations
            if ($request->has('max_label_translations') && is_array($request->max_label_translations)) {
                $translations = array_filter($request->max_label_translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $question->max_label_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $originalLang = $question->original_language;
                $question->max_label_json = json_encode([$originalLang => $question->max_label], JSON_UNESCAPED_UNICODE);
            }

            // Update available languages based on all translations
            $allTranslations = [$question->original_language];
            if ($question->question_json) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($question->question_json, true)));
            }
            if ($question->question_self_json) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($question->question_self_json, true)));
            }
            if ($question->min_label_json) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($question->min_label_json, true)));
            }
            if ($question->max_label_json) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($question->max_label_json, true)));
            }
            
            $allTranslations = array_unique($allTranslations);
            if (!empty($allTranslations)) {
                $question->available_languages = json_encode($allTranslations, JSON_UNESCAPED_UNICODE);
            }

            $question->save();
        });

        return response()->json(['ok' => true, 'id' => $question->id ?? null]);
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

        return response()->json(['ok' => true]);
    }

    public function getCompetencyQuestion(Request $request)
    {
        $q = CompetencyQuestion::find($request->id);
        if (!$q) AjaxService::error(__('admin/competencies.question-not-found'));

        return $q;
    }

    /**
     * NEW: Get competency translations
     */
    public function getCompetencyTranslations(Request $request)
    {
        $competency = Competency::find($request->id);
        
        if (!$competency) {
            return response()->json(['error' => __('admin/competencies.competency-not-found')], 404);
        }

        return response()->json([
            'id' => $competency->id,
            'name' => $competency->name,
            'name_json' => $competency->name_json ? json_decode($competency->name_json, true) : null,
            'original_language' => $competency->original_language ?? 'hu',
            'available_languages' => $competency->available_languages ? json_decode($competency->available_languages, true) : null
        ]);
    }

    /**
     * NEW: Get competency question translations
     */
    public function getCompetencyQuestionTranslations(Request $request)
    {
        $question = CompetencyQuestion::find($request->id);
        
        if (!$question) {
            return response()->json(['error' => __('admin/competencies.question-not-found')], 404);
        }

        return response()->json([
            'id' => $question->id,
            'question' => $question->question,
            'question_json' => $question->question_json ? json_decode($question->question_json, true) : null,
            'question_self' => $question->question_self,
            'question_self_json' => $question->question_self_json ? json_decode($question->question_self_json, true) : null,
            'min_label' => $question->min_label,
            'min_label_json' => $question->min_label_json ? json_decode($question->min_label_json, true) : null,
            'max_label' => $question->max_label,
            'max_label_json' => $question->max_label_json ? json_decode($question->max_label_json, true) : null,
            'max_value' => $question->max_value,
            'original_language' => $question->original_language ?? 'hu',
            'available_languages' => $question->available_languages ? json_decode($question->available_languages, true) : null
        ]);
    }

    /**
     * NEW: Get all available languages for global competencies (no organization filter)
     */
    public function getAvailableLanguages(Request $request)
    {
        $availableLocales = config('app.available_locales', []);
        $userDefaultLanguage = auth()->user()->locale ?? config('app.locale', 'hu');

        return response()->json([
            'available_locales' => $availableLocales,
            'user_default_language' => $userDefaultLanguage
        ]);
    }

    /**
     * NEW: Get all languages as selected for global competencies (no language restrictions)
     */
    public function getSelectedLanguages(Request $request)
    {
        $availableLocales = config('app.available_locales', []);
        $allLanguageKeys = array_keys($availableLocales);
        
        return response()->json([
            'selected_languages' => $allLanguageKeys // All languages available for global competencies
        ]);
    }
}