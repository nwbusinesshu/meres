@extends('layouts.master')

@section('head-extra')
@if (config('services.recaptcha.key'))
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  @endif
@endsection

@section('content')
<div class="tile register-tile">

  <div class="setup-grid">
    {{-- BAL OLDAL: brand + lépéscím/alcím --}}
    <aside class="setup-aside">
      <div class="brand-login">
        <img class="quarma-360" src="{{ asset('assets/logo/quarma360.svg') }}" alt="chaos-360">
      </div>
      <div class="flow-title" id="flow-title">{{ __('register.flow_title') }}</div>
      <div class="flow-subtitle" id="flow-subtitle">
        {{ __('register.flow_subtitle') }}
      </div>
    </aside>

    {{-- JOBB OLDAL: a lépéses űrlap --}}
    <section class="setup-form">
      <form id="register-form" method="POST" action="{{ route('register.perform') }}" class="w-100" style="max-width:640px;">
        @csrf

        {{-- STEP 1 – Admin + Employee Count --}}
        <section class="reg-step" data-step="0">
          <h3>{{ __('register.step1_title') }}</h3>
          <div class="form-group">
            <label>{{ __('register.admin.name') }}</label>
            <input type="text" name="admin_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>{{ __('register.admin.email') }}</label>
            <input type="email" name="admin_email" class="form-control" required>
          </div>
          <div class="form-group">
            <label>{{ __('register.admin.employee_limit') }}</label>
            <input type="number" name="employee_limit" class="form-control" min="1" required>
          </div>
          <div class="step-actions">
            <button type="button" class="btn btn-primary next-step">{{ __('register.buttons.next') }}</button>
          </div>
          @if ($errors->any())
            <div class="alert alert-danger mt-3">
              {{ $errors->first() }}
            </div>
          @endif
        </section>

        {{-- STEP 2 – Cég + számlázás --}}
        <section class="reg-step" data-step="1" hidden>
          <h3>{{ __('register.step2_title') }}</h3>
          
          <div class="form-group">
            <label>{{ __('register.company.name') }}</label>
            <input type="text" name="org_name" class="form-control" required>
          </div>

          <div class="form-row two">
            <div class="form-group">
              <label>{{ __('register.company.country') }}</label>
              <select name="country_code" class="form-control" required>
                <option value="HU" selected>{{ __('register.countries.HU') }}</option>
                <option value="AT">{{ __('register.countries.AT') }}</option>
                <option value="BE">{{ __('register.countries.BE') }}</option>
                <option value="BG">{{ __('register.countries.BG') }}</option>
                <option value="HR">{{ __('register.countries.HR') }}</option>
                <option value="CY">{{ __('register.countries.CY') }}</option>
                <option value="CZ">{{ __('register.countries.CZ') }}</option>
                <option value="DK">{{ __('register.countries.DK') }}</option>
                <option value="EE">{{ __('register.countries.EE') }}</option>
                <option value="FI">{{ __('register.countries.FI') }}</option>
                <option value="FR">{{ __('register.countries.FR') }}</option>
                <option value="DE">{{ __('register.countries.DE') }}</option>
                <option value="GR">{{ __('register.countries.GR') }}</option>
                <option value="IE">{{ __('register.countries.IE') }}</option>
                <option value="IT">{{ __('register.countries.IT') }}</option>
                <option value="LV">{{ __('register.countries.LV') }}</option>
                <option value="LT">{{ __('register.countries.LT') }}</option>
                <option value="LU">{{ __('register.countries.LU') }}</option>
                <option value="MT">{{ __('register.countries.MT') }}</option>
                <option value="NL">{{ __('register.countries.NL') }}</option>
                <option value="PL">{{ __('register.countries.PL') }}</option>
                <option value="PT">{{ __('register.countries.PT') }}</option>
                <option value="RO">{{ __('register.countries.RO') }}</option>
                <option value="SK">{{ __('register.countries.SK') }}</option>
                <option value="SI">{{ __('register.countries.SI') }}</option>
                <option value="ES">{{ __('register.countries.ES') }}</option>
                <option value="SE">{{ __('register.countries.SE') }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>{{ __('register.company.postal_code') }}</label>
              <input type="text" name="postal_code" class="form-control" required>
            </div>
          </div>

          <div class="form-row two">
            <div class="form-group region-field">
              <label>{{ __('register.company.region') }}</label>
              <input type="text" name="region" class="form-control">
            </div>
            <div class="form-group">
              <label>{{ __('register.company.city') }}</label>
              <input type="text" name="city" class="form-control" required>
            </div>
          </div>

          <div class="form-row two">
            <div class="form-group">
              <label>{{ __('register.company.street') }}</label>
              <input type="text" name="street" class="form-control" required>
            </div>
            <div class="form-group">
              <label>{{ __('register.company.house_number') }}</label>
              <input type="text" name="house_number" class="form-control" required>
            </div>
          </div>

          <div class="form-group">
            <label>{{ __('register.company.phone') }}</label>
            <input type="text" name="phone" class="form-control" placeholder="{{ __('register.company.phone_placeholder') }}" required>
          </div>

          <div class="form-row two">
            <div class="form-group hu-only">
              <label>{{ __('register.company.tax_number') }}</label>
              <input type="text" name="tax_number" class="form-control" placeholder="{{ __('register.company.tax_number_placeholder') }}">
            </div>
            <div class="form-group eu-vat">
              <label>{{ __('register.company.eu_vat') }}</label>
              <input type="text" name="eu_vat_number" class="form-control" placeholder="{{ __('register.company.eu_vat_placeholder') }}">
            </div>
          </div>

          <div class="step-actions">
            <button type="button" class="btn btn-secondary prev-step">{{ __('register.buttons.back') }}</button>
            <button type="button" class="btn btn-primary next-step">{{ __('register.buttons.next') }}</button>
          </div>
        </section>

        {{-- STEP 3 – Alapbeállítások --}}
        <section class="reg-step" data-step="2" hidden>
          <h3>{{ __('register.step3_title') }}</h3>
          <div class="tile tile-info inner">
            <div class="text">
              <div class="title"><h4>{{ __('register.settings.ai_telemetry_title') }}</h4></div>
              <div class="meta">{!! __('register.settings.ai_telemetry_description') !!}</div>
            </div>
            <label class="switch">
              <input type="checkbox" name="ai_telemetry_enabled" checked>
              <span class="slider"></span>
            </label>
          </div>

          <div class="tile tile-info inner">
            <div class="text">
              <div class="title"><h4>{{ __('register.settings.multi_level_title') }}</h4></div>
              <div class="meta">{!! __('register.settings.multi_level_description') !!}</div>
            </div>
            <label class="switch">
              <input type="checkbox" name="enable_multi_level">
              <span class="slider"></span>
            </label>
          </div>

          <div class="tile tile-info inner">
            <div class="text">
              <div class="title"><h4>{{ __('register.settings.bonus_malus_title') }}</h4></div>
              <div class="meta">{!! __('register.settings.bonus_malus_description') !!}</div>
            </div>
            <label class="switch">
              <input type="checkbox" name="show_bonus_malus" checked>
              <span class="slider"></span>
            </label>
          </div>

          <div class="step-actions">
            <button type="button" class="btn btn-secondary prev-step">{{ __('register.buttons.back') }}</button>
            <button type="button" class="btn btn-primary next-step">{{ __('register.buttons.next') }}</button>
          </div>
        </section>

        {{-- STEP 4 – Összegzés --}}
        <section class="reg-step" data-step="3" hidden>
          <h3>{{ __('register.step4_title') }}</h3>
          <div class="summary"><!-- JS tölti fel --></div>
          @if (config('services.recaptcha.key'))
            <div class="g-recaptcha mb-3" data-sitekey="{{ config('services.recaptcha.key') }}"></div>
          @endif
          <div class="step-actions">
            <button type="button" class="btn btn-secondary prev-step">{{ __('register.buttons.back') }}</button>
            <button type="submit" class="btn btn-success btn-block">{{ __('register.buttons.finalize') }}</button>
          </div>
        </section>
      </form>
    </section>
  </div>

  <img class="nwb-logo" src="{{ asset('assets/logo/nwb_logo.svg') }}" alt="">
</div>
<div class="footnote">
    {{ __('register.footer.already_have_account') }} <a href="{{ route('login') }}">{{ __('register.footer.login') }}</a>
  </div>

@endsection

@section('scripts')
@endsection