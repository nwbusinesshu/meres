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
use App\Services\SuggestedThresholdService;



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
    $assessment = Assessment::findOrFail($request->id);

    // --- ORG SCOPING ---
    $orgId = (int) $assessment->organization_id;
    if ($orgId !== (int) session('org_id')) {
        throw ValidationException::withMessages([
            'assessment' => 'Nem jogosult szervezet.',
        ]);
    }

    // --- CEO RANK KÖTELEZŐ (legalább egy vezetői rangsor legyen) ---
    $hasCeoRank = DB::table('user_ceo_rank')
        ->where('assessment_id', $assessment->id)
        ->exists();
    if (!$hasCeoRank) {
        throw ValidationException::withMessages([
            'ceo_rank' => 'A lezáráshoz legalább egy CEO rangsorolás szükséges.',
        ]);
    }

    // --- Résztvevő pontok begyűjtése (org scope) ---
    $userIds = DB::table('organization_user')
        ->where('organization_id', $orgId)
        ->pluck('user_id')
        ->unique()
        ->toArray();

    $participants = User::query()
        ->whereIn('id', $userIds)
        ->whereNull('removed_at')
        ->where(function ($q) {  // nincs ENUM, ezért így zárjuk ki az adminokat
            $q->whereNull('type')->orWhere('type', '!=', 'admin');
        })
        ->get();

    if ($participants->isEmpty()) {
        throw ValidationException::withMessages([
            'participants' => 'Nincsenek résztvevők az értékelésben.',
        ]);
    }

    // Összegyűjtjük a pontszámokat minden résztvevőre, aki ténylegesen kitöltött
    $scores = [];
    $userStats = [];  // user_id => stat objektum
    foreach ($participants as $user) {
        $stat = UserService::calculateUserPoints($assessment, $user);
        if ($stat === null) {
            continue;  // Felhasználó nem vett részt (nincs kitöltés) -> kihagyjuk
        }
        $scores[] = (float) $stat->total;
        $userStats[$user->id] = $stat;
    }

    if (empty($scores)) {
        throw ValidationException::withMessages([
            'scores' => 'Nincs egyetlen lezárt/érvényes pontszám sem ehhez az értékeléshez.',
        ]);
    }

    // --- Küszöbérték-számítási mód ellenőrzése ---
    $method = strtolower((string) ($assessment->threshold_method ?? ''));
    if ($method === '') {
        // Nincs beállítva threshold_method – nem folytathatjuk (nincs silent default)
        throw ValidationException::withMessages([
            'threshold_method' => 'Hiányzik az értékelési küszöbszámítási mód (threshold_method).',
        ]);
    }

    // --- Suggested (AI) küszöbszámítás esetén: AI hívás előkészítése és végrehajtása ---
    $suggestedResult = null;
    if ($method === 'suggested') {
        /** @var SuggestedThresholdService $ai */
        $ai = app(SuggestedThresholdService::class);
        $payload = $ai->buildAiPayload($assessment, $scores, $userStats);
        $suggestedResult = $ai->callAiForSuggested($payload);

        // Ellenőrizzük az AI válasz sikerességét és tartalmát
        if (!$suggestedResult) {
            throw ValidationException::withMessages([
                'ai' => 'AI hiba: érvénytelen vagy hiányzó válasz.',
            ]);
        }
        if (
            !isset($suggestedResult['thresholds']['normal_level_up']) ||
            !isset($suggestedResult['thresholds']['normal_level_down'])
        ) {
            throw ValidationException::withMessages([
                'ai' => 'AI hiba: a válasz nem tartalmaz küszöbértékeket.',
            ]);
        }
        // Megjegyzés: További validálás történik a ThresholdService-ben (negatív, sorrend, stb.)
    }

    // --- Tranzakció: küszöbök alkalmazása és Bonus/Malus frissítése ---
    return DB::transaction(function () use (
        $assessment, $orgId, $participants, $userStats, $scores, $method, $suggestedResult
    ) {
        /** @var ThresholdService $T */
        $T = app(ThresholdService::class);
        $cfg = $T->getOrgConfigMap($orgId);  // szervezeti config (minden szükséges kulccsal vagy defaulttal)

        // 1) Küszöbértékek meghatározása a választott módszer szerint
        switch ($method) {
            case 'fixed':
                $thresholds = $T->thresholdsForFixed($cfg);
                break;
            case 'hybrid':
                $thresholds = $T->thresholdsForHybrid($cfg, $scores);
                break;
            case 'dynamic':
                $thresholds = $T->thresholdsForDynamic($cfg, $scores);
                break;
            case 'suggested':
                $thresholds = $T->thresholdsFromSuggested($cfg, $suggestedResult);
                break;
            default:
                throw ValidationException::withMessages([
                    'threshold_method' => "Ismeretlen threshold_method: {$method}",
                ]);
        }

        // Ésszerűségi ellenőrzés: 0..100 tartomány
        $up   = (int) $thresholds['normal_level_up'];
        $down = (int) $thresholds['normal_level_down'];
        $mon  = (int) ($thresholds['monthly_level_down'] ?? ($cfg['monthly_level_down'] ?? 70));
        if ($up < 0 || $up > 100 || $down < 0 || $down > 100 || $mon < 0 || $mon > 100) {
            throw ValidationException::withMessages([
                'thresholds' => 'A küszöbök 0..100 tartományon kívüliek vagy érvénytelenek.',
            ]);
        }

        // 2) Assessment lezárása és küszöbök mentése
        $assessment->normal_level_up    = $up;
        $assessment->normal_level_down  = $down;
        $assessment->monthly_level_down = $mon;
        $assessment->closed_at          = now();
        $assessment->save();

        // 3) Bonus/Malus szintek frissítése minden résztvevőnél
        $useGrace = ($method === 'hybrid');
        $gracePts = $useGrace ? (int)($cfg['threshold_grace_points'] ?? 0) : 0;
        $hybridUpRaw = null;
        if ($method === 'hybrid') {
            // Hybrid esetén az eredeti százalékos küszöb (nyers fel) kell a grace logikához
            $hybridUpRaw = $T->topPercentileScore($scores, (float)$cfg['threshold_top_pct']);
        }

        foreach ($participants as $user) {
            $stat = $userStats[$user->id] ?? null;
            if ($stat === null) {
                continue;  // nem vett részt, nincs mit frissíteni
            }
            $points = (float) $stat->total;
            $bm = $user->bonusMalus()->first();
            if (!$bm) {
                // Ha nincs előzmény rekord, létrehozzuk aktuális hónappal, 1-es szinttel
                $bm = new UserBonusMalus([
                    'user_id' => $user->id,
                    'month'   => now()->format('Y-m-01'),
                    'level'   => 1,
                ]);
                $bm->save();
            }

            if ((int) $user->has_auto_level_up === 1) {
                // Auto-level-up felhasználó: csak lefokozást vizsgálunk a havi küszöb alapján
                if ($points < $mon) {
                    // 3 szintnél nagyobbat nem eshet vissza egyszerre (4->1 direkt)
                    if ($bm->level < 4) {
                        $bm->level = 1;
                    } else {
                        $bm->level -= 3;
                    }
                }
            } else {
                // Normál fel- és lefokozási logika
                $promote = false;
                if ($useGrace && $gracePts > 0 && $hybridUpRaw !== null) {
                    // HYBRID GRACE: ha a feljebb tolt küszöb miatt valaki éppen lemaradt,
                    // de elérte az eredeti (nyers) küszöböt ÉS csak kevéssel maradt el az új küszöbtől (<= gracePts)
                    if ($points >= $hybridUpRaw && $points >= ($up - $gracePts)) {
                        $promote = true;
                    }
                }
                // Normál promóciós feltétel
                if ($points >= $up) {
                    $promote = true;
                }

                if ($promote) {
                    // Előléptetés: szint növelés (maximum 15)
                    $bm->level = min(15, $bm->level + 1);
                } elseif ($points < $down) {
                    // Lefokozás: szint csökkentés (minimum 1)
                    $bm->level = max(1, $bm->level - 1);
                }
            }

            // Mentjük a módosított szintet (upsert jelleggel az aktuális hónapra)
            UserBonusMalus::where('month', $bm->month)
                ->where('user_id', $bm->user_id)
                ->update(['level' => $bm->level]);
        }

        // Sikeres lezárás visszajelzése
        return response()->json([
            'ok'        => true,
            'message'   => 'Az értékelés sikeresen lezárva.',
            'thresholds'=> [
                'normal_level_up'    => $up,
                'normal_level_down'  => $down,
                'monthly_level_down' => $mon,
                'method'             => $method,
            ],
        ]);
    });
}

}

