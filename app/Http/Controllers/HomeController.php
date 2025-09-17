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
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function admin(Request $request)
{
    $orgId = (int) session('org_id');
    $assessment = AssessmentService::getCurrentAssessment();

    // Dolgozók betöltése (változatlan)
    $employees = User::whereNull('removed_at')
        ->where('type', '!=', UserType::ADMIN)
        ->whereHas('organizations', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })
        ->withCount('relations')
        ->get()
        ->map(function ($user) {
            $user['competency_submits_count'] = $user->competencySubmits()->count();
            $user['self_competency_submit_count'] = $user->selfCompetencySubmit()->count() > 0;
            return $user;
        });

    // CEO-k száma
    $ceoCount = User::where('type', UserType::CEO)
        ->whereHas('organizations', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })
        ->count();

    // Managerek száma, akiknek van beosztottjuk
    $managerCount = DB::table('organization_department_managers as odm')
        ->join('organization_user as ou', function ($j) {
            $j->on('ou.organization_id', '=', 'odm.organization_id')
              ->on('ou.department_id', '=', 'odm.department_id');
        })
        ->where('odm.organization_id', $orgId)
        ->where('ou.role', '!=', 'manager')
        ->distinct('odm.manager_id')
        ->count('odm.manager_id');

    // Nyitott fizetések ellenőrzése
    $hasOpenPayments = DB::table('payments')
        ->where('organization_id', $orgId)
        ->whereNull('paid_at')
        ->whereNull('billingo_document_id')
        ->exists();

    return view('admin.home', [
        'assessment'       => $assessment,
        'employees'        => $employees,
        'neededCeoRanks'   => $ceoCount + $managerCount,
        'ceoRanks'         => $assessment?->ceoRanks()->distinct('ceo_id')->count() ?? 0,
        'neededAssessment' => UserRelation::where('organization_id', $orgId)->count(),
        'assessed'         => $assessment?->userCompetencySubmits()->count() ?? 0,
        'hasOpenPayments'  => $hasOpenPayments,   // új flag átadva a blade-nek
    ]);
}


    public function normal(Request $request){
        $orgId = session('org_id');
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

