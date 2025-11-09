@extends('layouts.master')

@section('head-extra')
<style>
/* Mobile-specific inline styles for immediate effect */
@media all and (max-width: 767px) {
  .no-mobile {
    display: none !important;
  }
  /* Hide the desktop employees section on mobile */
  .desktop-only-section {
    display: none !important;
  }
}
</style>
@endsection

@section('content')
<div>
<h2>{{ $_('ranking') }}</h2>
<div class="ranks">
@foreach ($ceoranks as $rank)
<div class="tile tile-primary rank" data-rank-id="{{ $rank->id }}">
  <div class="info">
    <div>
      <span>{{ $_('value') }}</span>
      <span>{{ $rank->value }}</span>
    </div>
    <div>
      <span>{{ $_('name') }}</span>
      <span class="{{ $rank->name_is_fallback ?? false ? 'fallback-text' : '' }}">
      {{ $rank->translated_name ?? $rank->name }}
    </span>
    </div>
    <div>
    @if (!is_null($rank->calcMin))
      <span>{{ $_('min') }}: {{ $rank->calcMin }} {{ $_('head') }}</span>
    @endif
    @if(!is_null($rank->calcMax))
      <span>{{ $_('max') }}: {{ $rank->calcMax }} {{ $_('head') }}</span>
    @endif
    </div> 
  </div>
  <div class="employees" data-id="{{ $rank->id }}" data-max="{{ $rank->calcMax ?? 'none' }}" data-min="{{ $rank->calcMin ?? 'none' }}">
    {{-- Mobile-only instruction text --}}
    <span class="mobile-tap-instruction">{{ __('ceorank.tap-to-add') ?? 'Kattints ide alkalmazottak hozzáadásához' }}</span>
  </div>
  
  {{-- Mobile-only dropdown interface --}}
  <div class="mobile-employee-select" style="display:none;">
    <select multiple class="form-control mobile-select" size="6">
      {{-- Options will be populated dynamically via JavaScript --}}
    </select>
  </div>
</div>
@endforeach
</div>
{{-- Save button moved outside ranks container for better mobile positioning --}}
<div class="mobile-save-container">
  <div class="tile tile-button save-ranks hidden">
    <span>{{ $_('save-ranks') }}</span>
  </div>
</div>
</div>
<div class="desktop-only-section">
<h2>{{ $_('employees') }}</h2>
<div class="employee-list">
@foreach ($employees as $employee)
<div class="tile tile-info employee" data-id="{{ $employee->id }}" data-name="{{ $employee->name }}" draggable="true">
  <p>{{ $employee->name }}</p>
</div>
@endforeach
</div>
{{-- Desktop save button --}}
<div class="tile tile-button save-ranks hidden">
  <span>{{ $_('save-ranks') }}</span>
</div>
</div>
<div class="tile tile-warning no-mobile">
  <p>{{ $_('no-mobile') }}</p>
</div>
@endsection

@section('scripts')
@endsection