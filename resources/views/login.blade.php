@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<div class="tile">

  <img class="chaos-360" src="{{ asset('assets/logo/chaos360.svg') }}" alt="chaos-360">

  {{-- Email + jelszó belépés --}}
  <form method="POST" action="{{ route('attempt-password-login') }}" class="w-100" style="max-width:420px;margin:0 auto;">
    @csrf

    <div class="form-group">
      <label for="login-email">Email</label>
      <input id="login-email"
             name="email"
             type="email"
             class="form-control"
             value="{{ old('email') }}"
             required
             autofocus
             autocomplete="username">
    </div>

    <div class="form-group">
      <label for="login-password">Jelszó</label>
      <input id="login-password"
             name="password"
             type="password"
             class="form-control"
             required
             autocomplete="current-password">
    </div>

    <div class="form-check">
      <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
      <label class="form-check-label" for="remember">Emlékezz rám</label>
    </div>

    <button type="submit" class="btn btn-primary btn-block mt-3">
      Belépés
    </button>

    @if ($errors->any())
      <div class="alert alert-danger mt-3">
        {{ $errors->first() }}
      </div>
    @endif
  </form>

  {{-- Elválasztó --}}
  <div class="text-center my-3" style="opacity:.7;">— vagy —</div>

  {{-- Google belépés (változatlanul) --}}
  <a href="{{ route('trigger-login') }}" role="button" class="btn btn-outline-secondary btn-block trigger-login" style="max-width:420px;margin:0 auto;">
    {{ $_('login') }} <i class="fa fa-google"></i>
  </a>

  <img class="mewocont-logo" src="{{ asset('assets/logo/mewocont_logo_dark.svg') }}" alt="">
</div>
@endsection

@section('scripts')
@endsection
