@component('mail::message')
# {{ __('emails.password_reset.title') }}

{{ __('emails.password_reset.intro', ['org_name' => $org->name, 'email' => $user->email]) }}

{{ __('emails.password_reset.action_text') }}

@component('mail::button', ['url' => $url])
{{ __('emails.password_reset.button') }}
@endcomponent

{{ __('emails.password_reset.expires', ['expires_at' => $expiresAt->format('Y-m-d H:i')]) }}

{{ __('emails.password_reset.warning') }}

{{ __('emails.password_reset.salutation') }}  
**{{ $org->name }}**
@endcomponent