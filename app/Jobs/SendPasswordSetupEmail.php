<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Services\PasswordSetupService;
use App\Models\User;

class SendPasswordSetupEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;
    public $tries = 3; // Retry up to 3 times
    public $backoff = 60; // Wait 60 seconds between retries

    protected $orgId;
    protected $userId;
    protected $createdBy;
    protected $batchJobId;

    public function __construct(int $orgId, int $userId, int $createdBy, ?int $batchJobId = null)
    {
        $this->orgId = $orgId;
        $this->userId = $userId;
        $this->createdBy = $createdBy;
        $this->batchJobId = $batchJobId;
    }

    public function handle()
    {
        try {
            $user = User::find($this->userId);
            
            if (!$user) {
                throw new \Exception("User not found: {$this->userId}");
            }
            
            // Send password setup email using the correct method signature
            PasswordSetupService::createAndSend($this->orgId, $this->userId, $this->createdBy);
            
            // Update batch job if exists
            if ($this->batchJobId) {
                DB::table('email_batch_jobs')
                    ->where('id', $this->batchJobId)
                    ->increment('sent_emails');
            }
            
            \Log::info('employee.import.email_sent', [
                'org_id' => $this->orgId,
                'user_id' => $this->userId,
                'email' => $user->email
            ]);
            
        } catch (\Exception $e) {
            // Update batch job failure count
            if ($this->batchJobId) {
                DB::table('email_batch_jobs')
                    ->where('id', $this->batchJobId)
                    ->increment('failed_emails');
            }
            
            \Log::error('employee.import.email_failed', [
                'org_id' => $this->orgId,
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            
            throw $e; // Rethrow to trigger retry
        }
    }
    
    /**
     * Handle failed job after all retries
     */
    public function failed(\Throwable $exception)
    {
        \Log::error('employee.import.email_permanently_failed', [
            'org_id' => $this->orgId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
    }
}