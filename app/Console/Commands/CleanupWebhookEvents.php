<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupWebhookEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:cleanup 
                           {--days=30 : Number of days to keep webhook events}
                           {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old webhook event records to prevent database bloat';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = now()->subDays($days);
        
        $this->info("Cleaning up webhook events older than {$days} days (before {$cutoffDate})...");
        
        // Count records to be deleted
        $count = DB::table('webhook_events')
            ->where('created_at', '<', $cutoffDate)
            ->count();
        
        if ($count === 0) {
            $this->info('No webhook events to clean up.');
            return Command::SUCCESS;
        }
        
        if ($dryRun) {
            $this->warn("[DRY RUN] Would delete {$count} webhook event(s)");
            
            // Show some examples
            $examples = DB::table('webhook_events')
                ->where('created_at', '<', $cutoffDate)
                ->orderBy('created_at')
                ->limit(5)
                ->get(['id', 'event_type', 'external_id', 'status', 'created_at']);
            
            $this->table(
                ['ID', 'Type', 'External ID', 'Status', 'Created At'],
                $examples->map(fn($e) => [
                    $e->id,
                    $e->event_type,
                    $e->external_id,
                    $e->status,
                    $e->created_at
                ])->toArray()
            );
            
            $this->info('Run without --dry-run to actually delete these records.');
            return Command::SUCCESS;
        }
        
        // Actually delete the records
        try {
            $deleted = DB::table('webhook_events')
                ->where('created_at', '<', $cutoffDate)
                ->delete();
            
            $this->info("Successfully deleted {$deleted} webhook event(s).");
            
            Log::info('webhook.cleanup.completed', [
                'deleted_count' => $deleted,
                'cutoff_date' => $cutoffDate,
                'days' => $days,
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->error('Failed to clean up webhook events: ' . $e->getMessage());
            
            Log::error('webhook.cleanup.failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}