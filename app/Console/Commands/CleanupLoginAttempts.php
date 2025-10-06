<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LoginAttemptService;

class CleanupLoginAttempts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'login-attempts:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired login attempt records';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Cleaning up expired login attempts...');
        
        $deleted = LoginAttemptService::cleanupExpiredLockouts();
        
        $this->info("Cleaned up {$deleted} expired lockout records.");
        
        return 0;
    }
}