<div class="user-row {{ $extraClass ?? '' }} {{ (isset($user->type) && $user->type === 'manager') ? 'user-row--manager' : '' }}" data-id="{{ $user->id }}">
    {{-- Név + belépési mód --}}
    <div class="col col-name">
        <div>{{ $user->name }}</div>
        <div class="text-muted small">
            Belépési mód:
            <span class="login-mode">{{ $user->login_mode_text ?? '—' }}</span>
        </div>
    </div>

    {{-- E-mail --}}
    <div class="col col-email">
        {{ $user->email ?? '—' }}
    </div>

    {{-- Típus / Szerep --}}
    <div class="col col-type">
        @if(isset($user->type) && $user->type === 'manager')
            Vezető
        @else
            {{ method_exists($user, 'getNameOfType') ? $user->getNameOfType() : ucfirst($user->type ?? '—') }}
        @endif
    </div>

    {{-- Bonus/Malus --}}
    <div class="col col-bonusmalus">
        @if(!empty($user->bonusMalus))
            {{ __("global.bonus-malus.$user->bonusMalus") }}
        @else
            —
        @endif
    </div>

    {{-- Műveletek --}}
    <div class="col col-actions">
        <div class="d-flex" style="gap:.25rem;">
            <button class="btn btn-outline-info bonusmalus" data-tippy-content="{{ __('admin/employees.bonusmalus') }}">
                <i class="fa fa-layer-group"></i>
            </button>

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
