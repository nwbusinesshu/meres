<?php

namespace App\Http\Controllers;

use App\Models\Enums\UserRelationType;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserBonusMalus;
use App\Models\UserCompetency;
use App\Models\UserRelation;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Models\Competency;
use App\Services\PasswordSetupService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\OrgConfigService;


class AdminEmployeeController extends Controller
{
    public function __construct(){
        if(AssessmentService::isAssessmentRunning()){
            return abort(403);
        }
    }

    
public function index(Request $request)
{
    $orgId = (int) session('org_id');

    // ===== CRITICAL FIX: Ensure $selectedLanguages is ALWAYS set first =====
    $selectedLanguages = \App\Services\OrgConfigService::getJson($orgId, 'translation_languages', [auth()->user()->locale ?? config('app.locale', 'hu')]);
    
    // Additional safety check - ensure it's always an array
    if (!is_array($selectedLanguages)) {
        $selectedLanguages = [auth()->user()->locale ?? config('app.locale', 'hu')];
    }

    $hasClosedAssessment = DB::table('assessment')
        ->where('organization_id', $orgId)
        ->whereNotNull('closed_at')
        ->exists();
    
    // Get employee limit and current employee count
    $employeeLimit = null;
    $currentEmployeeCount = 0;
    $isLimitReached = false;
    
    if (!$hasClosedAssessment) {
        // Only apply limit if there are no closed assessments yet
        $employeeLimit = DB::table('organization_profiles')
            ->where('organization_id', $orgId)
            ->value('employee_limit');
        
        // Count current employees (excluding admins)
        $currentEmployeeCount = DB::table('organization_user as ou')
            ->join('user as u', 'u.id', '=', 'ou.user_id')
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->whereNotIn('u.type', ['admin'])
            ->count();
        
        // Check if limit is reached
        if ($employeeLimit && $currentEmployeeCount >= $employeeLimit) {
            $isLimitReached = true;
        }
    }

    // ===== LEGACY (régi) lista adatai – változatlan =====
    $users = \App\Services\UserService::getUsers()->map(function ($user) {
        // bonusMalus feltöltése biztonságosan (nincs eager load, nincs created_at ordering)
        $user['bonusMalus'] = $user->bonusMalus()->first()?->level ?? null;
        $user['is_locked'] = $this->isUserLocked($user->email);
        return $user;
    });

    // Add rater counts to legacy users
    $users = $this->addRaterCounts($users, $orgId);

    // multi-level flag
    $enableMultiLevel = \App\Services\OrgConfigService::getBool($orgId, 'enable_multi_level', false);
    $showBonusMalus = \App\Services\OrgConfigService::getBool($orgId, 'show_bonus_malus', true);
$easyRelationSetup = \App\Services\OrgConfigService::getBool($orgId, 'easy_relation_setup', false);

    if ($users->count() > 0) {
        $userIds = $users->pluck('id')->all();

        $positionsMap = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->whereIn('user_id', $userIds)
            ->pluck('position', 'user_id'); // [user_id => position]

        $users = $users->map(function ($u) use ($positionsMap) {
            $u->position = $positionsMap[$u->id] ?? null;
            return $u;
        });
    }

    // ===== MULTI-LEVEL handling if enabled =====
    if ($enableMultiLevel) {
        
        // ===== CEO + nem besorolt felhasználók =====
        $ceos = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->where('u.type', 'ceo')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email', 'u.type', 'ou.position']);

        // FIXED: Exclude admins AND already assigned managers from unassigned list
        $unassigned = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->whereNull('ou.department_id')
            ->where('u.type', '!=', 'ceo')
            ->where('u.type', '!=', 'admin')
            ->whereNotExists(function($query) use ($orgId) {
                $query->from('organization_department_managers as odm')
                      ->whereColumn('odm.manager_id', 'u.id')
                      ->where('odm.organization_id', $orgId);
            })
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email', 'u.type', 'ou.position']);

        // FIXED: Add rater counts to CEOs and unassigned
        $ceos = $this->addRaterCounts($ceos, $orgId);
        $unassigned = $this->addRaterCounts($unassigned, $orgId);
        $ceos = $this->attachBonusMalus($ceos);
        $unassigned = $this->attachBonusMalus($unassigned);

        $ceos = $ceos->map(function($user) {
            $user->is_locked = $this->isUserLocked($user->email);
            return $user;
        });

        $unassigned = $unassigned->map(function($user) {
            $user->is_locked = $this->isUserLocked($user->email);
            return $user;
        });

        // ===== Részlegek listája =====
        $departments = collect();
        $rawDepartments = DB::table('organization_departments as od')
            ->where('od.organization_id', $orgId)
            ->whereNull('od.removed_at')
            ->orderBy('od.department_name')
            ->get();

        $emptyDepartments = collect();

        foreach ($rawDepartments as $dept) {
            // Managers
            $managers = DB::table('organization_department_managers as odm')
                ->join('user as u', 'u.id', '=', 'odm.manager_id')
                ->join('organization_user as ou', function($join) {
                    $join->on('ou.user_id', '=', 'odm.manager_id')
                         ->on('ou.organization_id', '=', 'odm.organization_id');
                })
                ->where('odm.organization_id', $orgId)
                ->where('odm.department_id', $dept->id)
                ->whereNull('u.removed_at')
                ->select('u.id', 'u.name', 'u.email', 'u.type', 'ou.position', 'odm.created_at as assigned_at')
                ->get();

            // Members - FIXED: Remove hardcoded rater_count
            $members = DB::table('user as u')
                ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
                ->where('ou.organization_id', $orgId)
                ->where('ou.department_id', $dept->id)
                ->where('ou.role', '!=', 'manager')
                ->whereNull('u.removed_at')
                ->orderBy('u.name')
                ->get(['u.id', 'u.name', 'u.email', 'u.type', 'ou.position']);

            // FIXED: Add rater counts to managers and members
            $managers = $this->addRaterCounts($managers, $orgId);
            $members = $this->addRaterCounts($members, $orgId);
            $managers = $this->attachBonusMalus($managers);
            $members = $this->attachBonusMalus($members);

            $managers = $managers->map(function($user) {
                $user->is_locked = $this->isUserLocked($user->email);
                return $user;
            });
            $members = $members->map(function($user) {
                $user->is_locked = $this->isUserLocked($user->email);
                return $user;
            });

            $deptData = (object) [
                'id' => $dept->id,
                'department_name' => $dept->department_name,
                'managers' => $managers,
                'members' => $members,
            ];

            if ($managers->isEmpty() && $members->isEmpty()) {
                $emptyDepartments->push($deptData);
            } else {
                $departments->push($deptData);
            }
        }

        // UPDATED: Get eligible managers for CREATE/EDIT modals (all managers, not just unused ones)
        $eligibleManagers = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->where('u.type', 'manager')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email']);

        // Üres részlegek a végére
        $departments = $departments->concat($emptyDepartments);

         return view('admin.employees', [
            // LEGACY-hoz
            'users'            => $users,

            // MULTI-LEVEL adatok
            'enableMultiLevel' => true,
            'ceos'             => $ceos,
            'unassigned'       => $unassigned,
            'departments'      => $departments,

            // modálokhoz
            'eligibleManagers' => $eligibleManagers,
            
            // settings
            'showBonusMalus'   => $showBonusMalus,
            'selectedLanguages' => $selectedLanguages,
            
            // NEW: Employee limit
            'hasClosedAssessment'   => $hasClosedAssessment,
            'employeeLimit'         => $employeeLimit,
            'currentEmployeeCount'  => $currentEmployeeCount,
            'isLimitReached'        => $isLimitReached,
            'easyRelationSetup' => $easyRelationSetup,
        ]);
    }

    // ===== LEGACY VIEW (multi-level OFF) =====
    return view('admin.employees', [
        'users'             => $users,
        'enableMultiLevel'  => false,
        'showBonusMalus'    => $showBonusMalus,
        'selectedLanguages' => $selectedLanguages,
        
        // NEW: Employee limit
        'hasClosedAssessment'   => $hasClosedAssessment,
        'employeeLimit'         => $employeeLimit,
        'currentEmployeeCount'  => $currentEmployeeCount,
        'isLimitReached'        => $isLimitReached,
        'easyRelationSetup' => $easyRelationSetup,
    ]);
}

// Hozzácsatolja a bonusMalus szintet tetszőleges user-collection-höz (id, name, email, ...)
private function attachBonusMalus($users)
{
    $ids = collect($users)->pluck('id')->filter()->unique()->values()->all();
    if (empty($ids)) {
        return $users;
    }

    // 1 körben betöltjük a hozzájuk tartozó bonusMalus értéket
    // (óvatos megoldás: per-user lekérés a reláción keresztül, stabil, nem találgat táblanevet)
    $levels = [];
    foreach (\App\Models\User::whereIn('id', $ids)->get() as $u) {
        $levels[$u->id] = $u->bonusMalus()->first()?->level ?? null;
    }

    // Rárakjuk a mezőt a kapott objektumokra
    return collect($users)->map(function ($u) use ($levels) {
        $u = (object) $u;
        $u->bonusMalus = $levels[$u->id] ?? null;
        return $u;
    });
}
    /**
     * Get rater counts for given user IDs in bulk
     */
   /**
 * Get rater counts for given user IDs in bulk
 */
private function addRaterCounts($users, $orgId)
{
    $userIds = $users->pluck('id')->toArray();
    if (empty($userIds)) {
        return $users;
    }

    // Értékelők (rater) darabszáma célonként
    // FIXED: Exclude self-relations from the count
    $raterCounts = DB::table('user_relation as ur')
        ->join('user as rater', 'rater.id', '=', 'ur.user_id')
        ->whereIn('ur.target_id', $userIds)
        ->where('ur.organization_id', $orgId)
        ->whereNull('rater.removed_at')
        ->where('ur.type', '!=', 'self')  // FIXED: Exclude self-relations
        ->whereColumn('ur.user_id', '!=', 'ur.target_id')  // FIXED: Extra safety - exclude where user_id = target_id
        ->groupBy('ur.target_id')
        ->select('ur.target_id', DB::raw('COUNT(*) as rater_count'))
        ->get()
        ->pluck('rater_count', 'target_id');

    // DEBUGGING: Log the rater counts
    \Log::info('Rater counts fetched', ['counts' => $raterCounts->toArray()]);

    // NINCS login_mode: vizsgáljuk, van-e jelszó a user táblában
    // has_password: 1, ha password nem NULL és nem üres; különben 0
    $hasPassword = DB::table('user')
        ->whereIn('id', $userIds)
        ->whereNull('removed_at')
        ->select('id', DB::raw("(CASE WHEN password IS NULL OR password = '' THEN 0 ELSE 1 END) as has_password"))
        ->pluck('has_password', 'id');

    // login_mode_text + rater_count hozzárendelése
    return $users->map(function ($user) use ($raterCounts, $hasPassword) {
        $user = (object) $user; // biztosítsuk, hogy objektum legyen

        // Rater count
        $count = $raterCounts[$user->id] ?? 0;
        $user->rater_count = $count;

        // DEBUGGING: Log each user's count
        \Log::info('User rater count', ['user_id' => $user->id, 'name' => $user->name ?? 'unknown', 'count' => $count]);

        // Login mód szöveg a jelszó megléte alapján
        $user->login_mode_text = (!empty($hasPassword[$user->id]) && (int)$hasPassword[$user->id] === 1)
            ? 'jelszó + OAuth'
            : 'OAuth';

        return $user;
    });
}


/**
 * Get rater counts for given user IDs in bulk
 */
private function getRaterCounts($userIds, $orgId)
{
    if ($userIds->isEmpty()) {
        return collect();
    }

    // FIXED: Exclude self-relations from the count
    $counts = DB::table('user_relation')
        ->whereIn('target_id', $userIds->toArray())
        ->where('organization_id', $orgId)
        ->where('type', '!=', 'self')  // FIXED: Exclude self-relations
        ->whereColumn('user_id', '!=', 'target_id')  // FIXED: Extra safety - exclude where user_id = target_id
        ->groupBy('target_id')
        ->select('target_id', DB::raw('COUNT(*) as rater_count'))
        ->get()
        ->pluck('rater_count', 'target_id');

    // DEBUGGING: Log the counts
    \Log::info('getRaterCounts result', ['counts' => $counts->toArray()]);

    return $counts;
}


