<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\CeoRank;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserBonusMalus;
use App\Models\UserCeoRank;
use App\Models\UserRelation;
use App\Services\AssessmentService;
use App\Services\UserService;
use App\Services\WelcomeMessageService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function admin(Request $request){
        $assessment = AssessmentService::getCurrentAssessment();
        return view('admin.home',[
            'assessment' => $assessment,
            'employees' => User::whereNull('removed_at')
                            ->where('type', '!=', UserType::ADMIN)
                            ->withCount('relations')
                            ->get()->map(function($user){
                                $user['competency_submits_count'] = $user->competencySubmits()->count();
                                $user['self_competency_submit_count'] = $user->selfCompetencySubmit()->count() > 0;
                                return $user;
                            }),
            'neededCeoRanks' => User::where('type', UserType::CEO)->count(),
            'ceoRanks' => $assessment?->ceoRanks()->distinct('ceo_id')->count() ?? 0,
            'neededAssessment' => UserRelation::all()->count(),
            'assessed' => $assessment?->userCompetencySubmits()->count() ?? 0
        ]);
    }

    public function normal(Request $request){
        $assessment = AssessmentService::getCurrentAssessment();
        $user = UserService::getCurrentUser();
        return view('home',[
            "welcomeMessage" => WelcomeMessageService::generate(),
            'assessment' => $assessment,
            'relations' => $user->relations()
                ->whereNotIn('target_id', $user->competencySubmits->map(
                    function($sub){ return $sub->target_id; }))
                ->with('target')->get(),
            'selfAssessed' => $user->selfCompetencySubmit()->count() == 1,
            'madeCeoRank' => UserCeoRank::where('assessment_id', $assessment?->id)->where('ceo_id', session('uid'))->count() != 0,
        ]);
    }
}

