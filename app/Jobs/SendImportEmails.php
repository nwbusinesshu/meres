<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendImportEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour
    public $tries = 1;

    protected $importJobId;
    protected $orgId;
    protected $userIds;
    protected $createdBy;

    public function __construct(int $importJobId, int $orgId, array $userIds, int $createdBy)
    {
        $this->importJobId = $importJobId;
        $this->orgId = $orgId;
        $this->userIds = $userIds;
        $this->createdBy = $createdBy;
    }

    public function handle()
    {
        \Log::info('employee.import.emails_starting', [
            'import_job_id' => $this->importJobId,
            'org_id' => $this->orgId,
            'user_count' => count($this->userIds)
        ]);
        
        // Create email batch job record
        $batchJobId = DB::table('email_batch_jobs')->insertGetId([
            'import_job_id' => $this->importJobId,
            'organization_id' => $this->orgId,
            'batch_type' => 'password_setup',
            'user_ids' => json_encode($this->userIds),
            'total_emails' => count($this->userIds),
            'delay_seconds' => 2,
            'status' => 'processing',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Dispatch individual email jobs with delays
        // This prevents blocking and allows better queue management
        foreach ($this->userIds as $index => $userId) {
            $delaySeconds = $index * 2; // 2 seconds between each email
            
            SendPasswordSetupEmail::dispatch(
                $this->orgId,
                $userId,
                $this->createdBy,
                $batchJobId
            )
                ->delay(now()->addSeconds($delaySeconds))
                ->onQueue('default');
        }

        \Log::info('employee.import.emails_dispatched', [
            'batch_job_id' => $batchJobId,
            'total_jobs' => count($this->userIds)
        ]);
    }
}