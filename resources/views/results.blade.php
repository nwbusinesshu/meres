@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('titles.results') }}</h1>


@if ($assessment)
  <div class="period-nav">
    <a
  class="nav-btn {{ $prevAssessment ? '' : 'is-disabled' }}"
  @if($prevAssessment)
    href="{{ route(Route::currentRouteName(), $prevAssessment->id) }}{{ request()->has('as') ? '?as=' . request('as') : '' }}"
  @endif
  aria-label="Előző lezárt időszak"
>
  <i class="fa fa-chevron-left" aria-hidden="true"></i>
</a>

    <div class="period-chip" title="Lezárás dátuma">
      <i class="fa fa-calendar" aria-hidden="true"></i>
      {{-- YYYY.MM --}}
      <span>{{ \Carbon\Carbon::parse($assessment->closed_at)->format('Y.m') }}</span>
    </div>

    <a
  class="nav-btn {{ $nextAssessment ? '' : 'is-disabled' }}"
  @if($nextAssessment)
    href="{{ route(Route::currentRouteName(), $nextAssessment->id) }}{{ request()->has('as') ? '?as=' . request('as') : '' }}"
  @endif
  aria-label="Következő lezárt időszak"
>
  <i class="fa fa-chevron-right" aria-hidden="true"></i>
</a>
  </div>
@endif

@if(isset($user))
  <div class="tile tile-info mb-4" style="display: flex; align-items: center; gap: 24px;">
    <div>
      <i class="fa fa-user" aria-hidden="true" style="font-size:2rem; margin-right: 12px; color: #2F6FEB;"></i>
    </div>
    <div>
      <div style="font-size:1.2rem; font-weight:600;">{{ $user->name }}</div>
      <div style="color:#666;">{{ $user->email }}</div>
    </div>
  </div>
@endif

@php
  $trendIcon = function($t){ return $t==='up'?'▲':($t==='down'?'▼':'▬'); };
@endphp

@if (is_null($assessment) || is_null($user->stats))
  <div class="tile tile-warning">
    <p>{{ $_('no-results') }}</p>
  </div>
@else
<div class="list">
  {{-- ÖSSZPONT + BONUS/MALUS + TREND --}}
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
            @case("up")   "#6AB06E" @break
            @case("down") "#D9253D" @break
            @default      "#44A3BC"
          @endswitch, "colorCircle": "#00000010", "size": 100, "fontSize": "3em" }'
        ></div>

        {{-- ÚJ: összpont szám + trend ikon egy sorban --}}
        <div class="value-trend value-trend--big" style="margin-top:.5rem;">
          <span class="trend trend-{{ $user['trend']['total'] }}" title="Trend">
            {{ $trendIcon($user['trend']['total']) }}
          </span>
        </div>
      </div>
    </div>
  </div>

  {{-- RÉSZPONTOK + TREND --}}
  <div class="tile tile-info">
    <div>
      <span>{{ $_('self') }}</span>
      <span class="value-trend">
        <strong>{{ number_format(($user->stats?->selfTotal ?? 0) * 1, 1) }}</strong>
        <span class="trend trend-{{ $user['trend']['self'] }}">{{ $trendIcon($user['trend']['self']) }}</span>
      </span>
    </div>
    <div>
      <span>{{ $_('colleagues') }}</span>
      <span class="value-trend">
        <strong>{{ number_format(($user->stats?->colleagueTotal ?? 0) * 1, 1) }}</strong>
        <span class="trend trend-{{ $user['trend']['employees'] }}">{{ $trendIcon($user['trend']['employees']) }}</span>
      </span>
    </div>
    <div>
      <span>{{ $_('managers') }}</span>
      <span class="value-trend">
        <strong>{{ number_format(($user->stats?->managersTotal ?? 0) / 1, 1) }}</strong>
        <span class="trend trend-{{ $user['trend']['leaders'] }}">{{ $trendIcon($user['trend']['leaders']) }}</span>
      </span>
    </div>
  </div>
