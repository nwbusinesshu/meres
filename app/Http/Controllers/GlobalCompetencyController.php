<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\AjaxService;
use App\Services\AiTranslationService;
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
        $comp = Competency::find($request->id);

        if (!$comp) AjaxService::error(__('admin/competencies.competency-not-found'));

        // Only global competencies can be edited
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
        $q = CompetencyQuestion::find($request->id);

        $this->validate($request, [
            'question' => ['required'],
            'questionSelf' => ['required'],
            'minLabel' => ['required'], 
            'maxLabel' => ['required'],
            'scale' => ['required', 'numeric', 'min:1', 'max:10']
        ], [], [
            'question' => __('admin/competencies.question'),
            'questionSelf' => __('admin/competencies.question-self'),
            'minLabel' => __('admin/competencies.min-label'),
            'maxLabel' => __('admin/competencies.max-label'),
            'scale' => __('admin/competencies.scale')
        ]);

        AjaxService::DBTransaction(function() use ($request, &$q) {
            if (is_null($q)) {
                // NEW: Global question with translation support
                $q = new CompetencyQuestion();
                $q->competency_id = $request->competency_id;
                $q->organization_id = null;
                $q->original_language = $request->original_language ?? auth()->user()->locale ?? config('app.locale', 'hu');
            } else {
                // ADAPTED: Allow editing global questions only
                if(!is_null($q->organization_id)){
                    abort(403);
                }
                if ($request->has('original_language')) {
                    $q->original_language = $request->original_language;
                }
            }

            // Set the main values
            $q->question = $request->question;
            $q->question_self = $request->questionSelf;
            $q->min_label = $request->minLabel;
            $q->max_label = $request->maxLabel;
            $q->max_value = $request->scale;

            // EXACT SAME translation handling as working AdminCompetencyController
            
            // Handle question translations
            if ($request->has('question_translations') && is_array($request->question_translations)) {
                $translations = array_filter($request->question_translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $q->question_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                }
            } else {
                // Store original question in JSON format
                $originalLang = $q->original_language;
                $q->question_json = json_encode([$originalLang => $q->question], JSON_UNESCAPED_UNICODE);
            }

            // Handle question_self translations
            if ($request->has('question_self_translations') && is_array($request->question_self_translations)) {
                $translations = array_filter($request->question_self_translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $q->question_self_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $originalLang = $q->original_language;
                $q->question_self_json = json_encode([$originalLang => $q->question_self], JSON_UNESCAPED_UNICODE);
            }

            // Handle min_label translations
            if ($request->has('min_label_translations') && is_array($request->min_label_translations)) {
                $translations = array_filter($request->min_label_translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $q->min_label_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $originalLang = $q->original_language;
                $q->min_label_json = json_encode([$originalLang => $q->min_label], JSON_UNESCAPED_UNICODE);
            }

            // Handle max_label translations
            if ($request->has('max_label_translations') && is_array($request->max_label_translations)) {
                $translations = array_filter($request->max_label_translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $q->max_label_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $originalLang = $q->original_language;
                $q->max_label_json = json_encode([$originalLang => $q->max_label], JSON_UNESCAPED_UNICODE);
            }

            // Set available languages - EXACT SAME as working AdminCompetencyController
            $allTranslations = [];
            if ($q->question_json) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($q->question_json, true)));
            }
            if ($q->question_self_json) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($q->question_self_json, true)));
            }
            if ($q->min_label_json) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($q->min_label_json, true)));
            }
            if ($q->max_label_json) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($q->max_label_json, true)));
            }
            
            $allTranslations = array_unique($allTranslations);
            if (!empty($allTranslations)) {
                $q->available_languages = json_encode($allTranslations, JSON_UNESCAPED_UNICODE);
            }

            $q->save();
        });

        // EXACT SAME return as working AdminCompetencyController
        return response()->json(['ok' => true]);
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
     * Get competency translations
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
     * Get competency question translations
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
     * Get available languages from config 
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
     * For global competencies, return all available languages
     */
    public function getSelectedLanguages(Request $request)
    {
        $availableLanguages = config('app.available_locales', []);
        $allLanguageKeys = array_keys($availableLanguages);
        
        return response()->json([
            'selected_languages' => $allLanguageKeys
        ]);
    }

    /**
     * NEW: Translate competency name using AI service
     */
    public function translateCompetencyName(Request $request)
    {
        $request->validate([
            'competency_name' => 'required|string',
            'source_language' => 'required|string',
            'target_languages' => 'required|array',
        ]);

        $aiTranslationService = new AiTranslationService();
        
        $translations = $aiTranslationService->translateCompetencyName(
            $request->competency_name,
            $request->source_language,
            $request->target_languages
        );

        if ($translations === null) {
            return response()->json([
                'success' => false,
                'message' => __('admin/competencies.translation-failed')
            ], 500);
        }

        return response()->json([
            'success' => true,
            'translations' => $translations
        ]);
    }

    /**
     * NEW: Translate competency question using AI service
     */
    public function translateCompetencyQuestion(Request $request)
    {
        $request->validate([
            'question_data' => 'required|array',
            'source_language' => 'required|string',
            'target_languages' => 'required|array',
        ]);

        $aiTranslationService = new AiTranslationService();
        
        $translations = $aiTranslationService->translateCompetencyQuestion(
            $request->question_data,
            $request->source_language,
            $request->target_languages
        );

        if ($translations === null) {
            return response()->json([
                'success' => false,
                'message' => __('admin/competencies.translation-failed')
            ], 500);
        }

        return response()->json([
            'success' => true,
            'translations' => $translations
        ]);
    }
}