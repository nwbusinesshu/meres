@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h2>{{ $_('settings.title') }}</h2>
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
@endsection




@section('scripts')
<script>
    window.lang = {
        confirm: "{{ __('admin/setting.confirm') }}",
        warn_strict_on: "{{ __('admin/setting.warn_strict_on') }}",
        warn_ai_on: "{{ __('admin/setting.warn_ai_on') }}",
        warn_ai_off: "{{ __('admin/setting.warn_ai_off') }}",
        saved: "{{ __('admin/setting.saved') }}",
        error: "{{ __('admin/setting.error') }}",
        yes: "{{ __('common.yes') }}",
        no: "{{ __('common.no') }}",
    };
</script>

@endsection
