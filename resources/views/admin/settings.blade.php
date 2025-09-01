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

@endsection
