{{-- resources/views/admin/results.blade.php --}}
@extends('layouts.master')

@section('head-extra')
  <link rel="stylesheet" href="{{ asset('css/admin.results.css') }}">
@endsection

@section('content')
<h1>{{ __('titles.admin.results') }}</h1>

@if ($assessment)
  <div class="period-nav">
    <a class="nav-btn {{ $prevAssessment ? '' : 'is-disabled' }}"
       @if($prevAssessment) href="{{ route(Route::currentRouteName(), $prevAssessment->id) }}" @endif
       aria-label="Előző lezárt időszak">
      <i class="fa fa-chevron-left" aria-hidden="true"></i>
    </a>

    <div class="period-chip" title="Lezárás dátuma">
      <i class="fa fa-calendar" aria-hidden="true"></i>
      <span>{{ \Carbon\Carbon::parse($assessment->closed_at)->translatedFormat('Y. m') }}</span>
    </div>

    <a class="nav-btn {{ $nextAssessment ? '' : 'is-disabled' }}"
       @if($nextAssessment) href="{{ route(Route::currentRouteName(), $nextAssessment->id) }}" @endif
       aria-label="Következő lezárt időszak">
      <i class="fa fa-chevron-right" aria-hidden="true"></i>
    </a>
  </div>

  {{-- Küszöbök – mindig --}}
  <div class="thresholds-strip">
    <div class="threshold-chip up" title="Előléptetési küszöb">
      <i class="fa fa-level-up" aria-hidden="true"></i>
      <span>Felső ponthatár: <strong>{{ (int) $assessment->normal_level_up }}</strong></span>
    </div>
    <div class="threshold-chip down" title="Lefokozási küszöb">
      <i class="fa fa-level-down" aria-hidden="true"></i>
      <span>Alsó ponthatár: <strong>{{ (int) $assessment->normal_level_down }}</strong></span>
    </div>
    <div class="threshold-chip method" title="Küszöbszámítási mód">
      <i class="fa fa-cog" aria-hidden="true"></i>
      <span>Módszer: <strong>{{ strtoupper($assessment->threshold_method ?? 'fixed') }}</strong></span>
    </div>
  </div>

  {{-- Suggested összefoglaló / diagnosztika --}}
  @if (strtolower((string)($assessment->threshold_method ?? '')) === 'suggested')
    @if (!empty($summaryHu))
      <div class="tile tile-summary">
        <div class="tile-header">
          <i class="fa fa-lightbulb-o" aria-hidden="true"></i>
          <span>AI összefoglaló</span>
        </div>
        <div class="tile-body">
          <p>{{ $summaryHu }}</p>
        </div>
      </div>
    @else
      <div class="tile tile-warning">
        <div class="tile-header">
          <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
          <span>AI összefoglaló nem elérhető</span>
        </div>
        <div class="tile-body">
          <p>{{ $summaryDbg ?? 'A summary_hu mező nem található a suggested_decision logban.' }}</p>
        </div>
      </div>
    @endif
  @endif
@endif

<div class="employee-list">
  @if (is_null($assessment))
    <div class="tile tile-warning">
      <p>{{ $_('no-results') }}</p>
    </div>
  @else
    @foreach ($users as $user)
      <a class="user-tile-link"
         href="{{ route('results.index', ['assessmentId' => optional($assessment)->id, 'as' => $user->id]) }}"
         title="Megnyitás: {{ $user->name }} eredményei"
         target="_blank" rel="noopener">
        <div class="tile tile-info employee">
          <div>
            <div class="name">
              <span>{{ $user->name }}</span>
              <span>{{ __("global.bonus-malus.$user->bonusMalus") }}</span>
            </div>

            @if (is_null($user->stats))
              <div class="stats">
                <div><span>{{ $_('self') }}</span><span>?</span></div>
                <div><span>{{ $_('colleagues') }}</span><span>?</span></div>
                <div><span>{{ $_('managers') }}</span><span>?</span></div>
                <div><span>{{ $_('ceos') }}</span><span>?</span></div>
              </div>
            @else
              <div class="stats">
                <div><span>{{ $_('self') }}</span><span>{{ $user->stats->selfTotal }}</span></div>
                <div><span>{{ $_('colleagues') }}</span><span>{{ $user->stats->colleagueTotal }}</span></div>
                <div><span>{{ $_('managers') }}</span><span>{{ round($user->stats->managersTotal) }}</span></div>
                <div><span>{{ $_('ceos') }}</span><span>{{ $user->stats->ceoTotal }}</span></div>
              </div>
            @endif
          </div>

          <div class="result">
            <div class="pie"
                 data-pie='{
                   "percent": {{ $user->stats?->total ?? 0 }},
                   "unit": "",
                   "colorSlice": @switch($user->change)
                      @case("up") "#6AB06E" @break
                      @case("down") "#D9253D" @break
                      @default "#44A3BC"
                   @endswitch,
                   "colorCircle": "#00000010",
                   "size": 80,
                   "fontSize": "3em"
                 }'></div>
          </div>
        </div>
      </a>
    @endforeach
  @endif
</div>
@endsection

@section('scripts')
@endsection
