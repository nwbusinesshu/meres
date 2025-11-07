<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public $verificationCode;
    public $userName;
    public $locale;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $verificationCode, string $userName, string $locale = 'hu')
    {
        $this->verificationCode = $verificationCode;
        $this->userName = $userName;
        $this->locale = $locale;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        // Set locale for this email
        app()->setLocale($this->locale);

        return (new MailMessage)
            ->subject(__('emails.verification_code.subject'))
            ->greeting(__('emails.verification_code.greeting', ['user_name' => $this->userName]))
            ->line(__('emails.verification_code.intro'))
            ->line('**' . $this->verificationCode . '**')
            ->line(__('emails.verification_code.expires'))
            ->line(__('emails.verification_code.warning'))
            ->salutation(__('emails.verification_code.salutation'))
            ->salutation(__('emails.verification_code.team'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'verification_code' => $this->verificationCode,
            'user_name' => $this->userName,
        ];
    }
}