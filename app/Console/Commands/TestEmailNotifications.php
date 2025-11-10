<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Assessment;
use App\Models\User;
use App\Models\Organization;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Enums\OrgRole;
use App\Models\Enums\UserType;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;


class TestEmailNotifications extends Command
{
    protected $signature = 'test:emails {type?} {--email=}';
    protected $description = 'Test email notifications without creating real data';

    public function handle()
    {
        $type = $this->argument('type');
        $testEmail = $this->option('email');
        
        if (!$testEmail) {
            $this->error('Please provide --email=your@email.com');
            return 1;
        }
        
        $types = [
            'assessment-started',
            'assessment-closed',
            'payment-pending',
            'payment-success',
            'ticket-created',
            'ticket-updated',
            'assessment-progress',
        ];
        
        if ($type && !in_array($type, $types)) {
            $this->error("Invalid type. Available: " . implode(', ', $types));
            return 1;
        }
        
        if ($type) {
            $this->sendTestEmail($type, $testEmail);
        } else {
            foreach ($types as $emailType) {
                $this->sendTestEmail($emailType, $testEmail);
                $this->info("Sent: {$emailType}");
            }
        }
        
        $this->info("\n✅ Test emails sent to: {$testEmail}");
        return 0;
    }
    
    private function sendTestEmail(string $type, string $email)
    {

        config(['queue.default' => 'sync']);
        // Get first organization and user for testing
        $org = Organization::first();
        if (!$org) {
            $this->error('No organization found in database');
            return;
        }
        
        $user = User::where('type', UserType::NORMAL)->first();
        if (!$user) {
            $this->error('No normal user found in database');
            return;
        }
        
        $loginUrl = config('app.url') . '/login';
        
        switch ($type) {
            case 'assessment-started':
                $assessment = $this->getMockAssessment($org);
                Mail::to($email)->send(new \App\Mail\AssessmentStartedMail(
                    $org, $user, $assessment, $loginUrl, 'hu'
                ));
                break;
                
            case 'assessment-closed':
                $assessment = $this->getMockAssessment($org, true);
                Mail::to($email)->send(new \App\Mail\AssessmentClosedMail(
                    $org, $user, $assessment, $loginUrl, 'hu'
                ));
                break;
                
            case 'payment-pending':
                $assessment = $this->getMockAssessment($org);
                $payment = $this->getMockPayment($org, $assessment);
                Mail::to($email)->send(new \App\Mail\PaymentPendingMail(
                    $org, $user, $payment, $assessment, $loginUrl, 'hu'
                ));
                break;
                
            case 'payment-success':
                $payment = $this->getMockPayment($org, null, true);
                $invoiceUrl = config('app.url') . '/admin/payment/999/invoice';
                Mail::to($email)->send(new \App\Mail\PaymentSuccessMail(
                    $org, $user, $payment, $invoiceUrl, 'hu'
                ));
                break;
                
            case 'ticket-created':
                [$ticket, $messages] = $this->getMockTicket($org, $user);
                Mail::to($email)->send(new \App\Mail\TicketNotificationMail(
                    $org, $user, $ticket, $messages, $loginUrl, 'hu', true
                ));
                break;
                
            case 'ticket-updated':
                [$ticket, $messages] = $this->getMockTicket($org, $user, true);
                Mail::to($email)->send(new \App\Mail\TicketNotificationMail(
                    $org, $user, $ticket, $messages, $loginUrl, 'hu', false
                ));
                break;
                
            case 'assessment-progress':
                $assessment = $this->getMockAssessment($org);
                $progressData = [
                    'completion_percentage' => 65.5,
                    'completed_count' => 45,
                    'total_count' => 68,
                    'ranking_percentage' => 80.0,
                    'ranking_completed' => 32,
                    'ranking_total' => 40,
                    'has_open_payment' => true,
                    'payment_amount' => '245,000 HUF',
                ];
                Mail::to($email)->send(new \App\Mail\AssessmentProgressMail(
                    $org, $user, $assessment, $progressData, $loginUrl, 'hu'
                ));
                break;
        }
    }
    
    private function getMockAssessment(Organization $org, bool $closed = false): Assessment
    {
        $assessment = new Assessment();
        $assessment->id = 999;
        $assessment->organization_id = $org->id;
        $assessment->due_at = now()->addWeeks(2);
        $assessment->started_at = now()->subDays(3);
        $assessment->closed_at = $closed ? now()->subDays(1) : null;
        return $assessment;
    }
    
    private function getMockPayment(Organization $org, ?Assessment $assessment, bool $paid = false): object
    {
        return (object)[
            'id' => 888,
            'organization_id' => $org->id,
            'assessment_id' => $assessment?->id ?? 999,
            'currency' => 'HUF',
            'net_amount' => 193307.09,
            'vat_rate' => 0.27,
            'vat_amount' => 52192.91,
            'gross_amount' => 245500.00,
            'status' => $paid ? 'paid' : 'pending',
            'paid_at' => $paid ? now() : null,
            'billingo_invoice_number' => $paid ? 'TEST-2024-001' : null,
            'created_at' => now()->subDays(2),
        ];
    }
    
    private function getMockTicket(Organization $org, User $user, bool $withReply = false): array
    {
        $ticket = new SupportTicket();
        $ticket->id = 777;
        $ticket->organization_id = $org->id;
        $ticket->user_id = $user->id;
        $ticket->title = 'Test Support Ticket - Email Testing';
        $ticket->priority = 'high';
        $ticket->status = 'open';
        $ticket->created_at = now()->subHours(2);
        
        $message1 = new SupportTicketMessage();
        $message1->user_id = $user->id;
        $message1->message = 'This is a test ticket message for email notification testing.';
        $message1->is_staff_reply = false;
        $message1->created_at = now()->subHours(2);
        $message1->setRelation('user', $user);
        
        $messages = collect([$message1]);
        
        if ($withReply) {
            $message2 = new SupportTicketMessage();
            $message2->user_id = $user->id;
            $message2->message = 'This is a follow-up message on the ticket.';
            $message2->is_staff_reply = false;
            $message2->created_at = now()->subHour();
            $message2->setRelation('user', $user);
            
            $messages->push($message2);
        }
        
        return [$ticket, $messages];
    }
}