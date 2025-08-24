@extends('layouts.master')

@section('content')
<div class="tile" style="max-width:520px;margin:0 auto;">
  <h3 class="mb-3">Jelszó beállítása</h3>
  <p class="text-muted">Szervezet: <strong>{{ $org->name }}</strong></p>

  <form method="POST" action="{{ route('password-setup.store', ['org' => $org->slug, 'token' => $token]) }}" class="mb-4">
    @csrf

    <div class="form-group">
      <label>E-mail</label>
      <input type="email" class="form-control" value="{{ $email }}" readonly tabindex="-1">
    </div>

    <div class="form-group">
      <label>Jelszó</label>
      <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
    </div>

    <div class="form-group">
      <label>Jelszó megerősítése</label>
      <input type="password" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
    </div>

    <button class="btn btn-primary btn-block mt-2">Jelszó beállítása és belépés</button>

    @if ($errors->any())
      <div class="alert alert-danger mt-3">
        {{ $errors->first() }}
      </div>
    @endif
  </form>

  <div class="text-center my-2" style="opacity:.7;">— vagy —</div>

  <a class="btn btn-outline-secondary btn-block" href="{{ route('trigger-login') }}">
    Folytatom Google-lel
  </a>
</div>
@endsection
