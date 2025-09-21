<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    private string $verificationCode;
    private string $userName;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $verificationCode, string $userName)
    {
        $this->verificationCode = $verificationCode;
        $this->userName = $userName;
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
        return (new MailMessage)
            ->subject('Bejelentkezési ellenőrző kód')
            ->greeting('Kedves ' . $this->userName . '!')
            ->line('A bejelentkezéshez szükséges ellenőrző kódod:')
            ->line('**' . $this->verificationCode . '**')
            ->line('Ez a kód 10 percig érvényes.')
            ->line('Ha nem te próbáltál meg bejelentkezni, kérjük, hagyd figyelmen kívül ezt az emailt.')
            ->salutation('Üdvözlettel,')
            ->salutation('A Quarma360 csapata');
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