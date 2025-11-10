@component('mail::message')
# {{ __('emails.assessment_progress.title') }}

{{ __('emails.assessment_progress.greeting', ['name' => $admin->name]) }}

{{ __('emails.assessment_progress.intro', ['org_name' => $org->name]) }}

## {{ __('emails.assessment_progress.completion_status') }}

**{{ __('emails.assessment_progress.assessments_completed') }}:** {{ $progressData['completed_count'] }} / {{ $progressData['total_count'] }} ({{ number_format($progressData['completion_percentage'], 1) }}%)

**{{ __('emails.assessment_progress.rankings_completed') }}:** {{ $progressData['ranking_completed'] }} / {{ $progressData['ranking_total'] }} ({{ number_format($progressData['ranking_percentage'], 1) }}%)

**{{ __('emails.assessment_progress.deadline') }}:** {{ $assessment->due->format('Y-m-d H:i') }}

@if($progressData['has_open_payment'])
@component('mail::panel')
⚠️ **{{ __('emails.assessment_progress.payment_warning') }}**

{{ __('emails.assessment_progress.payment_blocked', ['amount' => $progressData['payment_amount']]) }}
@endcomponent
@endif

{{ __('emails.assessment_progress.action_text') }}

@component('mail::button', ['url' => $loginUrl])
{{ __('emails.assessment_progress.button') }}
@endcomponent

{{ __('emails.assessment_progress.salutation') }}  
**{{ $org->name }}**
@endcomponent