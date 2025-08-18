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
                $user['bonusMalus'] = $user->bonusMalus()->first()->level;
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

    public function getAllEmployee(Request $request){
        return User::whereNull('removed_at')->where('type','!=',UserType::ADMIN)->orderBy('name')->get();
    }

    public function getEmployeeRelations(Request $request){
        return User::findOrFail($request->id)->allRelations()->with('target')->get();
    }

    public function saveEmployeeRelations(Request $request){
        $user = User::findOrFail($request->id);
        if(!$request->has('relations') || ($request->relations = collect($request->relations))->count() == 0){
            return abort(403);
        }
        AjaxService::DBTransaction(function() use ($request, &$user){
            $user->allRelations()->delete();
            $request->relations->each(function($item) use (&$user){
                $user->relations()->create([
                    "target_id" => $item['target'],
                    "type" => $item['type']
                ]);
            });
        });
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

