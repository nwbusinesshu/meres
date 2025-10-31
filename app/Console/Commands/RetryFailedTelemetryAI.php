<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessTelemetryAI;
use App\Services\OrgConfigService;

class RetryFailedTelemetryAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telemetry:retry-failed 
                            {--limit=50 : Maximum number of jobs to retry in this run}
                            {--assessment= : Specific assessment ID to process}
                            {--force : Retry even if already processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry AI telemetry processing for failed/pending submissions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $assessmentId = $this->option('assessment');
        $force = $this->option('force');

        $this->info('🔍 Scanning for failed AI telemetry processing...');

        // Find open assessments (not closed)
        $query = DB::table('assessment')
            ->select('id', 'organization_id')
            /*->whereNull('closed_at')*/;

        if ($assessmentId) {
            $query->where('id', $assessmentId);
        }

        $assessments = $query->get();

        if ($assessments->isEmpty()) {
            $this->warn('No open assessments found.');
            return 0;
        }

        $this->info("Found {$assessments->count()} open assessment(s)");

        $totalRetried = 0;
        $totalSkipped = 0;

        foreach ($assessments as $assessment) {
            // Check if AI telemetry is enabled for this org
            $aiEnabled = OrgConfigService::getBool(
                $assessment->organization_id, 
                OrgConfigService::AI_TELEMETRY_KEY, 
                true
            );

            if (!$aiEnabled) {
                $this->line("  ⊘ Assessment {$assessment->id}: AI telemetry disabled for org");
                continue;
            }

            // Find submissions with telemetry_raw but no telemetry_ai
            $submitsQuery = DB::table('user_competency_submit')
                ->where('assessment_id', $assessment->id)
                ->whereNotNull('telemetry_raw')
                ->where('telemetry_raw', '!=', 'null');

            if (!$force) {
                $submitsQuery->whereNull('telemetry_ai');
            }

            $submitsQuery->limit($limit - $totalRetried);
            $submits = $submitsQuery->get(['assessment_id', 'user_id', 'target_id']);

            if ($submits->isEmpty()) {
                $this->line("  ✓ Assessment {$assessment->id}: No pending submissions");
                continue;
            }

            $this->line("  📊 Assessment {$assessment->id}: Found {$submits->count()} pending submission(s)");

            foreach ($submits as $submit) {
                try {
                    ProcessTelemetryAI::dispatch(
                        $submit->assessment_id,
                        $submit->user_id,
                        $submit->target_id
                    )->onQueue('default');

                    $totalRetried++;

                    $this->line("    → Dispatched: user {$submit->user_id} → target {$submit->target_id}");

                    Log::info('[Retry] AI telemetry job dispatched', [
                        'assessment_id' => $submit->assessment_id,
                        'user_id' => $submit->user_id,
                        'target_id' => $submit->target_id,
                        'context' => 'retry_command'
                    ]);

                    // Rate limiting: small delay between dispatches to avoid overwhelming queue
                    if ($totalRetried % 10 === 0) {
                        usleep(100000); // 100ms pause every 10 jobs
                    }

                    if ($totalRetried >= $limit) {
                        $this->warn("⚠️  Reached limit of {$limit} retries");
                        break 2; // Break out of both loops
                    }

                } catch (\Throwable $e) {
                    $totalSkipped++;
                    $this->error("    ✗ Failed to dispatch: {$e->getMessage()}");
                    
                    Log::error('[Retry] Failed to dispatch AI telemetry job', [
                        'assessment_id' => $submit->assessment_id,
                        'user_id' => $submit->user_id,
                        'target_id' => $submit->target_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->newLine();
        $this->info("✅ Complete: {$totalRetried} job(s) retried, {$totalSkipped} skipped");

        Log::info('[Retry] Command completed', [
            'total_retried' => $totalRetried,
            'total_skipped' => $totalSkipped,
            'limit' => $limit
        ]);

        return 0;
    }
}