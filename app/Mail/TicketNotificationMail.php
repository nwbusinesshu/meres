<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Organization $org;
    public User $recipient;
    public SupportTicket $ticket;
    public Collection $messages;
    public string $loginUrl;
    public $locale;
    public bool $isNewTicket;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Organization $org,
        User $recipient,
        SupportTicket $ticket,
        Collection $messages,
        string $loginUrl,
        string $locale = 'hu',
        bool $isNewTicket = false
    ) {
        $this->org = $org;
        $this->recipient = $recipient;
        $this->ticket = $ticket;
        $this->messages = $messages;
        $this->loginUrl = $loginUrl;
        $this->locale = $locale;
        $this->isNewTicket = $isNewTicket;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Set locale for this email
        app()->setLocale($this->locale);

        $subjectKey = $this->isNewTicket 
            ? 'emails.ticket_notification.subject_new' 
            : 'emails.ticket_notification.subject_update';

        return $this->subject(__($subjectKey, ['ticket_id' => $this->ticket->id]))
            ->markdown('emails.ticket.notification', [
                'org' => $this->org,
                'recipient' => $this->recipient,
                'ticket' => $this->ticket,
                'messages' => $this->messages,
                'loginUrl' => $this->loginUrl,
                'isNewTicket' => $this->isNewTicket,
            ]);
    }
}