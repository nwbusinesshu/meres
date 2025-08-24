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




class AdminEmployeeController extends Controller
{
    public function __construct(){
        if(AssessmentService::isAssessmentRunning()){
            return abort(403);
        }
    }

    public function index(Request $request){
        return view('admin.employees',[
            "users" => UserService::getUsers()->map(function($user){
                $user['bonusMalus'] = $user->bonusMalus()->first()?->level ?? null;
                return $user;
            }),
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
        'type'        => ['required', Rule::in(['normal', 'ceo'])],
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
            if (is_null($user)) {
                // ÚJ FELHASZNÁLÓ
                $created = true;

                $user = User::create([
                    'name'              => $request->name,
                    'email'             => $request->email,
                    'type'              => $request->type,
                    'has_auto_level_up' => (int) $request->autoLevelUp,
                ]);

                // SELF kapcsolat
                $user->relations()->create([
                    'target_id'       => $user->id,
                    'type'            => UserRelationType::SELF,
                    'organization_id' => $orgId,
                ]);

                // Org hozzárendelés
                $user->organizations()->syncWithoutDetaching([$orgId]);

                // Bonus/Malus — EXPLICIT MODELL, user_id is benne a kulcsban
                UserBonusMalus::updateOrCreate(
                    [
                        'user_id'         => $user->id,
                        'month'           => date('Y-m-01'),
                        'organization_id' => $orgId,
                    ],
                    [
                        'level' => UserService::DEFAULT_BM,
                    ]
                );

            } else {
                // MEGLÉVŐ FELHASZNÁLÓ FRISSÍTÉSE
                $user->name              = $request->name;
                $user->email             = $request->email;
                $user->type              = $request->type;
                $user->has_auto_level_up = (int) $request->autoLevelUp;
                $user->save();

                // Biztonság kedvéért org kapcsolat (ha korábbról nem lenne meg)
                $user->organizations()->syncWithoutDetaching([$orgId]);

                // Bonus/Malus — EXPLICIT MODELL
                UserBonusMalus::updateOrCreate(
                    [
                        'user_id'         => $user->id,
                        'month'           => date('Y-m-01'),
                        'organization_id' => $orgId,
                    ],
                    [
                        'level' => UserService::DEFAULT_BM,
                    ]
                );
            }
        });
    } catch (\Throwable $e) {
        \Log::error('employee.save.transaction_failed', [
            'org_id'  => $orgId,
            'user_id' => $user ? $user->id : null,
            'error'   => $e->getMessage(),
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
}