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


    public function closeAssessment(Request $request)
{
    $orgId = (int) session('org_id');

    /** @var \App\Models\Assessment $assessment */
    $assessment = Assessment::where('organization_id', $orgId)
        ->findOrFail($request->id);

        if (!is_null($assessment->closed_at)) {
    // már lezárt – nem számolunk újra
    return;
}

    AjaxService::DBTransaction(function () use (&$assessment, $orgId) {

        // 1) Módszer az assessmentből
        $method = strtolower((string) $assessment->threshold_method);

        // 2) Szükséges org-config értékek
        /** @var \App\Services\ThresholdService $thresholds */
        $thresholds = app(\App\Services\ThresholdService::class);
        $cfg = $thresholds->getOrgConfigMap($orgId);

        // clamp-elt segéd értékek
        $topPct      = max(0, min(100, (int) ($cfg['threshold_top_pct'] ?? 15)));
        $bottomPct   = max(0, min(100, (int) ($cfg['threshold_bottom_pct'] ?? 15)));
        $minAbs      = max(0, (int) ($cfg['threshold_min_abs_up'] ?? 80));
        $gracePoints = max(0, (int) ($cfg['threshold_grace_points'] ?? 5));  // HYBRID finomhangolás
        $gapMin      = max(0, (int) ($cfg['threshold_gap_min'] ?? 2));       // stagnálási rés

        // 3) Résztvevők pontszámai az adott assessmentben
        $users = \App\Models\User::whereNull('removed_at')
            ->whereNotIn('type', [\App\Models\Enums\UserType::ADMIN, \App\Models\Enums\UserType::SUPERADMIN])
            ->get();

        $totals = [];
        foreach ($users as $user) {
            $stat = \App\Services\UserService::calculateUserPoints($assessment, $user);
            if ($stat && isset($stat->total)) {
                $totals[] = (int) $stat->total;
            }
        }

        // 4) Küszöb-számítás (csak HYBRID/DYNAMIC/SUGGESTED), FIXED-nél nincs teendő
        $needCompute = in_array($method, ['hybrid', 'dynamic', 'suggested'], true);

        if ($needCompute && count($totals) > 0) {

            sort($totals);                 // növekvő
            $ascending  = $totals;
            $descending = array_reverse($ascending); // csökkenő
            $n = count($totals);

            // percentile index kiválasztása (1..N)
            $pickIndex = function (int $pct) use ($n) {
                $idx = (int) ceil($n * $pct / 100.0);
                if ($idx < 1) $idx = 1;
                if ($idx > $n) $idx = $n;
                return $idx; // 1-based index
            };

            if ($method === 'hybrid') {
                // alsó minimum (minAbs) az assessmentben legyen meg
                if (is_null($assessment->normal_level_down)) {
                    $assessment->normal_level_down = $minAbs;
                }
                $down = (int) $assessment->normal_level_down;

                // felső küszöb: top X% legalsó pontja
                $idxTop = $pickIndex($topPct);
                $pTop   = $descending[$idxTop - 1]; // 1-based → 0-based
                $best   = $descending[0];

                // HYBRID fairness: grace + gap + best-1
                // 1) alap jelölt: ne menjen minAbs alá
                $up0 = max($pTop, $down);
                // 2) ha pTop minAbs alatt, engedjünk legfeljebb grace pontot lefelé
                $up1 = ($pTop < $down) ? max($pTop, $down - $gracePoints) : $up0;
                // 3) biztosítsunk minimális "stagnálási" rést
                $up2 = max($up1, $down + $gapMin);
                // 4) a '>' összehasonlítás miatt legyen elég tér a legjobbnak
                $assessment->normal_level_up = ($best > $up2) ? min($up2, $best - 1) : $up2;

            } elseif ($method === 'dynamic') {
                // felső küszöb (top X%)
                $idxTop = $pickIndex($topPct);
                $up     = $descending[$idxTop - 1];

                // alsó küszöb (bottom Y%)
                $idxBottom = $pickIndex($bottomPct);
                $down      = $ascending[$idxBottom - 1];

                // nem kényszerítünk átfedést; a '>' / '<' / else logika konszisztens
                $assessment->normal_level_up   = $up;
                $assessment->normal_level_down = $down;

            } elseif ($method === 'suggested') {
                /** @var \App\Services\SuggestedThresholdService $sugg */
                $sugg    = app(\App\Services\SuggestedThresholdService::class);
                $payload = $sugg->buildAiPayload($assessment);
                $ai      = $sugg->callAiForSuggested($payload);

                if (is_array($ai)
                    && isset($ai['thresholds']['normal_level_up'], $ai['thresholds']['normal_level_down'])
                ) {
                    // AI által javasolt küszöbök
                    $assessment->normal_level_up   = (int) $ai['thresholds']['normal_level_up'];
                    $assessment->normal_level_down = (int) $ai['thresholds']['normal_level_down'];

                    // (opcionális) AI decisions logolása/mentése itt

                } else {
                    // Fallback: dynamic százalékok
                    $idxTop = $pickIndex($topPct);
                    $up     = $descending[$idxTop - 1];

                    $idxBottom = $pickIndex($bottomPct);
                    $down      = $ascending[$idxBottom - 1];

                    $assessment->normal_level_up   = $up;
                    $assessment->normal_level_down = $down;
                }
            }
        }

        // 5) Assessment lezárása és MENTÉSE (küszöbökkel együtt)
        $assessment->closed_at = moment();
        $assessment->save();

        // 6) Bonus–Malus frissítés (változatlan alaplogika) – a frissített küszöbökkel
        $users->each(function ($user) use ($assessment) {
            $stat = \App\Services\UserService::calculateUserPoints($assessment, $user);
            if (is_null($stat)) { return; } // aki nem vett részt, kimarad

            $bonusMalus = $user->bonusMalus()->first();

            if ($user->has_auto_level_up == 1) {
                // csak lefelé vizsgálunk havi küszöbbel
                if (!is_null($assessment->monthly_level_down) && $stat->total < $assessment->monthly_level_down) {
                    if ($bonusMalus->level < 4) {
                        $bonusMalus->level = 1;
                    } else {
                        $bonusMalus->level -= 3;
                    }
                }
            } else {
                // felfelé
                if (!is_null($assessment->normal_level_up) && $stat->total > $assessment->normal_level_up) {
                    if ($bonusMalus->level < 15) {
                        $bonusMalus->level++;
                    } else {
                        $bonusMalus->level = 15;
                    }
                // lefelé
                } elseif (!is_null($assessment->normal_level_down) && $stat->total < $assessment->normal_level_down) {
                    if ($bonusMalus->level < 2) {
                        $bonusMalus->level = 1;
                    } else {
                        $bonusMalus->level--;
                    }
                }
            }

            \App\Models\UserBonusMalus::where('month', $bonusMalus->month)
                ->where('user_id', $bonusMalus->user_id)
                ->update(['level' => $bonusMalus->level]);
        });
    });

    // NINCS explicit válasz (marad a régi minta)
    }
}

