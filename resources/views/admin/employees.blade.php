@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('titles.admin.employees') }}</h1>

<div class="mb-2">
    <div class="tile tile-info search-tile" style="margin-bottom:.75rem;">
        <p>{{ $_('search') }}</p>
        <div>
            <input type="text" class="form-control search-input" @if ($users->count() < 5) readonly @endif>
            <i class="fa fa-ban clear-search" data-tippy-content="{{ $_('clear-search') }}"></i>
        </div>
    </div>
    <div class="tile tile-button trigger-new">
        <span><i class="fa fa-user-plus"></i>{{ $_('new-employee') }}</span>
    </div>
    @if(!empty($enableMultiLevel))
        <div class="tile tile-button trigger-new-dept">
            <span><i class="fa fa-sitemap"></i> Új részleg</span>
        </div>
    @endif
</div>

@if(!empty($enableMultiLevel))
    <div class="employees-ml">
        {{-- === CEO + nem besorolt felhasználók === --}}
        <div class="userlist">
            @foreach(collect($ceos)->concat($unassigned) as $user)
                @include('admin.partials.user-row', ['user' => $user])
            @endforeach
            @if(collect($ceos)->concat($unassigned)->isEmpty())
                <div class="muted small px-2 py-1">Nincs megjeleníthető felhasználó ezen a szinten.</div>
            @endif
        </div>

        {{-- === Részlegek listája === --}}
        @foreach($departments as $d)
            <div class="userlist dept-block" data-dept-id="{{ $d->id }}">
                {{-- Fejléc --}}
                <div class="dept-header dept-header--dept js-dept-toggle" data-dept-id="{{ $d->id }}">
                    <div class="left">
                        <i class="fa fa-chevron-right caret"></i>
                        <span class="dept-title">{{ $d->department_name }}</span>
                        <span class="badge count">{{ $d->members->count() }}</span>
                    </div>
                    <div class="actions">
                        <button class="btn btn-outline-success dept-members" data-tippy-content="Tagok kezelése"><i class="fa fa-users"></i></button>
                        <button class="btn btn-outline-primary dept-edit" data-tippy-content="Szerkesztés"><i class="fa fa-pen"></i></button>
                        <button class="btn btn-outline-danger dept-remove" data-tippy-content="{{ $_('remove') }}"><i class="fa fa-trash-alt"></i></button>
                    </div>
                </div>

                {{-- Részleg tartalma (lenyitható) --}}
                <div class="dept-body">
                    {{-- Manager --}}
                    @php
                      $managerUser = (object)[
                        'id'              => $d->manager_id,
                        'name'            => $d->manager_name ?? '— nincs kijelölt manager —',
                        'email'           => $d->manager_email ?? null,
                        'type'            => 'manager',
                        'login_mode_text' => $d->manager_login_mode_text ?? null, // <-- ÚJ
                        'bonusMalus'      => $d->manager_bonusMalus ?? null,      // <-- ÚJ
                      ];
                    @endphp
                    @include('admin.partials.user-row', [
                      'user' => $managerUser,
                      'lockDelete' => !empty($d->manager_id)
                    ])

                    {{-- Tagok --}}
                    @forelse($d->members as $user)
                        @include('admin.partials.user-row', ['user' => $user])
                    @empty
                        <div class="muted small px-2 py-1">Nincs tag a részlegben.</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

@else
    {{-- === RÉGI NÉZET (multi-level OFF) === --}}
    <div class="tile userlist">
        <table class="table table-hover">
            <thead>
                <th>{{ __('global.name') }}</th>
                <th>{{ __('global.email') }}</th>
                <th>{{ __('global.type') }}</th>
                <th>{{ __('global.bonusmalus') }}</th>
                <th>{{ $_('operations') }}</th>
            </thead>
            <tbody>
                @foreach ($users as $user)
                <tr data-id="{{ $user->id }}">
                    <td data-col="{{ __('global.name') }}">
                        <div>{{ $user->name }}</div>
                        <div class="text-muted small">
                            Belépési mód:
                            <span class="login-mode">{{ $user->login_mode_text }}</span>
                        </div>
                    </td>
                    <td data-col="{{ __('global.email') }}">{{ $user->email }}</td>
                    <td data-col="{{ __('global.type') }}">{{ $user->getNameOfType() }}</td>
                    <td data-col="{{ __('global.bonusmalus') }}">{{ __("global.bonus-malus.$user->bonusMalus") }}</td>
                    <td data-col="{{ $_('operations') }}">
                        <div class="d-flex gap-1" style="gap:.25rem;">
                            <button class="btn btn-outline-info bonusmalus" data-tippy-content="{{ $_('bonusmalus') }}">
                                <i class="fa fa-layer-group"></i>
                            </button>
                            <button class="btn btn-outline-success competencies" data-tippy-content="{{ $_('competencies') }}">
                                <i class="fa fa-medal"></i>
                            </button>
                            <button class="btn btn-outline-primary relations" data-tippy-content="{{ $_('relations') }}">
                                <i class="fa fa-network-wired"></i>
                            </button>
                            <button class="btn btn-outline-warning datas" data-tippy-content="{{ $_('datas') }}">
                                <i class="fa fa-user-gear"></i>
                            </button>
                            <button class="btn btn-outline-secondary password-reset" data-tippy-content="Jelszó visszaállító levél küldése (jelszó törlése)">
                                <i class="fa fa-key"></i>
                            </button>
                            <button class="btn btn-outline-danger remove" data-tippy-content="{{ $_('remove') }}">
                                <i class="fa fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
                <tr class="no-employee @if($users->count() != 0) hidden @endif">
                    <td colspan="5">{{ __('global.no-employee') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endif
@endsection

@section('scripts')
    @parent
    @include('admin.modals.employee')
    @include('admin.modals.relations')
    @include('admin.modals.select')
    @include('admin.modals.user-competencies')
    @include('admin.modals.bonusmalus')
    @includeWhen(!empty($enableMultiLevel), 'admin.modals.department')
    @includeWhen(!empty($enableMultiLevel), 'admin.modals.departmentuser')
@endsection