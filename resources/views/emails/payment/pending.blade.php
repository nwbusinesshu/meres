@component('mail::message')
# {{ __('emails.payment_pending.title') }}

{{ __('emails.payment_pending.greeting', ['name' => $admin->name]) }}

{{ __('emails.payment_pending.intro', ['org_name' => $org->name]) }}

@if($assessment)
{{ __('emails.payment_pending.assessment_info', ['assessment_id' => $assessment->id]) }}
@endif

**{{ __('emails.payment_pending.amount') }}:** {{ number_format($payment->gross_amount, 0, ',', ' ') }} {{ $payment->currency }}  
**{{ __('emails.payment_pending.created') }}:** {{ $payment->created_at->format('Y-m-d H:i') }}

{{ __('emails.payment_pending.action_text') }}

@component('mail::button', ['url' => $loginUrl])
{{ __('emails.payment_pending.button') }}
@endcomponent

{{ __('emails.payment_pending.note') }}

{{ __('emails.payment_pending.salutation') }}  
**{{ $org->name }}**
@endcomponent