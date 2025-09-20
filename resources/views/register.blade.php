@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<div class="tile register-tile">

  <div class="setup-grid">
    {{-- BAL OLDAL: brand + lépéscím/alcím --}}
    <aside class="setup-aside">
      <div class="brand-login">
        <img class="quarma-360" src="{{ asset('assets/logo/quarma360.svg') }}" alt="chaos-360">
      </div>
      <div class="flow-title" id="flow-title">Regisztráljon egy admin felhasználót!</div>
      <div class="flow-subtitle" id="flow-subtitle">
        Az admin hozzáfér mindhez: munkavállalók, értékelések, beállítások. Adja meg az admin nevét és e-mail címét.
      </div>
    </aside>

    {{-- JOBB OLDAL: a lépéses űrlap --}}
    <section class="setup-form">
      <form id="register-form" method="POST" action="{{ route('register.perform') }}" class="w-100" style="max-width:640px;">
        @csrf

        {{-- STEP 1 – Admin --}}
        <section class="reg-step" data-step="0">
          <h3>Admin felhasználó</h3>
          <div class="form-group">
            <label>Név</label>
            <input type="text" name="admin_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>E-mail</label>
            <input type="email" name="admin_email" class="form-control" required>
          </div>
          <div class="step-actions">
            <button type="button" class="btn btn-primary next-step">Tovább</button>
          </div>
          @if ($errors->any())
            <div class="alert alert-danger mt-3">
              {{ $errors->first() }}
            </div>
          @endif
        </section>

        {{-- STEP 2 – Cég + számlázás --}}
        <section class="reg-step" data-step="1" hidden>
          <h3>Cég és számlázási adatok</h3>
          <div class="form-group">
            <label>Cégnév</label>
            <input type="text" name="org_name" class="form-control" required>
          </div>

          <div class="form-row two">
            <div class="form-group">
              <label>Ország</label>
              <select name="country_code" class="form-control" required>
                <option value="HU" selected>Magyarország (HU)</option>
                <option value="AT">Ausztria (AT)</option>
                <option value="BE">Belgium (BE)</option>
                <option value="BG">Bulgária (BG)</option>
                <option value="HR">Horvátország (HR)</option>
                <option value="CY">Ciprus (CY)</option>
                <option value="CZ">Csehország (CZ)</option>
                <option value="DK">Dánia (DK)</option>
                <option value="EE">Észtország (EE)</option>
                <option value="FI">Finnország (FI)</option>
                <option value="FR">Franciaország (FR)</option>
                <option value="DE">Németország (DE)</option>
                <option value="GR">Görögország (GR)</option>
                <option value="IE">Írország (IE)</option>
                <option value="IT">Olaszország (IT)</option>
                <option value="LV">Lettország (LV)</option>
                <option value="LT">Litvánia (LT)</option>
                <option value="LU">Luxemburg (LU)</option>
                <option value="MT">Málta (MT)</option>
                <option value="NL">Hollandia (NL)</option>
                <option value="PL">Lengyelország (PL)</option>
                <option value="PT">Portugália (PT)</option>
                <option value="RO">Románia (RO)</option>
                <option value="SK">Szlovákia (SK)</option>
                <option value="SI">Szlovénia (SI)</option>
                <option value="ES">Spanyolország (ES)</option>
                <option value="SE">Svédország (SE)</option>
              </select>
            </div>
            <div class="form-group">
              <label>Irányítószám</label>
              <input type="text" name="postal_code" class="form-control">
            </div>
          </div>

          <div class="form-row two">
            <div class="form-group">
              <label>Megye/Régió</label>
              <input type="text" name="region" class="form-control">
            </div>
            <div class="form-group">
              <label>Város</label>
              <input type="text" name="city" class="form-control">
            </div>
          </div>

          <div class="form-row two">
            <div class="form-group">
              <label>Közterület</label>
              <input type="text" name="street" class="form-control">
            </div>
            <div class="form-group">
              <label>Házszám</label>
              <input type="text" name="house_number" class="form-control">
            </div>
          </div>

          <div class="form-group">
            <label>Telefonszám</label>
            <input type="text" name="phone" class="form-control" placeholder="+36…">
          </div>

          <div class="form-row two">
            <div class="form-group hu-only">
              <label>Adószám</label>
              <input type="text" name="tax_number" class="form-control" placeholder="pl. 12345678-1-12">
            </div>
            <div class="form-group eu-vat">
              <label>EU VAT</label>
              <input type="text" name="eu_vat_number" class="form-control" placeholder="pl. DE123456789">
            </div>
          </div>

          <div class="step-actions">
            <button type="button" class="btn btn-secondary prev-step">Vissza</button>
            <button type="button" class="btn btn-primary next-step">Tovább</button>
          </div>
        </section>

        {{-- STEP 3 – Alapbeállítások --}}
        <section class="reg-step" data-step="2" hidden>
          <h3>Alapbeállítások</h3>
          <div class="tile tile-info inner">
            <div class="text">
              <div class="title"><h4>AI telemetria</h4></div>
              <div class="meta">Telemetria és AI segédfunkciók bekapcsolása. (Később módosítható.)</div>
            </div>
            <label class="switch">
              <input type="checkbox" name="ai_telemetry_enabled" checked>
              <span class="slider"></span>
            </label>
          </div>

          <div class="tile tile-info inner">
            <div class="text">
              <div class="title"><h4>Multi-level részlegkezelés</h4></div>
              <div class="meta">Részlegek és vezetői szintek bekapcsolása. <strong>Visszavonhatatlan:</strong> később nem kapcsolható ki.</div>
            </div>
            <label class="switch">
              <input type="checkbox" name="enable_multi_level">
              <span class="slider"></span>
            </label>
          </div>

          <div class="tile tile-info inner">
            <div class="text">
              <div class="title"><h4>Bonus/Malus megjelenítés</h4></div>
              <div class="meta">A besorolások láthatóságának kapcsolója a felületen.</div>
            </div>
            <label class="switch">
              <input type="checkbox" name="show_bonus_malus" checked>
              <span class="slider"></span>
            </label>
          </div>

          <div class="step-actions">
            <button type="button" class="btn btn-secondary prev-step">Vissza</button>
            <button type="button" class="btn btn-primary next-step">Tovább</button>
          </div>
        </section>

        {{-- STEP 4 – Összegzés --}}
        <section class="reg-step" data-step="3" hidden>
          <h3>Összegzés</h3>
          <div class="summary"><!-- JS tölti fel --></div>
          <div class="step-actions">
            <button type="button" class="btn btn-secondary prev-step">Vissza</button>
            <button type="submit" class="btn btn-success btn-block">Véglegesítés</button>
          </div>
        </section>
      </form>
    </section>
  </div>

  <img class="nwb-logo" src="{{ asset('assets/logo/nwb_logo.svg') }}" alt="">
</div>
 <div class="footnote">
    Már van fiókja? <a href="{{ route('login') }}">Bejelentkezés</a>
  </div>

@endsection

@section('scripts')
@endsection