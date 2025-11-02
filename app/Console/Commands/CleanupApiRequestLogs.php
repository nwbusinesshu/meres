<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupApiRequestLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-logs:cleanup 
                           {--days=30 : Number of days to keep API request logs}
                           {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old API request logs to prevent database bloat';

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
        
        $this->info("Cleaning up API request logs older than {$days} days (before {$cutoffDate})...");
        
        // Count records to be deleted
        $count = DB::table('api_request_logs')
            ->where('created_at', '<', $cutoffDate)
            ->count();
        
        if ($count === 0) {
            $this->info('No API request logs to clean up.');
            return Command::SUCCESS;
        }
        
        if ($dryRun) {
            $this->warn("[DRY RUN] Would delete {$count} API request log(s)");
            
            // Show some statistics
            $stats = DB::table('api_request_logs')
                ->where('created_at', '<', $cutoffDate)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('COUNT(DISTINCT api_key_id) as unique_keys')
                ->selectRaw('COUNT(DISTINCT organization_id) as unique_orgs')
                ->selectRaw('MIN(created_at) as oldest')
                ->selectRaw('MAX(created_at) as newest')
                ->first();
            
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total records', number_format($stats->total)],
                    ['Unique API keys', $stats->unique_keys],
                    ['Unique organizations', $stats->unique_orgs],
                    ['Oldest record', $stats->oldest],
                    ['Newest record', $stats->newest],
                ]
            );
            
            // Show some examples by endpoint
            $examples = DB::table('api_request_logs')
                ->where('created_at', '<', $cutoffDate)
                ->selectRaw('endpoint, COUNT(*) as count')
                ->groupBy('endpoint')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get();
            
            if ($examples->isNotEmpty()) {
                $this->newLine();
                $this->info('Top 5 endpoints to be cleaned:');
                $this->table(
                    ['Endpoint', 'Count'],
                    $examples->map(fn($e) => [$e->endpoint, number_format($e->count)])->toArray()
                );
            }
            
            $this->info('Run without --dry-run to actually delete these records.');
            return Command::SUCCESS;
        }
        
        // Actually delete the records
        try {
            $deleted = DB::table('api_request_logs')
                ->where('created_at', '<', $cutoffDate)
                ->delete();
            
            $this->info("Successfully deleted {$deleted} API request log(s).");
            
            Log::info('api_logs.cleanup.completed', [
                'deleted_count' => $deleted,
                'cutoff_date' => $cutoffDate,
                'days' => $days,
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->error('Failed to clean up API request logs: ' . $e->getMessage());
            
            Log::error('api_logs.cleanup.failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}