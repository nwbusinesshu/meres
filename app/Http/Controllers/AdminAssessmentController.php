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
            attributes: $attributes,
        );

        // ✅ NEW: Pre-creation validation (only for NEW assessments)
        if (is_null($assessment)) {
            $validator = app(\App\Services\AssessmentValidator::class);
            
            try {
                $validator->validateAssessmentCreation($orgId);
            } catch (ValidationException $e) {
                // Return validation errors to user
                return response()->json([
                    'message' => 'Az értékelés nem indítható el.',
                    'errors'  => $e->errors()
                ], 422);
            }
        }

        // Tranzakció
        return AjaxService::DBTransaction(function () use ($request, &$assessment, $orgId) {

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

                // ✅ NEW: Wrap snapshot creation in try-catch
                try {
                    /** @var \App\Services\SnapshotService $snap */
                    $snap = app(\App\Services\SnapshotService::class);
                    $snapshotArr  = $snap->buildOrgSnapshot($orgId);
                } catch (\Throwable $e) {
                    \Log::error('Snapshot creation failed', [
                        'org_id' => $orgId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw ValidationException::withMessages([
                        'snapshot' => 'A snapshot létrehozása sikertelen: ' . $e->getMessage()
                    ]);
                }

                $snapshotJson = $this->safeJsonEncode($snapshotArr, 'organization snapshot');

                // Assessment létrehozása
                $assessment = new Assessment();
                $assessment->organization_id = $orgId;
                $assessment->started_at = now();
                $assessment->due_at = $request->due;
                $assessment->closed_at = null;
                $assessment->threshold_method = $init['threshold_method'] ?? null;
                $assessment->normal_level_up = $init['normal_level_up'] ?? null;
                $assessment->normal_level_down = $init['normal_level_down'] ?? null;
                $assessment->monthly_level_down = $init['monthly_level_down'] ?? null;
                $assessment->org_snapshot = $snapshotJson;
                $assessment->org_snapshot_version = 'v1';
                $assessment->save();

                // ========== BILLING LOGIC (unchanged) ==========
                // Count employees (excluding admins)
                // Count employees (EXCLUDING admins)
                $userIds = DB::table('organization_user')
                    ->where('organization_id', $orgId)
                    ->where('role', '!=', OrgRole::ADMIN)  // ✅ ADD THIS LINE
                    ->pluck('user_id')
                    ->unique()
                    ->toArray();

                $employeeCount = User::query()
                    ->whereIn('id', $userIds)
                    ->whereNull('removed_at')
                    ->where('type', '!=', UserType::SUPERADMIN)  // ✅ ADD THIS LINE TOO
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
                    $hasUnpaidPayment = DB::table('payments')
                        ->where('organization_id', $orgId)
                        ->where('status', '!=', 'paid')
                        ->exists();
                    
                    if ($hasUnpaidPayment) {
                        \Log::info('assessment.billing.skip', [
                            'reason' => 'Already has unpaid payment',
                            'org_id' => $orgId,
                        ]);
                    } else {
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
                            \Log::info('assessment.billing.skip', [
                                'reason' => 'Within employee limit',
                                'org_id' => $orgId,
                            ]);
                        } else {
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
                // ========== END BILLING LOGIC ==========

            } else {
                // Meglévő assessment → csak due_at frissítés
                $assessment->due_at = $request->due;
                $assessment->save();
            }
        });
    }

    public function closeAssessment(Request $request)
    {
        // ========================================
        // SECTION 1: LOAD & VALIDATE ACCESS
        // ========================================
        $assessment = Assessment::findOrFail($request->id);
        $orgId = (int) $assessment->organization_id;
        
        if ($orgId !== (int) session('org_id')) {
            throw ValidationException::withMessages([
                'assessment' => 'Nem jogosult szervezet.'
            ]);
        }

        // ========================================
        // SECTION 2: PRE-CLOSE VALIDATION
        // ========================================
        $validator = app(\App\Services\AssessmentValidator::class);
        
        try {
            $validator->validateAssessmentReadyToClose($assessment->id);
        } catch (ValidationException $e) {
            // Return detailed validation errors
            Log::warning('Assessment close validation failed', [
                'assessment_id' => $assessment->id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'ok' => false,
                'message' => 'Az értékelés még nem zárható le.',
                'errors' => $e->errors()
            ], 422);
        }

        // ========================================
        // SECTION 3: PREPARE DATA & CALCULATE THRESHOLDS
        // ========================================
        $cfg = $this->thresholds->getOrgConfigMap($orgId);
        $method = strtolower((string)($cfg['threshold_mode'] ?? 'fixed'));

        // Get participants
        $participants = UserService::getAssessmentParticipants($assessment->id);
        
        if ($participants->isEmpty()) {
            throw ValidationException::withMessages([
                'participants' => 'Nincsenek résztvevők az értékelésben.'
            ]);
        }

        // Calculate user stats and collect scores
        $scores = [];
        $userStats = [];
        
        foreach ($participants as $user) {
            $stat = UserService::calculateUserPoints($assessment, $user);
            if ($stat && $stat->total > 0) {
                $userStats[$user->id] = $stat;
                $scores[] = (float)$stat->total;
            }
        }

        // Validate we have scores
        if (empty($scores)) {
            throw ValidationException::withMessages([
                'scores' => 'Nincsenek pontszámok az értékelésben. Nincs mit lezárni.'
            ]);
        }

        // ========================================
        // SECTION 4: THRESHOLD CALCULATION WITH ERROR HANDLING
        // ========================================
        $T = $this->thresholds;
        $suggestedResult = null;

        // Special handling for SUGGESTED method (AI call)
        if ($method === 'suggested') {
            try {
                /** @var SuggestedThresholdService $ai */
                $ai = app(SuggestedThresholdService::class);
                $payload = $ai->buildAiPayload($assessment, $scores, $userStats);
                $suggestedResult = $ai->callAiForSuggested($payload);

                // Validate AI response
                if (!$suggestedResult) {
                    throw new \RuntimeException('AI válasz üres.');
                }
                
                if (!isset($suggestedResult['thresholds']['normal_level_up']) ||
                    !isset($suggestedResult['thresholds']['normal_level_down'])) {
                    throw new \RuntimeException('AI válasz nem tartalmaz küszöbértékeket.');
                }

            } catch (\Throwable $e) {
                Log::error('AI call failed during assessment close', [
                    'assessment_id' => $assessment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                throw ValidationException::withMessages([
                    'ai' => 'AI küszöbszámítás sikertelen: ' . $e->getMessage() . 
                            '. Az értékelés nem zárható le "suggested" móddal. ' .
                            'Kérjük, változtassa meg a küszöbszámítási módot a beállításokban.'
                ]);
            }
        }

        // Calculate thresholds based on method
        try {
            $result = match ($method) {
                'fixed' => $T->thresholdsForFixed($cfg),
                'hybrid' => $T->thresholdsForHybrid($cfg, $scores),
                'dynamic' => $T->thresholdsForDynamic($cfg, $scores),
                'suggested' => $T->thresholdsFromSuggested($cfg, $suggestedResult),
                default => throw new \RuntimeException("Ismeretlen threshold_method: {$method}")
            };
        } catch (\Throwable $e) {
            Log::error('Threshold calculation failed', [
                'assessment_id' => $assessment->id,
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            
            throw ValidationException::withMessages([
                'thresholds' => 'Küszöbszámítás sikertelen: ' . $e->getMessage()
            ]);
        }

        $up = (int)$result['normal_level_up'];
        $down = (int)$result['normal_level_down'];
        $mon = (int)($result['monthly_level_down'] ?? $cfg['monthly_level_down'] ?? 70);

        // ========================================
        // SECTION 5: VALIDATE CALCULATED THRESHOLDS
        // ========================================
        if ($up < 0 || $up > 100 || $down < 0 || $down > 100 || $mon < 0 || $mon > 100) {
            throw ValidationException::withMessages([
                'thresholds' => "A küszöbök 0..100 tartományon kívüliek. Up: {$up}, Down: {$down}, Monthly: {$mon}"
            ]);
        }

        if ($up <= $down) {
            throw ValidationException::withMessages([
                'thresholds' => "Az előléptetési küszöb ({$up}) nem lehet kisebb vagy egyenlő a lefokozási küszöbbel ({$down})."
            ]);
        }

        // ========================================
        // SECTION 6: TRANSACTION - ATOMIC CLOSE
        // ========================================
        return DB::transaction(function () use (
            $assessment, $up, $down, $mon, $method, $cfg, $participants, $userStats, $scores, $T, $orgId
        ) {
            // 1. Update assessment record
            $assessment->normal_level_up = $up;
            $assessment->normal_level_down = $down;
            $assessment->monthly_level_down = $mon;
            $assessment->threshold_method = $method;
            $assessment->closed_at = now();
            $assessment->save();

            // 2. Update Bonus/Malus levels
            $month = now()->format('Y-m-01');
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

                // Get or create bonus/malus record
                $bm = UserBonusMalus::where('user_id', $user->id)
                    ->where('organization_id', $orgId)
                    ->where('month', $month)
                    ->first();

                if (!$bm) {
                    $bm = UserBonusMalus::create([
                        'user_id' => $user->id,
                        'organization_id' => $orgId,
                        'month' => $month,
                        'level' => UserService::DEFAULT_BM,
                    ]);
                }

                // Calculate promotion/demotion
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

                $bm->save();
            }

            // 3. Build user results for snapshot
            $previousAssessment = Assessment::where('organization_id', $orgId)
                ->whereNotNull('closed_at')
                ->where('closed_at', '<', $assessment->closed_at)
                ->orderByDesc('closed_at')
                ->first();

            $previousStats = [];
            if ($previousAssessment) {
                foreach ($participants as $user) {
                    $cached = UserService::getUserResultsFromSnapshot($previousAssessment->id, $user->id);
                    if ($cached) {
                        $previousStats[$user->id] = $cached['total'];
                    }
                }
            }

            $userResults = [];
            foreach ($participants as $user) {
                $stat = $userStats[$user->id] ?? null;
                if ($stat === null) {
                    continue;
                }

                // Get bonus/malus
                $bm = UserBonusMalus::where('user_id', $user->id)
                    ->where('organization_id', $orgId)
                    ->where('month', $month)
                    ->first();

                // Calculate change
                $change = 'none';
                if (isset($previousStats[$user->id])) {
                    $prevTotal = (int)$previousStats[$user->id];
                    $currTotal = (int)$stat->total;
                    if ($currTotal > $prevTotal) {
                        $change = 'up';
                    } elseif ($currTotal < $prevTotal) {
                        $change = 'down';
                    }
                }

                // Build user result
                $userResults[(string)$user->id] = [
                    'total' => (int)$stat->total,
                    'sum' => (int)$stat->sum,
                    'self' => (int)$stat->selfTotal,
                    'colleague' => (int)round($stat->colleagueTotal),
                    'colleagues_raw' => (int)$stat->colleaguesTotal,
                    'manager' => (int)round($stat->managersTotal),
                    'managers_raw' => (int)$stat->bossTotal,
                    'boss_raw' => (int)$stat->bossTotal,
                    'ceo' => (int)$stat->ceoTotal,
                    'complete' => (bool)($stat->complete ?? true),
                    'bonus_malus_level' => $bm?->level,
                    'bonus_malus_month' => $month,
                    'change' => $change,
                    'has_auto_level_up' => (int)$user->has_auto_level_up,
                ];
            }

            // 4. Save user results to snapshot
            /** @var \App\Services\SnapshotService $snapService */
            $snapService = app(\App\Services\SnapshotService::class);
            $saved = $snapService->saveUserResultsToSnapshot($assessment->id, $userResults);

            if (!$saved) {
                Log::error('Failed to save user results to snapshot', [
                    'assessment_id' => $assessment->id,
                ]);
                throw new \RuntimeException('Az eredmények mentése a snapshot-ba sikertelen.');
            }

            // 5. Calculate bonuses (if enabled)
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

            // 6. Return success
            return response()->json([
                'ok' => true,
                'message' => 'Az értékelés sikeresen lezárva.',
                'thresholds' => [
                    'normal_level_up' => $up,
                    'normal_level_down' => $down,
                    'monthly_level_down' => $mon,
                    'method' => $method,
                ],
            ]);
        });
    }

    /**
     * Safely encode data to JSON with validation
     * 
     * @param mixed $data
     * @param string $context Description of what's being encoded (for error messages)
     * @return string
     * @throws ValidationException
     */
    private function safeJsonEncode($data, string $context = 'data'): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            $errorMsg = json_last_error_msg();
            
            Log::error('JSON encoding failed', [
                'context' => $context,
                'json_error' => $errorMsg,
                'json_error_code' => json_last_error()
            ]);
            
            throw ValidationException::withMessages([
                'json' => "JSON kódolás sikertelen ({$context}): {$errorMsg}"
            ]);
        }
        
        // Validate size (should be reasonable, not empty, not > 15MB)
        $size = strlen($json);
        
        if ($size === 0) {
            throw ValidationException::withMessages([
                'json' => "JSON kódolás eredménye üres ({$context})"
            ]);
        }
        
        if ($size > 15000000) { // 15MB limit
            $sizeMb = round($size / 1024 / 1024, 2);
            throw ValidationException::withMessages([
                'json' => "JSON túl nagy ({$context}): {$sizeMb} MB. Maximum 15 MB."
            ]);
        }
        
        return $json;
    }
}