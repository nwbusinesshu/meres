<?php

namespace App\Http\Controllers;

use App\Models\CeoRank;
use App\Services\AjaxService;
use App\Services\AiTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminCeoRanksController extends Controller
{
    /**
     * Display default CEO ranks (organization_id IS NULL)
     */
    public function index(Request $request)
    {
        // Get current locale
        $currentLocale = app()->getLocale();
        
        // Get default CEO ranks (where organization_id is NULL)
        $ceoranks = CeoRank::whereNull('organization_id')
                ->whereNull('removed_at')
                ->orderByDesc('value')
                ->get();

        // Process translations for each rank
        foreach ($ceoranks as $rank) {
            $translatedData = $this->getTranslatedName($rank, $currentLocale);
            $rank->translated_name = $translatedData['text'];
            $rank->name_is_fallback = $translatedData['is_fallback'];
        }

        return view('superadmin.ceorank-defaults', [
            "ceoranks" => $ceoranks,
        ]);
    }

    /**
     * Get a specific default CEO rank
     */
    public function getCeoRank(Request $request)
    {
        return CeoRank::where('id', $request->id)
              ->whereNull('organization_id')
              ->firstOrFail();
    }

    /**
     * Save (create or update) a default CEO rank
     */
    public function saveCeoRank(Request $request)
    {
        $rank = CeoRank::find($request->id);

        $rules = [
            "name" => ['required'],
            "value" => ['required', 'numeric', 'min:0', 'max:100'],
            "min" => ['required', 'numeric', 'min:0', 'max:100'],
            "max" => ['required', 'numeric', 'min:0', 'max:100'],
        ];

        $attributes = [
            "name" => __('admin/ceoranks.name'),
            "value" => __('admin/ceoranks.value'),
            "min" => __('admin/ceoranks.min'),
            "max" => __('admin/ceoranks.max'),
        ];
    
        $this->validate($request, $rules, [], $attributes); 

        AjaxService::DBTransaction(function() use ($request, &$rank){
            if(is_null($rank)){
                // Create new default rank
                $ceoRank = CeoRank::create([
                    "organization_id" => null, // NULL for defaults
                    "name" => $request->name,
                    "value" => $request->value,
                    "min" => $request->min == 0 ? null : $request->min,
                    "max" => $request->max == 0 ? null : $request->max,
                    "original_language" => auth()->user()->locale ?? config('app.locale', 'hu'),
                ]);
                
                // Handle translations for new record
                $this->handleTranslations($ceoRank, $request);
            } else {
                // Update existing default rank
                $rank->name = $request->name;
                $rank->value = $request->value;
                $rank->min = $request->min == 0 ? null : $request->min;
                $rank->max = $request->max == 0 ? null : $request->max;
                
                // Handle translations for existing record
                $this->handleTranslations($rank, $request);
                
                $rank->save();
            }
        });
    }

    /**
     * Remove (soft delete) a default CEO rank
     */
    public function removeCeoRank(Request $request)
    {
        $rank = CeoRank::where('id', $request->id)
              ->whereNull('organization_id')
              ->firstOrFail();
              
        AjaxService::DBTransaction(function() use(&$rank) {
            $rank->removed_at = date('Y-m-d H:i:s');
            $rank->save();
        });
    }

    /**
     * Get CEO rank translations for editing
     */
    public function getCeoRankTranslations(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);

        $rank = CeoRank::where('id', $request->id)
                      ->whereNull('organization_id')
                      ->firstOrFail();

        return response()->json([
            'id' => $rank->id,
            'name' => $rank->name,
            'name_json' => $rank->name_json ? json_decode($rank->name_json, true) : null,
            'original_language' => $rank->original_language ?? 'hu',
        ]);
    }

    /**
     * AI translate CEO rank name
     */
    public function translateCeoRankName(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'target_language' => 'required|string|size:2',
        ]);

        $rank = CeoRank::where('id', $request->id)
                      ->whereNull('organization_id')
                      ->firstOrFail();

        $sourceLang = $rank->original_language ?? 'hu';
        $targetLang = $request->target_language;
        $sourceText = $rank->name;

        // Don't translate if source and target are the same
        if ($sourceLang === $targetLang) {
            return response()->json([
                'translation' => $sourceText,
                'source_language' => $sourceLang,
                'target_language' => $targetLang,
            ]);
        }

        try {
            $translation = AiTranslationService::translate(
                $sourceText,
                $sourceLang,
                $targetLang,
                'ceo_rank_name'
            );

            return response()->json([
                'translation' => $translation,
                'source_language' => $sourceLang,
                'target_language' => $targetLang,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('admin/competencies.translation-failed'),
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available languages - for global content, return ALL available languages
     */
    public function getTranslationLanguages(Request $request)
    {
        $availableLanguages = config('app.available_locales', [
            'hu' => 'Magyar',
            'en' => 'English',
            'de' => 'Deutsch',
            'ro' => 'Română',
        ]);

        // For superadmin/global content, always use ALL available languages
        $allLanguageKeys = array_keys($availableLanguages);

        return response()->json([
            'available' => $availableLanguages,
            'selected' => $allLanguageKeys, // All languages for global content
        ]);
    }

    /**
     * Handle name translations
     */
    private function handleTranslations($rank, $request)
    {
        // Handle name translations
        if ($request->has('translations') && is_array($request->translations)) {
            $translations = array_filter($request->translations, function($value) {
                return !empty(trim($value));
            });
            
            if (!empty($translations)) {
                $rank->name_json = json_encode($translations, JSON_UNESCAPED_UNICODE);
            }
        } else {
            // Store original name in JSON format
            $originalLang = $rank->original_language ?? config('app.locale', 'hu');
            $rank->name_json = json_encode([
                $originalLang => $rank->name
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Get translated name with fallback logic
     */
    private function getTranslatedName($rank, $currentLocale)
    {
        // If no translations or we're in the original language, return original text
        if (empty($rank->name_json) || $currentLocale === ($rank->original_language ?? 'hu')) {
            return [
                'text' => $rank->name,
                'is_fallback' => false,
            ];
        }

        $translations = json_decode($rank->name_json, true);
        
        // Check if translation exists for current locale
        if (isset($translations[$currentLocale]) && !empty(trim($translations[$currentLocale]))) {
            return [
                'text' => $translations[$currentLocale],
                'is_fallback' => false,
            ];
        }

        // Fallback to original name
        return [
            'text' => $rank->name,
            'is_fallback' => true,
        ];
    }

    public function getAvailableLanguages(Request $request)
{
    $availableLocales = config('app.available_locales', []);
    $userDefaultLanguage = auth()->user()->locale ?? config('app.locale', 'hu');

    return response()->json([
        'available_locales' => $availableLocales,
        'user_default_language' => $userDefaultLanguage
    ]);
}

    public function getSelectedLanguages(Request $request)
{
    // For superadmin/global content, return ALL languages
    $availableLanguages = config('app.available_locales', []);
    $allLanguageKeys = array_keys($availableLanguages);
    
    return response()->json([
        'selected_languages' => $allLanguageKeys
    ]);
}
}