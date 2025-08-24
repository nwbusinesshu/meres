@component('mail::message')
# Jelszó visszaállítás

A(z) **{{ $org->name }}** rendszerében jelszó visszaállítást kértünk a **{{ $user->email }}** fiókhoz.

Kattints az alábbi gombra az új jelszó beállításához:

@component('mail::button', ['url' => $url])
Jelszó visszaállítása
@endcomponent

Ez a link **{{ $expiresAt->format('Y-m-d H:i') }}** időpontig érvényes.

Ha nem te kérted, azonnal értesítsd a céges admin kapcsolattartód.

Üdv,  
**{{ $org->name }}**
@endcomponent
