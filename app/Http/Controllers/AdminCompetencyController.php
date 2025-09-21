<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\AjaxService;
use App\Services\CompetencyTranslationService;
use App\Services\LanguageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminCompetencyController extends Controller
{
    public function index(Request $request)
    {
        $orgId = session('org_id');
        
        // Organization-specific competencies
        $competencies = Competency::query()
            ->whereNull('removed_at')
            ->where('organization_id', $orgId)
            ->with(['questions' => function ($q) use ($orgId) {
                $q->whereNull('removed_at')->where('organization_id', $orgId);
            }])
            ->orderBy('name')
            ->get();

        // Global competencies (read-only)
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

        return view('admin.competencies', [
            'competencies' => $competencies,
            'globals' => $globals,
            'availableLanguages' => $availableLanguages,
            'languageNames' => $languageNames,
            'currentLocale' => $currentLocale,
        ]);
    }

    public function saveCompetency(Request $request)
    {
        $comp = Competency::find($request->id);
        $orgId = session('org_id');

        $request->validate(['name' => ['required', 'string', 'max:255']]);

        AjaxService::DBTransaction(function () use ($request, &$comp, $orgId) {
            if (is_null($comp)) {
                // NEW: Organization-specific
                $comp = Competency::create([
                    'name' => $request->name,
                    'organization_id' => $orgId,
                    'original_language' => LanguageService::getCurrentLocale(),
                    'available_languages' => [LanguageService::getCurrentLocale()],
                ]);
                
                // Set initial translation
                $comp->setTranslation(LanguageService::getCurrentLocale(), $request->name);
                $comp->save();
            } else {
                // Check permissions
                if ($comp->organization_id !== $orgId) {
                    abort(403);
                }
                
                // Update translation for current language
                $currentLocale = LanguageService::getCurrentLocale();
                $comp->setTranslation($currentLocale, $request->name);
                $comp->name = $request->name;
                $comp->save();
            }
        });

        return response()->json(['success' => true, 'id' => $comp->id]);
    }

    public function removeCompetency(Request $request)
    {
        $comp = Competency::findOrFail($request->id);
        $orgId = session('org_id');
        
        if ($comp->organization_id !== $orgId) {
            abort(403);
        }
        
        AjaxService::DBTransaction(function() use ($comp) {
            $comp->update(['removed_at' => now()]);
        });

        return response()->json(['ok' => true]);
    }

    public function getCompetencyQuestion(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        $orgId = session('org_id');
        
        if ($question->competency->organization_id !== $orgId) {
            abort(403);
        }
        
        return $question;
    }

    public function saveCompetencyQuestion(Request $request)
    {
        $comp = Competency::findOrFail($request->compId);
        $q = CompetencyQuestion::find($request->id);
        $orgId = session('org_id');
        
        if ($comp->organization_id !== $orgId) {
            abort(403);
        }
        
        $this->validate($request, [
            'question' => ['required'],
            'question_self' => ['required'],
            'min_label' => ['required'],
            'max_label' => ['required'],
            'scale' => ['required', 'numeric', 'min:3', 'max:10']
        ]);

        AjaxService::DBTransaction(function() use ($request, &$q, $comp, $orgId) {
            if (is_null($q)) {
                $q = CompetencyQuestion::create([
                    'competency_id' => $comp->id,
                    'organization_id' => $orgId,
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
                // Check permissions
                if ($q->competency->organization_id !== $orgId) {
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
        $orgId = session('org_id');
        
        if ($q->competency->organization_id !== $orgId) {
            abort(403);
        }
        
        AjaxService::DBTransaction(function() use ($q) {
            $q->update(['removed_at' => now()]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Get translations for a competency
     */
    public function getCompetencyTranslations(Request $request)
    {
        $competency = Competency::findOrFail($request->id);
        $orgId = session('org_id');
        
        // Check permissions - can only edit org competencies
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
        $orgId = session('org_id');
        
        // Check permissions
        if ($competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit global competencies from admin panel');
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
            Log::error('Failed to save competency translations: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Translate competency with AI
     */
    public function translateCompetencyWithAI(Request $request)
    {
        $competency = Competency::findOrFail($request->id);
        $orgId = session('org_id');
        
        // Check permissions
        if ($competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit global competencies from admin panel');
        }

        $targetLanguages = $request->languages ?? [];

        // Call AI translation service
        try {
            $translations = CompetencyTranslationService::translateCompetencyName($competency, $targetLanguages);
        } catch (\Exception $e) {
            Log::error('AI translation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
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
        $orgId = session('org_id');
        
        // Check permissions through competency
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
     * FIXED: Use setTranslation() method instead of setQuestionTranslation()
     */
    public function saveQuestionTranslations(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        $orgId = session('org_id');
        
        // Check permissions
        if ($question->competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit questions for global competencies from admin panel');
        }

        $translations = $request->translations ?? [];

        try {
            foreach ($translations as $language => $fields) {
                if (is_array($fields)) {
                    // FIXED: Use the correct method name setTranslation()
                    $question->setTranslation($language, $fields);
                }
            }
            
            $question->save();
        } catch (\Exception $e) {
            Log::error('Failed to save question translations: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Translate question with AI
     */
    public function translateQuestionWithAI(Request $request)
    {
        $question = CompetencyQuestion::findOrFail($request->id);
        $orgId = session('org_id');
        
        // Check permissions
        if ($question->competency->organization_id !== $orgId) {
            return abort(403, 'Cannot edit questions for global competencies from admin panel');
        }

        $targetLanguages = $request->languages ?? [];

        try {
            $translations = CompetencyTranslationService::translateCompetencyQuestion($question, $targetLanguages);
        } catch (\Exception $e) {
            Log::error('AI question translation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }

        return response()->json([
            'success' => true,
            'translations' => $translations
        ]);
    }
}