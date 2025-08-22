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

    public function saveEmployee(Request $request){
        $user = User::find($request->id);

        $rules = [
            "name" => ['required'],
            "email" => ['exclude_unless:id,0','required','email:rfc','unique:App\Models\User,email','regex:/.+@gmail\.com$/i'],
            "type" => ['required', Rule::in(['normal', 'ceo'])] , 
            "autoLevelUp" => ['required', Rule::in([0,1])]  
        ];

        $attributes = [
            "name" => __('global.name'),
            "email" => __('global.email'),
            "type" => __('global.type'),
            "autoLevelUp" => __('global.auto-level-up') 
        ];
    
        $this->validate(
            request: $request,
            rules: $rules,
            customAttributes: $attributes,
            messages: [
                "email.regex" => __('admin/employees.email-only-gmail')
            ]
        ); 

        AjaxService::DBTransaction(function() use ($request, &$user){
            if(is_null($user)){
                $user = User::create([
                    "name" => $request->name,
                    "email" => $request->email,
                    "type" => $request->type,
                    "has_auto_level_up" => $request->autoLevelUp,
                ]);

                $user->relations()->create([
                    "target_id" => $user->id,
                    "type" => UserRelationType::SELF
                ]);

                $user->bonusMalus()->create([
                    "level" => UserService::DEFAULT_BM,
                    "month" => date('Y-m-01')
                ]);
            }else{
                $user->name = $request->name;
                $user->email = $request->email;
                $user->type = $request->type;
                $user->has_auto_level_up = $request->autoLevelUp;
                $user->save();
            }
        });
    }

    public function removeEmployee(Request $request){
        $user = User::findOrFail($request->id);
        AjaxService::DBTransaction(function() use (&$user){
            $user->removed_at = date('Y-m-d H:i:s');
            $user->save();

            UserRelation::where('target_id', $user->id)->delete();
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
            ->whereHas('target.organizations', function ($q) {
                $q->where('organization_id', session('org_id'));
            })
            ->get();
    }

    public function saveEmployeeRelations(Request $request)
    {
        Log::info('Kapott adat', $request->all());

        $request->validate([
            'id' => 'required|integer|exists:users,id',
            'relations' => 'required|array|min:1',
            'relations.*.target' => 'required|integer|exists:users,id',
            'relations.*.type' => 'required|string|in:self,colleague,subordinate,superior',
        ]);

        $user = User::findOrFail($request->id);
        $relations = collect($request->input('relations'));

        // 💡 Feltételezzük, hogy a user legalább 1 szervezet tagja
        $organizationId = $user->organizations()->first()->id;

        try {
            AjaxService::DBTransaction(function () use ($relations, $user, $organizationId) {
                // korábbi kapcsolatok törlése
                $user->allRelations()->delete();

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
        return User::findOrFail($request->id)->competencies;
    }

    public function saveEmployeeCompetencies(Request $request){
        $user = User::findOrFail($request->id);
        if(!$request->has('competencies') || ($request->competencies = collect($request->competencies))->count() == 0){
            return abort(403);
        }
        AjaxService::DBTransaction(function() use ($request, &$user){
            UserCompetency::where('user_id', $user->id)->delete();

            $request->competencies->each(function($compId) use (&$user){
                UserCompetency::create([
                    "user_id" => $user->id,
                    "competency_id" => $compId,
                ]);
            });
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