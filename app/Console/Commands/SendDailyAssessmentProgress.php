<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Assessment;
use App\Models\User;
use App\Models\Enums\OrgRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\AssessmentProgressMail;

class SendDailyAssessmentProgress extends Command
{
    protected $signature = 'assessment:daily-progress';
    protected $description = 'Send daily assessment progress emails to organization admins';

    public function handle()
    {
        $this->info('Starting daily assessment progress email job...');
        
        // Get all open assessments
        $openAssessments = Assessment::whereNull('closed_at')
            ->where('due_at', '>', now())
            ->get();
        
        if ($openAssessments->isEmpty()) {
            $this->info('No open assessments found.');
            return 0;
        }
        
        $this->info("Found {$openAssessments->count()} open assessments.");
        
        foreach ($openAssessments as $assessment) {
            try {
                $this->processAssessment($assessment);
            } catch (\Throwable $e) {
                $this->error("Failed to process assessment {$assessment->id}: {$e->getMessage()}");
                \Log::error('assessment.daily_progress.failed', [
                    'assessment_id' => $assessment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info('Daily assessment progress emails sent successfully.');
        return 0;
    }
    
    private function processAssessment(Assessment $assessment)
    {
        $orgId = $assessment->organization_id;
        $org = \App\Models\Organization::find($orgId);
        
        if (!$org) {
            $this->warn("Organization {$orgId} not found for assessment {$assessment->id}");
            return;
        }
        
        // Get organization admins
        $admins = User::whereNull('removed_at')
            ->whereHas('organizations', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                  ->where('organization_user.role', OrgRole::ADMIN);
            })
            ->get();
        
        if ($admins->isEmpty()) {
            $this->warn("No admins found for organization {$orgId}");
            return;
        }
        
        // Calculate progress data
        $progressData = $this->calculateProgress($assessment);
        
        // Send emails to all admins
        $loginUrl = config('app.url') . '/login';
        
        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new AssessmentProgressMail(
                    $org,
                    $admin,
                    $assessment,
                    $progressData,
                    $loginUrl,
                    $admin->locale ?? 'hu'
                ));
                
                $this->info("Email sent to {$admin->email} for assessment {$assessment->id}");
            } catch (\Throwable $e) {
                $this->error("Failed to send email to {$admin->email}: {$e->getMessage()}");
            }
        }
        
        \Log::info('assessment.daily_progress.sent', [
            'assessment_id' => $assessment->id,
            'org_id' => $orgId,
            'admin_count' => $admins->count()
        ]);
    }
    
    private function calculateProgress(Assessment $assessment): array
    {
        $orgId = $assessment->organization_id;
        
        // Get total participants (excluding admins)
        $totalCount = DB::table('assessment_relation_snapshot')
            ->where('assessment_id', $assessment->id)
            ->distinct('user_id')
            ->count('user_id');
        
        // Get completed assessments count
        $completedCount = DB::table('assessment_relation_snapshot')
            ->where('assessment_id', $assessment->id)
            ->where('complete', 1)
            ->distinct('user_id')
            ->count('user_id');
        
        $completionPercentage = $totalCount > 0 
            ? round(($completedCount / $totalCount) * 100, 1) 
            : 0;
        
        // Get CEO ranking progress
        $rankingTotal = DB::table('assessment_relation_snapshot')
            ->where('assessment_id', $assessment->id)
            ->whereNotNull('ceo_id')
            ->distinct('user_id')
            ->count('user_id');
        
        $rankingCompleted = DB::table('ceo_ranks')
            ->where('assessment_id', $assessment->id)
            ->whereNotNull('ranked_at')
            ->count();
        
        $rankingPercentage = $rankingTotal > 0 
            ? round(($rankingCompleted / $rankingTotal) * 100, 1) 
            : 0;
        
        // Check for open payment
        $openPayment = DB::table('payments')
            ->where('organization_id', $orgId)
            ->where('assessment_id', $assessment->id)
            ->where('status', 'pending')
            ->first();
        
        $paymentAmount = null;
        if ($openPayment) {
            $paymentAmount = number_format($openPayment->gross_amount, 0, ',', ' ') 
                           . ' ' . $openPayment->currency;
        }
        
        return [
            'completion_percentage' => $completionPercentage,
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'ranking_percentage' => $rankingPercentage,
            'ranking_completed' => $rankingCompleted,
            'ranking_total' => $rankingTotal,
            'has_open_payment' => (bool)$openPayment,
            'payment_amount' => $paymentAmount,
        ];
    }
}