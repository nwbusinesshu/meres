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
use Carbon\Carbon;
use App\Services\ThresholdService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class AdminAssessmentController extends Controller
{

    protected ThresholdService $thresholds;

    public function __construct(ThresholdService $thresholds)
    {
        $this->thresholds = $thresholds;
    }


    public function getAssessment(Request $request){
        $orgId = session('org_id');
        if (!$orgId) {
            abort(403); // or return a 422 JSON error similar to other controllers
        }
        return Assessment::where('organization_id', $orgId)
                 ->findOrFail($request->id);
    }

    public function saveAssessment(Request $request)
{
    $orgId = (int) session('org_id');

    \Log::info('saveAssessment', [
        'orgId'   => $orgId,
        'request' => $request->all()
    ]);

    if (!$orgId) {
        return response()->json([
            'message' => 'Nincs kiválasztott szervezet.',
            'errors'  => ['org' => ['Nincs kiválasztott szervezet.']]
        ], 422);
    }

    // Meglévő assessment (határidő módosítás esetén)
    $assessment = Assessment::where('organization_id', $orgId)
        ->find($request->id);

    // Validáció – ugyanaz, mint a régi kódban
    $rules = [
        'due' => ['required', 'date'],
    ];
    $attributes = [
        'due' => __('admin/home.due'),
    ];
    $this->validate(
        request: $request,
        rules: $rules,
        customAttributes: $attributes,
    );

    // Tranzakció – marad az AjaxService wrapper
    AjaxService::DBTransaction(function () use ($request, &$assessment, $orgId) {

        // egyszerre csak egy futó assessment (új indításnál tiltjuk)
        $alreadyRunning = Assessment::where('organization_id', $orgId)
            ->whereNull('closed_at')
            ->exists();

        if ($alreadyRunning && is_null($assessment)) {
            // HIBA: ugyanaz a minta, mint a régi kódban
            return response()->json([
                'message' => 'Már van folyamatban értékelési időszak.',
                'errors'  => ['assessment' => ['Már van folyamatban értékelési időszak.']]
            ], 422);
        }

        if (is_null($assessment)) {
            // ÚJ assessment indítása → org-config alapú thresholdok
            /** @var \App\Services\ThresholdService $thresholds */
            $thresholds = app(\App\Services\ThresholdService::class);
            $init = $thresholds->buildInitialThresholdsForStart($orgId);

            Assessment::create([
                'organization_id'     => $orgId,
                'started_at'          => date('Y-m-d H:i:s'),
                'due_at'              => $request->due,
                'closed_at'           => null,
                'threshold_method'    => $init['threshold_method'],
                // FIXED/HYBRID: konkrét számok; DYNAMIC/SUGGESTED: NULL
                'normal_level_up'     => $init['normal_level_up'],
                'normal_level_down'   => $init['normal_level_down'],
                // havi küszöb marad configból (üzletileg nem változott)
                'monthly_level_down'  => $init['monthly_level_down'],
            ]);

            // Siker esetén NINCS return → követjük a régi viselkedést
            // TODO: kiosztások/ívek generálása, ha itt szokott történni

        } else {
            // Meglévő assessment → csak due_at frissítés (régi viselkedés)
            $assessment->due_at = $request->due;
            $assessment->save();

            // Siker esetén itt sincs return → marad a régi viselkedés
        }
    });

    // NINCS explicit válasz → marad a régi minta (a frontend ezt várja)
}


    public function closeAssessment(Request $request){
        $orgId = (int) session('org_id'); 
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
                $stat = UserService::calculateUserPoints($assessment, $user);
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

