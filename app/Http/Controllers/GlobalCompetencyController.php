<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\AjaxService;
use App\Services\CompetencyTranslationService;
use App\Services\LanguageService;
use Illuminate\Http\Request;

class GlobalCompetencyController extends Controller
{
    public function index(Request $request)
    {
        // Only global competencies and their global questions
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
                // NEW: GLOBAL
                $comp = Competency::create([
                    'name' => $request->name,
                    'organization_id' => null,
                    'original_language' => LanguageService::getCurrentLocale(),
                    'available_languages' => [LanguageService::getCurrentLocale()],
                ]);
                
                // Set initial translation
                $comp->setTranslation(LanguageService::getCurrentLocale(), $request->name);
                $comp->save();
            } else {
                if (!is_null($comp->organization_id)) {
                    abort(403);
                }
                
                // Update translation for current language
                $currentLocale = LanguageService::getCurrentLocale();
                $comp->setTranslation($currentLocale, $request->name);
                $comp->name = $request->name;
                $comp->save();
            }
        });

        return response()->json(['ok' => true, 'id' => $comp->id ?? null]);
    }

    // NEW TRANSLATION ENDPOINTS FOR GLOBAL COMPETENCIES

    /**
     * Get translations for a global competency
     */
    public function getCompetencyTranslations(Request $request)
    {
        $competency = Competency::findOrFail($request->id);
        
        // Check that it's a global competency
        if ($competency->organization_id !== null) {
            return abort(403, 'Cannot edit org-specific competencies from superadmin panel');
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
     * Save translations for a global competency
     */
    public function saveCompetencyTranslations(Request $request)
    {
        $competency = Competency::findOrFail($request->id);
        
        // Check that it's a global competency
        if ($competency->organization_id !== null) {
            return abort(403, 'Cannot edit org-specific competencies from superadmin panel');
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
     * Translate global competency using AI
     */
    public function translateCompetencyWithAI(Request $request)
    {
        $competency = Competency::findOrFail($request->id);
        
        // Check that it's a global competency
        if ($competency->organization_id !== null) {
            return abort(403, 'Cannot edit org-specific competencies from superadmin panel');
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
     * Get translations for a global competency question
     */
    public function getQuestionTranslations(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        
        // Check that it belongs to a global competency
        if ($question->competency->organization_id !== null || $question->organization_id !== null) {
            return abort(403, 'Cannot edit questions for org-specific competencies from superadmin panel');
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
     * Save translations for a global competency question
     */
    public function saveQuestionTranslations(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        
        // Check that it belongs to a global competency
        if ($question->competency->organization_id !== null || $question->organization_id !== null) {
            return abort(403, 'Cannot edit questions for org-specific competencies from superadmin panel');
        }

        $translations = $request->translations ?? [];
        
        // Validate translations (same logic as admin controller)
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

            // Check for partial translations
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
            
            if (!empty($filledFields) && !empty($emptyFields)) {
                $errors[] = "Incomplete translation for {$language}: missing " . implode(', ', $emptyFields);
            }
            
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
     * Translate global question using AI
     */
    public function translateQuestionWithAI(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        
        // Check that it belongs to a global competency
        if ($question->competency->organization_id !== null || $question->organization_id !== null) {
            return abort(403, 'Cannot edit questions for org-specific competencies from superadmin panel');
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

    public function removeCompetency(Request $request)
    {
        $comp = Competency::findOrFail($request->id);

        if (!is_null($comp->organization_id)) {
            abort(403);
        }

        AjaxService::DBTransaction(function () use (&$comp) {
            $comp->users()->detach();
            $comp->removed_at = date('Y-m-d H:i:s');
            $comp->save();
        });

        return response()->json(['ok' => true]);
    }

    public function getCompetencyQuestion(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);

        if (!is_null($question->organization_id)) {
            abort(403);
        }

        return $question;
    }

    public function saveCompetencyQuestion(Request $request)
    {
        $question = CompetencyQuestion::find($request->id);

        $request->validate([
            'question' => ['required', 'string', 'max:1024'],
            'questionSelf' => ['required', 'string', 'max:1024'],
            'minLabel' => ['required', 'string', 'max:255'],
            'maxLabel' => ['required', 'string', 'max:255'],
            'scale' => ['required', 'numeric', 'min:3', 'max:10'],
            'compId' => ['required', 'exists:competency,id'],
        ]);

        AjaxService::DBTransaction(function () use ($request, &$question) {
            $comp = Competency::findOrFail($request->compId);

            if (!is_null($comp->organization_id)) {
                abort(403);
            }

            $currentLocale = LanguageService::getCurrentLocale();

            if (is_null($question)) {
                $question = $comp->questions()->create([
                    'question' => $request->question,
                    'question_self' => $request->questionSelf,
                    'min_label' => $request->minLabel,
                    'max_label' => $request->maxLabel,
                    'max_value' => $request->scale,
                    'organization_id' => null,
                    'original_language' => $currentLocale,
                    'available_languages' => [$currentLocale],
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
                if (!is_null($question->organization_id)) {
                    abort(403);
                }

                $question->question = $request->question;
                $question->question_self = $request->questionSelf;
                $question->min_label = $request->minLabel;
                $question->max_label = $request->maxLabel;
                $question->max_value = $request->scale;
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

        return response()->json(['ok' => true, 'id' => $question->id ?? null]);
    }

    public function removeCompetencyQuestion(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);

        if (!is_null($question->organization_id)) {
            abort(403);
        }

        AjaxService::DBTransaction(function () use (&$question) {
            $question->removed_at = date('Y-m-d H:i:s');
            $question->save();
        });

        return response()->json(['ok' => true]);
    }
}