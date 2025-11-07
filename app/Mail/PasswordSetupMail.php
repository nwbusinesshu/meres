<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\CarbonInterface;

class PasswordSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public Organization $org;
    public User $user;
    public string $url;
    public CarbonInterface $expiresAt;
    public $locale; // NO type hint - parent Mailable doesn't have typed properties

    public function __construct(Organization $org, User $user, string $url, CarbonInterface $expiresAt, string $locale = 'hu')
    {
        $this->org = $org;
        $this->user = $user;
        $this->url = $url;
        $this->expiresAt = $expiresAt;
        $this->locale = $locale;
    }

    public function build()
    {
        // Set locale for this email
        app()->setLocale($this->locale);

        return $this->subject(__('emails.password_setup.subject', ['org_name' => $this->org->name]))
            ->markdown('emails.password.setup', [
                'org'       => $this->org,
                'user'      => $this->user,
                'url'       => $this->url,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}