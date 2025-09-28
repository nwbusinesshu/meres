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

    // ===== LEGACY (régi) lista adatai – változatlan =====
    $users = \App\Services\UserService::getUsers()->map(function ($user) {
        // bonusMalus feltöltése biztonságosan (nincs eager load, nincs created_at ordering)
        $user['bonusMalus'] = $user->bonusMalus()->first()?->level ?? null;
        return $user;
    });

    // Add rater counts to legacy users
    $users = $this->addRaterCounts($users, $orgId);

    // multi-level flag
    $enableMultiLevel = \App\Services\OrgConfigService::getBool($orgId, 'enable_multi_level', false);
    $showBonusMalus = \App\Services\OrgConfigService::getBool($orgId, 'show_bonus_malus', true);

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
            ->get(['u.id', 'u.name', 'u.email', 'u.type', DB::raw("'—' as login_mode_text"), DB::raw("null as bonusMalus"), DB::raw("0 as rater_count"), 'ou.position']);

        // FIXED: Exclude admins AND already assigned managers from unassigned list
        $unassigned = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->whereNull('ou.department_id')
            ->where('u.type', '!=', 'ceo')
            ->where('u.type', '!=', 'admin')  // FIXED: Exclude admins
            // FIXED: Exclude managers who are already assigned to departments
            ->whereNotExists(function($query) use ($orgId) {
                $query->from('organization_department_managers as odm')
                      ->whereColumn('odm.manager_id', 'u.id')
                      ->where('odm.organization_id', $orgId);
            })
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email', 'u.type', DB::raw("'—' as login_mode_text"), DB::raw("null as bonusMalus"), DB::raw("0 as rater_count"), 'ou.position']);

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
                // FIXED: Use created_at instead of assigned_at
                ->select('u.id', 'u.name', 'u.email', 'u.type', 'ou.position', 'odm.created_at as assigned_at')
                ->get();

            // Members
            $members = DB::table('user as u')
                ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
                ->where('ou.organization_id', $orgId)
                ->where('ou.department_id', $dept->id)
                ->where('ou.role', '!=', 'manager')
                ->whereNull('u.removed_at')
                ->orderBy('u.name')
                ->get(['u.id', 'u.name', 'u.email', 'u.type', DB::raw("'—' as login_mode_text"), DB::raw("null as bonusMalus"), DB::raw("0 as rater_count"), 'ou.position']);

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
            'selectedLanguages' => $selectedLanguages, // ✅ CRITICAL FIX: Always pass this
        ]);
    }

    // ===== LEGACY VIEW (multi-level OFF) =====
    return view('admin.employees', [
        'users'             => $users,
        'enableMultiLevel'  => false,
        'showBonusMalus'    => $showBonusMalus,
        'selectedLanguages' => $selectedLanguages, // ✅ CRITICAL FIX: Always pass this
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
    private function addRaterCounts($users, $orgId)
{
    $userIds = $users->pluck('id')->toArray();
    if (empty($userIds)) {
        return $users;
    }

    // Értékelők (rater) darabszáma célonként
    $raterCounts = DB::table('user_relation as ur')
        ->join('user as rater', 'rater.id', '=', 'ur.user_id')
        ->whereIn('ur.target_id', $userIds)
        ->where('ur.organization_id', $orgId)
        ->whereNull('rater.removed_at')
        ->groupBy('ur.target_id')
        ->pluck(DB::raw('COUNT(*)'), 'ur.target_id');

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
        $user->rater_count = $raterCounts[$user->id] ?? 0;

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

    return DB::table('user_relation')
        ->whereIn('target_id', $userIds->toArray())
        ->where('organization_id', $orgId)
        ->groupBy('target_id')
        ->get(['target_id', DB::raw('COUNT(*) as rater_count')])
        ->pluck('rater_count', 'target_id');
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
    $orgId = (int) session('org_id');

    /** @var \App\Models\User $u */
    $u = \App\Models\User::findOrFail($request->id);

    // UPDATED: Check if user is a department manager using the new structure
    $isDeptManager = \DB::table('organization_department_managers as odm')
        ->join('organization_departments as d', 'd.id', '=', 'odm.department_id')
        ->where('d.organization_id', $orgId)
        ->where('odm.manager_id', $u->id)
        ->whereNull('d.removed_at')
        ->exists();

    // částleg-tag?
    $deptId = \DB::table('organization_user')
        ->where('organization_id', $orgId)
        ->where('user_id', $u->id)
        ->value('department_id'); // NULL ha nincs részleghez rendelve

    $position = DB::table('organization_user')
    ->where('organization_id', $orgId)
    ->where('user_id', $u->id)
    ->value('position');

    return response()->json([
        'id'                => $u->id,
        'name'              => $u->name,
        'email'             => $u->email,
        'type'              => $u->type,            // 'normal' | 'manager' | 'ceo'
        'has_auto_level_up' => (int) $u->has_auto_level_up,

        'is_dept_manager'   => (bool) $isDeptManager,
        'is_in_department'  => !is_null($deptId),
        'position' => $position,
        'department_id'     => $deptId,             // opcionális, but useful for UI
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


        // Ellenőrizd, hogy az e-mail már létezik-e bármely usernél (és nincs törölve)
    $existingUser = User::where('email', $request->email)
                        ->whereNull('removed_at')
                        ->first();

    // Ha user van, nézd meg a tagságát!
    if ($existingUser) {
        $alreadyInOrg = $existingUser->organizations()->where('organization_id', $orgId)->exists();

        // Már ebben a cégben van? Akkor lehet update (de NEM hozhatsz létre még egyet)
        if (!$alreadyInOrg) {
            // LÉNYEG: Ha máshol tag, NEM engedjük!
            return response()->json([
                'message' => 'Ez az e-mail cím már egy másik szervezethez tartozik!',
                'errors'  => ['email' => ['Ez az e-mail cím már egy másik szervezethez tartozik!']]
            ], 422);
        }
}


    /** @var User|null $user */
    $user    = User::find($request->id);
    $created = false;

    // VALIDÁCIÓ (gmail-regex NINCS; unique csak élő userre)
    $rules = [
        'name'        => ['required', 'string', 'max:255'],
        'email'       => [
            'required',
            'email:rfc',
            Rule::unique('user', 'email')
                ->ignore($request->id)
                ->where(function ($q) { $q->whereNull('removed_at'); }),
        ],
        'type'        => ['required', Rule::in(['normal', 'manager', 'ceo'])],
        'autoLevelUp' => ['required', Rule::in([0, 1])],
        'position'    => ['nullable', 'string', 'max:255'],
    ];

    $attributes = [
        'name'        => __('global.name'),
        'email'       => __('global.email'),
        'type'        => __('global.type'),
        'autoLevelUp' => __('global.auto-level-up'),
    ];

    $validator = \Validator::make($request->all(), $rules, [], $attributes);
    if ($validator->fails()) {
        \Log::warning('employee.save.validation_failed', [
            'org_id'  => $orgId,
            'payload' => $request->all(),
            'errors'  => $validator->errors()->toArray(),
        ]);

        return response()->json([
            'message' => 'Hibás adatok.',
            'errors'  => $validator->errors()->toArray(),
        ], 422);
    }

    // ---- Szerver oldali típusváltás-védelem (részleg-tagság alapján) ----
if ($user) {
    $requestedType = $request->type;   // amit a kliens kér
    $currentType   = $user->type;      // ami most van

    // Részleg-tagság (member) az aktuális orgban
    $hasDept = DB::table('organization_user')
        ->where('organization_id', $orgId)
        ->where('user_id', $user->id)
        ->whereNotNull('department_id')
        ->exists();

    // UPDATED: Check if user is department manager using new structure
    $isDeptManager = DB::table('organization_department_managers as odm')
        ->join('organization_departments as d', 'd.id', '=', 'odm.department_id')
        ->where('d.organization_id', $orgId)
        ->where('odm.manager_id', $user->id)
        ->whereNull('d.removed_at')
        ->exists();

    // Szabály 1: Ha manager és részleghez is van rendelve (vezetőként ÉS/VAGY tagként),
    // a típust nem lehet megváltoztatni (manager marad).
    if ($currentType === 'manager' && ($isDeptManager || $hasDept) && $requestedType !== 'manager') {
        return response()->json([
            'message' => 'A felhasználó menedzser és részleghez van rendelve; a típus nem módosítható.',
            'errors'  => ['type' => ['A felhasználó menedzser és részleghez van rendelve; a típus nem módosítható.']]
        ], 422);
    }

    // Szabály 2: Ha partial tag és normal típusú, a típus nem változtatható
    if ($hasDept && $currentType === 'normal' && $requestedType !== 'normal') {
        return response()->json([
            'message' => 'Ez a dolgozó már tagja egy részlegnek, ezért a típus nem módosítható.',
            'errors'  => ['type' => ['Ez a dolgozó már tagja egy részlegnek, ezért a típus nem módosítható.']]
        ], 422);
    }
}
// ---- /típusváltás-védelem ----


    try {
    AjaxService::DBTransaction(function () use ($request, $orgId, &$user, &$created) {

        \Log::info('employee.save.debug.branch', [
    'branch'  => is_null($user) ? 'CREATE' : 'UPDATE',
    'user_id' => optional($user)->id,
]);


        // 1) USER létrehozás / frissítés
        if (is_null($user)) {
            $created = true;

            $user = User::create([
                'name'              => $request->name,
                'email'             => $request->email,
                'type'              => $request->type,
                'has_auto_level_up' => (int) $request->autoLevelUp,
            ]);

            Log::info('employee.save.step_ok', ['step' => 'create_user', 'user_id' => $user->id]);
        } else {
            $user->name              = $request->name;
            $user->email             = $request->email;
            $user->type              = $request->type;
            $user->has_auto_level_up = (int) $request->autoLevelUp;
            $user->save();

            Log::info('employee.save.step_ok', ['step' => 'update_user', 'user_id' => $user->id]);
        }

        // 2) ORG tagság (EZT KELL ELŐBB!)
        $user->organizations()->syncWithoutDetaching([$orgId]);
        Log::info('employee.save.step_ok', ['step' => 'attach_org', 'user_id' => $user->id, 'org_id' => $orgId]);

        $position = trim((string) $request->input('position', ''));
        DB::table('organization_user')->updateOrInsert(
    ['organization_id' => $orgId, 'user_id' => $user->id],
    ['position' => ($position !== '' ? $position : null)]
);


        // 3) SELF relation – idempotens, az egyedi kulcsra szűrünk:
        UserRelation::updateOrCreate(
            [
                'organization_id' => $orgId,
                'user_id'         => $user->id,
                'target_id'       => $user->id,
            ],
            [
                'type'            => UserRelationType::SELF,  // ha már létezik, frissítjük erre
            ]
        );
        \Log::info('employee.save.step_ok', ['step' => 'create_self_relation', 'user_id' => $user->id, 'org_id' => $orgId]);

        // 4) Bonus/Malus (idempotens, reláción – user_id automatikus)
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
    });
        } catch (\Throwable $e) {
            \Log::error('employee.save.transaction_failed', [
                'org_id'  => $orgId,
                'user_id' => $user ? $user->id : null,
                'error'   => $e->getMessage(),
                'prev'    => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
            ]);

            return response()->json([
                'message' => 'Sikertelen művelet: szerverhiba!',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], 422);
        }



            // E-MAIL csak ÚJ usernél
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
                    ], 200);
                }
            }

            return response()->json([
                'ok'      => true,
                'user_id' => $user->id,
                'created' => $created,
            ], 200);
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



    public function removeEmployee(Request $request){
        $user = User::findOrFail($request->id);
        AjaxService::DBTransaction(function() use (&$user){
            $user->removed_at = date('Y-m-d H:i:s');
            $user->save();

            UserRelation::where('organization_id', session('org_id'))
                ->where(function($q) use ($user) {
                    $q->where('target_id', $user->id)
                      ->orWhere('user_id', $user->id);
                })
                ->delete();
        });
    }

    public function getAllEmployee(Request $request)
    {
        $query = User::whereNull('removed_at')
            ->where('type', '!=', UserType::ADMIN)
            ->whereHas('organizations', function ($q) {
                $q->where('organization_id', session('org_id'));
            });

        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->has('except') && is_array($request->except)) {
            $query->whereNotIn('id', $request->except);
        }

        // Ne jelenjen meg önmaga
        if ($request->has('id') && is_numeric($request->id)) {
            $query->where('id', '!=', $request->id);
        }

        return response()->json($query->select('id', 'name')->get());
    }


    public function getEmployeeRelations(Request $request)
    {
        $user = User::findOrFail($request->id);

        return $user->allRelations()
            ->with('target')
            ->where('organization_id', session('org_id'))   // ← ez kell
            ->whereHas('target.organizations', function ($q) {
                $q->where('organization_id', session('org_id'));
            })
            ->get();

    }

    public function saveEmployeeRelations(Request $request)
    {
        Log::info('Kapott adat', $request->all());

        $request->validate([
            'id' => 'required|integer|exists:user,id',
            'relations' => 'required|array|min:1',
            'relations.*.target_id' => 'required|integer|exists:user,id',
            'relations.*.type' => 'required|string|in:self,colleague,subordinate,superior',
        ]);

        $user = User::findOrFail($request->id);
        $relations = collect($request->input('relations'));

        // 💡 Feltételezzük, hogy a user legalább 1 szervezet tagja
        $organizationId = session('org_id'); // pontosan az aktív szervezet

        try {
            AjaxService::DBTransaction(function () use ($relations, $user, $organizationId) {

    // 1) CSAK a nem-SELF típusok törlése az adott usernél, explicit query-vel
    DB::table('user_relation')
        ->where('user_id', $user->id)
        ->where('organization_id', $organizationId)
        ->whereIn('type', ['colleague','subordinate','superior'])
        ->delete();

    // 2) SELF kapcsolat garantálása (idempotens)
    DB::table('user_relation')->insertOrIgnore([
        'user_id'         => $user->id,
        'target_id'       => $user->id,
        'organization_id' => $organizationId,
        'type'            => 'self',
    ]);

    // 3) Payload tisztítása: SELF kihagyása, önmagára mutató nem-SELF tiltása, duplikátumok kiszűrése
    $rows = $relations
        ->map(function ($item) use ($user, $organizationId) {
            return [
                'user_id'         => $user->id,
                'target_id'       => (int)($item['target_id'] ?? $item['target'] ?? 0),
                'organization_id' => $organizationId,
                'type'            => $item['type'] ?? null,
            ];
        })
        ->filter(function ($r) use ($user) {
            return in_array($r['type'], ['colleague','subordinate','superior'], true)
                && $r['target_id'] > 0
                && $r['target_id'] !== $user->id;
        })
        ->unique(fn($r) => $r['user_id'].'-'.$r['target_id'].'-'.$r['organization_id'].'-'.$r['type'])
        ->values()
        ->all();

    if (!empty($rows)) {
        DB::table('user_relation')->insertOrIgnore($rows);
    }
});

            return response()->json(['message' => 'Sikeres mentés.']);
        } catch (\Throwable $e) {
            Log::error('Hiba a relation mentés közben', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Sikertelen mentés: belső hiba!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getEmployeeCompetencies(Request $request){
        $orgId = session('org_id');
        $user = User::findOrFail($request->id);

        // ha belongsToMany pivotot használsz:
        return $user->competencies()
            ->wherePivot('organization_id', $orgId)
            ->get(['competency.id','competency.name']);
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
                'invalid' => $invalid->values(),
            ], 422);
        }

        \DB::transaction(function() use ($user, $orgId, $validIds) {
            // csak az adott orgra törlünk
            \DB::table('user_competency')
                ->where('user_id', $user->id)
                ->where('organization_id', $orgId)
                ->delete();

            // beszúrás org‑gal
            foreach ($validIds as $cid) {
                \DB::table('user_competency')->insert([
                    'user_id' => $user->id,
                    'organization_id' => $orgId,
                    'competency_id' => $cid,
                ]);
            }
        });
    }

    public function getBonusMalus(Request $request){
        return User::findOrFail($request->id)->bonusMalus->take(4);
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
    $orgId = (int) session('org_id');
    $deptId = (int) $request->input('id');
    
    if (!$deptId) {
        return response()->json([
            'message' => 'Nincs megadva részleg ID.',
            'errors' => ['id' => ['Részleg ID kötelező.']],
        ], 422);
    }

    try {
        DB::transaction(function () use ($orgId, $deptId) {
            // Check if department exists and belongs to the organization
            $department = DB::table('organization_departments')
                ->where('id', $deptId)
                ->where('organization_id', $orgId)
                ->whereNull('removed_at')
                ->first();

            if (!$department) {
                throw new \Exception('A részleg nem található vagy már törölve lett.');
            }

            // 1. Remove all members from the department
            DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('department_id', $deptId)
                ->update(['department_id' => null]);

            // 2. Remove all manager assignments
            DB::table('organization_department_managers')
                ->where('department_id', $deptId)
                ->delete();

            // 3. Mark the department as removed (soft delete)
            DB::table('organization_departments')
                ->where('id', $deptId)
                ->where('organization_id', $orgId)
                ->update(['removed_at' => now()]);
        });

        return response()->json([
            'message' => 'A részleg sikeresen törölve lett. Minden felhasználó eltávolításra került a részlegből.',
            'success' => true
        ]);

    } catch (\Exception $e) {
        Log::error('Department deletion failed', [
            'department_id' => $deptId,
            'organization_id' => $orgId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Hiba történt a részleg törlésekor: ' . $e->getMessage(),
            'errors' => ['general' => ['A részleg törlése sikertelen volt.']],
        ], 500);
    }
}


    public function getNetworkData(Request $request)
{
    $orgId = (int) session('org_id');
    
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


}