   public function getEligibleManagers(Request $request)
{
    $orgId = (int) session('org_id');
    $currentDepartmentId = $request->input('department_id'); // For edit mode

    // Get all managers in the organization
    $managersQuery = DB::table('user as u')
        ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
        ->where('ou.organization_id', $orgId)
        ->whereNull('u.removed_at')
        ->where('u.type', 'manager');

    // Filter out managers who are already managing other departments
    $managersQuery->whereNotExists(function ($q) use ($currentDepartmentId) {
        $q->from('organization_department_managers as odm')
          ->whereColumn('odm.manager_id', 'u.id');
        
        // If editing a department, allow managers from that department
        if ($currentDepartmentId) {
            $q->where('odm.department_id', '!=', $currentDepartmentId);
        }
    });

    $managers = $managersQuery
        ->orderBy('u.name')
        ->get(['u.id', 'u.name', 'u.email']);

    return response()->json($managers->toArray());
}


        public function getEmployee(Request $request)
{
    // SECURITY FIX: Add validation
    $request->validate([
        'id' => 'required|integer|exists:user,id'
    ]);

    $orgId = session('org_id');
    $u = User::findOrFail($request->id);

    // SECURITY: Verify user belongs to this organization
    $belongsToOrg = $u->organizations()->where('organization_id', $orgId)->exists();
    if (!$belongsToOrg) {
        abort(403, 'User does not belong to your organization');
    }

    // Rest of the method remains unchanged...
    $isDeptManager = DB::table('organization_department_managers as odm')
        ->join('organization_departments as d', 'd.id', '=', 'odm.department_id')
        ->where('d.organization_id', $orgId)
        ->where('odm.manager_id', $u->id)
        ->whereNull('d.removed_at')
        ->exists();

    $deptId = DB::table('organization_user')
        ->where('organization_id', $orgId)
        ->where('user_id', $u->id)
        ->value('department_id');

    $position = DB::table('organization_user')
        ->where('organization_id', $orgId)
        ->where('user_id', $u->id)
        ->value('position');

    return response()->json([
        'id'                => $u->id,
        'name'              => $u->name,
        'email'             => $u->email,
        'type'              => $u->type,
        'has_auto_level_up' => (int) $u->has_auto_level_up,
        'is_dept_manager'   => (bool) $isDeptManager,
        'is_in_department'  => !is_null($deptId),
        'position'          => $position,
        'department_id'     => $deptId,
    ]);
}

