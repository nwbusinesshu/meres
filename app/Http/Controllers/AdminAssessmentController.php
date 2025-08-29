<?php
namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserBonusMalus;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use App\Services\ConfigService;
use App\Services\UserService;
use Illuminate\Http\Request;

class AdminAssessmentController extends Controller
{
    public function getAssessment(Request $request){
        $orgId = session('org_id');
        if (!$orgId) {
            abort(403); // or return a 422 JSON error similar to other controllers
        }
        return Assessment::where('organization_id', $orgId)
                 ->findOrFail($request->id);
    }

    public function saveAssessment(Request $request){
        $orgId = (int) session('org_id');
        \Log::info('saveAssessment', [
          'orgId' => $orgId,
          'request' => $request->all()
        ]);
        
        if (!$orgId) {
            return response()->json([
                'message' => 'Nincs kiválasztott szervezet.',
                'errors'  => ['org' => ['Nincs kiválasztott szervezet.']]
            ], 422);
        }
        $assessment = Assessment::where('organization_id', $orgId)
                         ->find($request->id);
        $rules = [
            "due" => ['required', 'date'],
        ];

        $attributes = [
            "due" => __('admin/home.due'),
        ];
    
        $this->validate(
            request: $request,
            rules: $rules,
            customAttributes: $attributes,
        ); 

        AjaxService::DBTransaction(function() use ($request, &$assessment,$orgId){
            $alreadyRunning = Assessment::where('organization_id', $orgId)
                            ->whereNull('closed_at')
                            ->exists();
            if ($alreadyRunning) {
                return response()->json([
                    'message' => 'Már van folyamatban értékelési időszak.', 
                    'errors'  => ['assessment' => ['Már van folyamatban értékelési időszak.']]
                ], 422);
            }
            if(is_null($assessment)){
                Assessment::create([
                "organization_id"   => $orgId,
                "started_at"        => date('Y-m-d H:i:s'),
                "due_at"            => $request->due,
                "normal_level_up"   => ConfigService::getConfigItem('normal_level_up'),
                "normal_level_down" => ConfigService::getConfigItem('normal_level_down'),
                "monthly_level_down"=> ConfigService::getConfigItem('monthly_level_down'),
            ]);
            }else{
                $assessment->due_at = $request->due;
                $assessment->save();
            }
        });
    }

    public function closeAssessment(Request $request){
        $assessment = Assessment::where('organization_id', $orgId)
                         ->findOrFail($request->id);
        AjaxService::DBTransaction(function() use (&$assessment){
            $assessment->closed_at = moment();
            $assessment->save();

            // CALCULATE NEW BONUS MALUS LEVELS
            $users = User::whereNull('removed_at')
             ->whereNotIn('type', [UserType::ADMIN, UserType::SUPERADMIN])
             ->get();
            $users->each(function($user) use ($assessment){
                $stat = UserService::calculateUserPoints($user,$assessment);
                // skipping users that werent participating
                if(is_null($stat)){ return; }

                $bonusMalus = $user->bonusMalus()->first();

                if($user->has_auto_level_up == 1){ 
                    // only level down
                    if($stat->total < $assessment->monthly_level_down){
                        if($bonusMalus->level < 4){
                            $bonusMalus->level = 1;
                        }else{
                            $bonusMalus->level-=3;
                        }
                    }
                }else{
                    // level up
                    if($stat->total > $assessment->normal_level_up){
                        if($bonusMalus->level < 15){
                            $bonusMalus->level++;            
                        }else{
                            $bonusMalus->level = 15;
                        }
                    // level down
                    }else if($stat->total < $assessment->normal_level_down){
                        if($bonusMalus->level < 2){
                            $bonusMalus->level = 1;
                        }else{
                            $bonusMalus->level--;
                        }
                    }
                }

                UserBonusMalus::where('month', $bonusMalus->month)->where('user_id', $bonusMalus->user_id)->update(['level' => $bonusMalus->level]);
            });
        });
    }
}

