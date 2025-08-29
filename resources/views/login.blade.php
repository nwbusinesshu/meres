@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<div class="tile">

  <img class="quarma-360" src="{{ asset('assets/logo/quarma360.svg') }}" alt="chaos-360">

  {{-- Email + jelszó belépés --}}
  <form method="POST" action="{{ route('attempt-password-login') }}" class="w-100" style="max-width:420px;margin:0 auto;">
    @csrf

    <div class="form-group">
      <label for="login-email">{{ $_('email') }}</label>
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
      <label for="login-password">{{ $_('password') }}</label>
      <input id="login-password"
             name="password"
             type="password"
             class="form-control"
             required
             autocomplete="current-password">
    </div>

    <button type="submit" class="btn btn-primary btn-block mt-3">
      {{ $_('login_button') }}
    </button>

    @if ($errors->any())
      <div class="alert alert-danger mt-3">
        {{ $errors->first() }}
      </div>
    @endif
  </form>

  {{-- Elválasztó --}}
  <div class="text-center my-3" style="opacity:.7;">{{ $_('or') }}</div>

  {{-- Google belépés (változatlanul) --}}
  <a href="{{ route('trigger-login') }}" role="button" class="google-login btn btn-outline-secondary btn-block trigger-login" style="max-width:420px;margin:0 auto;">
    {{ $_('login') }} <i class="fa fa-google"></i>
  </a>
  <a href="{{ route('trigger-microsoft-login') }}" role="button" class="microsoft-login btn btn-outline-secondary btn-block trigger-microsoft-login" style="max-width:420px;margin:0 auto;">
    {{ $_('login_microsoft') }}    <span class="ms-logo">
     <span style="background:#f25022"></span>
     <span style="background:#7fba00"></span>
     <span style="background:#00a4ef"></span>
     <span style="background:#ffb900"></span>
   </span> </a>
   <div><p>A bejelentkezéssel elfogadod az Adatvédelmi Irányelveinket.</p></div>

  <img class="nwb-logo" src="{{ asset('assets/logo/nwb_logo.svg') }}" alt="">
</div>
@endsection

@section('scripts')
@endsection
