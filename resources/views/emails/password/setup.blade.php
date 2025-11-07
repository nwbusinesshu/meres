@component('mail::message')
# {{ __('emails.password_setup.title') }}

{{ __('emails.password_setup.greeting', ['org_name' => $org->name]) }}

{{ __('emails.password_setup.invitation', ['email' => $user->email]) }}

{{ __('emails.password_setup.action_text') }}

@component('mail::button', ['url' => $url])
{{ __('emails.password_setup.button') }}
@endcomponent

{{ __('emails.password_setup.expires', ['expires_at' => $expiresAt->format('Y-m-d H:i')]) }}

{{ __('emails.password_setup.ignore') }}

{{ __('emails.password_setup.salutation') }}  
**{{ $org->name }}**
@endcomponent