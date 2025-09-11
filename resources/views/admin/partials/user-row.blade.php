<div class="user-row {{ $extraClass ?? '' }}" data-id="{{ $user->id }}">
    <div class="col col-name">
        <div>{{ $user->name }}</div>
        @if(!empty($user->email))
            <div class="text-muted small">
                Belépési mód:
                <span class="login-mode">{{ $user->login_mode_text ?? '—' }}</span>
            </div>
        @endif
    </div>
    <div class="col col-email">{{ $user->email ?? '—' }}</div>
    <div class="col col-type">{{ method_exists($user, 'getNameOfType') ? $user->getNameOfType() : ucfirst($user->type) }}</div>
    {{-- A hiba kijavítása: ellenőrizzük, hogy létezik-e a bonusMalus property --}}
    <div class="col col-bonusmalus">
        @if(isset($user->bonusMalus))
            {{ __("global.bonus-malus.$user->bonusMalus") }}
        @else
            —
        @endif
    </div>
    <div class="col col-actions">
        <div class="d-flex" style="gap:.25rem;">
            <button class="btn btn-outline-info bonusmalus" data-tippy-content="{{ $_('bonusmalus') }}"><i class="fa fa-layer-group"></i></button>
            <button class="btn btn-outline-success competencies" data-tippy-content="{{ $_('competencies') }}"><i class="fa fa-medal"></i></button>
            <button class="btn btn-outline-primary relations" data-tippy-content="{{ $_('relations') }}"><i class="fa fa-network-wired"></i></button>
            <button class="btn btn-outline-warning datas" data-tippy-content="{{ $_('datas') }}"><i class="fa fa-user-gear"></i></button>
            <button class="btn btn-outline-secondary password-reset" data-tippy-content="Jelszó visszaállítás"><i class="fa fa-key"></i></button>
            @php $deleteDisabled = !empty($lockDelete); @endphp
            <button class="btn btn-outline-danger remove {{ $deleteDisabled ? 'disabled' : '' }}"
                    {{ $deleteDisabled ? 'disabled' : '' }}
                    data-tippy-content="{{ $deleteDisabled ? 'Előbb állítsd át a részleg vezetőjét.' : $_('remove') }}">
                <i class="fa fa-trash-alt"></i>
            </button>
        </div>
    </div>
</div>