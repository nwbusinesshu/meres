<?php

namespace App\Mail;

use App\Models\PasswordSetup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Organization;
use App\Models\User;

class PasswordSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public Organization $org;
    public User $user;
    public string $url;
    public \Carbon\CarbonInterface $expiresAt;

    public function __construct(Organization $org, User $user, string $url, \Carbon\CarbonInterface $expiresAt)
    {
        $this->org = $org;
        $this->user = $user;
        $this->url = $url;
        $this->expiresAt = $expiresAt;
    }

    public function build()
    {
        return $this->subject('Jelszó beállítása – 360 értékelés')
            ->view('emails.password.setup', [
                'url'        => $this->url,
                'email'      => $this->user->email,
                'org'        => $this->org->name,
                'expires_at' => $this->expiresAt,
            ]);
    }
}

