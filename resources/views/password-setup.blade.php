@extends('layouts.master')

@section('head-extra')
  {{-- opcionális saját CSS a laphoz; ha még nincs, maradhat így is --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/password-setup.css') }}">
@endsection

@section('content')
<div class="tile">
  <img class="chaos-360" src="{{ asset('assets/logo/quarma360.svg') }}" alt="quarma-360">
  <h3> Új jelszó beállítása</h3>

  {{-- Jelszó beállítás --}}
  <form method="POST"
        action="{{ route('password-setup.store', ['token' => $token]) }}"
        class="w-100"
        style="max-width:420px;margin:0 auto;">
    @csrf

    <div class="form-group">
      <label for="ps-email">E-mail</label>
      <input id="ps-email"
             type="email"
             class="form-control"
             value="{{ isset($user) ? $user->email : '' }}"
             disabled
             readonly>
    </div>

    <div class="form-group">
      <label for="ps-password">Új jelszó</label>
      <input id="ps-password"
             name="password"
             type="password"
             class="form-control"
             required
             autocomplete="new-password">
    </div>

    <div class="form-group">
      <label for="ps-password-confirmation">Jelszó megerősítése</label>
      <input id="ps-password-confirmation"
             name="password_confirmation"
             type="password"
             class="form-control"
             required
             autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn-primary btn-block mt-3">
      Jelszó beállítása
    </button>

    @if ($errors->any())
      <div class="alert alert-danger mt-3">
        {{ $errors->first() }}
      </div>
    @endif

    @if (session('error'))
      <div class="alert alert-danger mt-3">{{ session('error') }}</div>
    @endif

    @if (session('success'))
      <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif
  </form>

  {{-- Elválasztó --}}
  <div class="text-center my-3" style="opacity:.7;">— vagy —</div>

  {{-- Google belépés (változatlanul) --}}
  <a href="{{ route('trigger-login') }}"
     role="button"
     class="btn btn-outline-secondary btn-block trigger-login"
     style="max-width:420px;margin:0 auto;">
    Belépés Google-fiókkal <i class="fa fa-google"></i>
  </a>
  <a href="{{ route('trigger-microsoft-login') }}" role="button" class="btn btn-outline-secondary btn-block trigger-microsoft-login" style="max-width:420px;margin:0 auto;">
    Belépés Microsofttal <i class="fa-brands fa-microsoft"></i> </a>

  <img class="mewocont-logo" src="{{ asset('assets/logo/nwb_logo.svg') }}" alt="">
</div>
@endsection

@section('scripts')
@endsection