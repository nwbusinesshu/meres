<?php

namespace App\Console\Commands;

use App\Models\EmailVerificationCode;
use Illuminate\Console\Command;

class CleanupExpiredVerificationCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:cleanup-verification-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired email verification codes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deletedCount = EmailVerificationCode::cleanupExpired();
        
        $this->info("Cleaned up {$deletedCount} expired verification codes.");
        
        return Command::SUCCESS;
    }
}