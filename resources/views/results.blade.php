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
    href="{{ route(Route::currentRouteName(), $assessmentId) }}{{ request()->has('as') ? '?as=' . request('as') : '' }}"
  @endif
  aria-label="{{ __('results.previous-closed-period') }}"
>
  <i class="fa fa-chevron-left" aria-hidden="true"></i>
</a>

    <div class="period-chip" title="{{ __('results.closure-date') }}">
      <i class="fa fa-calendar" aria-hidden="true"></i>
      {{-- YYYY.MM --}}
      <span>{{ \Carbon\Carbon::parse($assessment->closed_at)->format('Y.m') }}</span>
    </div>

    <a
  class="nav-btn {{ $nextAssessment ? '' : 'is-disabled' }}"
  @if($nextAssessment)
    href="{{ route(Route::currentRouteName(), $nextAssessment->id) }}{{ request()->has('as') ? '?as=' . request('as') : '' }}"
  @endif
  title="{{ __('results.next-closed-period') }}"
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
  $missingList = $user['missingComponents'] ?? [];
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
      @if(!empty($showBonusMalus))
      <div class="bonusmalus">
        <span>{{ __('global.bonusmalus') }}</span>
        <span>{{ __("global.bonus-malus.$user->bonusMalus") }}</span>
      </div> @endif

      <div class="result">
        <div class="pie"
          data-pie='{ "percent":  {{ $user->stats?->total ?? 0 }}, "unit": "", "colorSlice": @switch($user['change'])
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
    @php
      // ✅ Calculate merged managers score (average of manager + ceo if both present)
      $managerScore = $user->stats?->managersTotal ?? null;
      $ceoScore = $user->stats?->ceoTotal ?? null;
      
      $mergedManagerScore = null;
      if ($managerScore !== null && $ceoScore !== null) {
          // Both present: average them
          $mergedManagerScore = ($managerScore + $ceoScore) / 2;
      } elseif ($managerScore !== null) {
          // Only manager present
          $mergedManagerScore = $managerScore;
      } elseif ($ceoScore !== null) {
          // Only CEO present
          $mergedManagerScore = $ceoScore;
      }
      // Otherwise both null, stay null
    @endphp

    {{-- Self --}}
    @if(!in_array('self', $missingList))
    <div>
      <span>{{ $_('self') }}</span>
      <span class="value-trend">
        <strong>{{ number_format(($user->stats?->selfTotal ?? 0) * 1, 1) }}</strong>
        <span class="trend trend-{{ $user['trend']['self'] }}">{{ $trendIcon($user['trend']['self']) }}</span>
      </span>
    </div>
    @endif

    {{-- Colleagues --}}
    @if(!in_array('colleagues', $missingList))
    <div>
      <span>{{ $_('colleagues') }}</span>
      <span class="value-trend">
        <strong>{{ number_format(($user->stats?->colleagueTotal ?? 0) * 1, 1) }}</strong>
        <span class="trend trend-{{ $user['trend']['employees'] }}">{{ $trendIcon($user['trend']['employees']) }}</span>
      </span>
    </div>
    @endif

    {{-- Direct Reports --}}
    @if(!in_array('direct_reports', $missingList))
    <div>
      <span>{{ $_('direct_reports') }}</span>
      <span class="value-trend">
        <strong>{{ number_format(($user->stats?->directReportsTotal ?? 0) * 1, 1) }}</strong>
        <span class="trend trend-{{ $user['trend']['direct_reports'] }}">{{ $trendIcon($user['trend']['direct_reports']) }}</span>
      </span>
    </div>
    @endif

    {{-- Managers (merged with CEO) --}}
    @if($mergedManagerScore !== null)
    <div>
      <span>{{ $_('managers') }}</span>
      <span class="value-trend">
        <strong>{{ number_format($mergedManagerScore, 1) }}</strong>
        <span class="trend trend-{{ $user['trend']['leaders'] }}">{{ $trendIcon($user['trend']['leaders']) }}</span>
      </span>
    </div>
    @endif
  </div>

  {{-- ✅ MISSING COMPONENTS BADGES --}}
  @if(!empty($missingList) && count($missingList) > 0)
    <div class="tile tile-warning" style="padding: 0.75rem;">
      <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center;">
        <span style="font-size: 0.85rem; color: #666; font-weight: 600;">{{ __('results.missing') }}:</span>
        @foreach($missingList as $component)
          <span class="badge badge-missing">{{ __('results.component_' . $component) }}</span>
        @endforeach
      </div>
    </div>
  @endif
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
    $seriesDirectReports = [];
    $seriesLeaders = [];

    foreach ($history as $row) {
        $labels[] = \Carbon\Carbon::parse($row['closed_at'])->format('Y.m'); // YYYY.MM
        $seriesTotal[]         = isset($row['total'])          ? round($row['total'],1)          : null;
        $seriesSelf[]          = isset($row['self'])           ? round($row['self'],1)           : null;
        $seriesEmployees[]     = isset($row['employees'])      ? round($row['employees'],1)      : null;
        $seriesDirectReports[] = isset($row['direct_reports']) ? round($row['direct_reports'],1) : null;
        $seriesLeaders[]       = isset($row['leaders'])        ? round($row['leaders'],1)        : null;
    }
    $currentIdx = is_numeric($currentIdx) ? (int)$currentIdx : ($history->count()-1);
  @endphp

  <div class="tile tile-info" style="padding:16px;">
    <h3 class="mb-2">{{ __('results.time-series-performance') }}</h3>
    <div class="chartjs-wrap">
      <canvas id="resultsTrendChart"></canvas>
    </div>
  </div>

  <script>
    (function(){
      const labels = @json($labels);
      const currentIdx = @json($currentIdx);

      const datasets = [
        { key: '{{ __("results.total-points") }}',        data: @json($seriesTotal),         color: '#2F6FEB' },
        { key: '{{ __("results.self-assessment") }}',     data: @json($seriesSelf),          color: '#10B981' },
        { key: '{{ __("results.colleagues-rating") }}',   data: @json($seriesEmployees),     color: '#F59E0B' },
        { key: '{{ __("results.direct-reports-rating") }}', data: @json($seriesDirectReports), color: '#8B5CF6' },
        { key: '{{ __("results.managers-rating") }}',     data: @json($seriesLeaders),       color: '#EF4444' },
      ].filter(ds => Array.isArray(ds.data) && ds.data.some(v => v !== null));

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
            pointHoverRadius: 7
          }))
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { position: 'top' },
            tooltip: {
              callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)}`
              }
            }
          },
          scales: {
            x: { title: { display: true, text: '{{ __("results.period") }}' } },
            y: {
              title: { display: true, text: '{{ __("results.points") }}' },
              min: 0,
              max: 100
            }
          }
        }
      });
    })();
  </script>
@endif

{{-- ===== Kompetenciák szerinti eredmény (Bar Chart) ===== --}}
  <div class="tile tile-info" style="padding:16px; margin-top: 1rem;">
    <h3 class="mb-2">{{ __('results.competency-breakdown') }}</h3>
    <div class="chartjs-wrap">
      <canvas id="competencyChart"></canvas>
    </div>
  </div>

  <script>
    (function(){
      const competencyData = @json($competencyScores);
      
      const labels = competencyData.map(c => c.name);
      const scores = competencyData.map(c => c.avg_score);
      
      const ctx = document.getElementById('competencyChart').getContext('2d');
      
      // Plugin to draw labels below bars
      const belowBarLabelsPlugin = {
        id: 'belowBarLabels',
        afterDatasetsDraw(chart) {
          const ctx = chart.ctx;
          const meta = chart.getDatasetMeta(0);
          
          ctx.save();
          ctx.font = '13px sans-serif';
          ctx.fillStyle = '#495057';
          ctx.textAlign = 'left';
          ctx.textBaseline = 'top';
          
          meta.data.forEach((bar, index) => {
            const label = competencyData[index].name;
            const x = chart.scales.x.left + 5; // 5px from left edge
            // For horizontal bars: bar.y is top, bar.height is the bar's vertical size
            // Position label below the bar bottom edge
            const y = bar.y + (bar.height / 2) + 5; // 5px below bar bottom
            
            ctx.fillText(label, x, y);
          });
          
          ctx.restore();
        }
      };
      
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels.map(() => ''), // Empty labels on y-axis
          datasets: [{
            label: '{{ __("results.average-score") }}',
            data: scores,
            backgroundColor: '#2F6FEB',
            borderColor: '#1E4FBB',
            borderWidth: 1,
            barThickness: 40
          }]
        },
        options: {
          indexAxis: 'y', // Horizontal bar chart
          responsive: true,
          maintainAspectRatio: false,
          layout: {
            padding: {
              bottom: 25 // Space for labels below bars
            }
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                title: (tooltipItems) => competencyData[tooltipItems[0].dataIndex].name,
                label: (ctx) => `{{ __("results.average-score") }}: ${ctx.parsed.x.toFixed(1)}`
              }
            }
          },
          scales: {
            x: {
              beginAtZero: true,
              max: 100,
              title: {
                display: true,
                text: '{{ __("results.points") }}'
              },
              grid: {
                display: true
              }
            },
            y: {
              display: true,
              grid: {
                display: false
              },
              ticks: {
                display: false // Hide y-axis labels
              }
            }
          }
        },
        plugins: [belowBarLabelsPlugin]
      });
    })();
  </script>

{{-- ===== USER AS RATER - AI TELEMETRY (ADMIN ONLY) ===== --}}
@if(auth()->user()->isCurrentAdmin() && isset($raterTelemetry) && count($raterTelemetry) > 0)
  <div class="telemetry-section">
    <div>
      <h3 class="mb-2">{{ __('results.user-as-rater-title') }}</h3>      
    </div>

    @if(empty($raterTelemetry))
      <div class="no-telemetry-message">
        {{ __('results.no-telemetry-data') }}
      </div>
    @else
      <div class="telemetry-grid">
        @foreach($raterTelemetry as $telemetry)
          @php
            $target = \App\Models\User::find($telemetry['target_id']);
            $trustIndex = $telemetry['trust_index'];
            $trustClass = 'trust-badge-medium';
            if($trustIndex !== null) {
              if($trustIndex >= 60) $trustClass = 'trust-badge-high';
              elseif($trustIndex < 40) $trustClass = 'trust-badge-low';
            }
          @endphp

          <div class="telemetry-card">
            <div class="telemetry-card-header">
              <div class="telemetry-target">
                <i class="fa fa-user"></i>
                {{ $target ? $target->name : __('results.target') . ' #' . $telemetry['target_id'] }}
              </div>
              
              @if($trustIndex !== null)
                <div class="telemetry-trust-index">
                  <span>{{ __('results.trust-index') }}:</span>
                  <span class="trust-badge {{ $trustClass }}">{{ $trustIndex }}</span>
                </div>
              @endif
            </div>

            <div class="telemetry-flags">
              @if(empty($telemetry['flags']))
                <span class="flag-badge">{{ __('results.no-flags') }}</span>
              @else
                @foreach($telemetry['flags'] as $flag)
                  @php
                    $flagClass = '';
                    if(in_array($flag, ['too_fast', 'fast_read', 'suspicious_pattern'])) {
                      $flagClass = 'flag-danger';
                    } elseif(in_array($flag, ['one_click_fast_read', 'too_uniform'])) {
                      $flagClass = 'flag-warning';
                    }
                  @endphp
                  <span class="flag-badge {{ $flagClass }}">
                    {{ __('results.flag_' . $flag) }}
                  </span>
                @endforeach
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
@endif

{{-- Back to admin results button (for admins viewing user results) --}}
@if(auth()->user()->isCurrentAdmin())
  <div class="mb-4">
    <a href="{{ route('admin.results.index') }}" class="btn btn-primary">
      <i class="fa fa-arrow-left"></i>
      {{ __('results.back-to-admin-results') }}
    </a>
  </div>
@endif

@endsection

@section('scripts')
@endsection