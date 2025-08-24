<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\CarbonImmutable;

class PasswordResetMail extends Mailable /* implements ShouldQueue ha később sorba akarod tenni */
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $org,
        public User $user,
        public string $url,
        public CarbonImmutable $expiresAt
    ) {}

    public function build()
    {
        return $this->subject('Jelszó visszaállítása – '.$this->org->name)
            ->markdown('emails.password.reset', [
                'org'       => $this->org,
                'user'      => $this->user,
                'url'       => $this->url,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}
