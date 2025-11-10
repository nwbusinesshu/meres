@component('mail::message')
# {{ __('emails.assessment_closed.title') }}

{{ __('emails.assessment_closed.greeting', ['name' => $user->name]) }}

{{ __('emails.assessment_closed.intro', ['org_name' => $org->name]) }}

{{ __('emails.assessment_closed.results_ready') }}

{{ __('emails.assessment_closed.action_text') }}

@component('mail::button', ['url' => $loginUrl])
{{ __('emails.assessment_closed.button') }}
@endcomponent

{{ __('emails.assessment_closed.reminder') }}

{{ __('emails.assessment_closed.salutation') }}  
**{{ $org->name }}**
@endcomponent