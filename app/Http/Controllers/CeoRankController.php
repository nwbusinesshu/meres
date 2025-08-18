<?php

namespace App\Http\Controllers;

use App\Models\CeoRank;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserCeoRank;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use Exception;
use Illuminate\Http\Request;

class CeoRankController extends Controller
{
    public function index(Request $request){
        $assessment = AssessmentService::getCurrentAssessment();
        if(is_null($assessment) || UserCeoRank::where('assessment_id', $assessment?->id)->where('ceo_id', session('uid'))->count() != 0){
            return abort(403);
        }

        $employees = User::where('type', UserType::NORMAL)->whereNull('removed_at')->orderBy('name')->get();
        return view('ceorank',[
            "ceoranks" => CeoRank::whereNull('removed_at')->orderByDesc('value')->get()->map(function($rank) use ($employees){
                $rank['calcMin'] = round($employees->count()/100 * ($rank->min ?? 0));
                $rank['calcMin'] = $rank['calcMin'] == 0 ? null : $rank['calcMin'];
                $rank['calcMax'] = round($employees->count()/100 * ($rank->max ?? 0));
                $rank['calcMax'] = $rank['calcMax'] == 0 ? null : $rank['calcMax'];
                return $rank;
            }),
            "employees" => $employees,
        ]);
    }

    public function submitRanking(Request $request){
        $assessment = AssessmentService::getCurrentAssessment();

        if(UserCeoRank::where('assessment_id', $assessment?->id)->where('ceo_id', session('uid'))->count() != 0){
            return abort(403);
        }
        if(!$request->has('ranks')){ return abort(403); }
    
        AjaxService::DBTransaction(function() use ($request, $assessment) {
            collect($request->ranks)->each(function($rank) use ($assessment){
                $value = CeoRank::findOrFail($rank['rankId'])->value;
                if(empty($rank['employees'])){ return; }
                collect($rank['employees'])->each(function($uid) use ($value, $assessment){
                    UserCeoRank::create([
                        'assessment_id' => $assessment->id,
                        'ceo_id' => session('uid'),
                        'user_id' => $uid,
                        'value' => $value
                    ]);
                });
            });
        });
    }
}

