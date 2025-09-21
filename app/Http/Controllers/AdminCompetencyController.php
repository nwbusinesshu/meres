<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use App\Services\CompetencyTranslationService;
use App\Services\LanguageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminCompetencyController extends Controller
{
    public function __construct()
    {
        if (AssessmentService::isAssessmentRunning()) {
            return abort(403);
        }
    }

    public function index(Request $request)
    {
        $orgId = session('org_id');

        $comps = Competency::whereNull('removed_at')
            ->where(function($q) use ($orgId) {
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $orgId);
            })
            ->with(['questions' => function($q) use ($orgId) {
                $q->whereNull('removed_at')
                  ->where(function($q2) use ($orgId) {
                      $q2->whereNull('organization_id')
                         ->orWhere('organization_id', $orgId);
                  });
            }])
            ->orderBy('name')
            ->get();

        return view('admin.competencies', ["competencies" => $comps]);
    }

    public function getAllCompetency(Request $request)
    {
        $orgId = session('org_id');

        return Competency::whereNull('removed_at')
            ->where(function($q) use ($orgId) {
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $orgId);
            })
            ->orderBy('name')
            ->get();
    }

    public function saveCompetency(Request $request)
    {
        $comp = Competency::find($request->id);
        $this->validate($request, ['name' => ['required']], [], ['name' => __('global.name')]);

        $orgId = session('org_id');

        AjaxService::DBTransaction(function() use ($request, &$comp, $orgId) {
            if (is_null($comp)) {
                $comp = Competency::create([
                    'name' => $request->name,
                    'organization_id' => $orgId,
                    'original_language' => LanguageService::getCurrentLocale(),
                    'available_languages' => [LanguageService::getCurrentLocale()],
                ]);
                
                // Also set the JSON version for consistency
                $comp->setTranslation(LanguageService::getCurrentLocale(), $request->name);
                $comp->save();
            } else {
                // Update the translation for current language
                $currentLocale = LanguageService::getCurrentLocale();
                $comp->setTranslation($currentLocale, $request->name);
                $comp->name = $request->name; // Keep backward compatibility
                $comp->save();
            }
        });

        return response()->json(['success' => true, 'id' => $comp->id]);
    }

    // NEW TRANSLATION ENDPOINTS

    /**
     * Get translations for a competency
     */
    public function getCompetencyTranslations(Request $request)
    {
        $competency = Competency::findOrFail($request->id);
        
        // Check permissions - can only edit org competencies
        $orgId = session('org_id');
        if ($competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit global competencies from admin panel');
        }

        $availableLanguages = LanguageService::getAvailableLanguages();
        $translations = [];
        
        foreach ($availableLanguages as $language) {
            $translations[$language] = [
                'name' => $competency->hasTranslation($language) ? $competency->getTranslatedName($language) : '',
                'exists' => $competency->hasTranslation($language),
                'is_original' => $language === $competency->original_language,
            ];
        }

        return response()->json([
            'translations' => $translations,
            'original_language' => $competency->original_language,
            'available_languages' => $competency->getAvailableLanguages(),
            'missing_languages' => $competency->getMissingLanguages(),
        ]);
    }

    /**
     * Save translations for a competency
     */
    public function saveCompetencyTranslations(Request $request)
    {
        $competency = Competency::findOrFail($request->id);
        
        // Check permissions
        $orgId = session('org_id');
        if ($competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit global competencies from admin panel');
        }

        $translations = $request->translations ?? [];
        
        // Validate translations
        foreach ($translations as $language => $translation) {
            if (!LanguageService::isValidLanguage($language)) {
                return response()->json(['error' => "Invalid language: {$language}"], 422);
            }
            
            if (empty($translation) && $language === $competency->original_language) {
                return response()->json(['error' => 'Original language translation cannot be empty'], 422);
            }
        }

        AjaxService::DBTransaction(function() use ($competency, $translations) {
            foreach ($translations as $language => $translation) {
                if (empty($translation)) {
                    $competency->removeTranslation($language);
                } else {
                    $competency->setTranslation($language, $translation);
                }
            }
            $competency->save();
        });

        return response()->json(['success' => true]);
    }

    /**
     * Translate competency using AI
     */
    public function translateCompetencyWithAI(Request $request)
    {
        $competency = Competency::findOrFail($request->id);
        
        // Check permissions
        $orgId = session('org_id');
        if ($competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit global competencies from admin panel');
        }

        $targetLanguages = $request->languages ?? [];
        
        // Validate target languages
        foreach ($targetLanguages as $language) {
            if (!LanguageService::isValidLanguage($language)) {
                return response()->json(['error' => "Invalid language: {$language}"], 422);
            }
        }

        if (empty($targetLanguages)) {
            return response()->json(['error' => 'No target languages specified'], 422);
        }

        // Call AI translation service
        $translations = CompetencyTranslationService::translateCompetencyName($competency, $targetLanguages);

        if ($translations === null) {
            return response()->json(['error' => 'AI translation failed. Please try again later.'], 500);
        }

        return response()->json([
            'success' => true,
            'translations' => $translations
        ]);
    }

    /**
     * Get translations for a competency question
     */
    public function getQuestionTranslations(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        
        // Check permissions through competency
        $orgId = session('org_id');
        if ($question->competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit questions for global competencies from admin panel');
        }

        $availableLanguages = LanguageService::getAvailableLanguages();
        $translations = [];
        
        foreach ($availableLanguages as $language) {
            $translations[$language] = [
                'question' => $question->hasTranslation($language) ? $question->getTranslatedQuestion($language) : '',
                'question_self' => $question->hasTranslation($language) ? $question->getTranslatedQuestionSelf($language) : '',
                'min_label' => $question->hasTranslation($language) ? $question->getTranslatedMinLabel($language) : '',
                'max_label' => $question->hasTranslation($language) ? $question->getTranslatedMaxLabel($language) : '',
                'exists' => $question->hasTranslation($language),
                'is_complete' => $question->isTranslationComplete($language),
                'is_partial' => $question->hasPartialTranslation($language),
                'missing_fields' => $question->getMissingFields($language),
                'is_original' => $language === $question->original_language,
            ];
        }

        return response()->json([
            'translations' => $translations,
            'original_language' => $question->original_language,
            'available_languages' => $question->getAvailableLanguages(),
            'missing_languages' => $question->getMissingLanguages(),
        ]);
    }

    /**
     * Save translations for a competency question
     */
    public function saveQuestionTranslations(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        
        // Check permissions
        $orgId = session('org_id');
        if ($question->competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit questions for global competencies from admin panel');
        }

        $translations = $request->translations ?? [];
        
        // Validate translations
        $errors = [];
        foreach ($translations as $language => $languageData) {
            if (!LanguageService::isValidLanguage($language)) {
                $errors[] = "Invalid language: {$language}";
                continue;
            }
            
            if (!is_array($languageData)) {
                $errors[] = "Invalid data format for language: {$language}";
                continue;
            }

            // Check for partial translations (some fields filled, others empty)
            $fields = ['question', 'question_self', 'min_label', 'max_label'];
            $filledFields = [];
            $emptyFields = [];
            
            foreach ($fields as $field) {
                if (isset($languageData[$field]) && !empty($languageData[$field])) {
                    $filledFields[] = $field;
                } else {
                    $emptyFields[] = $field;
                }
            }
            
            // If some fields are filled but not all, it's an error
            if (!empty($filledFields) && !empty($emptyFields)) {
                $errors[] = "Incomplete translation for {$language}: missing " . implode(', ', $emptyFields);
            }
            
            // Original language cannot be completely empty
            if ($language === $question->original_language && empty($filledFields)) {
                $errors[] = 'Original language translation cannot be completely empty';
            }
        }

        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        AjaxService::DBTransaction(function() use ($question, $translations) {
            foreach ($translations as $language => $languageData) {
                $fields = ['question', 'question_self', 'min_label', 'max_label'];
                $hasAnyContent = false;
                
                foreach ($fields as $field) {
                    if (isset($languageData[$field]) && !empty($languageData[$field])) {
                        $hasAnyContent = true;
                        break;
                    }
                }
                
                if (!$hasAnyContent) {
                    $question->removeTranslation($language);
                } else {
                    $question->setTranslation($language, $languageData);
                }
            }
            $question->save();
        });

        return response()->json(['success' => true]);
    }

    /**
     * Translate question using AI
     */
    public function translateQuestionWithAI(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        
        // Check permissions
        $orgId = session('org_id');
        if ($question->competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit questions for global competencies from admin panel');
        }

        $targetLanguages = $request->languages ?? [];
        
        // Validate target languages
        foreach ($targetLanguages as $language) {
            if (!LanguageService::isValidLanguage($language)) {
                return response()->json(['error' => "Invalid language: {$language}"], 422);
            }
        }

        if (empty($targetLanguages)) {
            return response()->json(['error' => 'No target languages specified'], 422);
        }

        // Call AI translation service
        $translations = CompetencyTranslationService::translateCompetencyQuestion($question, $targetLanguages);

        if ($translations === null) {
            return response()->json(['error' => 'AI translation failed. Please try again later.'], 500);
        }

        return response()->json([
            'success' => true,
            'translations' => $translations
        ]);
    }

    // EXISTING METHODS (updated to handle new structure)

    public function saveCompetencyQuestion(Request $request)
    {
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

        AjaxService::DBTransaction(function() use ($request, &$question) {
            $comp = Competency::findOrFail($request->compId);
            $currentLocale = LanguageService::getCurrentLocale();

            if (is_null($question)) {
                $question = $comp->questions()->create([
                    "question" => $request->question,
                    "question_self" => $request->questionSelf,
                    "min_label" => $request->minLabel,
                    "max_label" => $request->maxLabel,
                    "max_value" => $request->scale,
                    "organization_id" => $comp->organization_id,
                    "original_language" => $currentLocale,
                    "available_languages" => [$currentLocale],
                ]);
                
                // Set initial translation
                $question->setTranslation($currentLocale, [
                    'question' => $request->question,
                    'question_self' => $request->questionSelf,
                    'min_label' => $request->minLabel,
                    'max_label' => $request->maxLabel,
                ]);
                $question->save();
            } else {
                $question->question = $request->question;
                $question->question_self = $request->questionSelf;
                $question->min_label = $request->minLabel;
                $question->max_label = $request->maxLabel;
                $question->max_value = $request->scale;
                $question->organization_id = $comp->organization_id;
                $question->competency_id = $comp->id;
                
                // Update translation for current language
                $question->setTranslation($currentLocale, [
                    'question' => $request->question,
                    'question_self' => $request->questionSelf,
                    'min_label' => $request->minLabel,
                    'max_label' => $request->maxLabel,
                ]);
                $question->save();
            }
        });

        return response()->json(['success' => true, 'id' => $question->id]);
    }

    public function getCompetencyQuestion(Request $request)
    {
        return CompetencyQuestion::findOrFail($request->id);
    }

    public function removeCompetency(Request $request)
    {
        $comp = Competency::findOrFail($request->id);
        AjaxService::DBTransaction(function() use(&$comp) {
            $comp->users()->detach();
            $comp->removed_at = date('Y-m-d H:i:s');
            $comp->save();
        });
        
        return response()->json(['success' => true]);
    }

    public function removeCompetencyQuestion(Request $request)
    {
        $q = CompetencyQuestion::findOrFail($request->id);
        AjaxService::DBTransaction(function() use(&$q) {
            $q->removed_at = date('Y-m-d H:i:s');
            $q->save();
        });
        
        return response()->json(['success' => true]);
    }
}