@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h2>{{ $_('settings.title') }}</h2>

{{-- ===== FELSŐ KÉT CSEMPE ===== --}}
<h3 class="settings-subtitle">{{ $_('settings.section_ai_privacy') }}</h3>
<div class="settings-grid">
  <div class="tile tile-info">
    <div class="text">
      <div class="title"><h3>{{ $_('settings.strict.title') }}</h3></div>
      <div class="meta">{!! $_('settings.strict.meta_html') !!}</div>
    </div>
    <label class="switch">
      <input type="checkbox" id="toggle-strict" {{ $strictAnon ? 'checked' : '' }}>
      <span class="slider"></span>
    </label>
  </div>

  <div class="tile tile-info">
    <div class="text">
      <div class="title"><h3>{{ $_('settings.ai.title') }}</h3></div>
      <div class="meta">{!! $_('settings.ai.meta_html') !!}</div>
    </div>
    <label class="switch">
      <input type="checkbox" id="toggle-ai" {{ $aiTelemetry ? 'checked' : '' }} {{ $strictAnon ? 'disabled' : '' }}>
      <span class="slider"></span>
    </label>
  </div>
</div>

{{-- ===== PONTOZÁSI MÓDSZER ===== --}}
<h3 class="settings-subtitle" style="margin-top:1.2rem;">{{ $_('settings.scoring_subtitle') }}</h3>
@php
  $activeMode = old('threshold_mode', $threshold_mode ?? 'fixed');

  // suggested engedélyezettsége a már átadott változókból:
  $canUseSuggested = ($hasClosedAssessment && $aiTelemetry);

  // hibák összegyűjtése (ha több ok is van, mindet listázzuk)
  $suggestedErrors = [];
  if (!$hasClosedAssessment)  $suggestedErrors[] = 'Nem választható, mert nincs még lezárt mérés.';
  if (!$aiTelemetry)          $suggestedErrors[] = 'Nem választható, mert az AI telemetria le van tiltva.';
@endphp

{{-- 1) Mód mentése --}}
<form method="POST" action="{{ route('admin.settings.save') }}" id="mode-form">
  @csrf
  <div class="">
    <div class="tile tile-info tile-full">
      <div class="text">
        <div class="title"><h3>{{ $_('settings.mode.title') }}</h3></div>
        <div class="meta">{{ $_('settings.mode.meta') }}</div>

        <div class="mode-switch" id="threshold-mode-switch">
          <label class="mode-option">
            <input type="radio" name="threshold_mode" value="fixed" {{ $activeMode==='fixed' ? 'checked' : '' }}>
            <span>{{ $_('settings.mode.options.fixed') }}</span>
          </label>
          <label class="mode-option">
            <input type="radio" name="threshold_mode" value="hybrid" {{ $activeMode==='hybrid' ? 'checked' : '' }}>
            <span>{{ $_('settings.mode.options.hybrid') }}</span>
          </label>
          <label class="mode-option">
            <input type="radio" name="threshold_mode" value="dynamic" {{ $activeMode==='dynamic' ? 'checked' : '' }}>
            <span>{{ $_('settings.mode.options.dynamic') }}</span>
          </label>
              <div class="mode-option-wrapper">
        <label class="mode-option {{ $canUseSuggested ? '' : 'disabled-option' }}">
          <input type="radio" name="threshold_mode" value="suggested"
                 {{ $activeMode==='suggested' ? 'checked' : '' }}
                 {{ $canUseSuggested ? '' : 'disabled' }}>
          <span>{{ $_('settings.mode.options.suggested') }}</span>
        </label>
        @if(!$canUseSuggested && !empty($suggestedErrors))
      <ul class="mode-error-list">
        @foreach($suggestedErrors as $msg)
          <li class="mode-error">{{ $msg }}</li>
        @endforeach
      </ul>
    @endif
        </div>
      </div>
    </div>
  </div>
</form>

