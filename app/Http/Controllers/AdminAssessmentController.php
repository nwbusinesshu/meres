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
use App\Services\SnapshotService;
use Illuminate\Validation\ValidationException;

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
            abort(403);
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

        // Validáció
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

        // Tranzakció
        AjaxService::DBTransaction(function () use ($request, &$assessment, $orgId) {

            // egyszerre csak egy futó assessment (új indításnál tiltjuk)
            $alreadyRunning = Assessment::where('organization_id', $orgId)
                ->whereNull('closed_at')
                ->exists();

            if ($alreadyRunning && is_null($assessment)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'assessment' => ['Már van folyamatban értékelési időszak.']
                ]);
            }

            if (is_null($assessment)) {
                // ÚJ assessment indítása → org-config alapú thresholdok
                /** @var \App\Services\ThresholdService $thresholds */
                $thresholds = app(\App\Services\ThresholdService::class);
                $init = $thresholds->buildInitialThresholdsForStart($orgId);

                // Snapshot összeállítása induláskor
                /** @var \App\Services\SnapshotService $snap */
                $snap = app(\App\Services\SnapshotService::class);
                $snapshotArr  = $snap->buildOrgSnapshot($orgId);
                $snapshotJson = json_encode($snapshotArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                // Assessment létrehozása
                $assessment = Assessment::create([
                    'organization_id'     => $orgId,
                    'started_at'          => now(),
                    'due_at'              => $request->due,
                    'closed_at'           => null,
                    'threshold_method'    => $init['threshold_method'] ?? null,
                    'normal_level_up'     => $init['normal_level_up'] ?? null,
                    'normal_level_down'   => $init['normal_level_down'] ?? null,
                    'monthly_level_down'  => $init['monthly_level_down'] ?? null,
                    'org_snapshot'        => $snapshotJson,
                ]);

                // ========== NEW BILLING LOGIC ==========

// Count employees (excluding admins)
$userIds = DB::table('organization_user')
    ->where('organization_id', $orgId)
    ->pluck('user_id')
    ->unique()
    ->toArray();

$employeeCount = User::query()
    ->whereIn('id', $userIds)
    ->whereNull('removed_at')
    ->where(function ($q) {
        $q->whereNull('type')->orWhere('type', '!=', 'admin');
    })
    ->count();

// Check if there are any closed assessments for this organization
$hasClosedAssessment = Assessment::where('organization_id', $orgId)
    ->whereNotNull('closed_at')
    ->exists();

\Log::info('assessment.billing.check', [
    'org_id'                => $orgId,
    'employee_count'        => $employeeCount,
    'has_closed_assessment' => $hasClosedAssessment,
]);

if (!$hasClosedAssessment) {
    // FIRST ASSESSMENT - Special logic
    
    // Check for unpaid payments (to avoid creating duplicates)
    $hasUnpaidPayment = DB::table('payments')
        ->where('organization_id', $orgId)
        ->where('status', '!=', 'paid')
        ->exists();
    
    if ($hasUnpaidPayment) {
        // Already has unpaid payment - don't create another one
        \Log::info('assessment.billing.skip', [
            'reason' => 'Already has unpaid payment',
            'org_id' => $orgId,
        ]);
        // Do nothing - proceed without creating payment
        
    } else {
        // No unpaid payment - check employee limit
        $profile = DB::table('organization_profiles')
            ->where('organization_id', $orgId)
            ->first();
        
        $employeeLimit = $profile ? (int)($profile->employee_limit ?? 0) : 0;
        
        \Log::info('assessment.billing.first_assessment', [
            'org_id'         => $orgId,
            'employee_count' => $employeeCount,
            'employee_limit' => $employeeLimit,
        ]);

        if ($employeeCount <= $employeeLimit) {
            // Within limit - no payment needed
            \Log::info('assessment.billing.skip', [
                'reason' => 'Within employee limit',
                'org_id' => $orgId,
            ]);
            // Do nothing - proceed without creating payment
            
        } else {
            // Over limit - create payment for excess employees only
            $excessEmployees = $employeeCount - $employeeLimit;
            $amountHuf = (int) ($excessEmployees * 950);
            
            \Log::info('assessment.billing.excess_only', [
                'org_id'           => $orgId,
                'employee_count'   => $employeeCount,
                'employee_limit'   => $employeeLimit,
                'excess_employees' => $excessEmployees,
                'amount_huf'       => $amountHuf,
            ]);
            
            if ($assessment && $amountHuf > 0) {
                DB::table('payments')->insert([
                    'organization_id' => $orgId,
                    'assessment_id'   => $assessment->id,
                    'amount_huf'      => $amountHuf,
                    'status'          => 'pending',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }
    
} else {
    // HAS CLOSED ASSESSMENTS - Use current logic (all employees)
    $amountHuf = (int) ($employeeCount * 950);
    
    \Log::info('assessment.billing.normal', [
        'org_id'     => $orgId,
        'amount_huf' => $amountHuf,
        'reason'     => 'Has closed assessments',
    ]);
    
    if ($assessment && $amountHuf > 0) {
        DB::table('payments')->insert([
            'organization_id' => $orgId,
            'assessment_id'   => $assessment->id,
            'amount_huf'      => $amountHuf,
            'status'          => 'pending',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}

// ========== END NEW BILLING LOGIC ==========

            } else {
                // Meglévő assessment → csak due_at frissítés
                $assessment->due_at = $request->due;
                $assessment->save();
            }
        });
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

        $hasPaid = DB::table('payments')
            ->where('assessment_id', $assessment->id)
            ->where('status', 'paid')
            ->exists();

        if (!$hasPaid) {
            throw ValidationException::withMessages([
                'payment' => 'A mérés zárása nem lehetséges: a díj még nincs rendezve. Kérjük, fizesd ki a tartozást a Fizetések oldalon.',
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
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', '!=', 'admin');
            })
            ->get();

        if ($participants->isEmpty()) {
            throw ValidationException::withMessages([
                'participants' => 'Nincsenek résztvevők az értékelésben.',
            ]);
        }

        // Összegyűjtjük a pontszámokat minden résztvevőre
        $scores = [];
        $userStats = [];
        foreach ($participants as $user) {
            $stat = UserService::calculateUserPoints($assessment, $user);
            if ($stat === null) {
                continue;
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
            throw ValidationException::withMessages([
                'threshold_method' => 'Hiányzik az értékelési küszöbszámítási mód (threshold_method).',
            ]);
        }

        // --- Suggested (AI) küszöbszámítás esetén: AI hívás ---
        $suggestedResult = null;
        if ($method === 'suggested') {
            /** @var SuggestedThresholdService $ai */
            $ai = app(SuggestedThresholdService::class);
            $payload = $ai->buildAiPayload($assessment, $scores, $userStats);
            $suggestedResult = $ai->callAiForSuggested($payload);

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
        }

        // --- Tranzakció: küszöbök alkalmazása és Bonus/Malus frissítése ---
        return DB::transaction(function () use (
            $assessment, $orgId, $participants, $userStats, $scores, $method, $suggestedResult
        ) {
            /** @var ThresholdService $T */
            $T = app(ThresholdService::class);
            $cfg = $T->getOrgConfigMap($orgId);

            // 1) Küszöbértékek meghatározása
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

            $up   = (int) $thresholds['normal_level_up'];
            $down = (int) $thresholds['normal_level_down'];
            $mon  = (int) ($thresholds['monthly_level_down'] ?? ($cfg['monthly_level_down'] ?? 70));
            if ($up < 0 || $up > 100 || $down < 0 || $down > 100 || $mon < 0 || $mon > 100) {
                throw ValidationException::withMessages([
                    'thresholds' => 'A küszöbök 0..100 tartományon kívüliek vagy érvénytelenek.',
                ]);
            }

            // 2) Assessment lezárása
            $assessment->normal_level_up    = $up;
            $assessment->normal_level_down  = $down;
            $assessment->monthly_level_down = $mon;
            $assessment->closed_at          = now();
            $assessment->save();

            // 3) Bonus/Malus szintek frissítése
            $useGrace = ($method === 'hybrid');
            $gracePts = $useGrace ? (int)($cfg['threshold_grace_points'] ?? 0) : 0;
            $hybridUpRaw = null;
            if ($method === 'hybrid') {
                $hybridUpRaw = $T->topPercentileScore($scores, (float)$cfg['threshold_top_pct']);
            }

            foreach ($participants as $user) {
                $stat = $userStats[$user->id] ?? null;
                if ($stat === null) {
                    continue;
                }
                $points = (float) $stat->total;
                $bm = $user->bonusMalus()->first();
                if (!$bm) {
                    $bm = new UserBonusMalus([
                        'user_id' => $user->id,
                        'month'   => now()->format('Y-m-01'),
                        'level'   => 1,
                    ]);
                    $bm->save();
                }

                if ((int) $user->has_auto_level_up === 1) {
                    if ($points < $mon) {
                        if ($bm->level < 4) {
                            $bm->level = 1;
                        } else {
                            $bm->level -= 3;
                        }
                    }
                } else {
                    $promote = false;
                    if ($useGrace && $gracePts > 0 && $hybridUpRaw !== null) {
                        if ($points >= $hybridUpRaw && $points >= ($up - $gracePts)) {
                            $promote = true;
                        }
                    }
                    if ($points >= $up) {
                        $promote = true;
                    }

                    if ($promote) {
                        $bm->level = min(15, $bm->level + 1);
                    } elseif ($points < $down) {
                        $bm->level = max(1, $bm->level - 1);
                    }
                }

                UserBonusMalus::where('month', $bm->month)
                    ->where('user_id', $bm->user_id)
                    ->update(['level' => $bm->level]);
            }

             // 4) BUILD AND SAVE USER RESULTS TO SNAPSHOT
            $month = now()->format('Y-m-01');
            $userResults = [];
            
            // Get previous assessment to calculate change indicators
            $previousAssessment = Assessment::where('organization_id', $orgId)
                ->whereNotNull('closed_at')
                ->where('closed_at', '<', $assessment->closed_at)
                ->orderByDesc('closed_at')
                ->first();
            
            $previousStats = [];
            if ($previousAssessment) {
                foreach ($participants as $user) {
                    $prevStat = UserService::calculateUserPoints($previousAssessment, $user);
                    if ($prevStat) {
                        $previousStats[$user->id] = $prevStat->total;
                    }
                }
            }
            
            foreach ($participants as $user) {
                $stat = $userStats[$user->id] ?? null;
                if ($stat === null) {
                    continue; // Skip users without valid stats
                }
                
                // Get the UPDATED bonus/malus level (after the loop above)
                $bm = $user->bonusMalus()->first();
                
                // Calculate change indicator
                $change = 'none';
                if ((int)$user->has_auto_level_up === 1) {
                    // Monthly auto-level-up users
                    if ($stat->total < $mon) {
                        $change = 'down';
                    }
                } else {
                    // Normal users
                    if ($stat->total < $down) {
                        $change = 'down';
                    } elseif ($stat->total > $up) {
                        $change = 'up';
                    }
                }
                
                // Build user result data matching the planned structure
                $userResults[(string)$user->id] = [
                    // Core stats (matching UserService response)
                    'total'          => (int)$stat->total,              // 0..100
                    'sum'            => (int)$stat->sum,                // 0..500
                    'self'           => (int)$stat->selfTotal,          // 0..100
                    'colleague'      => (int)$stat->colleagueTotal,     // 0..100 (normalized)
                    'colleagues_raw' => (int)$stat->colleaguesTotal,    // 0..150
                    'manager'        => (int)$stat->managersTotal,      // 0..100 (normalized)
                    'managers_raw'   => (int)$stat->bossTotal,          // 0..150
                    'boss_raw'       => (int)$stat->bossTotal,          // 0..150 (alias)
                    'ceo'            => (int)$stat->ceoTotal,           // 0..100
                    'complete'       => (bool)($stat->complete ?? true),
                    
                    // Bonus/Malus snapshot (at close time)
                    'bonus_malus_level' => $bm?->level,
                    'bonus_malus_month' => $month,
                    
                    // Change indicator
                    'change'             => $change,
                    'has_auto_level_up'  => (int)$user->has_auto_level_up,
                ];
            }
            
            // Save user results to snapshot using SnapshotService
            /** @var \App\Services\SnapshotService $snapService */
            $snapService = app(\App\Services\SnapshotService::class);
            $saved = $snapService->saveUserResultsToSnapshot($assessment->id, $userResults);
            
            if (!$saved) {
                Log::warning('Failed to save user results to snapshot', [
                    'assessment_id' => $assessment->id,
                ]);
                // Don't fail the assessment close, just log the warning
            }


             try {
                app(\App\Services\BonusCalculationService::class)
                    ->processAssessmentBonuses($assessment->id);
            } catch (\Exception $e) {
                Log::error('Bonus calculation failed', [
                    'assessment_id' => $assessment->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail assessment close if bonus calc fails
            }

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