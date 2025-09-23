<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\CompetencyTranslationService;
use App\Services\LanguageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminCompetencyController extends Controller
{
    public function index()
    {
        $orgId = session('org_id');
        
        // Get competencies for the organization
        $competencies = Competency::where('organization_id', $orgId)
            ->whereNull('removed_at')
            ->with(['questions' => function($query) {
                $query->whereNull('removed_at');
            }])
            ->get();

        // Get global competencies (read-only for admin view)
        $globals = Competency::whereNull('organization_id')
            ->whereNull('removed_at')
            ->with(['questions' => function($query) {
                $query->whereNull('removed_at');
            }])
            ->get();

        // Get available languages and current locale
        $availableLanguages = LanguageService::getAvailableLanguages();
        $languageNames = LanguageService::getLanguageNames();
        $currentLocale = LanguageService::getCurrentLocale();
        
        // Get selected languages for this organization (from session or default)
        $selectedLanguages = session('admin.selected_languages', [$currentLocale]);
        
        // Ensure current locale is always included
        if (!in_array($currentLocale, $selectedLanguages)) {
            $selectedLanguages[] = $currentLocale;
            session(['admin.selected_languages' => $selectedLanguages]);
        }

        return view('admin.competencies', compact(
            'competencies', 
            'globals',
            'availableLanguages', 
            'languageNames', 
            'currentLocale',
            'selectedLanguages'
        ));
    }

    /**
     * Save language selection for the admin
     */
    public function saveLanguageSelection(Request $request)
    {
        $languages = $request->languages ?? [];
        $currentLocale = LanguageService::getCurrentLocale();
        
        // Ensure current locale is always included
        if (!in_array($currentLocale, $languages)) {
            $languages[] = $currentLocale;
        }
        
        // Validate that all languages exist in the system
        $availableLanguages = LanguageService::getAvailableLanguages();
        $languages = array_intersect($languages, $availableLanguages);
        
        // Save to session
        session(['admin.selected_languages' => $languages]);
        
        return response()->json(['success' => true]);
    }

    /**
     * Get translations for a competency
     * FIXED: Ensure proper data structure is returned
     */
    public function getCompetencyTranslations(Request $request)
    {
        try {
            $competency = Competency::findOrFail($request->id);
            $orgId = session('org_id');
            
            // Check permissions
            if ($competency->organization_id !== $orgId) {
                return response()->json(['error' => 'Cannot edit global competencies from admin panel'], 403);
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
                'original_language' => $competency->original_language ?? 'hu',
                'available_languages' => $competency->getAvailableLanguages(),
                'missing_languages' => $competency->getMissingLanguages(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get competency translations: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading translations'], 500);
        }
    }

    /**
     * Save competency (create or update)
     * FIXED: Added proper error handling
     */
    public function saveCompetency(Request $request)
    {
        try {
            $orgId = session('org_id');
            $currentLocale = LanguageService::getCurrentLocale();
            
            if ($request->id) {
                // Update existing competency
                $competency = Competency::where('id', $request->id)
                    ->where('organization_id', $orgId)
                    ->firstOrFail();
                
                // Update the translated name for current locale
                $competency->setTranslation($currentLocale, $request->name);
            } else {
                // Create new competency
                $competency = new Competency();
                $competency->organization_id = $orgId;
                $competency->original_language = $currentLocale;
                
                // Set the name in current locale
                $competency->setTranslation($currentLocale, $request->name);
                
                // Also set the legacy 'name' field for backwards compatibility
                $competency->name = $request->name;
            }
            
            $competency->save();
            
            return response()->json([
                'success' => true,
                'id' => $competency->id,
                'message' => 'Competency saved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to save competency: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }
    }

    /**
     * Remove competency
     */
    public function removeCompetency(Request $request)
    {
        $orgId = session('org_id');
        
        try {
            $competency = Competency::where('id', $request->id)
                ->where('organization_id', $orgId)
                ->firstOrFail();
            
            // Soft delete - set removed_at timestamp
            $competency->removed_at = now();
            $competency->save();
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            Log::error('Failed to remove competency: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }
    }

    /**
     * Get competency question
     * FIXED: Added proper error handling and data structure
     */
    public function getCompetencyQuestion(Request $request)
    {
        try {
            $orgId = session('org_id');
            
            $question = CompetencyQuestion::with('competency')
                ->where('id', $request->id)
                ->whereNull('removed_at')
                ->firstOrFail();
            
            // Check permissions
            if ($question->competency->organization_id !== $orgId) {
                return response()->json(['error' => 'Unauthorized access to question'], 403);
            }
            
            $currentLocale = LanguageService::getCurrentLocale();
            
            // Return the question data with safe structure
            return response()->json([
                'success' => true,
                'id' => $question->id,
                'competency_id' => $question->competency_id,
                'question' => $question->getTranslatedQuestion($currentLocale),
                'question_self' => $question->getTranslatedQuestionSelf($currentLocale),
                'min_label' => $question->getTranslatedMinLabel($currentLocale),
                'max_label' => $question->getTranslatedMaxLabel($currentLocale),
                'max_value' => $question->max_value,
                'original_language' => $question->original_language ?? 'hu',
                'available_languages' => $question->getAvailableLanguages(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get question: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading question'], 500);
        }
    }

    /**
     * Save competency question
     */
    public function saveCompetencyQuestion(Request $request)
    {
        try {
            $orgId = session('org_id');
            $currentLocale = LanguageService::getCurrentLocale();
            
            if ($request->id) {
                // Update existing question
                $question = CompetencyQuestion::with('competency')
                    ->where('id', $request->id)
                    ->whereNull('removed_at')
                    ->firstOrFail();
                
                // Check permissions
                if ($question->competency->organization_id !== $orgId) {
                    return response()->json(['error' => 'Unauthorized access to question'], 403);
                }
            } else {
                // Create new question
                $competency = Competency::where('id', $request->competency_id)
                    ->where('organization_id', $orgId)
                    ->firstOrFail();
                
                $question = new CompetencyQuestion();
                $question->competency_id = $competency->id;
                $question->organization_id = $orgId;
                $question->original_language = $currentLocale;
                $question->max_value = $request->max_value;
                
                // Set translations for current locale
                $question->setTranslation($currentLocale, [
                    'question' => $request->question,
                    'question_self' => $request->question_self,
                    'min_label' => $request->min_label,
                    'max_label' => $request->max_label,
                    'max_value' => $request->max_value,
                ]);
                
                // Also set legacy fields for backwards compatibility
                $question->question = $request->question;
                $question->question_self = $request->question_self;
                $question->min_label = $request->min_label;
                $question->max_label = $request->max_label;
            }
            
            $question->save();
            
            return response()->json([
                'success' => true,
                'id' => $question->id,
                'message' => 'Question saved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to save question: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }
    }

    /**
     * Remove competency question
     */
    public function removeCompetencyQuestion(Request $request)
    {
        $orgId = session('org_id');
        
        try {
            $question = CompetencyQuestion::with('competency')
                ->where('id', $request->id)
                ->whereNull('removed_at')
                ->firstOrFail();
            
            // Check permissions
            if ($question->competency->organization_id !== $orgId) {
                return response()->json(['error' => 'Unauthorized access to question'], 403);
            }
            
            // Soft delete
            $question->removed_at = now();
            $question->save();
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            Log::error('Failed to remove question: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }
    }

    /**
     * Save translations for a competency
     */
    public function saveCompetencyTranslations(Request $request)
    {
        try {
            $competency = Competency::findOrFail($request->id);
            $orgId = session('org_id');
            
            // Check permissions
            if ($competency->organization_id !== $orgId) {
                return response()->json(['error' => 'Cannot edit global competencies from admin panel'], 403);
            }

            $translations = $request->translations ?? [];

            foreach ($translations as $language => $name) {
                if (!empty(trim($name))) {
                    $competency->setTranslation($language, trim($name));
                } else {
                    $competency->removeTranslation($language);
                }
            }
            
            $competency->save();
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to save competency translations: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }
    }

    /**
     * Translate competency with AI
     */
    public function translateCompetencyWithAI(Request $request)
    {
        try {
            $competency = Competency::findOrFail($request->id);
            $orgId = session('org_id');
            
            // Check permissions
            if ($competency->organization_id !== $orgId) {
                return response()->json(['error' => 'Cannot edit global competencies from admin panel'], 403);
            }

            $targetLanguages = $request->languages ?? [];

            // Call AI translation service
            $translations = CompetencyTranslationService::translateCompetencyName($competency, $targetLanguages);
            
            return response()->json([
                'success' => true,
                'translations' => $translations
            ]);
        } catch (\Exception $e) {
            Log::error('AI translation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }
    }

    /**
     * Get translations for a competency question
     */
    public function getQuestionTranslations(Request $request)
    {
        try {
            $question = CompetencyQuestion::findOrFail($request->id);
            $orgId = session('org_id');
            
            // Check permissions through competency
            if ($question->competency->organization_id !== $orgId) {
                return response()->json(['error' => 'Cannot edit questions for global competencies from admin panel'], 403);
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
                'original_language' => $question->original_language ?? 'hu',
                'available_languages' => $question->getAvailableLanguages(),
                'missing_languages' => $question->getMissingLanguages(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get question translations: ' . $e->getMessage());
            return response()->json(['error' => 'Error loading question translations'], 500);
        }
    }

    /**
     * Save translations for a competency question
     */
    public function saveQuestionTranslations(Request $request)
    {
        try {
            $question = CompetencyQuestion::findOrFail($request->id);
            $orgId = session('org_id');
            
            // Check permissions
            if ($question->competency->organization_id !== $orgId) {
                return response()->json(['error' => 'Cannot edit questions for global competencies from admin panel'], 403);
            }

            $translations = $request->translations ?? [];

            foreach ($translations as $language => $fields) {
                if (is_array($fields)) {
                    $question->setTranslation($language, $fields);
                }
            }
            
            $question->save();
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to save question translations: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }
    }

    /**
     * Translate question with AI
     */
    public function translateQuestionWithAI(Request $request)
    {
        try {
            $question = CompetencyQuestion::findOrFail($request->id);
            $orgId = session('org_id');
            
            // Check permissions
            if ($question->competency->organization_id !== $orgId) {
                return response()->json(['error' => 'Cannot edit questions for global competencies from admin panel'], 403);
            }

            $targetLanguages = $request->languages ?? [];

            // Call AI translation service
            $translations = CompetencyTranslationService::translateQuestionFields($question, $targetLanguages);
            
            return response()->json([
                'success' => true,
                'translations' => $translations
            ]);
        } catch (\Exception $e) {
            Log::error('AI translation failed for question: ' . $e->getMessage());
            return response()->json(['error' => 'Please try again later.'], 500);
        }
    }
}