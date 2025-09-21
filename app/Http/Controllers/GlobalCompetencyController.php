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

        // Get language context for the view
        $availableLanguages = LanguageService::getAvailableLanguages();
        $languageNames = LanguageService::getLanguageNames();
        $currentLocale = LanguageService::getCurrentLocale();

        return view('superadmin.global-competencies', [
            'globals' => $globals,
            'availableLanguages' => $availableLanguages,
            'languageNames' => $languageNames,
            'currentLocale' => $currentLocale,
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

    public function removeCompetency(Request $request)
    {
        $comp = Competency::findOrFail($request->id);
        
        if (!is_null($comp->organization_id)) {
            abort(403);
        }
        
        AjaxService::DBTransaction(function() use ($comp) {
            $comp->update(['removed_at' => now()]);
        });

        return response()->json(['ok' => true]);
    }

    public function getCompetencyQuestion(Request $request)
    {
        return CompetencyQuestion::findOrFail($request->id);
    }

    public function saveCompetencyQuestion(Request $request)
    {
        $comp = Competency::findOrFail($request->competency_id);
        $q = CompetencyQuestion::find($request->id);
        
        if (!is_null($comp->organization_id)) {
            abort(403);
        }
        
        $this->validate($request, [
            'question' => ['required'],
            'question_self' => ['required'],
            'min_label' => ['required'],
            'max_label' => ['required'],
            'scale' => ['required', 'numeric', 'min:3', 'max:10']
        ]);

        AjaxService::DBTransaction(function() use ($request, &$q, $comp) {
            if (is_null($q)) {
                $q = CompetencyQuestion::create([
                    'competency_id' => $comp->id,
                    'organization_id' => null, // Global question
                    'question' => $request->question,
                    'question_self' => $request->question_self,
                    'min_label' => $request->min_label,
                    'max_label' => $request->max_label,
                    'max_value' => $request->scale,
                    'original_language' => LanguageService::getCurrentLocale(),
                    'available_languages' => [LanguageService::getCurrentLocale()],
                ]);
                
                // Set initial translations
                $q->setTranslation(LanguageService::getCurrentLocale(), [
                    'question' => $request->question,
                    'question_self' => $request->question_self,
                    'min_label' => $request->min_label,
                    'max_label' => $request->max_label,
                ]);
                $q->save();
            } else {
                if (!is_null($q->organization_id)) {
                    abort(403);
                }
                
                // Update translation for current language
                $currentLocale = LanguageService::getCurrentLocale();
                $q->setTranslation($currentLocale, [
                    'question' => $request->question,
                    'question_self' => $request->question_self,
                    'min_label' => $request->min_label,
                    'max_label' => $request->max_label,
                ]);
                
                $q->question = $request->question;
                $q->question_self = $request->question_self;
                $q->min_label = $request->min_label;
                $q->max_label = $request->max_label;
                $q->max_value = $request->scale;
                $q->save();
            }
        });

        return response()->json(['ok' => true, 'id' => $q->id ?? null]);
    }

    public function removeCompetencyQuestion(Request $request)
    {
        $q = CompetencyQuestion::findOrFail($request->id);
        
        if (!is_null($q->organization_id)) {
            abort(403);
        }
        
        AjaxService::DBTransaction(function() use ($q) {
            $q->update(['removed_at' => now()]);
        });

        return response()->json(['ok' => true]);
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

        try {
            foreach ($translations as $language => $name) {
                if (!empty(trim($name))) {
                    $competency->setTranslation($language, trim($name));
                } else {
                    $competency->removeTranslation($language);
                }
            }
            
            $competency->save();
        } catch (\Exception $e) {
            \Log::error('Failed to save global competency translations: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Translate global competency with AI
     */
    public function translateCompetencyWithAI(Request $request)
    {
        $competency = Competency::findOrFail($request->id);
        
        // Check that it's a global competency
        if ($competency->organization_id !== null) {
            return abort(403, 'Cannot edit org-specific competencies from superadmin panel');
        }

        $targetLanguages = $request->languages ?? [];

        // Call AI translation service
        try {
            $translations = CompetencyTranslationService::translateCompetencyName($competency, $targetLanguages);
        } catch (\Exception $e) {
            \Log::error('AI translation failed for global competency: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
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

        try {
            foreach ($translations as $language => $fields) {
                if (is_array($fields)) {
                    $question->setTranslation($language, $fields);
                }
            }
            
            $question->save();
        } catch (\Exception $e) {
            \Log::error('Failed to save global question translations: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Translate global question with AI
     */
    public function translateQuestionWithAI(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        
        // Check that it belongs to a global competency
        if ($question->competency->organization_id !== null || $question->organization_id !== null) {
            return abort(403, 'Cannot edit questions for org-specific competencies from superadmin panel');
        }

        $targetLanguages = $request->languages ?? [];

        try {
            $translations = CompetencyTranslationService::translateCompetencyQuestion($question, $targetLanguages);
        } catch (\Exception $e) {
            \Log::error('AI question translation failed for global competency: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }

        return response()->json([
            'success' => true,
            'translations' => $translations
        ]);
    }
}