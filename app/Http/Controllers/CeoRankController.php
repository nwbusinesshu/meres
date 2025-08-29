<?php

namespace App\Http\Controllers;

use App\Models\CeoRank;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserCeoRank;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        $employees = User::query()
            ->select('user.id', 'user.name', 'user.email') // ha kell még mező, itt add hozzá
            ->join('organization_user as ou', function ($q) use ($organizationId) {
                $q->on('ou.user_id', '=', 'user.id')
                  ->where('ou.organization_id', '=', $organizationId);
            })
            ->where('user.type', UserType::NORMAL)
            ->whereNull('user.removed_at')
            ->orderBy('user.name')
            ->get();

        $ceoRanks = CeoRank::where('organization_id', $organizationId)
                   ->whereNull('removed_at')
                   ->orderByDesc('value')
                   ->get()
                   ->map(function ($rank) use ($employees) {
                       $rank['calcMin'] = round($employees->count() / 100 * ($rank->min ?? 0));
                       $rank['calcMin'] = $rank['calcMin'] == 0 ? null : $rank['calcMin'];
                       $rank['calcMax'] = round($employees->count() / 100 * ($rank->max ?? 0));
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
            // ceo_rank: org szűrés HELYES, mert ebben a táblában van organization_id
            $ceoRank = CeoRank::where('id', $rank['rankId'])
                              ->where('organization_id', $organizationId)
                              ->firstOrFail();

            $value = $ceoRank->value;

            if (empty($rank['employees'])) {
                return;
            }

            // Ha ugyanaz a user többször szerepel, szűrjük egyedire
            collect($rank['employees'])->unique()->each(function($uid) use ($value, $assessment, $organizationId) {
                // ⬇️ JAVÍTOTT: org-tagság ellenőrzése pivoton (organization_user), nem a user.organization_id-n
                $user = User::query()
                    ->join('organization_user as ou', function ($q) use ($organizationId) {
                        $q->on('ou.user_id', '=', 'user.id')
                          ->where('ou.organization_id', '=', $organizationId);
                    })
                    ->where('user.id', $uid)
                    ->whereNull('user.removed_at')
                    ->select('user.id') // csak ami kell
                    ->firstOrFail();

                // ⬇️ Javasolt: idempotens mentés (elkerüli a duplázódást)
                // (Ha van egyedi index: (assessment_id, ceo_id, user_id), ez garantáltan ütközésmentes.)
                UserCeoRank::query()->updateOrInsert(
                    [
                        'assessment_id' => $assessment->id,
                        'ceo_id'        => session('uid'),
                        'user_id'       => $user->id,
                    ],
                    [
                        'value'         => $value,
                    ]
                );

                // Ha mindenképp create-et szeretnél és egyszer fut a mentés:
                // UserCeoRank::create([
                //     'assessment_id' => $assessment->id,
                //     'ceo_id'        => session('uid'),
                //     'user_id'       => $user->id,
                //     'value'         => $value,
                // ]);
            });
        });
    });

    return response()->json(['message' => 'Mentve']);
}

}
