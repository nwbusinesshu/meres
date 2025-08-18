@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('titles.results') }}</h1>
@if (is_null($assessment) || is_null($user->stats))
  <div class="tile tile-warning">
    <p>{{ $_('no-results') }}</p>
  </div>
@else
<div class="list">
  <div class="tile tile-info">
    <p>{{ $_('last-period') }}<br>{{ formatDateTime($assessment->closed_at) }}</p>
    <div>
      <div class="bonusmalus">
        <span>{{ __('global.bonusmalus') }}</span>
        <span>{{ __("global.bonus-malus.$user->bonusMalus") }}</span>
      </div>
      <div class="result">
        <div class="pie"
          data-pie='{ "percent":  {{ $user->stats?->total ?? 0 }}, "unit": "", "colorSlice": @switch($user->change)
            @case("up")
              "#6AB06E"
              @break
            @case("down")
              "#D9253D"
            @break
            @default
              "#44A3BC"
          @endswitch, "colorCircle": "#00000010", "size": 100, "fontSize": "3em" }'
          ></div>
      </div>
    </div>
  </div>
  <div class="tile tile-info"> 
    <div>
      <span>{{ $_('self') }}</span>
      <span>{{ $user->stats?->selfTotal * 2 }}</span>
    </div>
    <div>
      <span>{{ $_('colleagues') }}</span>
      <span>{{ $user->stats?->colleagueTotal * 1}}</span>
    </div>
    <div>
      <span>{{ $_('managers') }}</span>
      <span>{{ round($user->stats?->managersTotal / 2) }}</span>
    </div>
  </div>
</div>
@endif
@endsection

@section('scripts')
@endsection