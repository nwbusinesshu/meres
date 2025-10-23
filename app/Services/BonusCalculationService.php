<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentBonus;
use App\Models\BonusMalusConfig;
use App\Models\UserBonusMalus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BonusCalculationService
{
    /**
     * Calculate bonus amount using formula: (net_wage / 40) * multiplier
     * 
     * @param float $netWage
     * @param float $multiplier
     * @return float
     */
    public function calculateBonus(float $netWage, float $multiplier): float
    {
        return round(($netWage / 40) * $multiplier, 2);
    }

    /**
     * Process all bonuses for an assessment when it closes
     * This is called from AdminAssessmentController->closeAssessment()
     * 
     * @param int $assessmentId
     * @return void
     */
    public function processAssessmentBonuses(int $assessmentId): void
    {
        $assessment = Assessment::findOrFail($assessmentId);
        $orgId = $assessment->organization_id;

        // ✅ STEP 1: Check if bonus calculation is enabled
        $enableBonusCalculation = OrgConfigService::getBool($orgId, 'enable_bonus_calculation', false);
        
        if (!$enableBonusCalculation) {
            Log::info("Bonus calculation skipped for assessment {$assessmentId} - feature is disabled for org {$orgId}");
            return; // Exit early if bonus calculation is disabled
        }

        // ✅ STEP 2: Load snapshot
        $snapshot = json_decode($assessment->org_snapshot, true);
        if (!$snapshot) {
            Log::warning("No snapshot for assessment {$assessmentId}");
            return;
        }

        $users = $snapshot['users'] ?? [];
        
        // ✅ STEP 3: Check if wage data exists in snapshot
        $hasWageData = false;
        foreach ($users as $user) {
            if (isset($user['net_wage']) && $user['net_wage'] > 0) {
                $hasWageData = true;
                break;
            }
        }

        // ✅ STEP 4: If no wage data, refresh it from database
        if (!$hasWageData) {
            Log::info("Wage data missing in snapshot for assessment {$assessmentId}, refreshing...");
            
            $snapshotService = app(SnapshotService::class);
            $updated = $snapshotService->updateSnapshotWageData($assessmentId, $orgId);
            
            if (!$updated) {
                Log::error("Failed to update wage data in snapshot for assessment {$assessmentId}");
                return;
            }

            // Reload snapshot with updated wage data
            $assessment = Assessment::findOrFail($assessmentId);
            $snapshot = json_decode($assessment->org_snapshot, true);
            $users = $snapshot['users'] ?? [];
            
            Log::info("Wage data refreshed in snapshot for assessment {$assessmentId}");
        }

        // ✅ STEP 5: Calculate bonuses for each user
        $bonusRecords = [];
        $bonusDataForSnapshot = [];

        foreach ($users as $user) {
            $userId = $user['id'];
            $netWage = $user['net_wage'] ?? 0;
            $currency = $user['currency'] ?? 'HUF';

            // Skip if no wage data
            if ($netWage <= 0) {
                Log::debug("Skipping bonus for user {$userId} - no wage data");
                continue;
            }

            // Get NEW bonus-malus level (after assessment results)
            $newLevel = $this->getLatestBonusMalusLevel($userId, $orgId);
            
            // Get multiplier for this level
            $multiplier = BonusMalusConfig::getMultiplierForLevel($orgId, $newLevel);
            
            // Calculate bonus
            $bonusAmount = $this->calculateBonus($netWage, $multiplier);

            // Prepare bonus record for database
            $bonusRecords[] = [
                'assessment_id' => $assessmentId,
                'user_id' => $userId,
                'bonus_malus_level' => $newLevel,
                'net_wage' => $netWage,
                'currency' => $currency,
                'multiplier' => $multiplier,
                'bonus_amount' => $bonusAmount,
                'is_paid' => false,
                'paid_at' => null,
                'created_at' => now(),
            ];

            // Prepare bonus data for snapshot
            $bonusDataForSnapshot[$userId] = [
                'bonus_malus_level' => $newLevel,
                'multiplier' => $multiplier,
                'bonus_amount' => $bonusAmount,
                'currency' => $currency,
                'calculated_at' => now()->toIso8601String(),
            ];
        }

        // ✅ STEP 6: Save bonus records to database
        if (!empty($bonusRecords)) {
            DB::table('assessment_bonuses')->insert($bonusRecords);
            Log::info("Created " . count($bonusRecords) . " bonus records for assessment {$assessmentId}");
        } else {
            Log::info("No bonuses to create for assessment {$assessmentId} - no users with wage data");
            return; // No bonuses to save to snapshot either
        }

        // ✅ STEP 7: Save bonus results to snapshot for historical record
        $snapshotService = app(SnapshotService::class);
        $snapshotUpdated = $snapshotService->updateSnapshotBonusData($assessmentId, $bonusDataForSnapshot);
        
        if ($snapshotUpdated) {
            Log::info("Bonus calculation results saved to snapshot for assessment {$assessmentId}");
        } else {
            Log::warning("Failed to save bonus results to snapshot for assessment {$assessmentId}");
        }
    }

    /**
     * Get latest bonus-malus level for user
     * 
     * @param int $userId
     * @param int $orgId
     * @return int
     */
    private function getLatestBonusMalusLevel(int $userId, int $orgId): int
    {
        $latest = UserBonusMalus::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->orderBy('month', 'desc')
            ->first();

        return $latest ? $latest->level : 5; // Default A00
    }

    /**
     * Get multiplier for level (with fallback)
     * 
     * @param int $orgId
     * @param int $level
     * @return float
     */
    public function getMultiplierForLevel(int $orgId, int $level): float
    {
        return BonusMalusConfig::getMultiplierForLevel($orgId, $level);
    }
}