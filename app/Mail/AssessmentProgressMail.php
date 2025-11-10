<?php

namespace App\Mail;

use App\Models\Assessment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssessmentProgressMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Organization $org;
    public User $admin;
    public Assessment $assessment;
    public array $progressData;
    public string $loginUrl;
    public $locale;

    /**
     * Create a new message instance.
     * 
     * @param array $progressData Expected keys:
     *   - completion_percentage: float (0-100)
     *   - completed_count: int
     *   - total_count: int
     *   - ranking_percentage: float (0-100)
     *   - ranking_completed: int
     *   - ranking_total: int
     *   - has_open_payment: bool
     *   - payment_amount: string|null
     */
    public function __construct(
        Organization $org,
        User $admin,
        Assessment $assessment,
        array $progressData,
        string $loginUrl,
        string $locale = 'hu'
    ) {
        $this->org = $org;
        $this->admin = $admin;
        $this->assessment = $assessment;
        $this->progressData = $progressData;
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

        return $this->subject(__('emails.assessment_progress.subject', ['org_name' => $this->org->name]))
            ->markdown('emails.assessment.progress', [
                'org' => $this->org,
                'admin' => $this->admin,
                'assessment' => $this->assessment,
                'progressData' => $this->progressData,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}