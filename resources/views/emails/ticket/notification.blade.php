@component('mail::message')
# {{ $isNewTicket ? __('emails.ticket_notification.title_new') : __('emails.ticket_notification.title_update') }}

{{ __('emails.ticket_notification.greeting', ['name' => $recipient->name]) }}

@if($isNewTicket)
{{ __('emails.ticket_notification.new_ticket_intro', ['ticket_id' => $ticket->id]) }}
@else
{{ __('emails.ticket_notification.update_intro', ['ticket_id' => $ticket->id]) }}
@endif

**{{ __('emails.ticket_notification.ticket_title') }}:** {{ $ticket->title }}  
**{{ __('emails.ticket_notification.status') }}:** {{ __('support.status-' . $ticket->status) }}  
**{{ __('emails.ticket_notification.priority') }}:** {{ __('support.priority-' . $ticket->priority) }}

---

## {{ __('emails.ticket_notification.conversation') }}

@foreach($messages as $message)
**{{ $message->user->name }}** – {{ $message->created_at->format('Y-m-d H:i') }}

{{ $message->message }}

---
@endforeach

{{ __('emails.ticket_notification.action_text') }}

@component('mail::button', ['url' => $loginUrl])
{{ __('emails.ticket_notification.button') }}
@endcomponent

{{ __('emails.ticket_notification.salutation') }}  
**{{ $org->name }}**
@endcomponent