<?php

namespace App\Mail;

use App\Models\Assessment;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentPendingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Organization $org;
    public User $admin;
    public Payment $payment;
    public ?Assessment $assessment;
    public string $loginUrl;
    public $locale;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Organization $org,
        User $admin,
        Payment $payment,
        ?Assessment $assessment,
        string $loginUrl,
        string $locale = 'hu'
    ) {
        $this->org = $org;
        $this->admin = $admin;
        $this->payment = $payment;
        $this->assessment = $assessment;
        $this->loginUrl = $loginUrl;
        $this->locale = $locale;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Set locale for this email
        app()->setLocale($this->locale);

        return $this->subject(__('emails.payment_pending.subject', ['org_name' => $this->org->name]))
            ->markdown('emails.payment.pending', [
                'org' => $this->org,
                'admin' => $this->admin,
                'payment' => $this->payment,
                'assessment' => $this->assessment,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}