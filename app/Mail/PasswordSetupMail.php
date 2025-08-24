<?php

namespace App\Mail;

use App\Models\PasswordSetup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public PasswordSetup $ps;
    public string $orgSlug;
    public string $token;

    public function __construct(PasswordSetup $ps, string $orgSlug, string $token)
    {
        $this->ps = $ps;
        $this->orgSlug = $orgSlug;
        $this->token = $token;
    }

    public function build()
    {
        $url = route('password-setup.show', ['org' => $this->orgSlug, 'token' => $this->token]);

        return $this->subject('Jelszó beállítása – 360 értékelés')
            ->view('emails.password_setup', [
                'url'        => $url,
                'email'      => $this->ps->user->email,
                'org'        => $this->ps->organization->name,
                'expires_at' => $this->ps->expires_at,
            ]);
    }
}
