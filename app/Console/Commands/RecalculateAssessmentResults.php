<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\UserService;
use App\Services\SnapshotService;
use App\Models\Assessment;
use App\Models\UserBonusMalus;

class RecalculateAssessmentResults extends Command
{
    protected $signature = 'assessment:recalculate {assessment_id}';
    protected $description = 'Recalculate and re-cache results for a closed assessment (preserves existing bonus/malus levels)';

    public function handle()
    {
        $assessmentId = (int) $this->argument('assessment_id');
        
        $this->info("🔄 Recalculating results for assessment ID: {$assessmentId}");
        $this->newLine();
        $this->warn("⚠️  IMPORTANT: This command will:");
        $this->warn("   ✅ Recalculate scores (fixing manager ranking bug)");
        $this->warn("   ✅ Preserve existing bonus/malus levels (NO changes)");
        $this->warn("   ✅ Update cached display data only");
        $this->newLine();
        
        if (!$this->confirm('Do you want to continue?', true)) {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        $this->newLine();
        
        // 1. Verify assessment exists and is closed
        $assessment = Assessment::find($assessmentId);
        
        if (!$assessment) {
            $this->error("❌ Assessment {$assessmentId} not found!");
            return 1;
        }
        
        if (!$assessment->closed_at) {
            $this->error("❌ Assessment {$assessmentId} is not closed yet!");
            return 1;
        }
        
        $this->info("✅ Found closed assessment: {$assessment->id}");
        $this->info("   Organization ID: {$assessment->organization_id}");
        $this->info("   Closed at: {$assessment->closed_at}");
        
        // 2. Get all participants from snapshot
        $participants = UserService::getAssessmentParticipants($assessment->id);
        
        if ($participants->isEmpty()) {
            $this->error("❌ No participants found in assessment snapshot!");
            return 1;
        }
        
        $this->info("✅ Found {$participants->count()} participants");
        
        // 3. Get previous assessment for change tracking
        $prevAssessment = Assessment::where('organization_id', $assessment->organization_id)
            ->where('closed_at', '<', $assessment->closed_at)
            ->whereNotNull('closed_at')
            ->orderBy('closed_at', 'desc')
            ->first();
        
        $previousStats = [];
        if ($prevAssessment) {
            $this->info("✅ Found previous assessment for change tracking: {$prevAssessment->id}");
            $prevSnapshot = json_decode($prevAssessment->org_snapshot, true);
            foreach ($prevSnapshot['user_results'] ?? [] as $uid => $res) {
                $previousStats[(int)$uid] = $res['total'] ?? 0;
            }
        }
        
        // 4. Get original snapshot to preserve bonus/malus levels
        // NOTE: We can't use the original snapshot because it was already overwritten
        // Instead, we read from user_bonus_malus table
        
        // 5. Recalculate all user results
        $this->info("\n🔢 Recalculating scores...");
        $userResults = [];
        $orgId = $assessment->organization_id;
        // IMPORTANT: Two date formats used here:
        // 1. $monthForQuery = 'Y-m-01' format for querying user_bonus_malus (DATE column)
        // 2. $monthForDisplay = 'Y-m' format for storing in snapshot (consistency)
        $monthForQuery = date('Y-m-01', strtotime($assessment->closed_at));
        $monthForDisplay = date('Y-m', strtotime($assessment->closed_at));
        
        $bar = $this->output->createProgressBar($participants->count());
        $bar->start();
        
        foreach ($participants as $participant) {
            $detailed = UserService::calculateUserPointsDetailedByIds($assessment->id, $participant->id);
            
            if (!$detailed || $detailed['final_0_100'] <= 0) {
                $bar->advance();
                continue;
            }
            
            $stat = (object) [
                'total' => $detailed['final_0_100'],
                'selfTotal' => $detailed['self_points'] ?? 0,
                'colleagueTotal' => $detailed['colleague_points'] ?? 0,
                'colleaguesTotal' => $detailed['colleague_points'] ?? 0,
                'directReportsTotal' => $detailed['direct_reports_points'] ?? 0,
                'managersTotal' => $detailed['boss_points'] ?? 0,
                'bossTotal' => $detailed['boss_points'] ?? 0,
                'ceoTotal' => $detailed['ceo_points'] ?? 0,
                'sum' => $detailed['weighted_sum'] ?? 0,
                'complete' => $detailed['complete'] ?? false,
            ];
            
            // ✅ OPTION 1: Preserve existing bonus/malus level (no retroactive changes)
            // Read from user_bonus_malus table with correct date format (Y-m-01)
            // This prevents retroactive promotions/demotions after scores are corrected
            $bm = UserBonusMalus::where('user_id', $participant->id)
                ->where('organization_id', $orgId)
                ->where('month', $monthForQuery)
                ->first();
            
            $change = 'none';
            if (isset($previousStats[$participant->id])) {
                $prevTotal = (int)$previousStats[$participant->id];
                $currTotal = (int)$stat->total;
                if ($currTotal > $prevTotal) $change = 'up';
                elseif ($currTotal < $prevTotal) $change = 'down';
            }
            
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
                'bonus_malus_month' => $monthForDisplay, // Store as Y-m for display consistency
                'change' => $change,
                'components_available' => (int)$detailed['components_available'],
                'missing_components' => array_values((array)$detailed['missing_components']),
                'is_ceo' => (bool)$detailed['is_ceo'],
                'weighted_sum' => (float)$detailed['weighted_sum'],
                'total_weight' => (float)$detailed['total_weight'],
            ];
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // 5. Save results to snapshot
        $this->info("💾 Saving results to snapshot...");
        $snapService = app(SnapshotService::class);
        $saved = $snapService->saveUserResultsToSnapshot($assessment->id, $userResults);
        
        if (!$saved) {
            $this->error("❌ Failed to save results to snapshot!");
            return 1;
        }
        
        $this->info("✅ Results saved successfully!");
        
        // 6. Show summary
        $this->newLine();
        $this->info("📊 Summary:");
        $this->info("   (Scores recalculated, bonus/malus levels preserved from original)");
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Users recalculated', count($userResults)],
                ['Complete assessments', count(array_filter($userResults, fn($r) => $r['complete']))],
                ['Bonus/Malus levels preserved', count(array_filter($userResults, fn($r) => $r['bonus_malus_level'] !== null))],
                ['Users affected by fix', count(array_filter($userResults, fn($r) => $r['ceo'] > 0 && !in_array('ceo_rank', $r['missing_components'])))],
                ['---', '---'],
                ['Missing CEO rank', count(array_filter($userResults, fn($r) => in_array('ceo_rank', $r['missing_components'])))],
                ['Missing managers', count(array_filter($userResults, fn($r) => in_array('managers', $r['missing_components'])))],
                ['Missing colleagues', count(array_filter($userResults, fn($r) => in_array('colleagues', $r['missing_components'])))],
                ['Missing self', count(array_filter($userResults, fn($r) => in_array('self', $r['missing_components'])))],
                ['Missing direct reports', count(array_filter($userResults, fn($r) => in_array('direct_reports', $r['missing_components'])))],
            ]
        );
        
        $this->newLine();
        $this->info("🎉 Recalculation complete!");
        
        return 0;
    }
}