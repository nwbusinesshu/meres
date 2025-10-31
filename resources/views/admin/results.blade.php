{{-- resources/views/admin/results.blade.php --}}
@extends('layouts.master')

@section('head-extra')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/admin.results.css') }}">
@endsection

@section('content')
<h1>{{ __('titles.admin.results') }}</h1>

@if ($assessment)
  <div class="period-nav">
    <a class="nav-btn {{ $prevAssessment ? '' : 'is-disabled' }}"
       @if($prevAssessment) href="{{ route(Route::currentRouteName(), $prevAssessment->id) }}" @endif
       aria-label="{{ __('admin/results.previous-closed-period') }}">
      <i class="fa fa-chevron-left" aria-hidden="true"></i>
    </a>

    <div class="period-chip" title="{{ __('admin/results.closure-date') }}">
      <i class="fa fa-calendar" aria-hidden="true"></i>
      <span>{{ \Carbon\Carbon::parse($assessment->closed_at)->translatedFormat('Y. m') }}</span>
    </div>

    <a class="nav-btn {{ $nextAssessment ? '' : 'is-disabled' }}"
       @if($nextAssessment) href="{{ route(Route::currentRouteName(), $nextAssessment->id) }}" @endif
       aria-label="{{ __('admin/results.next-closed-period') }}">
      <i class="fa fa-chevron-right" aria-hidden="true"></i>
    </a>
  </div>

  {{-- Thresholds strip --}}
  <div class="thresholds-strip">
    <div class="threshold-chip up" title="{{ __('admin/results.promotion-threshold') }}">
      <i class="fa fa-level-up" aria-hidden="true"></i>
      <span>{{ __('admin/results.upper-score-limit') }}: <strong>{{ (int) $assessment->normal_level_up }}</strong></span>
    </div>
    <div class="threshold-chip down" title="{{ __('admin/results.demotion-threshold') }}">
      <i class="fa fa-level-down" aria-hidden="true"></i>
      <span>{{ __('admin/results.lower-score-limit') }}: <strong>{{ (int) $assessment->normal_level_down }}</strong></span>
    </div>
    <div class="threshold-chip method" title="{{ __('admin/results.threshold-calculation-method') }}">
      <i class="fa fa-cog" aria-hidden="true"></i>
      <span>{{ __('admin/results.method') }}: <strong>{{ strtoupper($assessment->threshold_method ?? 'fixed') }}</strong></span>
    </div>
  </div>

  {{-- AI Summary for suggested mode --}}
  @if (strtolower((string)($assessment->threshold_method ?? '')) === 'suggested')
    @if (!empty($summaryHu))
      <div class="tile tile-summary">
        <div class="tile-header">
          <i class="fa fa-lightbulb-o" aria-hidden="true"></i>
          <span>{{ __('admin/results.ai-summary') }}</span>
        </div>
        <div class="tile-body">
          <p>{{ $summaryHu }}</p>
        </div>
      </div>
    @else
      <div class="tile tile-warning">
        <div class="tile-header">
          <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
          <span>{{ __('admin/results.ai-summary-not-available') }}</span>
        </div>
        <div class="tile-body">
          <p>{{ $summaryDbg ?? __('admin/results.summary-field-not-found') }}</p>
        </div>
      </div>
    @endif
  @endif

  {{-- Search and Filter Bar --}}
  <div class="tile tile-info search-tile">
    <p>{{ __('admin/results.search') }}</p>
    <div>
      <input type="text" 
             class="form-control search-input" 
             placeholder="{{ __('admin/results.search-employees') }}"
             id="results-search-input">
      <i class="fa fa-ban clear-search" id="clear-search-btn" data-tippy-content="{{ __('admin/results.clear-search') }}"></i>
    </div>
  </div>

  {{-- Filters Section --}}
  <div class="filters-container tile">
    {{-- Threshold filters --}}
    <div class="filter-group">
      <div class="filter-group-label">{{ __('admin/results.filter-by-threshold') }}</div>
      <div class="filter-chips">
        <div class="filter-chip" data-filter="threshold" data-value="above">
          <i class="fa fa-arrow-up"></i>
          <span>{{ __('admin/results.above-upper-threshold') }}</span>
        </div>
        <div class="filter-chip" data-filter="threshold" data-value="between">
          <i class="fa fa-arrows-h"></i>
          <span>{{ __('admin/results.between-thresholds') }}</span>
        </div>
        <div class="filter-chip" data-filter="threshold" data-value="below">
          <i class="fa fa-arrow-down"></i>
          <span>{{ __('admin/results.below-lower-threshold') }}</span>
        </div>
      </div>
    </div>

    {{-- Trend filters --}}
    <div class="filter-group">
      <div class="filter-group-label">{{ __('admin/results.filter-by-trend') }}</div>
      <div class="filter-chips">
        <div class="filter-chip" data-filter="trend" data-value="up">
          <i class="fa fa-arrow-up"></i>
          <span>{{ __('admin/results.trend-up') }}</span>
        </div>
        <div class="filter-chip" data-filter="trend" data-value="stable">
          <i class="fa fa-minus"></i>
          <span>{{ __('admin/results.trend-stable') }}</span>
        </div>
        <div class="filter-chip" data-filter="trend" data-value="down">
          <i class="fa fa-arrow-down"></i>
          <span>{{ __('admin/results.trend-down') }}</span>
        </div>
      </div>
    </div>

    {{-- Bonus/Malus filters --}}
    @if(!empty($showBonusMalus))
      <div class="filter-group">
        <div class="filter-group-label">{{ __('admin/results.filter-by-bonusmalus') }}</div>
        <div class="filter-chips" id="bonusmalus-filters">
          {{-- Will be populated dynamically --}}
        </div>
      </div>
    @endif
  </div>

  {{-- Results Container --}}
  @if ($enableMultiLevel && $departments->isNotEmpty())
    {{-- Multi-level view: Department groups --}}
    <div class="results-container multi-level">
      @foreach ($departments as $dept)
        <div class="dept-block" data-dept-id="{{ $dept->id }}" data-dept-name="{{ strtolower($dept->department_name) }}">
          <div class="dept-header" onclick="toggleDepartment(this)">
            <div class="left">
              <i class="fa fa-chevron-down caret"></i>
              <span class="dept-title">{{ $dept->department_name }}</span>
              <span class="badge">{{ $dept->managers->count() + $dept->users->count() }}</span>
            </div>
          </div>

          <div class="dept-body">
            {{-- Managers first (with badge) --}}
            @foreach ($dept->managers as $manager)
              @include('admin.partials.result-user-tile', ['user' => $manager, 'assessment' => $assessment, 'showBonusMalus' => $showBonusMalus])
            @endforeach

            {{-- Then regular members --}}
            @foreach ($dept->users as $user)
              @include('admin.partials.result-user-tile', ['user' => $user, 'assessment' => $assessment, 'showBonusMalus' => $showBonusMalus])
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  @else
    {{-- Legacy view: Flat list --}}
    <div class="employee-list results-container">
      @foreach ($users as $user)
        @include('admin.partials.result-user-tile', ['user' => $user, 'assessment' => $assessment, 'showBonusMalus' => $showBonusMalus])
      @endforeach
    </div>
  @endif

  {{-- No results message (hidden by default) --}}
  <div class="no-results-message hidden" id="no-results-message">
    <i class="fa fa-search"></i>
    <h3>{{ __('admin/results.no-results-found') }}</h3>
    <p>{{ __('admin/results.try-different-search') }}</p>
  </div>

@else
  {{-- No assessment state --}}
  <div class="tile tile-empty-info">
    <img src="{{ asset('assets/img/monster-info-tile-3.svg') }}" alt="No assessment" class="empty-tile-monster">
    <div class="empty-tile-text">
      <p class="empty-tile-title">{{ __('admin/results.no-results-yet') }}</p>
      <p class="empty-tile-subtitle">{{ __('admin/results.no-results-info') }}</p>
      <p class="empty-tile-tasks">{!! __('admin/results.no-results-tasks') !!}</p>
    </div>
  </div>
@endif

@endsection

@section('scripts')
  <script>
  $(document).ready(function(){
    new CircularProgressBar('pie').initial();
  });
  </script>
  
  @include('js.admin.results')
@endsection