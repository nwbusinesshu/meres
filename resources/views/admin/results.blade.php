@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('titles.admin.results') }}</h1>
<div class="employee-list">
@if (is_null($assessment))
  <div class="tile tile-warning">
    <p>{{ $_('no-results') }}</p>
  </div>
@else
  @foreach ($users as $user)
  <div class="tile tile-info employee">
    <div>
      <div class="name">
        <span>{{ $user->name }}</span>
        <span>{{ __("global.bonus-malus.$user->bonusMalus") }}</span>
      </div>
      @if (is_null($user->stats))
      <div class="stats">
        <div>
          <span>{{ $_('self') }}</span>
          <span>?</span>
        </div>
        <div>
          <span>{{ $_('colleagues') }}</span>
          <span>?</span>
        </div>
        <div>
          <span>{{ $_('managers') }}</span>
          <span>?</span>
        </div>
        <div>
          <span>{{ $_('ceos') }}</span>
          <span>?</span>
        </div>
      </div>
      @else
      <div class="stats">
        <div>
          <span>{{ $_('self') }}</span>
          <span>{{ $user->stats->selfTotal*2 }}</span>
        </div>
        <div>
          <span>{{ $_('colleagues') }}</span>
          <span>{{ $user->stats->colleagueTotal }}</span>
        </div>
        <div>
          <span>{{ $_('managers') }}</span>
          <span>{{ round($user->stats->managersTotal / 2) }}</span>
        </div>
        <div>
          <span>{{ $_('ceos') }}</span>
          <span>{{ $user->stats->ceoTotal }}</span>
        </div>
      </div>
      @endif
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
        @endswitch, "colorCircle": "#00000010", "size": 80, "fontSize": "3em" }'
        ></div>
    </div>
  </div>
  @endforeach
@endif
</div>
@endsection

@section('scripts')
@endsection