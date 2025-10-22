<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\TelemetryService;

class ProcessTelemetryAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 240; // 4 minutes for OpenAI call
    public $tries = 3; // Retry up to 3 times on failure

    protected $assessmentId;
    protected $userId;
    protected $targetId;

    /**
     * Create a new job instance.
     *
     * @param int $assessmentId
     * @param int $userId
     * @param int $targetId
     */
    public function __construct(int $assessmentId, int $userId, int $targetId)
    {
        $this->assessmentId = $assessmentId;
        $this->userId = $userId;
        $this->targetId = $targetId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('[AI Job] ProcessTelemetryAI starting', [
            'assessment_id' => $this->assessmentId,
            'user_id' => $this->userId,
            'target_id' => $this->targetId
        ]);

        try {
            $result = TelemetryService::scoreAndStoreTelemetryAI(
                $this->assessmentId,
                $this->userId,
                $this->targetId
            );

            if ($result) {
                Log::info('[AI Job] ProcessTelemetryAI completed successfully', [
                    'assessment_id' => $this->assessmentId,
                    'user_id' => $this->userId,
                    'target_id' => $this->targetId,
                    'trust_score' => $result['trust_score'] ?? null
                ]);
            } else {
                Log::warning('[AI Job] ProcessTelemetryAI returned null', [
                    'assessment_id' => $this->assessmentId,
                    'user_id' => $this->userId,
                    'target_id' => $this->targetId
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[AI Job] ProcessTelemetryAI failed', [
                'assessment_id' => $this->assessmentId,
                'user_id' => $this->userId,
                'target_id' => $this->targetId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('[AI Job] ProcessTelemetryAI failed permanently after all retries', [
            'assessment_id' => $this->assessmentId,
            'user_id' => $this->userId,
            'target_id' => $this->targetId,
            'error' => $exception->getMessage()
        ]);
    }
}