  public function saveEmployee(Request $request)
{
    $orgId = (int) session('org_id');
    if (!$orgId) {
        return response()->json([
            'message' => 'Nincs kiválasztott szervezet.',
            'errors'  => ['org' => ['Nincs kiválasztott szervezet.']],
        ], 422);
    }

    \Log::info('employee.save.debug.input', [
        'org_id' => $orgId,
        'id_raw' => $request->id,
        'id_int' => (int) $request->id,
        'name'   => $request->name,
        'email'  => $request->email,
        'type'   => $request->type,
    ]);

    // Check if email already exists
    $existingUser = User::where('email', $request->email)
                        ->whereNull('removed_at')
                        ->first();

    // Determine if this is CREATE or UPDATE
    $user = null;
    $created = false;

    if ($request->id && (int) $request->id > 0) {
        // UPDATE mode - load existing user
        $user = User::find((int) $request->id);
        
        if (!$user || !is_null($user->removed_at)) {
            return response()->json([
                'message' => 'A felhasználó nem található.',
                'errors'  => ['user' => ['A felhasználó nem található.']]
            ], 404);
        }
    }

    // Email validation for CREATE and UPDATE
    if ($existingUser) {
        $alreadyInOrg = $existingUser->organizations()->where('organization_id', $orgId)->exists();

        // If not updating the same user
        if (!$user || $user->id !== $existingUser->id) {
            if (!$alreadyInOrg) {
                // Email exists in another organization
                return response()->json([
                    'message' => 'Ez az e-mail cím már egy másik szervezethez tartozik!',
                    'errors'  => ['email' => ['Ez az e-mail cím már egy másik szervezethez tartozik!']]
                ], 422);
            }
        }
    }

    // Type change protection (only for UPDATE mode)
    if ($user) {
        $currentType = $user->type;
        $requestedType = $request->type;

        // Check department relationships
        $isDeptManager = DB::table('organization_department_managers as odm')
            ->join('organization_departments as d', 'd.id', '=', 'odm.department_id')
            ->where('d.organization_id', $orgId)
            ->where('odm.manager_id', $user->id)
            ->whereNull('d.removed_at')
            ->exists();

        $hasDept = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('user_id', $user->id)
            ->whereNotNull('department_id')
            ->exists();

        // Rule 1: If manager with department assignment, cannot change type
        if ($currentType === 'manager' && ($isDeptManager || $hasDept) && $requestedType !== 'manager') {
            return response()->json([
                'message' => 'A felhasználó menedzser és részleghez van rendelve; a típus nem módosítható.',
                'errors'  => ['type' => ['A felhasználó menedzser és részleghez van rendelve; a típus nem módosítható.']]
            ], 422);
        }

        // Rule 2: If department member and normal type, cannot change type
        if ($hasDept && $currentType === 'normal' && $requestedType !== 'normal') {
            return response()->json([
                'message' => 'Ez a dolgozó már tagja egy részlegnek, ezért a típus nem módosítható.',
                'errors'  => ['type' => ['Ez a dolgozó már tagja egy részlegnek, ezért a típus nem módosítható.']]
            ], 422);
        }
    }

    try {
        // FIX: Use standard DB::transaction instead of AjaxService::DBTransaction
        // AjaxService::DBTransaction treats any non-null return as an error
        DB::transaction(function () use ($request, $orgId, &$user, &$created) {

            \Log::info('employee.save.debug.branch', [
                'branch'  => is_null($user) ? 'CREATE' : 'UPDATE',
                'user_id' => optional($user)->id,
            ]);

            // ========== CREATE MODE: Use EmployeeCreationService ==========
            if (is_null($user)) {
                $created = true;

                // Prepare data for service
                $employeeData = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'type' => $request->type,
                    'position' => trim((string) $request->input('position', '')) ?: null,
                ];

                // Create employee using service (handles everything)
                $user = \App\Services\EmployeeCreationService::createEmployee($employeeData, $orgId);

                Log::info('employee.save.created_via_service', [
                    'user_id' => $user->id,
                    'org_id' => $orgId
                ]);

            // ========== UPDATE MODE: Keep existing logic ==========
            } else {
                $user->name = $request->name;
                $user->email = $request->email;
                $user->type = $request->type;
                $user->save();

                Log::info('employee.save.step_ok', ['step' => 'update_user', 'user_id' => $user->id]);

                // Update organization attachment (idempotent)
                $user->organizations()->syncWithoutDetaching([$orgId]);
                Log::info('employee.save.step_ok', ['step' => 'attach_org', 'user_id' => $user->id, 'org_id' => $orgId]);

                // Update position
                $position = trim((string) $request->input('position', ''));
                DB::table('organization_user')->updateOrInsert(
                    ['organization_id' => $orgId, 'user_id' => $user->id],
                    ['position' => ($position !== '' ? $position : null)]
                );

                // Ensure SELF relation exists (idempotent)
                UserRelation::updateOrCreate(
                    [
                        'organization_id' => $orgId,
                        'user_id'         => $user->id,
                        'target_id'       => $user->id,
                    ],
                    [
                        'type'            => UserRelationType::SELF,
                    ]
                );
                \Log::info('employee.save.step_ok', ['step' => 'create_self_relation', 'user_id' => $user->id, 'org_id' => $orgId]);

                // Ensure Bonus/Malus exists (idempotent)
                $user->bonusMalus()->updateOrCreate(
                    [
                        'month'           => date('Y-m-01'),
                        'organization_id' => $orgId,
                    ],
                    [
                        'level' => UserService::DEFAULT_BM,
                    ]
                );
                Log::info('employee.save.step_ok', ['step' => 'bonus_malus', 'user_id' => $user->id, 'org_id' => $orgId]);
            }
        });

    } catch (\Throwable $e) {
        \Log::error('employee.save.transaction_failed', [
            'org_id'  => $orgId,
            'user_id' => $user ? $user->id : null,
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
            'prev'    => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
        ]);

        return response()->json([
            'message' => 'Sikertelen művelet: szerverhiba!',
            'errors'  => ['exception' => [$e->getMessage()]],
        ], 422);
    }

    // Send password setup email only for NEW users
    if ($created) {
        try {
            PasswordSetupService::createAndSend($orgId, $user->id, auth()->id());
        } catch (\Throwable $e) {
            \Log::error('password_setup_mail_failed', [
                'org_id'  => $orgId,
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => true,
                'user_id' => $user->id,
                'info'    => 'A felhasználó létrejött, de a jelszó-beállító e-mail küldése nem sikerült. Ellenőrizd a mail beállításokat.',
            ]);
        }
    }

    return response()->json([
        'ok'      => true,
        'user_id' => $user->id,
    ]);
}

public function PasswordReset(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:user,id'],
        ]);

        $orgId = (int) session('org_id');
        $adminId = (int) session('uid');
        /** @var User $user */
        $user = User::findOrFail($request->input('user_id'));

        // Biztonság: csak az aktuális org tagjain lehet resetelni
        $inOrg = $user->organizations()->where('organization.id', $orgId)->exists();
        if (!$inOrg) {
            return response()->json([
                'ok' => false,
                'message' => 'A felhasználó nem tagja az aktuális szervezetnek.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($user, $orgId, $adminId) {
                // 1) meglévő jelszó törlése
                $user->password = null;
                $user->save();

                Log::info('employee.password_reset.password_cleared', [
                    'org_id'  => $orgId,
                    'user_id' => $user->id,
                    'by'      => $adminId,
                ]);

                // 2) új jelszó-beállító e-mail küldése
                PasswordSetupService::createAndSendReset($orgId, $user->id, $adminId);
            });

            return response()->json([
                'ok' => true,
                'message' => 'Jelszó-visszaállító link elküldve a felhasználónak.',
            ]);
        } catch (\Throwable $e) {
            Log::error('employee.password_reset.failed', [
                'org_id'  => $orgId,
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Sikertelen művelet: szerverhiba!',
            ], 500);
        }
    }



    public function removeEmployee(Request $request)
    {
        // SECURITY FIX: Add validation
        $request->validate([
            'id' => 'required|integer|exists:user,id'
        ]);

        $user = User::findOrFail($request->id);
        
        // Additional security check: verify user belongs to current org
        $orgId = session('org_id');
        $belongsToOrg = $user->organizations()->where('organization_id', $orgId)->exists();
        
        if (!$belongsToOrg) {
            abort(403, 'User does not belong to your organization');
        }
        
        AjaxService::DBTransaction(function() use (&$user, $orgId){
            $user->removed_at = date('Y-m-d H:i:s');
            $user->save();

            UserRelation::where('organization_id', $orgId)
                ->where(function($q) use ($user) {
                    $q->where('target_id', $user->id)
                      ->orWhere('user_id', $user->id);
                })
                ->delete();
        });
        
        return response()->json(['ok' => true]);
    }

    /**
     * Get all employees for selection/autocomplete
     * 
     * SECURITY FIX: Properly sanitize LIKE pattern to prevent SQL injection
     * and wildcard character abuse
     */
    public function getAllEmployee(Request $request)
    {
        // SECURITY: Validate input before using in query
        $request->validate([
            'search' => 'nullable|string|max:255',
            'except' => 'nullable|array',
            'except.*' => 'integer',
            'id' => 'nullable|integer'
        ]);

        $query = User::whereNull('removed_at')
            ->where('type', '!=', UserType::ADMIN)
            ->whereHas('organizations', function ($q) {
                $q->where('organization_id', session('org_id'));
            });

        // SECURITY FIX: Escape LIKE wildcards and use parameterized binding
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            
            // Escape SQL LIKE special characters (_ and %)
            // This prevents users from using wildcards to bypass search restrictions
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchTerm);
            
            // Use parameterized binding - Laravel automatically escapes the value
            $query->where('name', 'like', '%' . $escapedSearch . '%');
        }

        if ($request->has('except') && is_array($request->except)) {
            $query->whereNotIn('id', $request->except);
        }

        // Don't show self
        if ($request->has('id') && is_numeric($request->id)) {
            $query->where('id', '!=', $request->id);
        }

        return response()->json($query->select('id', 'name')->get());
    }


    /**
 * FIXED: Get employee relations with clear structure for transformation
 * Returns BOTH directions:
 * - Relations FROM user (what they manage)
 * - Relations TO user (needed for "superior" detection)
 */
