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
    $aid = (int) ($request->input('assessment_id') ?? $request->input('id')); // kompatibilis a régi payloaddal is


    if (!$orgId || !$aid) {
        return response()->json([
            'message' => 'Hiányzó szervezet vagy értékelés azonosító.',
        ], 422);
    }

    /** @var \App\Models\Assessment|null $assessment */
    $assessment = Assessment::where('id', $aid)
        ->where('organization_id', $orgId)
        ->first();

    if (!$assessment) {
        return response()->json([
            'message' => 'Az értékelés nem található ebben a szervezetben.',
        ], 404);
    }

    if (!is_null($assessment->closed_at)) {
        return response()->json([
            'message' => 'Ez az értékelés már le van zárva.',
        ], 422);
    }

    // Módszer: assessment->threshold_method elsőbbséget élvez, különben org config
    $cfg = $this->thresholds->getOrgConfigMap($orgId);
    $method = $assessment->threshold_method ?: ($cfg['threshold_mode'] ?? 'dynamic');
    $method = in_array($method, ['fixed','hybrid','dynamic','suggested'], true) ? $method : 'dynamic';

    try {
        return \DB::transaction(function () use ($orgId, $aid, $assessment, $method, $cfg) {

            // Résztvevők: kizárjuk az adminokat (superadmin úgysem org-tag)
            $participants = \App\Models\User::query()
                ->select('user.*')
                ->join('organization_user as ou', 'ou.user_id', '=', 'user.id')
                ->where('ou.organization_id', $orgId)
                ->whereNull('user.removed_at')
                // ha nincs enumod, hagyd sima 'admin' stringen
                ->where('user.type', '!=', (\App\Models\Enums\UserType::ADMIN ?? 'admin'))
                ->get();

            if ($participants->isEmpty()) {
                // Nincs kit kockára tenni – NE zárjunk le!
                return response()->json([
                    'message' => 'Nincs egyetlen résztvevő sem az értékelésben (adminok kizárva).',
                ], 422);
            }

            // --- Küszöb számítás / módszer futtatás ---

            // Pontok/összesítések lekészítése ide (a saját implementációd szerint)...
            // pl. $totals = collect([...]);

            // Válasszuk szét a módszereket. NINCS fallback!
            switch ($method) {
                case 'fixed':
                    // itt a fixed logikád + validáció
                    break;

                case 'hybrid':
                    // hybrid logika + validáció
                    break;

                case 'dynamic':
                    // dynamic logika + validáció
                    break;

                case 'suggested':
                    // AI hívás – ha hibázik, exception/hiba és VISSZATÉRÜNK (nem zárunk)
                    try {
                        $ai = app(\App\Services\SuggestedThresholdService::class);
                            $payload  = $ai->buildAiPayload($assessment);
                            $aiResult = $ai->callAiForSuggested($payload);

                            if (!$aiResult) {
                                return response()->json([
                                    'message' => 'AI hiba – a modell nem válaszol vagy érvénytelen választ adott. Kérjük, próbálja meg később.',
                                ], 502);
                            }

                        if (!$aiResult || empty($aiResult['ok'])) {
                            // AI válasz hibás/üres → NE zárjunk le
                            return response()->json([
                                'message' => 'AI hiba – a modell nem válaszol vagy érvénytelen választ adott. Kérjük, próbálja meg később.',
                            ], 502);
                        }

                        // apply $aiResult ... (küszöbök/pontok érvényesítése)
                    } catch (\Throwable $e) {
                        \Log::error('assessment.close.suggested_failed', [
                            'org_id'        => $orgId,
                            'assessment_id' => $aid,
                            'error'         => $e->getMessage(),
                        ]);
                        return response()->json([
                            'message' => 'AI hiba – a modell nem válaszol. Kérjük, próbálja meg később.',
                        ], 502);
                    }
                    break;

                default:
                    return response()->json([
                        'message' => 'Ismeretlen küszöb-módszer: ' . $method,
                    ], 422);
            }

            // --- Ha idáig eljutottunk: minden számítás sikeres volt, LE ZÁRHATJUK ---
            $assessment->closed_at = now();
            $assessment->save();

            \Log::info('assessment.close.ok', [
                'org_id'        => $orgId,
                'assessment_id' => $aid,
                'method'        => $method,
            ]);

            return response()->json([
                'ok'             => true,
                'assessment_id'  => $aid,
                'method'         => $method,
                'message'        => 'Az értékelés sikeresen lezárva.',
            ], 200);
        });
    } catch (\Illuminate\Database\QueryException $qe) {
        // Tipikus trigger/séma hibák – maradjon NYITVA
        \Log::error('assessment.close.db_error', [
            'org_id'        => $orgId,
            'assessment_id' => $aid,
            'error'         => $qe->getMessage(),
        ]);
        return response()->json([
            'message' => 'Adatbázis hiba a lezárás során. Az értékelés nyitva maradt.',
        ], 500);
    } catch (\Throwable $e) {
        \Log::error('assessment.close.error', [
            'org_id'        => $orgId,
            'assessment_id' => $aid,
            'error'         => $e->getMessage(),
        ]);
        return response()->json([
            'message' => 'Váratlan hiba a lezárás során. Az értékelés nyitva maradt.',
        ], 500);
    }
}

}

