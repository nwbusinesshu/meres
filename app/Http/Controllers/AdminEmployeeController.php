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

    // meglévő user-lista (változatlan logikával)
    $users = UserService::getUsers()->map(function($user){
        $user['bonusMalus'] = $user->bonusMalus()->first()?->level ?? null;
        return $user;
    });

    // multi-level flag
    $enableMultiLevel = \App\Services\OrgConfigService::getBool($orgId, 'enable_multi_level', false);

    // részlegek és választható managerek
    $departments = collect();
    $eligibleManagers = collect();

    if ($enableMultiLevel) {
        // aktív részlegek listája a nézethez
        $departments = \DB::table('organization_departments as d')
            ->leftJoin('user as u', 'u.id', '=', 'd.manager_id')   // tábla neve: user
            ->where('d.organization_id', $orgId)
            ->whereNull('d.removed_at')
            ->orderBy('d.department_name')
            ->get([
                'd.id',
                'd.department_name',
                'd.manager_id',
                'u.name  as manager_name',
                'u.email as manager_email',
                'd.created_at',
            ]);

        // olyan managerek, akik az org tagjai és MÉG nem vezetnek aktív részleget
        $eligibleManagers = \DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('u.type', 'manager')
            ->whereNull('u.removed_at')
            ->whereNotExists(function($q) use ($orgId) {
                $q->from('organization_departments as d')
                  ->whereColumn('d.manager_id', 'u.id')
                  ->where('d.organization_id', $orgId)
                  ->whereNull('d.removed_at');
            })
            ->orderBy('u.name')
            ->get(['u.id','u.name','u.email']);
    }

    return view('admin.employees', [
        "users"            => $users,
        "enableMultiLevel" => $enableMultiLevel,
        "departments"      => $departments,
        "eligibleManagers" => $eligibleManagers,
    ]);
}

    public function getEmployee(Request $request){
        return User::findOrFail($request->id);
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

    try {
    AjaxService::DBTransaction(function () use ($request, $orgId, &$user, &$created) {

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

        // 3) SELF relation (tagság után)
        $user->relations()->firstOrCreate([
            'target_id'       => $user->id,
            'type'            => UserRelationType::SELF, // 'self'
            'organization_id' => $orgId,
        ]);
        Log::info('employee.save.step_ok', ['step' => 'create_self_relation', 'user_id' => $user->id, 'org_id' => $orgId]);

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
            'relations.*.target' => 'required|integer|exists:user,id',
            'relations.*.type' => 'required|string|in:self,colleague,subordinate,superior',
        ]);

        $user = User::findOrFail($request->id);
        $relations = collect($request->input('relations'));

        // 💡 Feltételezzük, hogy a user legalább 1 szervezet tagja
        $organizationId = session('org_id'); // pontosan az aktív szervezet

        try {
            AjaxService::DBTransaction(function () use ($relations, $user, $organizationId) {
                // korábbi kapcsolatok törlése
                $user->allRelations()
                    ->where('organization_id', $organizationId)
                    ->delete();


                $relations->each(function ($item) use ($user, $organizationId) {
                    Log::info('Creating relation', [
                        'user' => $user->id,
                        'target' => $item['target'],
                        'type' => $item['type'],
                        'organization_id' => $organizationId,
                    ]);

                    $user->relations()->create([
                        'target_id' => $item['target'],
                        'type' => $item['type'],
                        'organization_id' => $organizationId,
                    ]);
                });
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
        $user = User::findOrFail($request->id);
       
        $rules = [
            "level" => ['required', 'numeric', 'min:1', 'max:15']
        ];

        $attributes = [
            "level" => __('global.bonus-malus'),
        ];
    
        $this->validate(
            request: $request,
            rules: $rules,
            customAttributes: $attributes,
        ); 

        AjaxService::DBTransaction(function() use ($request, &$user){
            UserBonusMalus::where('user_id', $user->id)->where('month', date('Y-m-01'))->delete();
            $user->bonusMalus()->create([
                "level" => $request->level,
                "month" => date('Y-m-01')
            ]);
        });
    }

    //RÉSZLEGKEZELÉS//

    public function storeDepartment(Request $request)
{
    $orgId = (int) session('org_id');

    // csak ha be van kapcsolva a multi-level
    if (!\App\Services\OrgConfigService::getBool($orgId, 'enable_multi_level', false)) {
        return back()->with('error', 'A többszintű részlegkezelés nincs bekapcsolva.');
    }

    $data = $request->validate([
        'department_name' => ['required','string','max:255'],
        'manager_id'      => ['required','integer'],
    ]);

    // manager ellenőrzés: az adott org tagja és type='manager'
    $manager = \DB::table('user as u')
        ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
        ->where('ou.organization_id', $orgId)
        ->where('u.id', $data['manager_id'])
        ->where('u.type', 'manager')
        ->first(['u.id']);

    if (!$manager) {
        return back()->with('error', 'Csak olyan vezető választható, aki manager és az adott szervezet tagja.');
    }

    // ne vezessen már aktív részleget
    $already = \DB::table('organization_departments')
        ->where('organization_id', $orgId)
        ->where('manager_id', $data['manager_id'])
        ->whereNull('removed_at')
        ->exists();

    if ($already) {
        return back()->with('error', 'Ez a vezető már egy részleget vezet.');
    }

    // beszúrás
    \DB::table('organization_departments')->insert([
        'organization_id' => $orgId,
        'department_name' => $data['department_name'],
        'manager_id'      => $data['manager_id'],
        'created_at'      => now(),
        'removed_at'      => null,
    ]);

    return back()->with('success', 'Részleg létrehozva.');
}

public function getDepartment(Request $request)
{
    $orgId = (int) session('org_id');

    $data = $request->validate([
        'id' => ['required','integer'],
    ]);

    // alap adatok + manager adatok
    $dept = \DB::table('organization_departments as d')
        ->leftJoin('user as u', 'u.id', '=', 'd.manager_id')
        ->where('d.organization_id', $orgId)
        ->where('d.id', $data['id'])
        ->whereNull('d.removed_at')
        ->first([
            'd.id',
            'd.department_name',
            'd.manager_id',
            'u.name  as manager_name',
            'u.email as manager_email',
            'd.created_at',
        ]);

    if (!$dept) {
        return response()->json([
            'message' => 'A részleg nem található (vagy már inaktiválva lett).'
        ], 404);
    }

    // Választható managerek: akik org tagok, type='manager', nincs aktív részlegük
    // + a JELENLEGI manager mindig legyen benne a listában (különben nem tudnánk visszaállni)
    $eligible = \DB::table('user as u')
        ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
        ->where('ou.organization_id', $orgId)
        ->where('u.type', 'manager')
        ->whereNull('u.removed_at')
        ->where(function($q) use ($orgId, $dept) {
            $q->whereNotExists(function($q2) use ($orgId) {
                $q2->from('organization_departments as d2')
                   ->whereColumn('d2.manager_id', 'u.id')
                   ->where('d2.organization_id', $orgId)
                   ->whereNull('d2.removed_at');
            })
            ->orWhere('u.id', $dept->manager_id); // jelenlegi manager
        })
        ->orderBy('u.name')
        ->get(['u.id','u.name','u.email']);

    return response()->json([
        'department' => $dept,
        'eligibleManagers' => $eligible,
    ]);
}

public function updateDepartment(Request $request)
{
    $orgId = (int) session('org_id');

    if (!\App\Services\OrgConfigService::getBool($orgId, 'enable_multi_level', false)) {
        return response()->json(['message' => 'A többszintű részlegkezelés nincs bekapcsolva.'], 422);
    }

    $data = $request->validate([
        'id'              => ['required','integer'],
        'department_name' => ['required','string','max:255'],
        'manager_id'      => ['required','integer'],
    ]);

    $dept = \DB::table('organization_departments')
        ->where('organization_id', $orgId)
        ->where('id', $data['id'])
        ->whereNull('removed_at')
        ->first(['id','manager_id']);

    if (!$dept) {
        return response()->json(['message' => 'A részleg nem található (vagy már inaktiválva lett).'], 404);
    }

    // ellenőrzés: a választott manager org tag, type='manager'
    $manager = \DB::table('user as u')
        ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
        ->where('ou.organization_id', $orgId)
        ->where('u.id', $data['manager_id'])
        ->where('u.type', 'manager')
        ->whereNull('u.removed_at')
        ->first(['u.id']);

    if (!$manager) {
        return response()->json(['message' => 'Csak olyan manager választható, aki az adott szervezet tagja és aktív.'], 422);
    }

    // ha manager csere történik: az új manager ne vezessen másik aktív részleget
    if ((int)$data['manager_id'] !== (int)$dept->manager_id) {
        $already = \DB::table('organization_departments')
            ->where('organization_id', $orgId)
            ->where('manager_id', $data['manager_id'])
            ->whereNull('removed_at')
            ->exists();

        if ($already) {
            return response()->json(['message' => 'A kiválasztott vezető már egy másik részleget vezet.'], 422);
        }
    }

    \DB::table('organization_departments')
        ->where('organization_id', $orgId)
        ->where('id', $data['id'])
        ->update([
            'department_name' => $data['department_name'],
            'manager_id'      => $data['manager_id'],
            'created_at'      => \DB::raw('created_at'), // változatlanul hagyjuk
            'removed_at'      => null, // biztos ami biztos
        ]);

    return response()->json(['ok' => true, 'message' => 'Részleg frissítve.']);
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
        'user_ids'      => ['required','array'],
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

    $ids = collect($data['user_ids'])->unique()->values();

    // validáljuk: mind a user-ek ebben az orgban vannak, és nem admin/manager/ceo
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

    \DB::transaction(function() use ($orgId, $dept, $ids) {
        // 1) Az adott részlegből eltávolítunk mindenkit, aki eddig tag volt, de most nincs a listában
        \DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('department_id', $dept->id)
            ->whereNotIn('user_id', $ids) // akik nincsenek az új listában
            ->update(['department_id' => null]);

        // 2) Az új listában szereplőket ehhez a részleghez rendeljük — csak azokat, akiknél jelenleg NULL
        \DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->whereIn('user_id', $ids)
            ->whereNull('department_id') // ne mozgassunk máshonnan
            ->update(['department_id' => $dept->id]);
    });

    return response()->json(['ok' => true, 'message' => 'Részleg tagjai frissítve.']);
}


}