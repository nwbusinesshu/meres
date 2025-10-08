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

        // Get snapshot
        $snapshot = json_decode($assessment->org_snapshot, true);
        if (!$snapshot) {
            Log::warning("No snapshot for assessment {$assessmentId}");
            return;
        }

        $users = $snapshot['users'] ?? [];
        $bonusRecords = [];

        foreach ($users as $user) {
            $userId = $user['id'];
            $netWage = $user['net_wage'] ?? 0;
            $currency = $user['currency'] ?? 'HUF';

            // Skip if no wage data
            if ($netWage <= 0) {
                continue;
            }

            // Get NEW bonus-malus level (after assessment results)
            $newLevel = $this->getLatestBonusMalusLevel($userId, $orgId);
            
            // Get multiplier for this level
            $multiplier = BonusMalusConfig::getMultiplierForLevel($orgId, $newLevel);
            
            // Calculate bonus
            $bonusAmount = $this->calculateBonus($netWage, $multiplier);

            // Store bonus record
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
        }

        // Bulk insert bonuses
        if (!empty($bonusRecords)) {
            DB::table('assessment_bonuses')->insert($bonusRecords);
            Log::info("Created " . count($bonusRecords) . " bonus records for assessment {$assessmentId}");
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