<?php

namespace App\Http\Controllers;

use App\Models\CeoRank;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserCeoRank;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use Illuminate\Http\Request;

class CeoRankController extends Controller
{
    public function index(Request $request){
        $organizationId = session('org_id');
        $assessment = AssessmentService::getCurrentAssessment();

        if (is_null($assessment) || 
            UserCeoRank::where('assessment_id', $assessment->id)
                       ->where('ceo_id', session('uid'))
                       ->exists()) {
            return abort(403);
        }

        $employees = User::where('organization_id', $organizationId)
                         ->where('type', UserType::NORMAL)
                         ->whereNull('removed_at')
                         ->orderBy('name')
                         ->get();

        $ceoRanks = CeoRank::where('organization_id', $organizationId)
                           ->whereNull('removed_at')
                           ->orderByDesc('value')
                           ->get()
                           ->map(function($rank) use ($employees){
                               $rank['calcMin'] = round($employees->count()/100 * ($rank->min ?? 0));
                               $rank['calcMin'] = $rank['calcMin'] == 0 ? null : $rank['calcMin'];
                               $rank['calcMax'] = round($employees->count()/100 * ($rank->max ?? 0));
                               $rank['calcMax'] = $rank['calcMax'] == 0 ? null : $rank['calcMax'];
                               return $rank;
                           });

        return view('ceorank', [
            'ceoranks' => $ceoRanks,
            'employees' => $employees,
        ]);
    }

    public function submitRanking(Request $request){
        $organizationId = session('org_id');
        $assessment = AssessmentService::getCurrentAssessment();

        if (is_null($assessment) || 
            UserCeoRank::where('assessment_id', $assessment->id)
                       ->where('ceo_id', session('uid'))
                       ->exists()) {
            return abort(403);
        }

        if (!$request->has('ranks')) {
            return abort(403);
        }

        AjaxService::DBTransaction(function() use ($request, $assessment, $organizationId) {
            collect($request->ranks)->each(function($rank) use ($assessment, $organizationId) {
                $ceoRank = CeoRank::where('id', $rank['rankId'])
                                  ->where('organization_id', $organizationId)
                                  ->firstOrFail();

                $value = $ceoRank->value;

                if (empty($rank['employees'])) {
                    return;
                }

                collect($rank['employees'])->each(function($uid) use ($value, $assessment, $organizationId) {
                    $user = User::where('id', $uid)
                                ->where('organization_id', $organizationId)
                                ->whereNull('removed_at')
                                ->firstOrFail();

                    UserCeoRank::create([
                        'assessment_id' => $assessment->id,
                        'ceo_id' => session('uid'),
                        'user_id' => $user->id,
                        'value' => $value
                    ]);
                });
            });
        });
    }
}
