@extends('layouts.master')

@section('head-extra')
<style>
/* Styling for fallback text when translation is not available */
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
<h1>{{ __('titles.superadmin.ceorank-defaults') }}</h1>

<div class="tile tile-button add-rank">
  <span>{{ __('admin/ceoranks.add-rank') }}</span>
</div>

<div class="tile tile-info">
  <span>{{ __('admin/ceoranks.ranking-infos') }}</span>
</div>

@foreach ($ceoranks as $rank)
<div class="tile tile-info rank" data-id="{{ $rank->id }}">
  <div>
    <span>{{ __('admin/ceoranks.value') }}</span>
    <span>{{ $rank->value }}</span>
  </div>
  <div>
    <span>{{ __('admin/ceoranks.name') }}</span>
    <span class="{{ $rank->name_is_fallback ? 'fallback-text' : '' }}">{{ $rank->translated_name }}</span>
  </div>
  <div>
    <p>{{ __('admin/ceoranks.employee-number') }}</p>
    <div>
      <div>
        <span>{{ __('admin/ceoranks.min') }}</span>
        <span>{{ is_null($rank->min) ? '-' : $rank->min.'%' }}</span>
      </div>
      <div>
        <span>{{ __('admin/ceoranks.max') }}</span>
        <span>{{ is_null($rank->max) ? '-' : $rank->max.'%' }}</span>
      </div>
    </div>
  </div>
  <div>
    <button class="btn btn-outline-danger remove-rank" data-tippy-content="{{ __('admin/ceoranks.remove-rank') }}"><i class="fa fa-trash-alt"></i></button>
    <button class="btn btn-outline-warning modify-rank" data-tippy-content="{{ __('admin/ceoranks.modify-rank') }}"><i class="fa fa-edit"></i></button>
  </div>
</div>
@endforeach
@endsection

@section('scripts')
@include('admin.modals.ceorank')
@endsection