</div>
@endif

{{-- ===== Idősoros teljesítmény (Chart.js) ===== --}}
@if($assessment && $user && isset($history) && $history->count() > 0)
  @php
    // PHP -> JS-nek előkészített tömbök
    $labels = [];
    $seriesTotal = [];
    $seriesSelf = [];
    $seriesEmployees = [];
    $seriesLeaders = [];

    foreach ($history as $row) {
        $labels[] = \Carbon\Carbon::parse($row['closed_at'])->format('Y.m'); // YYYY.MM
        $seriesTotal[]     = isset($row['total'])     ? round($row['total'],1)         : null;
        $seriesSelf[]      = isset($row['self'])      ? round($row['self'],1)          : null;
        $seriesEmployees[] = isset($row['employees']) ? round($row['employees'],1)     : null;
        $seriesLeaders[]   = isset($row['leaders'])   ? round($row['leaders'],1)       : null;
    }
    $currentIdx = is_numeric($currentIdx) ? (int)$currentIdx : ($history->count()-1);
  @endphp

  <div class="tile tile-info" style="padding:16px;">
    <h3 class="mb-2">Idősoros teljesítmény</h3>
    <div class="chartjs-wrap">
      <canvas id="resultsTrendChart"></canvas>
    </div>
  </div>

  <script>
    (function(){
      const labels = @json($labels);
      const currentIdx = @json($currentIdx);

      const datasets = [
        { key: 'Összpont',   data: @json($seriesTotal),     color: '#2F6FEB' },
        { key: 'Önértékelés',data: @json($seriesSelf),      color: '#10B981' },
        { key: 'Kollégák',   data: @json($seriesEmployees), color: '#F59E0B' },
        { key: 'Vezetők',    data: @json($seriesLeaders),   color: '#EF4444' },
      ].filter(ds => Array.isArray(ds.data) && ds.data.some(v => v !== null)); // csak ami nem teljesen üres

      const ctx = document.getElementById('resultsTrendChart').getContext('2d');

      new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: datasets.map(ds => ({
            label: ds.key,
            data: ds.data,
            borderColor: ds.color,
            backgroundColor: ds.color + '33', // 20% átlátszó fill (ha bekapcsolnád)
            fill: false,
            spanGaps: true,       // null értékek felett ne húzzon vonalat
            tension: 0.3,
            borderWidth: 2,
            pointRadius: ctx => (ctx.dataIndex === currentIdx ? 5 : 3),
            pointHoverRadius: 6,
            pointBackgroundColor: '#fff',
            pointBorderColor: ds.color,
            pointBorderWidth: 2,
          }))
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'nearest', intersect: false },
          plugins: {
            legend: {
              position: 'top',
              labels: { boxWidth: 14, usePointStyle: true, pointStyle: 'circle', font: { size: 12 } }
            },
            tooltip: {
              callbacks: {
                title: items => items[0].label, // YYYY.MM
                label: item => `${item.dataset.label}: ${Number(item.formattedValue).toFixed(1)}`
              },
              bodyFont: { size: 12 },
              titleFont: { size: 12 }
            }
          },
          scales: {
            x: {
              ticks: { maxRotation: 0, autoSkip: true, font: { size: 11 } },
              grid: { display: false }
            },
            y: {
              min: 0, max: 100,
              ticks: { stepSize: 20, font: { size: 11 } },
              grid: { color: 'rgba(0,0,0,.08)' }
            }
          }
        }
      });
    })();
  </script>
@endif
@if(auth()->check() && in_array(strtolower(auth()->user()->type), ['admin', 'superadmin']))
  <div class="mb-4">
    <a href="{{ route('admin.results.index') }}" class="btn btn-primary">
      <i class="fa fa-arrow-left"></i>
      Vissza az összesített eredményekhez
    </a>
  </div>
@endif

@endsection

@section('scripts')
@endsection
