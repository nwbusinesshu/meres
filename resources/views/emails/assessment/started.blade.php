@component('mail::message')
# {{ __('emails.assessment_started.title') }}

{{ __('emails.assessment_started.greeting', ['name' => $user->name]) }}

{{ __('emails.assessment_started.intro', ['org_name' => $org->name]) }}

{{ __('emails.assessment_started.deadline_info', ['deadline' => $assessment->due->format('Y-m-d H:i')]) }}

{{ __('emails.assessment_started.action_text') }}

@component('mail::button', ['url' => $loginUrl])
{{ __('emails.assessment_started.button') }}
@endcomponent

{{ __('emails.assessment_started.reminder') }}

{{ __('emails.assessment_started.salutation') }}  
**{{ $org->name }}**
@endcomponent