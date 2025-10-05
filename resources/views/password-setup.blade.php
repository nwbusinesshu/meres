@extends('layouts.master')

@section('head-extra')

@endsection

@section('content')
<div class="tile">
  <form method="POST"
        action="{{ route('password-setup.store', ['token' => $token]) }}"
        class="w-100"
        id="password-setup-form"
        style="max-width:420px;margin:0 auto;">
    @csrf

    <img class="quarma-360" src="{{ asset('assets/logo/quarma360.svg') }}" alt="quarma-360">
    <h4>{{ __('password-setup.title') }}</h4>

    <div class="form-group">
      <label for="ps-email">{{ __('password-setup.email') }}</label>
      <input id="ps-email"
             type="email"
             class="form-control"
             value="{{ isset($user) ? $user->email : '' }}"
             disabled
             readonly>
    </div>

    <div class="form-group">
      <label for="ps-password">{{ __('password-setup.new_password') }}</label>
      <input id="ps-password"
             name="password"
             type="password"
             class="form-control"
             required
             autocomplete="new-password">
      
      <!-- Password Requirements Checklist -->
      <div class="password-requirements">
        <h6>{{ __('password-setup.requirements.title') }}</h6>
        <ul>
          <li id="req-length" class="invalid">
            <i class="fas fa-times"></i>
            <span>{{ __('password-setup.requirements.min_length') }}</span>
          </li>
          <li id="req-letter" class="invalid">
            <i class="fas fa-times"></i>
            <span>{{ __('password-setup.requirements.has_letter') }}</span>
          </li>
          <li id="req-number" class="invalid">
            <i class="fas fa-times"></i>
            <span>{{ __('password-setup.requirements.has_number') }}</span>
          </li>
          <li id="req-not-common" class="invalid">
            <i class="fas fa-times"></i>
            <span>{{ __('password-setup.requirements.not_common') }}</span>
          </li>
        </ul>
      </div>
    </div>

    <div class="form-group">
      <label for="ps-password-confirmation">{{ __('password-setup.confirm_password') }}</label>
      <input id="ps-password-confirmation"
             name="password_confirmation"
             type="password"
             class="form-control"
             required
             autocomplete="new-password">
      
      <!-- Password Match Indicator -->
      <div id="password-match" class="password-match-indicator"></div>
    </div>

    <button type="submit" class="btn btn-primary" id="submit-btn" disabled>
      {{ __('password-setup.submit') }}
    </button>

    @if ($errors->any())
      <div class="alert alert-danger mt-3">
        @foreach ($errors->all() as $error)
          {{ $error }}<br>
        @endforeach
      </div>
    @endif

    @if (session('error'))
      <div class="alert alert-danger mt-3">{{ session('error') }}</div>
    @endif

    @if (session('success'))
      <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif
  </form>

  <!-- Separator -->
  <div class="text-center my-3" style="opacity:.7;">{{ __('password-setup.separator') }}</div>

  <!-- Google Login -->
  <a href="{{ route('trigger-login') }}"
     role="button"
     class="btn btn-outline-secondary btn-block trigger-login"
     style="max-width:420px;margin:0 auto;">
    {{ __('password-setup.login_google') }} <i class="fa fa-google"></i>
  </a>

  <!-- Microsoft Login -->
  <a href="{{ route('trigger-microsoft-login') }}"
     role="button"
     class="btn btn-outline-secondary btn-block trigger-microsoft-login"
     style="max-width:420px;margin:0 auto;">
    {{ __('password-setup.login_microsoft') }} <i class="fa-brands fa-microsoft"></i>
  </a>

  <img class="nwb-logo" src="{{ asset('assets/logo/nwb_logo.svg') }}" alt="">
</div>
@endsection

@section('scripts')
@endsection