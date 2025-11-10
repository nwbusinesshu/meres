<?php

namespace App\Http\Controllers;

use App\Mail\AssessmentClosedMail;
use App\Mail\AssessmentProgressMail;
use App\Mail\AssessmentStartedMail;
use App\Mail\PasswordResetMail;
use App\Mail\PasswordSetupMail;
use App\Mail\PaymentPendingMail;
use App\Mail\PaymentSuccessMail;
use App\Mail\TicketNotificationMail;
use App\Models\Assessment;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class EmailPreviewController extends Controller
{
    public function preview(Request $request)
    {
        $type = $request->get('type', 'reset');
        
        // Get test data from database
        $org = Organization::first();
        $user = User::first();
        
        if (!$org || !$user) {
            return 'No organization or user found in database. Please create test data first.';
        }
        
        // Get locale from query parameter, default to 'hu'
        $locale = $request->get('locale', 'hu');
        
        // Common URLs
        $loginUrl = config('app.url') . '/login';
        $passwordSetupUrl = config('app.url') . '/password-setup/test-token-12345';
        $expiresAt = CarbonImmutable::now()->addWeek();
        
        switch ($type) {
            case 'setup':
                return new PasswordSetupMail($org, $user, $passwordSetupUrl, $expiresAt, $locale);
                
            case 'reset':
                return new PasswordResetMail($org, $user, $passwordSetupUrl, $expiresAt, $locale);
                
            case 'assessment-started':
                $assessment = $this->getMockAssessment($org);
                return new AssessmentStartedMail($org, $user, $assessment, $loginUrl, $locale);
                
            case 'assessment-closed':
                $assessment = $this->getMockAssessment($org, true);
                return new AssessmentClosedMail($org, $user, $assessment, $loginUrl, $locale);
                
            case 'ticket-new':
                [$ticket, $messages] = $this->getMockTicket($org, $user);
                return new TicketNotificationMail($org, $user, $ticket, $messages, $loginUrl, $locale, true);
                
            case 'ticket-update':
                [$ticket, $messages] = $this->getMockTicket($org, $user, true);
                return new TicketNotificationMail($org, $user, $ticket, $messages, $loginUrl, $locale, false);
                
            case 'payment-pending':
                $payment = $this->getMockPayment($org);
                $assessment = $this->getMockAssessment($org);
                return new PaymentPendingMail($org, $user, $payment, $assessment, $loginUrl, $locale);
                
            case 'payment-success':
                $payment = $this->getMockPayment($org, true);
                $invoiceDownloadUrl = config('app.url') . '/admin/payment/' . $payment->id . '/invoice';
                return new PaymentSuccessMail($org, $user, $payment, $invoiceDownloadUrl, $locale);
                
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
                return new AssessmentProgressMail($org, $user, $assessment, $progressData, $loginUrl, $locale);
                
            default:
                return response()->json([
                    'error' => 'Unknown email type',
                    'available_types' => [
                        'setup',
                        'reset',
                        'assessment-started',
                        'assessment-closed',
                        'ticket-new',
                        'ticket-update',
                        'payment-pending',
                        'payment-success',
                        'assessment-progress',
                    ],
                    'usage' => 'Add ?type=TYPE&locale=LOCALE to the URL',
                ], 400);
        }
    }
    
    /**
     * Create a mock Assessment object for preview
     */
    private function getMockAssessment(Organization $org, bool $closed = false): Assessment
    {
        $assessment = new Assessment();
        $assessment->id = 999;
        $assessment->organization_id = $org->id;
        $assessment->due = CarbonImmutable::now()->addWeeks(2);
        $assessment->created_at = CarbonImmutable::now()->subDays(3);
        
        if ($closed) {
            $assessment->closed_at = CarbonImmutable::now()->subDays(1);
        }
        
        return $assessment;
    }
    
    /**
     * Create a mock Payment object for preview
     */
    private function getMockPayment(Organization $org, bool $paid = false): Payment
    {
        $payment = new Payment();
        $payment->id = 888;
        $payment->organization_id = $org->id;
        $payment->assessment_id = 999;
        $payment->currency = 'HUF';
        $payment->net_amount = 193307.09;
        $payment->vat_rate = 0.27;
        $payment->vat_amount = 52192.91;
        $payment->gross_amount = 245500.00;
        $payment->status = $paid ? 'paid' : 'pending';
        $payment->created_at = CarbonImmutable::now()->subHours(2);
        
        if ($paid) {
            $payment->paid_at = CarbonImmutable::now()->subHours(1);
            $payment->billingo_invoice_number = 'Q360-2024-00123';
            $payment->billingo_document_id = 123456789;
            $payment->billingo_issue_date = CarbonImmutable::now()->subHours(1)->toDateString();
        }
        
        return $payment;
    }
    
    /**
     * Create a mock SupportTicket with messages for preview
     */
    private function getMockTicket(Organization $org, User $user, bool $hasReply = false): array
    {
        $ticket = new SupportTicket();
        $ticket->id = 777;
        $ticket->organization_id = $org->id;
        $ticket->user_id = $user->id;
        $ticket->title = 'Nem tudok bejelentkezni a rendszerbe';
        $ticket->status = $hasReply ? 'in_progress' : 'open';
        $ticket->priority = 'high';
        $ticket->created_at = CarbonImmutable::now()->subHours(3);
        $ticket->updated_at = CarbonImmutable::now()->subMinutes(15);
        
        // Create mock messages
        $messages = new Collection();
        
        // Initial message
        $message1 = new SupportTicketMessage();
        $message1->id = 1;
        $message1->ticket_id = $ticket->id;
        $message1->user_id = $user->id;
        $message1->message = 'Szia! Nem tudok bejelentkezni a rendszerbe. A jelszómat megadtam helyesen, de folyamatosan hibát kapok. Tudnátok segíteni?';
        $message1->is_staff = false;
        $message1->created_at = CarbonImmutable::now()->subHours(3);
        
        // Set user relationship
        $message1->setRelation('user', $user);
        $messages->push($message1);
        
        if ($hasReply) {
            // Support reply
            $staffUser = new User();
            $staffUser->id = 1;
            $staffUser->name = 'Support Team';
            $staffUser->email = 'support@quarma360.com';
            
            $message2 = new SupportTicketMessage();
            $message2->id = 2;
            $message2->ticket_id = $ticket->id;
            $message2->user_id = $staffUser->id;
            $message2->message = 'Szia! Köszönjük a bejelentést. Megvizsgáltuk a fiókodat és látjuk, hogy a jelszó helyesen van beállítva. Kérlek próbáld meg törölni a böngésző cache-t és sütiket, majd próbálj újra bejelentkezni. Ha továbbra is problémád van, jelezd nekünk!';
            $message2->is_staff = true;
            $message2->created_at = CarbonImmutable::now()->subMinutes(15);
            
            // Set user relationship
            $message2->setRelation('user', $staffUser);
            $messages->push($message2);
        }
        
        return [$ticket, $messages];
    }
}