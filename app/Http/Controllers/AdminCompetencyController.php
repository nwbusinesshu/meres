<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use App\Services\OrgConfigService;
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

        // Get organization's selected translation languages
        $selectedLanguages = OrgConfigService::getJson($orgId, 'translation_languages', [auth()->user()->locale ?? config('app.locale', 'hu')]);

        return view('admin.competencies', [
            "competencies" => $comps,
            "selectedLanguages" => $selectedLanguages
        ]);
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
            if(!$comp){
                $comp = new Competency();
                $comp->organization_id = $orgId;
                $comp->original_language = $request->original_language ?? auth()->user()->locale ?? config('app.locale', 'hu');
            } else {
                // global competenciát nem editálhat a client
                if(is_null($comp->organization_id)){
                    AjaxService::error(__('admin/competencies.cannot-modify-global'));
                }
            }

            $comp->name = $request->name;

            // Handle translations
            if ($request->has('translations') && is_array($request->translations)) {
                $translations = $request->translations;
                
                // Remove empty translations
                $translations = array_filter($translations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($translations)) {
                    $comp->name_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
                    $comp->available_languages = json_encode(array_keys($translations), JSON_UNESCAPED_UNICODE);
                }
            } else {
                // If no translations provided, store original name in JSON format
                $originalLang = $comp->original_language;
                $comp->name_json = json_encode([$originalLang => $comp->name], JSON_UNESCAPED_UNICODE);
                $comp->available_languages = json_encode([$originalLang], JSON_UNESCAPED_UNICODE);
            }

            $comp->save();
        });

        return response()->json(['ok' => true]);
    }

    public function removeCompetency(Request $request){
        $comp = Competency::find($request->id);

        if(!$comp) AjaxService::error(__('admin/competencies.competency-not-found'));

        if(is_null($comp->organization_id)){
            AjaxService::error(__('admin/competencies.cannot-modify-global'));
        }

        AjaxService::DBTransaction(function() use (&$comp) {
            $comp->removed_at = now();
            $comp->save();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Get competency translations
     */
    public function getCompetencyTranslations(Request $request)
    {
        $comp = Competency::find($request->id);
        
        if (!$comp) {
            return response()->json(['error' => __('admin/competencies.competency-not-found')], 404);
        }

        return response()->json([
            'id' => $comp->id,
            'name' => $comp->name,
            'name_json' => $comp->name_json ? json_decode($comp->name_json, true) : null,
            'original_language' => $comp->original_language ?? 'hu',
            'available_languages' => $comp->available_languages ? json_decode($comp->available_languages, true) : null
        ]);
    }

    /**
     * UPDATED: Save competency question with translation support
     */
    public function saveCompetencyQuestion(Request $request){
        $comp = Competency::find($request->compId);
        if(!$comp) AjaxService::error(__('admin/competencies.competency-not-found'));

        if(is_null($comp->organization_id)){
            AjaxService::error(__('admin/competencies.cannot-modify-global'));
        }

        $q = CompetencyQuestion::find($request->id);

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

        AjaxService::DBTransaction(function() use ($request, &$q){
            if(!$q){
                $q = new CompetencyQuestion();
                $q->competency_id = $request->compId;
                $q->organization_id = session('org_id');
                $q->original_language = auth()->user()->locale ?? config('app.locale', 'hu');
            }

            // Set the main values
            $q->question = $request->question;
            $q->question_self = $request->questionSelf;
            $q->min_label = $request->minLabel;
            $q->max_label = $request->maxLabel;
            $q->max_value = $request->scale;

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

            // Set available languages
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

        return response()->json(['ok' => true]);
    }

    public function getCompetencyQuestion(Request $request){
        $q = CompetencyQuestion::find($request->id);
        if(!$q) AjaxService::error(__('admin/competencies.question-not-found'));

        return $q;
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

    public function removeCompetencyQuestion(Request $request){
        $q = CompetencyQuestion::find($request->id);
        if(!$q) AjaxService::error(__('admin/competencies.question-not-found'));

        if(is_null($q->organization_id)){
            AjaxService::error(__('admin/competencies.cannot-modify-global'));
        }

        AjaxService::DBTransaction(function() use (&$q) {
            $q->removed_at = now();
            $q->save();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Get available languages from config and user's default language
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
     * Get currently selected translation languages for the organization
     */
    public function getSelectedLanguages(Request $request)
    {
        $orgId = session('org_id');
        $userDefaultLanguage = auth()->user()->locale ?? config('app.locale', 'hu');
        
        // Get selected languages from organization config
        $translationLanguages = OrgConfigService::getJson($orgId, 'translation_languages', [$userDefaultLanguage]);
        
        // Ensure user's default language is always included
        if (!in_array($userDefaultLanguage, $translationLanguages)) {
            $translationLanguages[] = $userDefaultLanguage;
        }

        return response()->json([
            'selected_languages' => array_unique($translationLanguages)
        ]);
    }

    /**
     * Save selected translation languages for the organization
     */
    public function saveTranslationLanguages(Request $request)
    {
        $request->validate([
            'languages' => 'required|array|min:1',
            'languages.*' => 'required|string|in:' . implode(',', array_keys(config('app.available_locales', [])))
        ]);

        $orgId = session('org_id');
        $userDefaultLanguage = auth()->user()->locale ?? config('app.locale', 'hu');
        $languages = $request->languages;

        // Ensure user's default language is always included
        if (!in_array($userDefaultLanguage, $languages)) {
            $languages[] = $userDefaultLanguage;
        }

        // Remove duplicates and save to organization config
        $languages = array_unique($languages);
        
        OrgConfigService::setJson($orgId, 'translation_languages', $languages);

        return response()->json([
            'success' => true,
            'message' => __('admin/competencies.languages-saved-successfully'),
            'selected_languages' => $languages
        ]);
    }
}