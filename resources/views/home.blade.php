@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')

<h2>{{ $userName }}</h2>

@if (is_null($assessment))
<div class="tile tile-empty-info">
  <img src="{{ asset('assets/img/monster-info-tile.svg') }}" alt="No assessment" class="empty-tile-monster">
  <div class="empty-tile-text">
    <p class="empty-tile-title">{{ $_('no-assessment-running') }}</p>
    <p class="empty-tile-subtitle">{{ $_('no-assessment-running-info') }}</p>
    <p class="empty-tile-tasks">{!! $_('no-assessment-running-tasks') !!}</p>
  </div>
</div>
@else
  <div class="assessment-running">
    <div class="tile tile-info">
      <p>{{ $_('assessment-running') }}</p>
    </div>
    <div class="tile tile-warning">
      <p>{{ $_('due') }}: <span>{{ formatDateTime($assessment->due_at) }}</span></p>
    </div>
  </div>

  {{-- ✅ FIXED: Only show CEO rank section if user can access it (has employees to rank) --}}
  @if($canAccessCeoRank)
    <h3>{{ $_('ceo-rank') }}</h3>
    @if (!$madeCeoRank)
      <div class="tile tile-button rank-users">
        <span>{{ $_('do-ceo-rank') }}</span>
      </div>
    @else
      <div class="tile tile-empty-info-slim">
  <img src="{{ asset('assets/img/monster-info-tile-5.svg') }}" alt="No payments" class="empty-tile-monster-slim">
  <div class="empty-tile-text">
    <p class="empty-tile-title">{{ $_('ceo-rank-done') }}</p>
    <p class="empty-tile-subtitle">{{ $_('ceo-rank-done-info') }}</p>
  </div>
</div>
    @endif
  @endif

  <h3>{{ $_('to-fill-out') }}</h3>
  <div class="person-list">
    @if (!$selfAssessed)
      <div class="tile person" data-id="{{ session('uid') }}">
        <p>{{ $_('assess') }}:</p>
        <p>{{ __('assessment.self') }}</p>
        <p>{{ $_('click-to-fill') }}</p>
      </div>
    @endif
    
    @foreach ($relations as $relation)
      <div class="tile person" data-id="{{ $relation->target->id }}">
        <p>{{ $_('assess') }} {{ __('userrelationtypes.for-you.'.$relation->type )}}:</p>
        <p>{{ $relation->target->name }}</p>
        <p>{{ $_('click-to-fill') }}</p>
      </div>
    @endforeach
    </div>
    @if($selfAssessed && $relations->count() == 0)
      <div class="tile tile-empty-info-slim">
  <img src="{{ asset('assets/img/monster-info-tile-3.svg') }}" alt="No payments" class="empty-tile-monster-slim">
  <div class="empty-tile-text">
    <p class="empty-tile-title">{{ $_('no-more-to-assess') }}</p>
    <p class="empty-tile-subtitle">{{ $_('no-more-to-assess-info') }}</p>
  </div>
</div>
    @endif
@endif

@endsection

@section('scripts')
@endsection