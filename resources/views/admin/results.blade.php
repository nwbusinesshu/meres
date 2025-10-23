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

  {{-- Küszöbök – mindig --}}
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

  {{-- Suggested összefoglaló / diagnosztika --}}
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
         title="{{ __('admin/results.open-user-results', ['name' => $user->name]) }}"
         target="_blank" rel="noopener">
        <div class="tile tile-info employee">
          <div>
            <div class="name">
              <span>
                {{ $user->name }}
                {{-- ✅ CEO Badge --}}
                @if($user->isCeo ?? false)
                  <span class="badge badge-ceo" title="{{ __('admin/results.ceo-role') }}">CEO</span>
                @endif
              </span>
            </div>

            @if (is_null($user->stats))
              {{-- ✅ No stats available --}}
              <div class="stats">
                <div><span>{{ $_('self') }}</span><span>?</span></div>
                <div><span>{{ $_('colleagues') }}</span><span>?</span></div>
                <div><span>{{ $_('direct_reports') }}</span><span>?</span></div>
                <div><span>{{ $_('managers') }}</span><span>?</span></div>
                <div><span>{{ $_('ceos') }}</span><span>?</span></div>
              </div>
            @else
              {{-- ✅ Display only available components (hide missing ones) --}}
              @php
                $missingList = $user->missingComponents ?? [];
              @endphp
              <div class="stats">
                {{-- Self --}}
                @if(!in_array('self', $missingList))
                  <div><span>{{ $_('self') }}</span><span>{{ $user->stats->selfTotal }}</span></div>
                @endif
                
                {{-- Colleagues --}}
                @if(!in_array('colleagues', $missingList))
                  <div><span>{{ $_('colleagues') }}</span><span>{{ $user->stats->colleagueTotal }}</span></div>
                @endif
                
                {{-- Direct Reports --}}
                @if(!in_array('direct_reports', $missingList))
                  <div><span>{{ $_('direct_reports') }}</span><span>{{ $user->stats->directReportsTotal ?? 0 }}</span></div>
                @endif
                
                {{-- Managers --}}
                @if(!in_array('managers', $missingList))
                  <div><span>{{ $_('managers') }}</span><span>{{ round($user->stats->managersTotal) }}</span></div>
                @endif
                
                {{-- CEOs --}}
                @if(!in_array('ceo_rank', $missingList))
                  <div><span>{{ $_('ceos') }}</span><span>{{ $user->stats->ceoTotal }}</span></div>
                @endif
              </div>

              {{-- ✅ Missing Components Badges --}}
              @if(!empty($missingList) && count($missingList) > 0)
                <div class="missing-components">
                  <span class="missing-label">{{ __('admin/results.missing') }}:</span>
                  @foreach($missingList as $component)
                    <span class="badge badge-missing">{{ __('admin/results.component_' . $component) }}</span>
                  @endforeach
                </div>
              @endif
            @endif
          </div>

          <div class="result">
            {{-- ✅ Bonus/Malus Badge - Top Right Corner --}}
            @if(!empty($showBonusMalus) && isset($user->bonusMalus))
              <span class="badge badge-bonusmalus" title="{{ __('global.bonusmalus') }}">
                {{ __("global.bonus-malus.$user->bonusMalus") }}
              </span>
            @endif
            
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