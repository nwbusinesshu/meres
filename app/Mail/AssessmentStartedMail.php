<?php

namespace App\Mail;

use App\Models\Assessment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssessmentStartedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Organization $org;
    public User $user;
    public Assessment $assessment;
    public string $loginUrl;
    public $locale; // NO type hint - parent Mailable doesn't have typed properties

    /**
     * Create a new message instance.
     */
    public function __construct(
        Organization $org,
        User $user,
        Assessment $assessment,
        string $loginUrl,
        string $locale = 'hu'
    ) {
        $this->org = $org;
        $this->user = $user;
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

        return $this->subject(__('emails.assessment_started.subject', ['org_name' => $this->org->name]))
            ->markdown('emails.assessment.started', [
                'org' => $this->org,
                'user' => $this->user,
                'assessment' => $this->assessment,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}