{{-- 2) Konfiguráció --}}
<form method="POST" action="{{ route('admin.settings.save') }}" id="config-form" style="margin-top:.8rem;">
  @csrf
  <input type="hidden" name="threshold_mode" id="config-mode" value="{{ $activeMode }}">

  <div class="settings-grid">

    {{-- FIXED --}}
    <div class="tile tile-info tile-full mode-pane mode-fixed {{ $activeMode==='fixed' ? 'active' : '' }}">
      <div class="text">
        <div class="title"><h3>{{ $_('settings.fixed.title') }}</h3></div>
        <div class="meta">{!! $_('settings.fixed.meta_html') !!}</div>

        {{-- Leírás-doboz --}}
        <div class="mode-explainer">
          <div class="chunk">{!! $_('settings.fixed.description_html') !!}</div>

          <div class="columns">
            <div class="col">
              <div class="label">Pro</div>
              <ul>
                @foreach($_('settings.fixed.pros') as $p)
                  <li>{{ $p }}</li>
                @endforeach
              </ul>
            </div>
            <div class="col">
              <div class="label">Contra</div>
              <ul>
                @foreach($_('settings.fixed.cons') as $c)
                  <li>{{ $c }}</li>
                @endforeach
              </ul>
            </div>
          </div>

          <div class="footnote">
            <strong>Ajánlott használat:</strong>
            {{ $_('settings.fixed.when') }}
          </div>
        </div>
        {{-- /Leírás-doboz --}}
      </div>

      <div class="fields two-col">
        <label class="field">
          <span>{{ $_('settings.fixed.fields.normal_level_up') }}</span>
          <input type="number" min="0" max="100" name="normal_level_up" value="{{ old('normal_level_up', $normal_level_up ?? 85) }}">
        </label>
        <label class="field">
          <span>{{ $_('settings.fixed.fields.normal_level_down') }}</span>
          <input type="number" min="0" max="100" name="normal_level_down" value="{{ old('normal_level_down', $normal_level_down ?? 70) }}">
        </label>
      </div>
      <div>
        <button class="btn btn-primary" type="submit">{{ $_('settings.buttons.save_settings') }}</button>
      </div>
    </div>

    {{-- HYBRID --}}
    <div class="tile tile-info tile-full mode-pane mode-hybrid {{ $activeMode==='hybrid' ? 'active' : '' }}">
      <div class="text">
        <div class="title"><h3>{{ $_('settings.hybrid.title') }}</h3></div>
        <div class="meta">{{ $_('settings.hybrid.meta') }}</div>

        {{-- Leírás-doboz --}}
        <div class="mode-explainer">
          <div class="chunk">{!! $_('settings.hybrid.description_html') !!}</div>

          <div class="columns">
            <div class="col">
              <div class="label">Pro</div>
              <ul>
                @foreach($_('settings.hybrid.pros') as $p)
                  <li>{{ $p }}</li>
                @endforeach
              </ul>
            </div>
            <div class="col">
              <div class="label">Contra</div>
              <ul>
                @foreach($_('settings.hybrid.cons') as $c)
                  <li>{{ $c }}</li>
                @endforeach
              </ul>
            </div>
          </div>

          <div class="footnote">
            <strong>Ajánlott használat:</strong>
            {{ $_('settings.hybrid.when') }}
          </div>
        </div>
        {{-- /Leírás-doboz --}}
      </div>

      <div class="fields two-col">
        <label class="field">
          <span>{{ $_('settings.hybrid.fields.threshold_min_abs_up') }}</span>
          <input type="number" min="0" max="100" name="threshold_min_abs_up" value="{{ old('threshold_min_abs_up', $threshold_min_abs_up ?? 80) }}">
        </label>
        <label class="field">
          <span>{{ $_('settings.hybrid.fields.threshold_top_pct') }}</span>
          <input type="number" min="0" max="100" name="threshold_top_pct" value="{{ old('threshold_top_pct', $threshold_top_pct ?? 15) }}">
        </label>
      </div>
      <div>
        <button class="btn btn-primary" type="submit">{{ $_('settings.buttons.save_settings') }}</button>
      </div>
    </div>

    {{-- DYNAMIC --}}
    <div class="tile tile-info tile-full mode-pane mode-dynamic {{ $activeMode==='dynamic' ? 'active' : '' }}">
      <div class="text">
        <div class="title"><h3>{{ $_('settings.dynamic.title') }}</h3></div>
        <div class="meta">{{ $_('settings.dynamic.meta') }}</div>

        {{-- Leírás-doboz --}}
        <div class="mode-explainer">
          <div class="chunk">{!! $_('settings.dynamic.description_html') !!}</div>

          <div class="columns">
            <div class="col">
              <div class="label">Pro</div>
              <ul>
                @foreach($_('settings.dynamic.pros') as $p)
                  <li>{{ $p }}</li>
                @endforeach
              </ul>
            </div>
            <div class="col">
              <div class="label">Contra</div>
              <ul>
                @foreach($_('settings.dynamic.cons') as $c)
                  <li>{{ $c }}</li>
                @endforeach
              </ul>
            </div>
          </div>

          <div class="footnote">
            <strong>Ajánlott használat:</strong>
            {{ $_('settings.dynamic.when') }}
          </div>
        </div>
        {{-- /Leírás-doboz --}}
      </div>

      <div class="fields two-col">
        <label class="field">
          <span>{{ $_('settings.dynamic.fields.threshold_bottom_pct') }}</span>
          <input type="number" min="0" max="100" name="threshold_bottom_pct" value="{{ old('threshold_bottom_pct', $threshold_bottom_pct ?? 20) }}">
        </label>
        <label class="field">
          <span>{{ $_('settings.dynamic.fields.threshold_top_pct') }}</span>
          <input type="number" min="0" max="100" name="threshold_top_pct" value="{{ old('threshold_top_pct', $threshold_top_pct ?? 15) }}">
        </label>
      </div>
      <div>
        <button class="btn btn-primary" type="submit">{{ $_('settings.buttons.save_settings') }}</button>
      </div>
    </div>

    {{-- SUGGESTED --}}
    <div class="tile tile-info tile-full mode-pane mode-suggested {{ $activeMode==='suggested' ? 'active' : '' }}">
      <div class="text">
        <div class="title"><h3>{{ $_('settings.suggested.title') }}</h3></div>
        <div class="meta">{{ $_('settings.suggested.meta') }}</div>

        {{-- Leírás-doboz --}}
        <div class="mode-explainer">
          <div class="chunk">{!! $_('settings.suggested.description_html') !!}</div>

          <div class="columns">
            <div class="col">
              <div class="label">Pro</div>
              <ul>
                @foreach($_('settings.suggested.pros') as $p)
                  <li>{{ $p }}</li>
                @endforeach
              </ul>
            </div>
            <div class="col">
              <div class="label">Contra</div>
              <ul>
                @foreach($_('settings.suggested.cons') as $c)
                  <li>{{ $c }}</li>
                @endforeach
              </ul>
            </div>
          </div>

          <div class="footnote">
            <strong>Ajánlott használat:</strong>
            {{ $_('settings.suggested.when') }}
          </div>
        </div>
        {{-- /Leírás-doboz --}}
      </div>

      <div>
        <button class="btn btn-primary" type="submit">{{ $_('settings.buttons.save_settings') }}</button>
      </div>
    </div>

  </div>
</form>
@endsection

@section('scripts')
@endsection