public function getEmployeeRelations(Request $request)
{
    // SECURITY FIX: Add validation
    $request->validate([
        'id' => 'required|integer|exists:user,id'
    ]);

    $user = User::findOrFail($request->id);
    
    // SECURITY: Verify user belongs to current org
    $orgId = session('org_id');
    $belongsToOrg = $user->organizations()->where('organization_id', $orgId)->exists();
    
    if (!$belongsToOrg) {
        abort(403, 'User does not belong to your organization');
    }

    // Get relations in BOTH directions
    // We need ALL relations to properly transform "colleague" → "superior"
    
    // Get all relations involving this user (as user_id OR target_id)
    $allRelations = UserRelation::where('organization_id', $orgId)
        ->where(function($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('target_id', $user->id);
        })
        ->with(['user', 'target'])
        ->whereHas('user.organizations', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })
        ->whereHas('target.organizations', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })
        ->get()
        ->map(function($relation) use ($user) {
            // Normalize the structure
            // Always return with: user_id, target_id, type, and the "other" person's data
            return [
                'user_id' => $relation->user_id,
                'target_id' => $relation->target_id,
                'type' => $relation->type,
                'user' => [
                    'id' => $relation->user->id,
                    'name' => $relation->user->name,
                ],
                'target' => [
                    'id' => $relation->target->id,
                    'name' => $relation->target->name,
                ],
                // Flag to help frontend identify which direction this is
                'is_from_current_user' => ($relation->user_id == $user->id)
            ];
        });
    
    return response()->json($allRelations);
}

   public function saveEmployeeRelations(Request $request)
{
    Log::info('Relations save started', $request->all());

    $request->validate([
        'id' => 'required|integer|exists:user,id',
        'relations' => 'required|array|min:1',
        'relations.*.target_id' => 'required|integer|exists:user,id',
        'relations.*.type' => 'required|string|in:self,colleague,subordinate,superior', // Now accepts superior!
    ]);

    $user = User::findOrFail($request->id);
    $organizationId = session('org_id');
    
    // Security: Verify user belongs to current org
    if (!$user->organizations()->where('organization_id', $organizationId)->exists()) {
        return response()->json(['message' => 'User does not belong to organization'], 403);
    }

    // Get Easy Setup setting
    $easyRelationSetup = \App\Services\OrgConfigService::getBool($organizationId, 'easy_relation_setup', false);
    
    Log::info('Easy Relation Setup mode', ['enabled' => $easyRelationSetup]);

    // SIMPLIFIED: No more conversion! Just collect the relations as-is
    $relations = collect($request->input('relations'));

    try {
        // Check for conflicts (same for both modes)
        $conflicts = $this->detectRelationConflicts($user->id, $relations, $organizationId, $easyRelationSetup);
        
        if (!empty($conflicts)) {
            Log::warning('Relation conflicts detected', ['conflicts' => $conflicts]);
            return response()->json([
                'success' => false,
                'has_conflicts' => true,
                'conflicts' => $conflicts,
                'message' => 'Cannot save relations due to conflicts.'
            ], 422);
        }

        // No conflicts - proceed with save
AjaxService::DBTransaction(function () use ($relations, $user, $organizationId, $easyRelationSetup) {

    // NEW: In Easy Setup ON mode, handle bidirectional deletion BEFORE deleting forward relations
    if ($easyRelationSetup) {
        // Get target IDs from new relations
        $newTargetIds = $relations->pluck('target_id')->map(fn($id) => (int)$id)->toArray();
        
        // Delete reverse relations for removed forward relations
        $this->handleBidirectionalDeletion($user->id, $newTargetIds, $organizationId);
    }

    // 1) Delete existing non-SELF relations for this user
    DB::table('user_relation')
        ->where('user_id', $user->id)
        ->where('organization_id', $organizationId)
        ->whereIn('type', ['colleague', 'subordinate', 'superior'])
        ->delete();
    
    Log::info('Deleted existing relations', ['user_id' => $user->id]);

    // 2) Ensure SELF relation exists
    DB::table('user_relation')->insertOrIgnore([
        'user_id' => $user->id,
        'target_id' => $user->id,
        'organization_id' => $organizationId,
        'type' => 'self',
    ]);

    // 3) Prepare and insert new relations (STORE AS-IS, NO CONVERSION!)
    $rows = $relations
        ->map(function ($item) use ($user, $organizationId) {
            return [
                'user_id' => $user->id,
                'target_id' => (int)($item['target_id'] ?? 0),
                'organization_id' => $organizationId,
                'type' => $item['type'] ?? null,
            ];
        })
        ->filter(function ($r) use ($user) {
            return in_array($r['type'], ['colleague', 'subordinate', 'superior'], true)
                && $r['target_id'] > 0
                && $r['target_id'] !== $user->id;
        })
        ->unique(fn($r) => $r['user_id'].'-'.$r['target_id'])
        ->values()
        ->all();

    if (!empty($rows)) {
        DB::table('user_relation')->insert($rows);
        Log::info('Inserted new relations', ['count' => count($rows), 'relations' => $rows]);
    }

    // 4) Handle bidirectional relations based on Easy Setup mode
    if ($easyRelationSetup) {
        // Easy Setup ON: Create/update reverse relations automatically
        $this->applyBidirectionalRelations($user->id, $rows, $organizationId);
    }
    // Easy Setup OFF: Do nothing - relations are independent
});

        Log::info('Relations saved successfully', ['user_id' => $user->id]);
        return response()->json(['success' => true, 'message' => 'Relations saved successfully']);

    } catch (\Throwable $e) {
        Log::error('Error saving relations', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to save relations: ' . $e->getMessage(),
        ], 500);
    }
}
/**
 * UPDATED: Detect conflicts in reverse relations
 * Only real conflict: Both users have each other as subordinate
 */
private function detectRelationConflicts($userId, $relations, $organizationId, $easyRelationSetup = false)
{
    $conflicts = [];

    foreach ($relations as $relation) {
        $targetId = (int)($relation['target_id'] ?? 0);
        $type = $relation['type'] ?? null;

        // Skip self and invalid
        if ($targetId === $userId || !in_array($type, ['colleague', 'subordinate', 'superior'], true)) {
            continue;
        }

        if ($easyRelationSetup) {
            // ===== EASY SETUP ON: Only check bidirectional subordinate =====
            if ($type === 'subordinate') {
                $existingReverse = DB::table('user_relation')
                    ->where('user_id', $targetId)
                    ->where('target_id', $userId)
                    ->where('organization_id', $organizationId)
                    ->where('type', 'subordinate')
                    ->first();

                if ($existingReverse) {
                    $targetUser = User::find($targetId);
                    $conflicts[] = [
                        'target_id' => $targetId,
                        'target_name' => $targetUser ? $targetUser->name : 'Unknown',
                        'message' => 'Cannot set as subordinate - they already have you as subordinate'
                    ];
                    
                    Log::warning('Bidirectional subordinate conflict detected', [
                        'user_id' => $userId,
                        'target_id' => $targetId
                    ]);
                }
            }
        } else {
            // ===== EASY SETUP OFF: Check for inconsistent reverse relations =====
            
            // Get existing reverse relation
            $existingReverse = DB::table('user_relation')
                ->where('user_id', $targetId)
                ->where('target_id', $userId)
                ->where('organization_id', $organizationId)
                ->whereIn('type', ['colleague', 'subordinate', 'superior'])
                ->first();
            
            if ($existingReverse) {
                // Determine what the expected type should be based on reverse
                $expectedType = match($existingReverse->type) {
                    'colleague' => 'colleague',
                    'subordinate' => 'superior',    // If B→A is subordinate, A→B must be superior
                    'superior' => 'subordinate',    // If B→A is superior, A→B must be subordinate
                    default => null
                };
                
                // Check if current type matches expected type
                if ($expectedType && $type !== $expectedType) {
                    $targetUser = User::find($targetId);
                    $conflicts[] = [
                        'target_id' => $targetId,
                        'target_name' => $targetUser ? $targetUser->name : 'Unknown',
                        'message' => "Inconsistent relation: You are setting as '{$type}', but they have you as '{$existingReverse->type}'"
                    ];
                    
                    Log::warning('Inconsistent relation detected in Easy Setup OFF', [
                        'user_id' => $userId,
                        'target_id' => $targetId,
                        'forward_type' => $type,
                        'reverse_type' => $existingReverse->type,
                        'expected_type' => $expectedType
                    ]);
                }
            }
        }
    }

    return $conflicts;
}

