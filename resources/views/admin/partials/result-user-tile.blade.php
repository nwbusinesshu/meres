{{-- resources/views/admin/partials/result-user-tile.blade.php --}}
<a class="user-tile-link"
   href="{{ route('results.index', ['assessmentId' => optional($assessment)->id, 'as' => $user->id]) }}"
   title="{{ __('admin/results.open-user-results', ['name' => $user->name]) }}"
   target="_blank" 
   rel="noopener"
   data-score="{{ $user->stats?->total ?? 0 }}"
   data-trend="{{ $user->change ?? 'stable' }}"
   data-bonusmalus="{{ isset($user->bonusMalus) ? __('global.bonus-malus.' . $user->bonusMalus) : '' }}"
   data-user-id="{{ $user->id }}">
  <div class="tile tile-info employee">
    <div>
      <div class="name">
        <span class="user-tile-name">
          {{ $user->name }}
          {{-- CEO Badge --}}
          @if($user->isCeo ?? false)
            <span class="badge badge-ceo" title="{{ __('admin/results.ceo-role') }}">CEO</span>
          @endif
          {{-- Manager Badge --}}
          @if(($user->isManager ?? false) && !($user->isCeo ?? false))
            <span class="badge badge-manager" title="{{ __('admin/results.manager-role') }}">MANAGER</span>
          @endif
        </span>
      </div>

      {{-- Email --}}
      @if(!empty($user->email))
        <div class="user-tile-email">
          <i class="fa fa-envelope" aria-hidden="true"></i> {{ $user->email }}
        </div>
      @endif

      {{-- Position (if available) --}}
      @if(!empty($user->position))
        <div class="user-tile-position">
          <i class="fa fa-briefcase" aria-hidden="true"></i> {{ $user->position }}
        </div>
      @endif

      @if (is_null($user->stats))
        {{-- No stats available --}}
        <div class="stats">
          <div><span>{{ __('admin/results.self') }}</span><span>?</span></div>
          <div><span>{{ __('admin/results.colleagues') }}</span><span>?</span></div>
          <div><span>{{ __('admin/results.direct_reports') }}</span><span>?</span></div>
          <div><span>{{ __('admin/results.managers') }}</span><span>?</span></div>
          <div><span>{{ __('admin/results.ceos') }}</span><span>?</span></div>
        </div>
      @else
        {{-- Display only available components (hide missing ones) --}}
        @php
          $missingList = $user->missingComponents ?? [];
        @endphp
        <div class="stats">
          {{-- Self --}}
          @if(!in_array('self', $missingList))
            <div><span>{{ __('admin/results.self') }}</span><span>{{ $user->stats->selfTotal }}</span></div>
          @endif
          
          {{-- Colleagues --}}
          @if(!in_array('colleagues', $missingList))
            <div><span>{{ __('admin/results.colleagues') }}</span><span>{{ $user->stats->colleagueTotal }}</span></div>
          @endif
          
          {{-- Direct Reports --}}
          @if(!in_array('direct_reports', $missingList))
            <div><span>{{ __('admin/results.direct_reports') }}</span><span>{{ $user->stats->directReportsTotal ?? 0 }}</span></div>
          @endif
          
          {{-- Managers --}}
          @if(!in_array('managers', $missingList))
            <div><span>{{ __('admin/results.managers') }}</span><span>{{ round($user->stats->managersTotal) }}</span></div>
          @endif
          
          {{-- CEOs --}}
          @if(!in_array('ceo_rank', $missingList))
            <div><span>{{ __('admin/results.ceos') }}</span><span>{{ $user->stats->ceoTotal }}</span></div>
          @endif
        </div>
      @endif
    </div>

    <div class="result">
      {{-- Bonus/Malus Badge - Top Right Corner --}}
      @if(!empty($showBonusMalus) && isset($user->bonusMalus))
        <span class="badge badge-bonusmalus" title="{{ __('global.bonusmalus') }}">
          {{ __('global.bonus-malus.' . $user->bonusMalus) }}
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