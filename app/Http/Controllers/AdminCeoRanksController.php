<?php

namespace App\Http\Controllers;

use App\Models\CeoRank;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use App\Services\OrgConfigService;
use App\Services\AiTranslationService;
use Illuminate\Http\Request;

class AdminCeoRanksController extends Controller
{
    public function __construct(){
        if(AssessmentService::isAssessmentRunning()){
            return abort(403);
        }
    }

    public function index(Request $request){
        $organizationId = session('org_id');

        // Get current locale
        $currentLocale = app()->getLocale();
        
        // Get CEO ranks with translated names
        $ceoranks = CeoRank::where('organization_id', $organizationId)
                ->whereNull('removed_at')
                ->orderByDesc('value')
                ->get();

        // Process translations for each rank
        foreach ($ceoranks as $rank) {
            $translatedData = $this->getTranslatedName($rank, $currentLocale);
            $rank->translated_name = $translatedData['text'];
            $rank->name_is_fallback = $translatedData['is_fallback'];
        }

        return view('admin.ceoranks',[
            "ceoranks" => $ceoranks,
        ]);
    }

    public function getCeoRank(Request $request){
        $organizationId = session('org_id');
        return CeoRank::where('id', $request->id)
              ->where('organization_id', $organizationId)
              ->firstOrFail();
    }

    public function saveCeoRank(Request $request){
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
                $ceoRank = CeoRank::create([
                    "organization_id" => session('org_id'),
                    "name" => $request->name,
                    "value" => $request->value,
                    "min" => $request->min == 0 ? null : $request->min,
                    "max" => $request->max == 0 ? null : $request->max,
                    "original_language" => auth()->user()->locale ?? config('app.locale', 'hu'),
                ]);
                
                // Handle translations for new record
                $this->handleTranslations($ceoRank, $request);
            }else{
                $organizationId = session('org_id');
                $rank = CeoRank::where('id', $request->id)->where('organization_id', $organizationId)->first();
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

    public function removeCeoRank(Request $request){
        $organizationId = session('org_id');
        $rank = CeoRank::where('id', $request->id)
              ->where('organization_id', $organizationId)
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

        $organizationId = session('org_id');
        $rank = CeoRank::where('id', $request->id)
                      ->where('organization_id', $organizationId)
                      ->firstOrFail();

        return response()->json([
            'id' => $rank->id,
            'name' => $rank->name,
            'name_json' => $rank->name_json ? json_decode($rank->name_json, true) : null,
            'original_language' => $rank->original_language ?? 'hu',
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
            $originalLang = $rank->original_language ?? auth()->user()->locale ?? config('app.locale', 'hu');
            $rank->name_json = json_encode([$originalLang => $rank->name], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Get translated name with fallback logic
     */
    private function getTranslatedName($rank, $currentLocale)
    {
        // If no translations or we're in the original language, return original text
        if (empty($rank->name_json) || $currentLocale === $rank->original_language) {
            return ['text' => $rank->name, 'is_fallback' => false];
        }
        
        $translations = json_decode($rank->name_json, true);
        if (!$translations || !is_array($translations)) {
            return ['text' => $rank->name, 'is_fallback' => true];
        }
        
        // Check if translation exists for current locale
        if (isset($translations[$currentLocale]) && !empty(trim($translations[$currentLocale]))) {
            return ['text' => $translations[$currentLocale], 'is_fallback' => false];
        }
        
        // Fallback to original text
        return ['text' => $rank->name, 'is_fallback' => true];
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
     * Get organization's selected translation languages
     */
    public function getSelectedLanguages(Request $request)
    {
        $orgId = session('org_id');
        $selectedLanguages = OrgConfigService::getJson($orgId, 'translation_languages', [auth()->user()->locale ?? config('app.locale', 'hu')]);
        
        return response()->json([
            'selected_languages' => $selectedLanguages
        ]);
    }

    /**
     * Save translation languages for organization
     */
    public function saveTranslationLanguages(Request $request)
    {
        $request->validate([
            'languages' => 'required|array|min:1',
            'languages.*' => 'required|string|max:5'
        ]);

        $orgId = session('org_id');
        
        OrgConfigService::setJson($orgId, 'translation_languages', $request->languages);

        return response()->json(['ok' => true]);
    }

    /**
     * Translate CEO rank name using AI service
     */
    public function translateCeoRankName(Request $request)
    {
        $request->validate([
            'rank_name' => 'required|string',
            'source_language' => 'required|string',
            'target_languages' => 'required|array',
        ]);

        $aiTranslationService = new AiTranslationService();
        
        $translations = $aiTranslationService->translateCeoRankName(
            $request->rank_name,
            $request->source_language,
            $request->target_languages
        );

        if ($translations === null) {
            return response()->json([
                'success' => false,
                'message' => __('admin/ceoranks.translation-failed')
            ], 500);
        }

        return response()->json([
            'success' => true,
            'translations' => $translations
        ]);
    }
}