private function handleBidirectionalDeletion($userId, $newRelationTargetIds, $organizationId)
{
    // Get existing relations FROM this user (before deletion)
    $existingRelations = DB::table('user_relation')
        ->where('user_id', $userId)
        ->where('organization_id', $organizationId)
        ->whereIn('type', ['colleague', 'subordinate', 'superior'])
        ->get();
    
    // Find which target IDs were REMOVED
    $removedTargetIds = [];
    foreach ($existingRelations as $existing) {
        if (!in_array($existing->target_id, $newRelationTargetIds)) {
            $removedTargetIds[] = $existing->target_id;
        }
    }
    
    if (empty($removedTargetIds)) {
        Log::info('No relations removed', ['user_id' => $userId]);
        return;
    }
    
    Log::info('Relations removed - deleting reverse pairs', [
        'user_id' => $userId,
        'removed_target_ids' => $removedTargetIds
    ]);
    
    // Delete reverse relations for all removed targets
    $deletedCount = DB::table('user_relation')
        ->whereIn('user_id', $removedTargetIds)
        ->where('target_id', $userId)
        ->where('organization_id', $organizationId)
        ->whereIn('type', ['colleague', 'subordinate', 'superior'])
        ->delete();
    
    Log::info('Deleted reverse relations', [
        'count' => $deletedCount,
        'from_users' => $removedTargetIds,
        'to_user' => $userId
    ]);
}


/**
 * UPDATED: Apply bidirectional relations with support for superior->subordinate reverse
 */
