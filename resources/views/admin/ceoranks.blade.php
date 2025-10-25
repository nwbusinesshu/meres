@extends('layouts.master')

@section('head-extra')
<style>
/* NEW: Styling for fallback text when translation is not available */
.fallback-text {
  color: #dc3545 !important;
  font-style: italic;
}

.fallback-text::after {
  content: ' (!)';
  font-size: 0.8em;
  opacity: 0.7;
}
</style>
@endsection

@section('content')
<h1>{{ __('titles.admin.ceoranks') }}</h1>

<div class="double-tiles">
  <div class="tile tile-button add-rank">
    <span>{{ $_('add-rank') }}</span>
  </div>
  <!-- NEW: Language Selection Button -->
  <div class="tile tile-button open-language-modal" onclick="initLanguageModal()">
    <span><i class="fa fa-language"></i>{{ __('admin/competencies.select-translation-languages') }}</span>
  </div>
</div>

<div class="tile tile-info">
  <span>{{ $_('ranking-infos') }}</span>
</div>

@foreach ($ceoranks as $rank)
<div class="tile tile-info rank" data-id="{{ $rank->id }}">
  <div>
    <span>{{ $_('value') }}</span>
    <span>{{ $rank->value }}</span>
  </div>
  <div>
    <span>{{ $_('name') }}</span>
    <span class="{{ $rank->name_is_fallback ? 'fallback-text' : '' }}">{{ $rank->translated_name }}</span>
  </div>
  <div>
    <p>{{ $_('employee-number') }}</p>
    <div>
      <div>
        <span>{{ $_('min') }}</span>
        <span>{{ is_null($rank->min) ? '-' : $rank->min.'%' }}</span>
      </div>
      <div>
        <span>{{ $_('max') }}</span>
        <span>{{ is_null($rank->max) ? '-' : $rank->max.'%' }}</span>
      </div>
    </div>
  </div>
  <div>
    <button class="btn btn-outline-danger remove-rank" data-tippy-content="{{ $_('remove-rank') }}"><i class="fa fa-trash-alt"></i></button>
    <button class="btn btn-outline-warning modify-rank" data-tippy-content="{{ $_('modify-rank') }}"><i class="fa fa-edit"></i></button>
  </div>
</div>
@endforeach
@endsection

@section('scripts')
@include('admin.modals.ceorank')
@include('admin.modals.language-select')
@endsection