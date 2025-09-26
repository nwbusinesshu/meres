@php
    $raterCount = $user->rater_count ?? 0;
    $raterClass = 'rater-sufficient';
    if ($raterCount < 3) {
        $raterClass = 'rater-insufficient';
    } elseif ($raterCount < 7) {
        $raterClass = 'rater-okay';
    }
@endphp
<div class="user-row {{ $extraClass ?? '' }} {{ (isset($user->type) && $user->type === 'manager') ? 'user-row--manager' : '' }} {{ !empty($showBonusMalus) ? 'has-bonus-malus' : '' }}" data-id="{{ $user->id }}">
    {{-- Név + belépési mód + E-mail --}}
    <div class="col col-name-email">
        <div class="user-name">{{ $user->name }}</div>
        <div class="text-muted small user-details">
            <div>
                Belépési mód:
                <span class="login-mode">{{ $user->login_mode_text ?? '—' }}</span>
            </div>
            <div class="user-email">
                {{ $user->email ?? '—' }}
            </div>
        </div>
    </div>

    {{-- Értékelők száma (új oszlop) --}}
    <div class="col col-raters">
        <div class="rater-counter {{ $raterClass }}">
            <div class="rater-number">{{ $raterCount }}</div>
            <div class="rater-label">{{ __('global.rater') }}</div>
            <div class="rater-bar">
                <div class="rater-progress" style="width: {{ min(100, ($raterCount / 10) * 100) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Típus / Szerep --}}
    <div class="col col-type">
        @if(isset($user->type))
            @if($user->type === 'ceo')
                {{ __('usertypes.ceo') }}
            @elseif($user->type === 'manager')
                {{ __('usertypes.manager') }}
            @elseif($user->type === 'normal')
                {{ __('usertypes.normal') }}
            @else
                {{ method_exists($user, 'getNameOfType') ? $user->getNameOfType() : ucfirst($user->type ?? '—') }}
            @endif
        @else
            —
        @endif @php $pos = data_get($user, 'position'); @endphp
    <br><small>{{ (is_string($pos) ? trim($pos) : $pos) ?: '—' }}</small>
    </div>

    {{-- Bonus/Malus (conditionally shown) --}}
    
    <div class="col col-bonusmalus">
        @if(!empty($showBonusMalus))
        @if(!empty($user->bonusMalus))
            {{ __("global.bonus-malus.$user->bonusMalus") }}
        @else
            —
        @endif
        @endif
    </div>
    

    {{-- Műveletek --}}
    <div class="col col-actions">
        <div class="d-flex" style="gap:.25rem;">
            @if(!empty($showBonusMalus))
            <button class="btn btn-outline-info bonusmalus" data-tippy-content="{{ __('admin/employees.bonusmalus') }}">
                <i class="fa fa-layer-group"></i>
            </button>
            @endif

            <button class="btn btn-outline-success competencies" data-tippy-content="{{ __('admin/employees.competencies') }}">
                <i class="fa fa-medal"></i>
            </button>

            <button class="btn btn-outline-primary relations" data-tippy-content="{{ __('admin/employees.relations') }}">
                <i class="fa fa-network-wired"></i>
            </button>

            <button class="btn btn-outline-warning datas" data-tippy-content="{{ __('admin/employees.datas') }}">
                <i class="fa fa-user-gear"></i>
            </button>

            <button class="btn btn-outline-secondary password-reset" data-tippy-content="{{ __('admin/employees.password-reset') }}">
                <i class="fa fa-key"></i>
            </button>

            @php $deleteDisabled = !empty($lockDelete); @endphp
            <button
                class="btn btn-outline-danger remove {{ $deleteDisabled ? 'disabled' : '' }}"
                {{ $deleteDisabled ? 'disabled' : '' }}
                data-tippy-content="{{ __('admin/employees.remove') }}"
            >
                <i class="fa fa-trash-alt"></i>
            </button>
        </div>
    </div>
</div>