@component('mail::message')
# {{ __('emails.payment_success.title') }}

{{ __('emails.payment_success.greeting', ['name' => $admin->name]) }}

{{ __('emails.payment_success.intro', ['org_name' => $org->name]) }}

**{{ __('emails.payment_success.amount') }}:** {{ number_format($payment->gross_amount, 0, ',', ' ') }} {{ $payment->currency }}  
**{{ __('emails.payment_success.invoice_number') }}:** {{ $payment->billingo_invoice_number ?? __('emails.payment_success.processing') }}  
**{{ __('emails.payment_success.paid_at') }}:** {{ $payment->paid_at->format('Y-m-d H:i') }}

{{ __('emails.payment_success.invoice_ready') }}

@component('mail::button', ['url' => $invoiceDownloadUrl])
{{ __('emails.payment_success.download_button') }}
@endcomponent

{{ __('emails.payment_success.thank_you') }}

{{ __('emails.payment_success.salutation') }}  
**{{ $org->name }}**
@endcomponent