<?php
namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserBonusMalus;
use App\Models\Enums\OrgRole;
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

        // ========================================
        // CHECK FOR UNPAID INITIAL PAYMENT (TRIAL BLOCKING)
        // ========================================
        if (is_null($assessment)) {
            // Only check when creating NEW assessment (not editing existing)
            $unpaidInitialPayment = DB::table('payments')
                ->where('organization_id', $orgId)
                ->whereNull('assessment_id')
                ->where('status', '!=', 'paid')
                ->first();

            if ($unpaidInitialPayment) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'assessment' => [__('assessment.unpaid_initial_payment')]
                ]);
            }
        }
        // ========================================
        // END TRIAL BLOCKING CHECK
        // ========================================


        \Log::info('saveAssessment', [
            'orgId'   => $orgId,
            'request' => $request->all()
        ]);

        if (!$orgId) {
            return response()->json([
                'message' => __('assessment.no_organization_selected'),
                'errors'  => ['org' => [__('assessment.no_organization_selected')]]
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
                    'message' => __('assessment.cannot_start'),
                    'errors'  => $e->errors()
                ], 422);
            }
        }

                if (is_null($assessment)) {
            // ✅ Validate pilot scope
            if ($request->scope === 'pilot') {
                $hasAssessments = Assessment::where('organization_id', $orgId)->exists();
                
                if ($hasAssessments) {
                    throw ValidationException::withMessages([
                        'scope' => [__('admin/home.scope-pilot-unavailable')]
                    ]);
                }
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
                    'assessment' => [__('assessment.already_running')]
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
                        'snapshot' => __('assessment.snapshot_creation_failed', ['error' => $e->getMessage()])
                    ]);
                }

                $snapshotJson = $this->safeJsonEncode($snapshotArr, 'organization snapshot');

                // Assessment létrehozása
                $assessment = new Assessment();
                $assessment->organization_id = $orgId;
                $assessment->started_at = now();
                $assessment->due_at = $request->due;
                $assessment->closed_at = null;
                $assessment->is_pilot = ($request->scope === 'pilot');
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
                        
                        \Log::info('assessment.billing.excess_only', [
                            'org_id'           => $orgId,
                            'employee_count'   => $employeeCount,
                            'employee_limit'   => $employeeLimit,
                            'excess_employees' => $excessEmployees,
                        ]);
                        
                        if ($assessment && $excessEmployees > 0) {
                            // Calculate payment amounts using PaymentHelper
                            $paymentAmounts = \App\Services\PaymentHelper::calculatePaymentAmounts($orgId, $excessEmployees);
                            
                            DB::table('payments')->insert([
                                'organization_id' => $orgId,
                                'assessment_id'   => $assessment->id,
                                'currency'        => $paymentAmounts['currency'],
                                'net_amount'      => $paymentAmounts['net_amount'],
                                'vat_rate'        => $paymentAmounts['vat_rate'],
                                'vat_amount'      => $paymentAmounts['vat_amount'],
                                'gross_amount'    => $paymentAmounts['gross_amount'],
                                'status'          => 'pending',
                                'created_at'      => now(),
                                'updated_at'      => now(),
                            ]);
                            
                            \Log::info('assessment.payment.created.excess', [
                                'org_id' => $orgId,
                                'assessment_id' => $assessment->id,
                                'excess_employees' => $excessEmployees,
                                'currency' => $paymentAmounts['currency'],
                                'gross_amount' => $paymentAmounts['gross_amount'],
                            ]);
                        }
                    }
                }
                } else {
                    // HAS CLOSED ASSESSMENTS - Use current logic (all employees)
                    \Log::info('assessment.billing.normal', [
                        'org_id'           => $orgId,
                        'employee_count'   => $employeeCount,
                        'reason'           => 'Has closed assessments',
                    ]);

                    if ($assessment && $employeeCount > 0) {
                        // Calculate payment amounts using PaymentHelper
                        $paymentAmounts = \App\Services\PaymentHelper::calculatePaymentAmounts($orgId, $employeeCount);
                        
                        DB::table('payments')->insert([
                            'organization_id' => $orgId,
                            'assessment_id'   => $assessment->id,
                            'currency'        => $paymentAmounts['currency'],
                            'net_amount'      => $paymentAmounts['net_amount'],
                            'vat_rate'        => $paymentAmounts['vat_rate'],
                            'vat_amount'      => $paymentAmounts['vat_amount'],
                            'gross_amount'    => $paymentAmounts['gross_amount'],
                            'status'          => 'pending',
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                        
                        \Log::info('assessment.payment.created.normal', [
                            'org_id' => $orgId,
                            'assessment_id' => $assessment->id,
                            'employee_count' => $employeeCount,
                            'currency' => $paymentAmounts['currency'],
                            'gross_amount' => $paymentAmounts['gross_amount'],
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

    public function checkPilotAvailable(Request $request)
    {
        $orgId = (int) session('org_id');
        
        if (!$orgId) {
            return response()->json(['pilot_available' => false]);
        }
        
        $hasAssessments = Assessment::where('organization_id', $orgId)->exists();
        
        return response()->json([
            'pilot_available' => !$hasAssessments
        ]);
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
            'assessment' => __('assessment.not_authorized_organization')
        ]);
    }

    // ========================================
    // SECTION 2: PRE-CLOSE VALIDATION
    // ========================================
    $validator = app(\App\Services\AssessmentValidator::class);
    
    try {
        $validator->validateAssessmentReadyToClose($assessment->id);
    } catch (ValidationException $e) {
        Log::warning('Assessment close validation failed', [
            'assessment_id' => $assessment->id,
            'errors' => $e->errors()
        ]);
        
        return response()->json([
            'ok' => false,
            'message' => __('assessment.cannot_close_yet'),
            'errors' => $e->errors()
        ], 422);
    }

    // ========================================
    // SECTION 3: PREPARE DATA & CALCULATE THRESHOLDS
    // ========================================
    $cfg = $this->thresholds->getOrgConfigMap($orgId);
    $method = strtolower((string)$assessment->threshold_method);
    if (!$method || !in_array($method, ['fixed', 'hybrid', 'dynamic', 'suggested'])) {
        throw ValidationException::withMessages([
            'assessment' => __('assessment.invalid_threshold_method', ['method' => $method])
        ]);
    }

    // Get participants
    $participants = UserService::getAssessmentParticipants($assessment->id);
    
    if ($participants->isEmpty()) {
        throw ValidationException::withMessages([
            'participants' => __('assessment.no_participants')
        ]);
    }

    // ✅ FIX: Calculate user stats using ID-based method
    $scores = [];
    $userStats = [];
    $detailedResults = [];
    
    foreach ($participants as $participant) {
        $detailedResult = UserService::calculateUserPointsDetailedByIds($assessment->id, $participant->id);
        
        if ($detailedResult && $detailedResult['final_0_100'] > 0) {
            $stat = (object) [
                'total'              => $detailedResult['final_0_100'],
                'selfTotal'          => $detailedResult['self_points'] ?? 0,
                'colleagueTotal'     => $detailedResult['colleague_points'] ?? 0,
                'colleaguesTotal'    => $detailedResult['colleague_points'] ?? 0,
                'directReportsTotal' => $detailedResult['direct_reports_points'] ?? 0,
                'managersTotal'      => $detailedResult['boss_points'] ?? 0,
                'bossTotal'          => $detailedResult['boss_points'] ?? 0,
                'ceoTotal'           => $detailedResult['ceo_points'] ?? 0,
                'sum'                => $detailedResult['weighted_sum'] ?? 0,
                'complete'           => $detailedResult['complete'] ?? false,
            ];
            
            $userStats[$participant->id] = $stat;
            $detailedResults[$participant->id] = $detailedResult;
            $scores[] = (float)$stat->total;
        }
    }

    // Validate we have scores
    if (empty($scores)) {
        throw ValidationException::withMessages([
            'scores' => __('assessment.no_scores')
        ]);
    }

    // ========================================
    // SECTION 4: THRESHOLD CALCULATION
    // ========================================
    $T = $this->thresholds;
    $suggestedResult = null;
    $hybridUpRaw = null;

    // Calculate thresholds based on method
    if ($method === 'suggested') {
        try {
            $ai = app(SuggestedThresholdService::class);
            $payload = $ai->buildAiPayload($assessment, $scores, $userStats);
            $suggestedResult = $ai->callAiForSuggested($payload);

            if (!$suggestedResult) {
                throw new \RuntimeException(__('assessment.ai_response_empty'));
            }
            
            if (!isset($suggestedResult['thresholds']['normal_level_up']) ||
                !isset($suggestedResult['thresholds']['normal_level_down'])) {
                throw new \RuntimeException(__('assessment.ai_response_missing_thresholds'));
            }

            // ✅ Use thresholdsFromSuggested
            $result = $T->thresholdsFromSuggested($cfg, $suggestedResult);
            $up = $result['normal_level_up'];
            $down = $result['normal_level_down'];
            $mon = $result['monthly_level_down'];
            
            $assessment->suggested_decision = $assessment->suggested_decision 
                ? array_merge((array)$assessment->suggested_decision, [$suggestedResult])
                : [$suggestedResult];

        } catch (\Throwable $e) {
            Log::error('AI call failed during assessment close', [
                'assessment_id' => $assessment->id,
                'error' => $e->getMessage()
            ]);
            
            throw ValidationException::withMessages([
                'ai' => __('assessment.ai_calculation_failed', ['error' => $e->getMessage()])
            ]);
        }
    } elseif ($method === 'fixed') {
        // ✅ Use thresholdsForFixed
        $result = $T->thresholdsForFixed($cfg);
        $up = $result['normal_level_up'];
        $down = $result['normal_level_down'];
        $mon = $result['monthly_level_down'];
    } elseif ($method === 'hybrid') {
        // ✅ Use thresholdsForHybrid
        $result = $T->thresholdsForHybrid($cfg, $scores);
        $up = $result['normal_level_up'];
        $down = $result['normal_level_down'];
        $mon = $result['monthly_level_down'];
        $hybridUpRaw = $result['_hybrid_up_raw'] ?? null;
    } elseif ($method === 'dynamic') {
        // ✅ Use thresholdsForDynamic
        $result = $T->thresholdsForDynamic($cfg, $scores);
        $up = $result['normal_level_up'];
        $down = $result['normal_level_down'];
        $mon = $result['monthly_level_down'];
    } else {
        // Fallback to fixed
        $result = $T->thresholdsForFixed($cfg);
        $up = $result['normal_level_up'];
        $down = $result['normal_level_down'];
        $mon = $result['monthly_level_down'];
    }

    // ========================================
    // SECTION 5: TRANSACTION - UPDATE ASSESSMENT & BONUS/MALUS
    // ========================================
    return DB::transaction(function () use ($assessment, $participants, $userStats, $detailedResults, $scores, $up, $down, $mon, $method, $cfg, $orgId, $hybridUpRaw) {

        // 1. Update assessment
        $assessment->normal_level_up = $up;
        $assessment->normal_level_down = $down;
        $assessment->monthly_level_down = $mon;
        $assessment->threshold_method = $method;
        $assessment->closed_at = now();
        $assessment->save();

        // 2. Update Bonus/Malus levels (SKIP if pilot assessment)
        $month = now()->format('Y-m-01');
        
        if (!$assessment->is_pilot) {
            $useGrace = ($method === 'hybrid');
            $gracePts = $useGrace ? (int)($cfg['threshold_grace_points'] ?? 0) : 0;

            // ✅ Load User models for has_auto_level_up
            $userModels = User::whereIn('id', array_keys($userStats))->get()->keyBy('id');

            foreach ($participants as $participant) {
                $stat = $userStats[$participant->id] ?? null;
                if ($stat === null) continue;

                $points = (float) $stat->total;
                $bm = UserBonusMalus::where('user_id', $participant->id)
                    ->where('organization_id', $orgId)
                    ->where('month', $month)
                    ->first();

                if (!$bm) {
                    $bm = UserBonusMalus::create([
                        'user_id' => $participant->id,
                        'organization_id' => $orgId,
                        'month' => $month,
                        'level' => UserService::DEFAULT_BM,
                    ]);
                }

                $promote = false;
                if ($useGrace && $gracePts > 0 && $hybridUpRaw !== null) {
                    if ($points >= $hybridUpRaw && $points >= ($up - $gracePts)) {
                        $promote = true;
                    }
                }

                if ($points >= $up) $promote = true;

                if ($promote) {
                    $bm->level = min(15, $bm->level + 1);
                } elseif ($points < $down) {
                    $bm->level = max(1, $bm->level - 1);
                }

                $bm->save();
            }
            
            Log::info("Bonus/malus levels updated for assessment {$assessment->id}");
        } else {
            Log::info("Skipping bonus/malus level changes - pilot assessment {$assessment->id}");
        }

        // 3. Build user results for snapshot
        $previousAssessment = Assessment::where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->where('closed_at', '<', $assessment->closed_at)
            ->orderByDesc('closed_at')
            ->first();

        $previousStats = [];
        if ($previousAssessment) {
            foreach ($participants as $participant) {
                $cached = UserService::getUserResultsFromSnapshot($previousAssessment->id, $participant->id);
                if ($cached) {
                    $previousStats[$participant->id] = $cached['total'];
                }
            }
        }

        // ✅ Load User models OUTSIDE the pilot check so they're available for snapshot
        $userModels = User::whereIn('id', array_keys($userStats))->get()->keyBy('id');

        $userResults = [];
        foreach ($participants as $participant) {
            $stat = $userStats[$participant->id] ?? null;
            if ($stat === null) continue;

            $bm = UserBonusMalus::where('user_id', $participant->id)
                ->where('organization_id', $orgId)
                ->where('month', $month)
                ->first();

            $change = 'none';
            if (isset($previousStats[$participant->id])) {
                $prevTotal = (int)$previousStats[$participant->id];
                $currTotal = (int)$stat->total;
                if ($currTotal > $prevTotal) $change = 'up';
                elseif ($currTotal < $prevTotal) $change = 'down';
            }

            $userModel = $userModels->get($participant->id);
            $hasAutoLevelUp = $userModel ? (int)$userModel->has_auto_level_up : 0;
            $detailed = $detailedResults[$participant->id] ?? null;

            // ✅ Build user result with NEW 5-component structure
            $userResults[(string)$participant->id] = [
                'total' => (int)$stat->total,
                'sum' => (int)$stat->sum,
                'self' => (int)$stat->selfTotal,
                'colleague' => (int)round($stat->colleagueTotal),
                'colleagues_raw' => (int)$stat->colleaguesTotal,
                'direct_reports' => (int)($stat->directReportsTotal ?? 0),
                'manager' => (int)round($stat->managersTotal),
                'managers_raw' => (int)$stat->bossTotal,
                'boss_raw' => (int)$stat->bossTotal,
                'ceo' => (int)$stat->ceoTotal,
                'complete' => (bool)($stat->complete ?? true),
                'bonus_malus_level' => $bm?->level,
                'bonus_malus_month' => $month,
                'change' => $change,
                'has_auto_level_up' => $hasAutoLevelUp,
                'components_available' => $detailed ? (int)$detailed['components_available'] : 0,
                'missing_components' => $detailed ? array_values((array)$detailed['missing_components']) : [],
                'is_ceo' => $detailed ? (bool)$detailed['is_ceo'] : false,
                'weighted_sum' => $detailed ? (float)$detailed['weighted_sum'] : 0.0,
                'total_weight' => $detailed ? (float)$detailed['total_weight'] : 0.0,
            ];
        }

        // 4. Save user results to snapshot
        $snapService = app(\App\Services\SnapshotService::class);
        $saved = $snapService->saveUserResultsToSnapshot($assessment->id, $userResults);

        if (!$saved) {
            Log::error('Failed to save user results to snapshot', [
                'assessment_id' => $assessment->id,
            ]);
            throw new \RuntimeException(__('assessment.snapshot_save_failed'));
        }

        // 5. Calculate bonuses (if enabled and NOT pilot assessment)
        if (!$assessment->is_pilot) {
            try {
                app(\App\Services\BonusCalculationService::class)
                    ->processAssessmentBonuses($assessment->id);
                Log::info("Bonuses calculated for assessment {$assessment->id}");
            } catch (\Exception $e) {
                Log::error('Bonus calculation failed', [
                    'assessment_id' => $assessment->id,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            Log::info("Skipping bonus calculation - pilot assessment {$assessment->id}");
        }

        // 6. Return success
        return response()->json([
            'ok' => true,
            'message' => __('assessment.closed_successfully'),
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
                'json' => __('assessment.json_encoding_failed', ['context' => $context, 'error' => $errorMsg])
            ]);
        }
        
        // Validate size (should be reasonable, not empty, not > 15MB)
        $size = strlen($json);
        
        if ($size === 0) {
            throw ValidationException::withMessages([
                'json' => __('assessment.json_result_empty', ['context' => $context])
            ]);
        }
        
        if ($size > 95000000) { // 95MB limit
            $sizeMb = round($size / 1024 / 1024, 2);
            throw ValidationException::withMessages([
                'json' => __('assessment.json_too_large', ['context' => $context, 'size' => $sizeMb])
            ]);
        }
        
        return $json;
    }
}