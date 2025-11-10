<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Organization $org;
    public User $admin;
    public object $payment;  // ✅ Change from Payment to object
    public string $invoiceDownloadUrl;
    public $locale;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Organization $org,
        User $admin,
        object $payment,  // ✅ Change from Payment to object
        string $invoiceDownloadUrl,
        string $locale = 'hu'
    ) {
        $this->org = $org;
        $this->admin = $admin;
        $this->payment = $payment;
        $this->invoiceDownloadUrl = $invoiceDownloadUrl;
        $this->locale = $locale;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Set locale for this email
        app()->setLocale($this->locale);

        return $this->subject(__('emails.payment_success.subject', ['org_name' => $this->org->name]))
            ->markdown('emails.payment.success', [
                'org' => $this->org,
                'admin' => $this->admin,
                'payment' => $this->payment,
                'invoiceDownloadUrl' => $this->invoiceDownloadUrl,
            ]);
    }
}