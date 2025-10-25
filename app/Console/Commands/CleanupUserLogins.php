<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupUserLogins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user-logins:cleanup 
                           {--days=30 : Number of days to keep login records}
                           {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old user login records while keeping at least one login per user';

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
        
        $this->info("Cleaning up user logins older than {$days} days (before {$cutoffDate})...");
        
        // Get all users who have login records
        $usersWithLogins = DB::table('user_login')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');
        
        if ($usersWithLogins->isEmpty()) {
            $this->info('No user login records found.');
            return Command::SUCCESS;
        }
        
        $totalToDelete = 0;
        $deletedCount = 0;
        
        // Process each user
        foreach ($usersWithLogins as $userId) {
            // Get the most recent login for this user
            $mostRecentLogin = DB::table('user_login')
                ->where('user_id', $userId)
                ->orderBy('logged_in_at', 'desc')
                ->first();
            
            if (!$mostRecentLogin) {
                continue;
            }
            
            // Count how many old logins can be deleted (excluding the most recent one)
            $oldLoginsQuery = DB::table('user_login')
                ->where('user_id', $userId)
                ->where('logged_in_at', '<', $cutoffDate)
                ->where('logged_in_at', '!=', $mostRecentLogin->logged_in_at);
            
            $count = $oldLoginsQuery->count();
            $totalToDelete += $count;
            
            if ($count > 0) {
                if ($dryRun) {
                    $this->line("User ID {$userId}: Would delete {$count} login(s), keeping most recent from {$mostRecentLogin->logged_in_at}");
                } else {
                    // Delete old logins but keep the most recent one
                    $deleted = $oldLoginsQuery->delete();
                    $deletedCount += $deleted;
                    $this->line("User ID {$userId}: Deleted {$deleted} login(s), kept most recent from {$mostRecentLogin->logged_in_at}");
                }
            }
        }
        
        if ($totalToDelete === 0) {
            $this->info('No old login records to clean up.');
            return Command::SUCCESS;
        }
        
        if ($dryRun) {
            $this->warn("[DRY RUN] Would delete {$totalToDelete} login record(s) total");
            $this->info('Run without --dry-run to actually delete these records.');
        } else {
            $this->info("Successfully deleted {$deletedCount} login record(s) total.");
            Log::info("user_logins.cleanup.success", [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate,
                'users_processed' => $usersWithLogins->count()
            ]);
        }
        
        return Command::SUCCESS;
    }
}