private function applyBidirectionalRelations($userId, $rows, $organizationId)
{
    $reversesToCreate = [];

    foreach ($rows as $row) {
        $targetId = $row['target_id'];
        $type = $row['type'];

        // Determine reverse type - THE INVERSE OF HOW THEY RATE EACH OTHER
        // If A rates B as subordinate (employee), then B rates A as superior (boss)
        // If A rates B as superior (boss), then B rates A as subordinate (employee)
        // If A rates B as colleague (peer), then B rates A as colleague (peer)
        $reverseType = match($type) {
            'superior' => 'subordinate',      // A rates B as boss → B rates A as employee
            'subordinate' => 'superior',      // A rates B as employee → B rates A as boss
            'colleague' => 'colleague',       // A rates B as peer → B rates A as peer
            default => 'colleague'
        };

        // Delete any existing reverse relation first
        DB::table('user_relation')
            ->where('user_id', $targetId)
            ->where('target_id', $userId)
            ->where('organization_id', $organizationId)
            ->whereIn('type', ['colleague', 'subordinate', 'superior'])
            ->delete();

        // Prepare reverse relation
        $reversesToCreate[] = [
            'user_id' => $targetId,
            'target_id' => $userId,
            'organization_id' => $organizationId,
            'type' => $reverseType,
        ];
        
        Log::info('Creating reverse relation', [
            'from' => $targetId,
            'to' => $userId,
            'type' => $reverseType,
            'because_forward_was' => $type
        ]);
    }

    // Insert all reverse relations
    if (!empty($reversesToCreate)) {
        DB::table('user_relation')->insert($reversesToCreate);
        Log::info('Applied bidirectional relations', ['count' => count($reversesToCreate)]);
    }
}

    public function getEmployeeCompetencies(Request $request){

        $request->validate([
        'id' => 'required|integer|exists:user,id'
    ]);

    $orgId = session('org_id');
    $user = User::findOrFail($request->id);

    $belongsToOrg = $user->organizations()->where('organization_id', $orgId)->exists();
    
    if (!$belongsToOrg) {
        abort(403, 'User does not belong to your organization');
    }

    // Get all competencies with their sources
    $competenciesWithSources = DB::table('user_competency as uc')
        ->join('competency as c', 'uc.competency_id', '=', 'c.id')
        ->leftJoin('user_competency_sources as ucs', function($join) use ($orgId) {
            $join->on('ucs.user_id', '=', 'uc.user_id')
                 ->on('ucs.competency_id', '=', 'uc.competency_id')
                 ->where('ucs.organization_id', '=', $orgId);
        })
        ->leftJoin('competency_groups as cg', function($join) {
            $join->on('ucs.source_id', '=', 'cg.id')
                 ->where('ucs.source_type', '=', 'group');
        })
        ->where('uc.user_id', $user->id)
        ->where('uc.organization_id', $orgId)
        ->whereNull('c.removed_at')
        ->select(
            'c.id',
            'c.name',
            DB::raw('GROUP_CONCAT(DISTINCT ucs.source_type) as source_types'),
            DB::raw('GROUP_CONCAT(DISTINCT CASE WHEN ucs.source_type = "group" THEN cg.name END SEPARATOR ", ") as group_names'),
            DB::raw('GROUP_CONCAT(DISTINCT CASE WHEN ucs.source_type = "group" THEN cg.id END) as group_ids')
        )
        ->groupBy('c.id', 'c.name')
        ->orderBy('c.name')
        ->get();

    // Transform the data for frontend
    return $competenciesWithSources->map(function($item) {
        $sourceTypes = $item->source_types ? explode(',', $item->source_types) : [];
        
        return [
            'id' => $item->id,
            'name' => $item->name,
            'is_manual' => in_array('manual', $sourceTypes),
            'is_from_group' => in_array('group', $sourceTypes),
            'group_names' => $item->group_names ? $item->group_names : null,
            'group_ids' => $item->group_ids ? explode(',', $item->group_ids) : []
        ];
    });
}

    public function saveEmployeeCompetencies(Request $request){
    $user = User::findOrFail($request->id);

    // kötelező: legyen mit menteni
    $ids = collect($request->competencies ?? [])->unique()->values();
    if ($ids->isEmpty()) return abort(403);

    $orgId = session('org_id');

    // csak az aktuális org + globális kompetenciák engedettek (competency.organization_id NULL vagy = $orgId)
    $validIds = Competency::whereNull('removed_at')
        ->whereIn('id', $ids)
        ->where(function($q) use ($orgId){
            $q->whereNull('organization_id')->orWhere('organization_id', $orgId);
        })
        ->pluck('id');

    $invalid = $ids->diff($validIds);
    if ($invalid->isNotEmpty()) {
        return response()->json([
            'message' => 'Érvénytelen kompetencia az aktuális szervezethez.',
            'errors' => ['competencies' => ['Érvénytelen kompetencia.']]
        ], 422);
    }

    try {
        DB::transaction(function() use ($user, $validIds, $orgId) {
            // Get current competencies from user_competency
            $currentCompIds = DB::table('user_competency')
                ->where('user_id', $user->id)
                ->where('organization_id', $orgId)
                ->pluck('competency_id')
                ->toArray();
            
            // Find competencies to ADD (new ones not in current)
            $toAdd = array_diff($validIds->toArray(), $currentCompIds);
            
            // Find competencies to REMOVE (current ones not in new list)
            $toRemove = array_diff($currentCompIds, $validIds->toArray());
            
            // === ADD NEW COMPETENCIES ===
            foreach ($toAdd as $compId) {
                // Add to user_competency
                DB::table('user_competency')->insertOrIgnore([
                    'user_id' => $user->id,
                    'competency_id' => $compId,
                    'organization_id' => $orgId
                ]);
                
                // Track as manual source
                DB::table('user_competency_sources')->insertOrIgnore([
                    'user_id' => $user->id,
                    'competency_id' => $compId,
                    'organization_id' => $orgId,
                    'source_type' => 'manual',
                    'source_id' => null,
                    'created_at' => now()
                ]);
            }
            
            // === REMOVE COMPETENCIES (smart removal) ===
            foreach ($toRemove as $compId) {
                // First, remove the manual source if it exists
                DB::table('user_competency_sources')
                    ->where('user_id', $user->id)
                    ->where('competency_id', $compId)
                    ->where('organization_id', $orgId)
                    ->where('source_type', 'manual')
                    ->delete();
                
                // Check if there are any remaining sources (e.g., from groups)
                $hasOtherSources = DB::table('user_competency_sources')
                    ->where('user_id', $user->id)
                    ->where('competency_id', $compId)
                    ->where('organization_id', $orgId)
                    ->exists();
                
                // Only remove from user_competency if NO other sources exist
                if (!$hasOtherSources) {
                    DB::table('user_competency')
                        ->where('user_id', $user->id)
                        ->where('competency_id', $compId)
                        ->where('organization_id', $orgId)
                        ->delete();
                }
            }
        });
        
        return response()->json(['message' => 'Sikeres mentés.']);
        
    } catch (\Throwable $e) {
        Log::error('Hiba a kompetencia mentés közben', [
            'user_id' => $user->id,
            'org_id' => $orgId,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Sikertelen mentés: belső hiba!',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function getBonusMalus(Request $request)
    {
        // SECURITY FIX: Add validation
        $request->validate([
            'id' => 'required|integer|exists:user,id'
        ]);

        $user = User::findOrFail($request->id);
        
        // SECURITY: Verify user belongs to current org
        $orgId = session('org_id');
        $belongsToOrg = $user->organizations()->where('organization_id', $orgId)->exists();
        
        if (!$belongsToOrg) {
            abort(403, 'User does not belong to your organization');
        }
        
        return $user->bonusMalus()->take(4)->get();
    }

    public function setBonusMalus(Request $request){
    $user  = User::findOrFail($request->id);
    $orgId = (int) session('org_id');

    $this->validate($request, [
        'level' => ['required','numeric','min:1','max:15'], // numeric helyett integer
    ], [], [
        'level' => __('global.bonus-malus'),
    ]);

    AjaxService::DBTransaction(function() use ($request, $user, $orgId) {
        // FONTOS: org szűrés is!
        UserBonusMalus::where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->where('month', date('Y-m-01'))
            ->delete();

        $user->bonusMalus()->create([
            'level'           => (int)$request->level,
            'organization_id' => $orgId,
            'month'           => date('Y-m-01'),
        ]);
    });

    return response()->json(['ok' => true]);
}

    //RÉSZLEGKEZELÉS//

   public function storeDepartment(Request $request)
{
    $orgId = (int) session('org_id');

    if (!\App\Services\OrgConfigService::getBool($orgId, 'enable_multi_level', false)) {
        return response()->json(['message' => 'A többszintű részlegkezelés nincs bekapcsolva.'], 422);
    }

    $data = $request->validate([
        'name' => ['required','string','max:255'],
        'manager_ids' => ['required','array','min:1'],
        'manager_ids.*' => ['required','integer'],
    ]);

    // Check all managers are valid
    foreach ($data['manager_ids'] as $managerId) {
        // Manager validation: organization member and type='manager'
        $manager = \DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('u.id', $managerId)
            ->where('u.type', 'manager')
            ->whereNull('u.removed_at')
            ->first(['u.id']);

        if (!$manager) {
            return response()->json(['message' => "Manager ID {$managerId} nem található vagy nem manager típusú."], 422);
        }

        // Check if manager is already managing another department
        $alreadyManaging = \DB::table('organization_department_managers as odm')
            ->join('organization_departments as d', 'd.id', '=', 'odm.department_id')
            ->where('d.organization_id', $orgId)
            ->where('odm.manager_id', $managerId)
            ->whereNull('d.removed_at')
            ->exists();

        if ($alreadyManaging) {
            $managerName = \DB::table('user')->where('id', $managerId)->value('name');
            return response()->json(['message' => "A vezető ({$managerName}) már egy másik részleget vezet."], 422);
        }
    }

    try {
        \DB::transaction(function() use ($orgId, $data) {
            // 1. Create department
            $deptId = \DB::table('organization_departments')->insertGetId([
                'organization_id' => $orgId,
                'department_name' => $data['name'],
                'created_at' => now(),
                'removed_at' => null,
            ]);

            // 2. Assign managers
            foreach ($data['manager_ids'] as $managerId) {
                \DB::table('organization_department_managers')->insert([
                    'organization_id' => $orgId,
                    'department_id' => $deptId,
                    'manager_id' => $managerId,
                    'created_at' => now(),
                ]);
            }
        });

        return response()->json(['ok' => true, 'message' => 'Részleg létrehozva.']);
    } catch (\Exception $e) {
        \Log::error('Department creation failed', [
            'organization_id' => $orgId,
            'error' => $e->getMessage(),
        ]);
        return response()->json(['message' => 'Hiba történt a részleg létrehozásakor.'], 500);
    }
}

public function getDepartment(Request $request)
{
    $request->validate([
        'id' => 'required|integer'
    ]);

    $orgId = (int) session('org_id');

    $data = $request->validate([
        'id' => ['required','integer'],
    ]);

    // Get department basic data
    $dept = \DB::table('organization_departments as d')
        ->where('d.organization_id', $orgId)
        ->where('d.id', $data['id'])
        ->whereNull('d.removed_at')
        ->first([
            'd.id',
            'd.department_name',
            'd.created_at',
        ]);

    if (!$dept) {
        return response()->json([
            'message' => 'A részleg nem található (vagy már inaktiválva lett).'
        ], 404);
    }

    // Get current managers
    $currentManagers = \DB::table('organization_department_managers as odm')
        ->join('user as u', 'u.id', '=', 'odm.manager_id')
        ->where('odm.department_id', $data['id'])
        ->whereNull('u.removed_at')
        ->orderBy('u.name')
        ->get([
            'u.id',
            'u.name',
            'u.email',
            'odm.created_at as assigned_at'
        ]);

    // Get eligible managers (those not managing any other department)
    $eligibleManagers = \DB::table('user as u')
        ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
        ->where('ou.organization_id', $orgId)
        ->where('u.type', 'manager')
        ->whereNull('u.removed_at')
        ->whereNotExists(function($q) use ($orgId, $data) {
            $q->from('organization_department_managers as odm')
              ->join('organization_departments as d', 'd.id', '=', 'odm.department_id')
              ->whereColumn('odm.manager_id', 'u.id')
              ->where('d.organization_id', $orgId)
              ->where('d.id', '!=', $data['id']) // Allow current department managers
              ->whereNull('d.removed_at');
        })
        ->orderBy('u.name')
        ->get(['u.id','u.name','u.email']);

    return response()->json([
        'department' => $dept,
        'currentManagers' => $currentManagers,
        'eligibleManagers' => $eligibleManagers,
    ]);
}

public function updateDepartment(Request $request)
{
    $orgId = (int) session('org_id');

    if (!\App\Services\OrgConfigService::getBool($orgId, 'enable_multi_level', false)) {
        return response()->json(['message' => 'A többszintű részlegkezelés nincs bekapcsolva.'], 422);
    }

    $data = $request->validate([
        'id' => ['required','integer'],
        'name' => ['required','string','max:255'],
        'manager_ids' => ['required','array','min:1'],
        'manager_ids.*' => ['required','integer'],
    ]);

    $dept = \DB::table('organization_departments')
        ->where('organization_id', $orgId)
        ->where('id', $data['id'])
        ->whereNull('removed_at')
        ->first(['id']);

    if (!$dept) {
        return response()->json(['message' => 'A részleg nem található (vagy már inaktiválva lett).'], 404);
    }

    // Validate all managers
    foreach ($data['manager_ids'] as $managerId) {
        // Check if manager exists and is valid
        $manager = \DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('u.id', $managerId)
            ->where('u.type', 'manager')
            ->whereNull('u.removed_at')
            ->first(['u.id']);

        if (!$manager) {
            return response()->json(['message' => "Manager ID {$managerId} nem található vagy nem manager típusú."], 422);
        }

        // Check if manager is managing another department (not this one)
        $alreadyManaging = \DB::table('organization_department_managers as odm')
            ->join('organization_departments as d', 'd.id', '=', 'odm.department_id')
            ->where('d.organization_id', $orgId)
            ->where('odm.manager_id', $managerId)
            ->where('d.id', '!=', $data['id']) // Different department
            ->whereNull('d.removed_at')
            ->exists();

        if ($alreadyManaging) {
            $managerName = \DB::table('user')->where('id', $managerId)->value('name');
            return response()->json(['message' => "A vezető ({$managerName}) már egy másik részleget vezet."], 422);
        }
    }

    try {
        \DB::transaction(function() use ($orgId, $data) {
            // 1. Update department name
            \DB::table('organization_departments')
                ->where('organization_id', $orgId)
                ->where('id', $data['id'])
                ->update([
                    'department_name' => $data['name'],
                ]);

            // 2. Remove all current managers
            \DB::table('organization_department_managers')
                ->where('department_id', $data['id'])
                ->delete();

            // 3. Add new managers
            foreach ($data['manager_ids'] as $managerId) {
                \DB::table('organization_department_managers')->insert([
                    'organization_id' => $orgId,
                    'department_id' => $data['id'],
                    'manager_id' => $managerId,
                    'created_at' => now(),
                ]);
            }
        });

        return response()->json(['ok' => true, 'message' => 'Részleg frissítve.']);
    } catch (\Exception $e) {
        \Log::error('Department update failed', [
            'department_id' => $data['id'],
            'organization_id' => $orgId,
            'error' => $e->getMessage(),
        ]);
        return response()->json(['message' => 'Hiba történt a részleg frissítésekor.'], 500);
    }
}

public function getDepartmentMembers(Request $request)
{
    $request->validate([
        'department_id' => 'required|integer'
    ]);

    $orgId = (int) session('org_id');

    $data = $request->validate([
        'department_id' => ['required','integer'],
    ]);

    // Ellenőrizzük, hogy ez a részleg ebben az orgban van és aktív
    $dept = \DB::table('organization_departments')
        ->where('id', $data['department_id'])
        ->where('organization_id', $orgId)
        ->whereNull('removed_at')
        ->first(['id']);
    if (!$dept) {
        return response()->json(['message' => 'A részleg nem található az aktuális szervezetben.'], 404);
    }

    // Tagok lekérése az org_user pivotból
    $members = \DB::table('organization_user as ou')
        ->join('user as u', 'u.id', '=', 'ou.user_id')
        ->where('ou.organization_id', $orgId)
        ->where('ou.department_id', $data['department_id'])
        ->whereNull('u.removed_at')
        ->orderBy('u.name')
        ->get([
            'u.id', 'u.name', 'u.email'
        ]);

    return response()->json([
        'department_id' => $dept->id,
        'members' => $members,
    ]);
}

public function getEligibleForDepartment(Request $request)
{
    $orgId = (int) session('org_id');

    $data = $request->validate([
        'department_id' => ['required','integer'],
    ]);

    // valid részleg?
    $dept = \DB::table('organization_departments')
        ->where('id', $data['department_id'])
        ->where('organization_id', $orgId)
        ->whereNull('removed_at')
        ->first(['id']);
    if (!$dept) {
        return response()->json(['message' => 'A részleg nem található az aktuális szervezetben.'], 404);
    }

    // Választható dolgozók: az org tagjai, type != admin && != manager && != ceo? (kérésed szerint admin/manager kizárva; CEO-t hagyjuk ki logikusan)
    $eligible = \DB::table('user as u')
        ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
        ->where('ou.organization_id', $orgId)
        ->whereNull('u.removed_at')
        ->whereNotIn('u.type', ['admin','manager','ceo'])
        ->whereNull('ou.department_id') // még nincs részleghez rendelve
        ->orderBy('u.name')
        ->get(['u.id','u.name','u.email']);

    // A select-modal a teljes tömböt várja
    return response()->json($eligible);
}

public function saveDepartmentMembers(Request $request)
{
    $orgId = (int) session('org_id');

    $data = $request->validate([
        'department_id' => ['required','integer'],
        'user_ids'      => ['present','array'], // FIXED: Changed from 'required' to 'present' to allow empty arrays
        'user_ids.*'    => ['integer'],
    ]);

    // valid részleg ebben az orgban?
    $dept = \DB::table('organization_departments')
        ->where('id', $data['department_id'])
        ->where('organization_id', $orgId)
        ->whereNull('removed_at')
        ->first(['id']);
    if (!$dept) {
        return response()->json(['message' => 'A részleg nem található az aktuális szervezetben.'], 404);
    }

    $ids = collect($data['user_ids'])->unique()->values(); // Now handles empty arrays properly

    // validáljuk: mind a user-ek ebben az orgban vannak, és nem admin/manager/ceo
    // Only validate if there are IDs to validate
    if ($ids->isNotEmpty()) {
        $validIds = \DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->whereIn('u.id', $ids)
            ->whereNull('u.removed_at')
            ->whereNotIn('u.type', ['admin','manager','ceo'])
            ->pluck('u.id');

        $invalid = $ids->diff($validIds);
        if ($invalid->isNotEmpty()) {
            return response()->json([
                'message' => 'Érvénytelen felhasználó az aktuális szervezethez.',
                'invalid' => $invalid->values(),
            ], 422);
        }
    } else {
        // Empty array - we'll remove all members
        $validIds = collect();
    }

    \DB::transaction(function() use ($orgId, $dept, $validIds) {
        // 1) Az adott részlegből eltávolítunk mindenkit, aki eddig tag volt, de most nincs a listában
        \DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('department_id', $dept->id)
            ->whereNotIn('user_id', $validIds->toArray()) // Now properly handles empty arrays
            ->update(['department_id' => null]);

        // 2) Az új listában szereplőket ehhez a részleghez rendeljük — csak azokat, akiknél jelenleg NULL
        if ($validIds->isNotEmpty()) {
            \DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->whereIn('user_id', $validIds->toArray())
                ->whereNull('department_id') // ne mozgassunk máshonnan
                ->update(['department_id' => $dept->id]);
        }
    });

    return response()->json(['ok' => true, 'message' => 'Részleg tagjai frissítve.']);
}

public function deleteDepartment(Request $request)
{
    // SECURITY FIX: Use Laravel validation instead of manual checks
    $validated = $request->validate([
        'id' => 'required|integer|min:1'
    ]);

    $orgId = (int) session('org_id');
    $deptId = $validated['id'];

    if (!$orgId) {
        return response()->json([
            'message' => 'Nincs kiválasztott szervezet.',
            'errors' => ['org' => ['Nincs kiválasztott szervezet.']]
        ], 422);
    }

    // CRITICAL FIX: Check department ownership BEFORE transaction
    $department = DB::table('organization_departments')
        ->where('id', $deptId)
        ->where('organization_id', $orgId)
        ->whereNull('removed_at')
        ->first();

    if (!$department) {
        Log::warning('Attempted to delete non-existent or wrong organization department', [
            'department_id' => $deptId,
            'org_id' => $orgId,
            'admin_id' => session('uid')
        ]);

        return response()->json([
            'message' => 'A részleg nem található vagy nem a szervezetedhez tartozik.',
            'errors' => ['department' => ['A részleg nem található.']]
        ], 404);
    }

    try {
        // FIXED: Pass $department to the closure
        DB::transaction(function () use ($orgId, $deptId, $department) {
            // 1. Remove all members from the department
            DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('department_id', $deptId)
                ->update(['department_id' => null]);

            // 2. Remove all manager assignments
            DB::table('organization_department_managers')
                ->where('department_id', $deptId)
                ->where('organization_id', $orgId)
                ->delete();

            // 3. Mark the department as removed (soft delete)
            DB::table('organization_departments')
                ->where('id', $deptId)
                ->where('organization_id', $orgId)
                ->update(['removed_at' => now()]);
        });

        Log::info('Department deleted successfully', [
            'department_id' => $deptId,
            'department_name' => $department->department_name,
            'org_id' => $orgId,
            'deleted_by' => session('uid')
        ]);

        return response()->json([
            'message' => 'A részleg sikeresen törölve lett. Minden felhasználó eltávolításra került a részlegből.',
            'success' => true
        ]);

    } catch (\Throwable $e) {
        Log::error('Department deletion failed', [
            'department_id' => $deptId,
            'organization_id' => $orgId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Hiba történt a részleg törlésekor.',
            'errors' => ['general' => ['A részleg törlése sikertelen volt.']]
        ], 500);
    }
}


    public function getNetworkData(Request $request)
{
    $request->validate([
        'user_id' => 'nullable|integer|exists:user,id'
    ]);

    $orgId = (int) session('org_id');

    if ($request->has('user_id')) {
        $user = User::find($request->user_id);
        if ($user) {
            $belongsToOrg = $user->organizations()->where('organization_id', $orgId)->exists();
            if (!$belongsToOrg) {
                abort(403, 'User does not belong to your organization');
            }
        }
    }
    
    try {
        // UPDATED: Get all users in the organization with proper multi-manager support
        $users = DB::table('organization_user as ou')
            ->join('user as u', 'u.id', '=', 'ou.user_id')
            ->leftJoin('organization_departments as d', function($join) {
                $join->on('d.id', '=', 'ou.department_id')
                     ->whereNull('d.removed_at');
            })
            // UPDATED: Check if user is a manager using the new table structure
            ->leftJoin('organization_department_managers as odm', function($join) use ($orgId) {
                $join->on('odm.manager_id', '=', 'u.id')
                     ->where('odm.organization_id', '=', $orgId);
            })
            ->leftJoin('organization_departments as managed_dept', function($join) {
                $join->on('managed_dept.id', '=', 'odm.department_id')
                     ->whereNull('managed_dept.removed_at');
            })
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->whereNotIn('u.type', ['admin']) // Exclude admins from network
            ->select([
                'u.id',
                'u.name', 
                'u.email',
                'u.type',
                'ou.department_id',
                'd.department_name',
                'managed_dept.id as managed_dept_id', // Department they manage
                'managed_dept.department_name as managed_dept_name'
            ])
            ->get();

        // Get rater counts for all users with login mode
        $userIds = $users->pluck('id');
        $raterCounts = $this->getRaterCounts($userIds, $orgId);

        // UPDATED: Get all relationships
        $relations = DB::table('user_relation as ur')
            ->join('user as u1', 'u1.id', '=', 'ur.user_id')
            ->join('user as u2', 'u2.id', '=', 'ur.target_id')
            ->join('organization_user as ou1', function($join) use ($orgId) {
                $join->on('ou1.user_id', '=', 'u1.id')
                     ->where('ou1.organization_id', '=', $orgId);
            })
            ->join('organization_user as ou2', function($join) use ($orgId) {
                $join->on('ou2.user_id', '=', 'u2.id')
                     ->where('ou2.organization_id', '=', $orgId);
            })
            ->where('ur.organization_id', $orgId)
            ->whereNull('u1.removed_at')
            ->whereNull('u2.removed_at')
            ->whereNotIn('u1.type', ['admin'])
            ->whereNotIn('u2.type', ['admin'])
            ->whereColumn('ur.user_id', '!=', 'ur.target_id') // Exclude self relations
            ->select([
                'ur.user_id',
                'ur.target_id', 
                'ur.type'
            ])
            ->get();

        // UPDATED: Get departments with multiple managers
        $departments = DB::table('organization_departments as d')
            ->leftJoin('organization_department_managers as odm', 'd.id', '=', 'odm.department_id')
            ->leftJoin('user as u', 'u.id', '=', 'odm.manager_id')
            ->where('d.organization_id', $orgId)
            ->whereNull('d.removed_at')
            ->whereNull('u.removed_at')
            ->select([
                'd.id', 
                'd.department_name',
                'u.id as manager_id',
                'u.name as manager_name'
            ])
            ->get()
            ->groupBy('id')
            ->map(function($deptManagers) {
                $first = $deptManagers->first();
                return [
                    'id' => $first->id,
                    'department_name' => $first->department_name,
                    'managers' => $deptManagers->filter(function($item) {
                        return !is_null($item->manager_id);
                    })->map(function($item) {
                        return [
                            'id' => $item->manager_id,
                            'name' => $item->manager_name
                        ];
                    })->values()->toArray()
                ];
            })
            ->values();

        // Transform users for frontend with proper login mode
        $networkNodes = $users->map(function($user) use ($raterCounts) {
            $raterCount = $raterCounts->get($user->id, 0);
            $raterStatus = $raterCount < 3 ? 'insufficient' : ($raterCount < 7 ? 'okay' : 'sufficient');
            
            
            
            return [
                'id' => 'user_' . $user->id,
                'user_id' => $user->id,
                'label' => $user->name,
                'email' => $user->email,
                'type' => $user->type,
                'department_id' => $user->department_id,
                'department_name' => $user->department_name,
                'managed_dept_id' => $user->managed_dept_id,
                'managed_dept_name' => $user->managed_dept_name, // ADDED
                'rater_count' => $raterCount,
                'rater_status' => $raterStatus,
                'is_manager' => !empty($user->managed_dept_id),
            ];
        });

        // Transform relationships for frontend
        $networkEdges = [];
        $relationshipPairs = [];

        foreach ($relations as $relation) {
            $sourceId = 'user_' . $relation->user_id;
            $targetId = 'user_' . $relation->target_id;
            $pairKey = min($sourceId, $targetId) . '_' . max($sourceId, $targetId);
            
            if (!isset($relationshipPairs[$pairKey])) {
                $relationshipPairs[$pairKey] = [
                    'source' => $sourceId,
                    'target' => $targetId,
                    'types' => [],
                    'bidirectional' => false
                ];
            }
            
            $relationshipPairs[$pairKey]['types'][] = $relation->type;
            
            // Check if we have the reverse relationship
            $reverseExists = $relations->contains(function($r) use ($relation) {
                return $r->user_id == $relation->target_id && $r->target_id == $relation->user_id;
            });
            
            if ($reverseExists) {
                $relationshipPairs[$pairKey]['bidirectional'] = true;
            }
        }

        // Create edges from relationship pairs
        $edgeId = 0;
        foreach ($relationshipPairs as $pair) {
            // Determine the strongest relationship type
            $types = $pair['types'];
            $strongestType = 'colleague'; // default
            
            if (in_array('superior', $types)) {
                $strongestType = 'superior';
            } elseif (in_array('subordinate', $types)) {
                $strongestType = 'subordinate';
            } elseif (in_array('colleague', $types)) {
                $strongestType = 'colleague';
            }
            
            $networkEdges[] = [
                'id' => 'edge_' . $edgeId++,
                'source' => $pair['source'],
                'target' => $pair['target'],
                'type' => $strongestType,
                'bidirectional' => $pair['bidirectional'],
                'types' => array_unique($pair['types']),
                'is_subordinate' => in_array('subordinate', $pair['types']) || in_array('superior', $pair['types'])
            ];
        }

        return response()->json([
            'nodes' => $networkNodes->values(),
            'edges' => $networkEdges,
            'departments' => $departments,
            'organization_id' => $orgId
        ]);

    } catch (\Exception $e) {
        Log::error('Network data fetch failed', [
            'organization_id' => $orgId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Failed to fetch network data: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * Check if a user email has any active lockout records
 * 
 * @param string $email
 * @return bool
 */
private function isUserLocked(string $email): bool
{
    // Check if there are ANY lockout records for this email (regardless of IP)
    $lockoutStatus = \App\Services\LoginAttemptService::getLockoutStatus($email);
    
    // User is considered locked if they have any locked IP addresses
    return !empty($lockoutStatus['locked']);
}

/**
 * Unlock a user account (admin action)
 * 
 * POST /admin/employee/unlock-account
 */
public function unlockAccount(Request $request)
{
    $request->validate([
        'user_id' => 'required|integer|exists:user,id'
    ]);

    $orgId = (int) session('org_id');
    $adminId = (int) session('uid');
    
    /** @var User $user */
    $user = User::findOrFail($request->input('user_id'));

    // Security: verify user belongs to current organization
    $inOrg = $user->organizations()->where('organization.id', $orgId)->exists();
    if (!$inOrg) {
        return response()->json([
            'ok' => false,
            'message' => 'A felhasználó nem tagja az aktuális szervezetnek.',
        ], 403);
    }

    try {
        // Clear all lockout attempts for this email
        $deletedRecords = \App\Services\LoginAttemptService::adminUnlock($user->email);

        Log::info('employee.account_unlocked', [
            'org_id' => $orgId,
            'user_id' => $user->id,
            'email' => $user->email,
            'by_admin' => $adminId,
            'deleted_records' => $deletedRecords,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Fiók feloldva. A felhasználó most már be tud jelentkezni.',
            'deleted_records' => $deletedRecords,
        ]);

    } catch (\Throwable $e) {
        Log::error('employee.account_unlock_failed', [
            'org_id' => $orgId,
            'user_id' => $user->id,
            'email' => $user->email,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Sikertelen művelet: szerverhiba!',
        ], 500);
    }
}


}