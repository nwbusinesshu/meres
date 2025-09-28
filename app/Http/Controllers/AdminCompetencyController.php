<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\CompetencyQuestion;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use App\Services\OrgConfigService;
use App\Services\AiTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\CompetencyGroup;
use Illuminate\Support\Facades\DB;



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

        // NEW: Get competency groups for this organization
        $competencyGroups = CompetencyGroup::where('organization_id', $orgId)
            ->orderBy('name')
            ->get();

        // Split competencies into globals and organization-specific
        $globals = $comps->where('organization_id', null);
        $orgCompetencies = $comps->where('organization_id', $orgId);

        return view('admin.competencies', [
            "competencies" => $comps, // Keep for backward compatibility
            "globals" => $globals,
            "orgCompetencies" => $orgCompetencies,
            "competencyGroups" => $competencyGroups, // NEW
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
            
            // ADDED: Handle description field
            $comp->description = $request->description ?? null;

            // Handle name translations
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

            // ADDED: Handle description translations
            if ($request->has('description_translations') && is_array($request->description_translations)) {
                $descriptionTranslations = $request->description_translations;
                
                // Remove empty description translations
                $descriptionTranslations = array_filter($descriptionTranslations, function($value) {
                    return !empty(trim($value));
                });
                
                if (!empty($descriptionTranslations)) {
                    $comp->description_json = json_encode($descriptionTranslations, JSON_UNESCAPED_UNICODE);
                }
            } else {
                // If description is provided but no translations, store original description in JSON format
                if (!empty($comp->description)) {
                    $originalLang = $comp->original_language;
                    $comp->description_json = json_encode([$originalLang => $comp->description], JSON_UNESCAPED_UNICODE);
                }
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
            // ADDED: Return description data
            'description' => $comp->description,
            'description_json' => $comp->description_json ? json_decode($comp->description_json, true) : null,
            'original_language' => $comp->original_language ?? 'hu',
            'available_languages' => $comp->available_languages ? json_decode($comp->available_languages, true) : null
        ]);
    }

    public function saveCompetencyQuestion(Request $request){
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

        $orgId = session('org_id');

        AjaxService::DBTransaction(function() use ($request, &$q, $orgId) {
            if(!$q){
                $q = new CompetencyQuestion();
                $q->competency_id = $request->competency_id;
                $q->organization_id = $orgId;
                $q->original_language = $request->original_language ?? auth()->user()->locale ?? config('app.locale', 'hu');
            } else {
                // global competency questiont nem editálhat a client
                if(is_null($q->organization_id)){
                    AjaxService::error(__('admin/competencies.cannot-modify-global'));
                }
            }

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

            // Get all unique languages
            $allTranslations = [];
            
            if (!empty($q->question_json)) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($q->question_json, true)));
            }
            if (!empty($q->question_self_json)) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($q->question_self_json, true)));
            }
            if (!empty($q->min_label_json)) {
                $allTranslations = array_merge($allTranslations, array_keys(json_decode($q->min_label_json, true)));
            }
            if (!empty($q->max_label_json)) {
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

    public function getCompetencyQuestion(Request $request){
        $q = CompetencyQuestion::find($request->id);
        if(!$q) AjaxService::error(__('admin/competencies.question-not-found'));

        return $q;
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
     * Translate competency name using AI service
     */
    public function translateCompetencyName(Request $request)
    {
        $request->validate([
            'competency_name' => 'required|string',
            'competency_description' => 'nullable|string',
            'source_language' => 'required|string',
            'target_languages' => 'required|array',
        ]);

        $aiTranslationService = new AiTranslationService();
        
        // FIXED: Updated to match new service signature
        $translations = $aiTranslationService->translateCompetencyName(
            $request->competency_name,
            $request->competency_description ?? null,  // Add description parameter
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
     * Translate competency question using AI service
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
 * Save a competency group
 */
public function saveCompetencyGroup(Request $request)
{
    $this->validate($request, [
        'name' => 'required|string|max:255',
        'competency_ids' => 'required|array|min:1',
        'competency_ids.*' => 'exists:competency,id'
    ], [], [
        'name' => __('admin/competencies.group-name'),
        'competency_ids' => __('admin/competencies.competencies')
    ]);

    $orgId = session('org_id');

    AjaxService::DBTransaction(function() use ($request, $orgId) {
        $group = $request->id ? CompetencyGroup::find($request->id) : new CompetencyGroup();

        if (!$group && $request->id) {
            AjaxService::error(__('admin/competencies.group-not-found'));
        }

        // Verify competencies belong to this organization or are global
        $validCompetencyIds = Competency::whereIn('id', $request->competency_ids)
            ->where(function($q) use ($orgId) {
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $orgId);
            })
            ->whereNull('removed_at')
            ->pluck('id')
            ->toArray();

        if (count($validCompetencyIds) !== count($request->competency_ids)) {
            AjaxService::error(__('admin/competencies.invalid-competencies'));
        }

        $group->organization_id = $orgId;
        $group->name = $request->name;
        $group->competency_ids = $validCompetencyIds;
        $group->save();
    });

    return response()->json(['ok' => true]);
}

/**
 * Get a competency group details
 */
public function getCompetencyGroup(Request $request)
{
    // FIXED: Changed from 'competency_group' to 'competency_groups' (correct table name)
    $this->validate($request, [
        'id' => 'required|exists:competency_groups,id'
    ]);

    $orgId = session('org_id');
    
    $group = CompetencyGroup::where('id', $request->id)
        ->where('organization_id', $orgId)
        ->first();

    if (!$group) {
        AjaxService::error(__('admin/competencies.group-not-found'));
    }

    // FIXED: Get the actual competency details using the competencies() method
    $competencies = $group->competencies();
    
    // FIXED: Convert to array format expected by JavaScript
    $competenciesArray = $competencies->map(function($comp) {
        return [
            'id' => $comp->id,
            'name' => $comp->name,
            'description' => $comp->description ?? ''
        ];
    })->toArray();

    return response()->json([
        'id' => $group->id,
        'name' => $group->name,
        'competency_ids' => $group->competency_ids,
        'competencies' => $competenciesArray
    ]);
}

/**
 * Remove a competency group
 */
public function removeCompetencyGroup(Request $request)
{
    // FIXED: Changed from 'competency_group' to 'competency_groups' (correct table name)
    $this->validate($request, [
        'id' => 'required|exists:competency_groups,id'
    ]);

    $orgId = session('org_id');

    AjaxService::DBTransaction(function() use ($request, $orgId) {
        $group = CompetencyGroup::where('id', $request->id)
            ->where('organization_id', $orgId)
            ->first();

        if (!$group) {
            AjaxService::error(__('admin/competencies.group-not-found'));
        }

        $group->delete();

        AjaxService::success(__('admin/competencies.group-removed-success'));
    });
}

/**
 * Get all competency groups for an organization
 */
public function getAllCompetencyGroups(Request $request)
{
    $orgId = session('org_id');

    return CompetencyGroup::where('organization_id', $orgId)
        ->orderBy('name')
        ->get()
        ->map(function($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'competency_count' => count($group->competency_ids ?? []),
                'competency_ids' => $group->competency_ids
            ];
        });
}

    public function getCompetencyGroupUsers(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required|exists:competency_groups,id'
        ]);

        $orgId = session('org_id');
        
        $group = CompetencyGroup::where('id', $request->group_id)
            ->where('organization_id', $orgId)
            ->first();

        if (!$group) {
            AjaxService::error(__('admin/competencies.group-not-found'));
        }

        // Get the assigned users with their details
        $assignedUsers = $group->assignedUsers();
        
        // Return user data in format expected by modal
        $userData = $assignedUsers->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email ?? null
            ];
        });

        return response()->json($userData);
    }

    /**
     * Save user assignments for a competency group
     */
     public function saveCompetencyGroupUsers(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'required|exists:competency_groups,id',
            'user_ids' => 'array',
            'user_ids.*' => 'exists:user,id'
        ]);

        $orgId = session('org_id');
        
        $group = CompetencyGroup::where('id', $request->group_id)
            ->where('organization_id', $orgId)
            ->first();

        if (!$group) {
            AjaxService::error(__('admin/competencies.group-not-found'));
        }

        $userIds = $request->user_ids ?? [];
        
        if (!empty($userIds)) {
            // Verify all users belong to this organization
            $validUserIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->toArray();

            if (count($validUserIds) !== count($userIds)) {
                AjaxService::error(__('admin/competencies.invalid-users'));
            }

            // NEW: Check if any users are already assigned to other groups
            $otherGroupsWithUsers = CompetencyGroup::where('organization_id', $orgId)
                ->where('id', '!=', $request->group_id)
                ->get()
                ->filter(function($otherGroup) use ($userIds) {
                    $otherGroupUsers = $otherGroup->assigned_users ?? [];
                    return !empty(array_intersect($userIds, $otherGroupUsers));
                });

            if ($otherGroupsWithUsers->count() > 0) {
                // Find which users are already assigned
                $conflictingUsers = [];
                foreach ($otherGroupsWithUsers as $otherGroup) {
                    $conflicts = array_intersect($userIds, $otherGroup->assigned_users ?? []);
                    if (!empty($conflicts)) {
                        $userNames = DB::table('user')->whereIn('id', $conflicts)->pluck('name')->toArray();
                        $conflictingUsers = array_merge($conflictingUsers, $userNames);
                    }
                }
                
                $conflictingUsersText = implode(', ', array_unique($conflictingUsers));
                AjaxService::error(__('admin/competencies.users-already-assigned', ['users' => $conflictingUsersText]));
            }
            
            $userIds = $validUserIds;
        }

        AjaxService::DBTransaction(function() use ($group, $userIds) {
            // Update the group's assigned users
            $group->setUsers($userIds);
        });

        return response()->json([
            'ok' => true,
            'message' => __('admin/competencies.user-assignments-saved')
        ]);
    }

    public function getEligibleUsersForGroup(Request $request)
    {
        $this->validate($request, [
            'group_id' => 'nullable|exists:competency_groups,id'
        ]);

        $orgId = session('org_id');
        $currentGroupId = $request->group_id;

        // Get all user IDs that are already assigned to competency groups
        $assignedUserIds = CompetencyGroup::where('organization_id', $orgId)
            ->when($currentGroupId, function($query, $groupId) {
                // Exclude the current group if editing
                return $query->where('id', '!=', $groupId);
            })
            ->get()
            ->pluck('assigned_users')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Get all users in the organization who are NOT assigned to any other group
        $eligibleUsers = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->whereNotIn('u.id', $assignedUserIds)
            ->where('u.type', '!=', 'admin')  // Exclude global admin users
            ->where('ou.role', '!=', 'admin') // Exclude organization admin users
            ->orderBy('u.name')
            ->select('u.id', 'u.name', 'u.email', 'u.type', 'ou.position')
            ->get();

        return response()->json($eligibleUsers);
    }
}