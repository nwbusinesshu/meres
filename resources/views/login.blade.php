@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<div class="tile tile-login">

  @if(session('show_verification'))
    {{-- 2FA Verification Step --}}
    <form method="POST" action="{{ route('verify-2fa-code') }}" class="w-100" style="max-width:420px;margin:0 auto;">
      @csrf

      <img class="quarma-360" src="{{ asset('assets/logo/quarma360.svg') }}" alt="chaos-360">

      <div class="text-center mb-4">
        <h4>{{ $_('verification_required') ?? 'Ellenőrzés szükséges' }}</h4>
        <p class="text-muted">
          {{ $_('verification_code_sent') ?? 'Ellenőrző kódot küldtünk a következő címre:' }}<br>
          <strong>{{ session('verification_email') }}</strong>
        </p>
      </div>

      <div class="form-group">
        <label for="verification-code">{{ $_('verification_code') ?? 'Ellenőrző kód' }}</label>
        <input id="verification-code"
               name="verification_code"
               type="text"
               class="form-control text-center"
               placeholder="000000"
               maxlength="6"
               pattern="[0-9]{6}"
               style="font-size: 1.5em; letter-spacing: 0.2em;"
               required
               autofocus
               autocomplete="one-time-code">
        <small class="form-text text-muted">{{ $_('enter_6_digit_code') ?? 'Add meg a 6 jegyű kódot' }}</small>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-3">
        <button type="submit" class="btn btn-primary">
          {{ $_('verify_and_login') ?? 'Ellenőrzés és belépés' }}
        </button>
        
        <button type="button" id="resend-code-btn" class="btn btn-link p-0" onclick="resendCode()">
          {{ $_('resend_code') ?? 'Kód újraküldése' }}
        </button>
      </div>

      @if ($errors->any())
        <div class="alert alert-danger mt-3">
          {{ $errors->first() }}
        </div>
      @endif

      @if (session('success'))
        <div class="alert alert-success mt-3">
          {{ session('success') }}
        </div>
      @endif

      {{-- Back to login button --}}
      <div class="text-center mt-3">
        <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm">
          {{ $_('back_to_login') ?? 'Vissza a bejelentkezéshez' }}
        </a>
      </div>
    </form>

  @else
    {{-- Regular Login Form --}}
    <form method="POST" action="{{ route('attempt-password-login') }}" class="w-100" style="max-width:420px;margin:0 auto;">
      @csrf

      <img class="quarma-360" src="{{ asset('assets/logo/quarma360.svg') }}" alt="chaos-360">

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

      <div class="form-group form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
        <label class="form-check-label" for="remember">
          {{ $_('remember_me') ?? 'Emlékezz rám' }}
        </label>
      </div>

      <button type="submit" class="btn btn-primary mt-3">
        {{ $_('login_button') }}
      </button>

      @if ($errors->any())
        <div class="alert alert-danger mt-3">
          {{ $errors->first() }}
        </div>
      @endif

      @if (session('success'))
        <div class="alert alert-success mt-3">
          {{ session('success') }}
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
     <div><p>{{ $_('privacy_policy_acceptance') }}</p></div>

    <img class="nwb-logo" src="{{ asset('assets/logo/nwb_logo.svg') }}" alt="">

    <div class="footnote">
      {{ $_('no_account_yet') }} <a href="{{ route('register.show') }}">{{ $_('register_link') }}</a>
    </div>
  @endif

</div>
@endsection

@section('scripts')
@endsection
