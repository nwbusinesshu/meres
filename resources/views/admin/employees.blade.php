@extends('layouts.master')

@section('head-extra')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
@endsection

@section('content')
<h1>
    {{ __('titles.admin.employees') }}
    
</h1>

{{-- NEW: Employee Limit Warning --}}


<div class="mb-2">
    <div class="tile tile-info search-tile" style="margin-bottom:.75rem;">
        <p>{{ $_('search') }}</p>
        <div>
            <input type="text" class="form-control search-input" @if ($users->count() < 5) readonly @endif>
            <i class="fa fa-ban clear-search" data-tippy-content="{{ $_('clear-search') }}"></i>
        </div>
    </div>
    @if(!$hasClosedAssessment && $employeeLimit)
    <div class="tile tile-{{ $isLimitReached ? 'danger' : 'warning' }}">
        <div>
                    <i class="fa fa-info-circle"></i>

            @if($isLimitReached)
                <strong>{{ __('admin/employees.employee-limit-reached-title') }}</strong>
                <p>{{ __('admin/employees.employee-limit-reached-text', ['limit' => $employeeLimit]) }}</p>
            @else
                <strong>{{ __('admin/employees.employee-limit-warning-title') }}</strong>
                <p>{{ __('admin/employees.employee-limit-warning-text', ['current' => $currentEmployeeCount, 'limit' => $employeeLimit]) }}</p>
            @endif
        </div>
    </div>
@endif
    {{-- Updated: Disable button when limit is reached --}}
    <div class="tile tile-button trigger-new {{ $isLimitReached ? 'disabled' : '' }}" 
         @if($isLimitReached)  
            data-tippy-content="{{ __('admin/employees.employee-limit-reached-tooltip') }}"
         @endif>
        <span><i class="fa fa-user-plus"></i>{{ $_('new-employee') }}</span>
    </div>
    @if(!empty($enableMultiLevel))
        <div class="tile tile-button trigger-new-dept">
            <span><i class="fa fa-sitemap"></i> {{ $_('new-department') }}</span>
        </div>
        <div class="tile tile-button network">
            <span><i class="fa fa-project-diagram"></i> {{ $_('company-network') }}</span>
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
                <div class="tile tile-info">{{ $_('no-users-at-level') }}</div>
            @endif
        </div>

        {{-- === Részlegek listája === --}}
        @foreach($departments as $d)
        <div class="userlist dept-block" data-dept-id="{{ $d->id }}">
            {{-- Fejléc --}}
            <div class="dept-header dept-header--dept " data-dept-id="{{ $d->id }}">
                <div class="left js-dept-toggle">
                    <i class="fa fa-chevron-down caret "></i>
                    <span class="dept-title">{{ $d->department_name }}</span>
                    <span class="badge count">{{ $d->members->count() }}</span>
                    {{-- NEW: Show manager count --}}
                    <span class="badge count-managers" style="background-color: #28a745;">{{ $d->managers->count() }} {{ $_('manager') }}</span>
                </div>
                <div class="actions">
                    <button class="btn btn-outline-success dept-members" data-tippy-content="{{ $_('manage-members') }}"><i class="fa fa-users"></i></button>
                    <button class="btn btn-outline-primary dept-edit" data-tippy-content="{{ $_('edit') }}"><i class="fa fa-pen"></i></button>
                    <button class="btn btn-outline-danger dept-remove" data-tippy-content="{{ $_('remove') }}"><i class="fa fa-trash-alt"></i></button>
                </div>
            </div>

            {{-- Részleg tartalma (lenyitható) --}}
            <div class="dept-body">
                {{-- NEW: Multiple Managers Section --}}
                @if($d->managers->isNotEmpty())
                    <div class="managers-section">
                        <div class="section-header">
                            <h6 class="text-success mb-2">
                                <i class="fa fa-user-tie"></i> {{ $_('managers') }} ({{ $d->managers->count() }})
                            </h6>
                        </div>
                        @foreach($d->managers as $manager)
                            @php
                              $managerUser = (object)[
                                'id'              => $manager->id,
                                'name'            => $manager->name,
                                'email'           => $manager->email,
                                'type'            => 'manager',
                                'login_mode_text' => $manager->login_mode_text ?? '—',
                                'bonusMalus'      => $manager->bonusMalus ?? null,
                                'rater_count'     => $manager->rater_count ?? 0,
                                'position'        => $manager->position ?? '—',
                              ];
                            @endphp
                            <div class="manager-row">
                                @include('admin.partials.user-row', [
                                  'user' => $managerUser,
                                  'lockDelete' => true,
                                  'isMultipleManager' => true,
                                  'assignedAt' => $manager->assigned_at ?? null
                                ])
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="no-managers-warning">
                        <div class="alert alert-warning small py-2">
                            <i class="fa fa-exclamation-triangle"></i> 
                            {{ $_('no-manager-assigned') }}
                        </div>
                    </div>
                @endif

                {{-- Members Section --}}
                @if($d->members->isNotEmpty())
                    <div class="members-section">
                        <div class="section-header">
                            <h6 class="text-primary mb-2">
                                <i class="fa fa-users"></i> {{ $_('members') }} ({{ $d->members->count() }})
                            </h6>
                        </div>
                        @foreach($d->members as $user)
                            @include('admin.partials.user-row', ['user' => $user])
                        @endforeach
                    </div>
                @else
                    <div class="tile tile-info">{{ $_('no-members-in-dept') }}</div>
                @endif
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
                <th>{{ $_('raters') }}</th>
                <th>{{ __('global.type') }}</th>
                @if(!empty($showBonusMalus))
                <th>{{ __('global.bonusmalus') }}</th>
                @endif
                <th>{{ $_('operations') }}</th>
            </thead>
            <tbody>
                @foreach ($users as $user)
                @php
                    $raterCount = $user->rater_count ?? 0;
                    $raterClass = 'rater-sufficient';
                    if ($raterCount < 3) {
                        $raterClass = 'rater-insufficient';
                    } elseif ($raterCount < 7) {
                        $raterClass = 'rater-okay';
                    }
                @endphp
                <tr class="user-row" data-id="{{ $user->id }}">
                    <td data-col="{{ __('global.name') }}">
                        <div>
                            <strong>{{ $user->name }}</strong>
                            @if(!empty($user->email))
                                <br><small class="text-muted">{{ $user->email }}</small>
                            @endif
                        </div>
                    </td>
                    
                    <td data-col="{{ $_('raters') }}">
                        <div class="rater-counter {{ $raterClass }}">
                            {{ $raterCount }}
                        </div>
                    </td>
                    
                    <td data-col="{{ __('global.type') }}">
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
                        @endif@php $pos = data_get($user, 'position'); @endphp
    <br><small>{{ (is_string($pos) ? trim($pos) : $pos) ?: '—' }}</small>
                    </td>
                    
                    @if(!empty($showBonusMalus))
                    <td data-col="{{ __('global.bonusmalus') }}">
                        @if(!empty($user->bonusMalus))
                            {{ __("global.bonus-malus.$user->bonusMalus") }}
                        @else
                            —
                        @endif
                    </td>
                    @endif
                    
                    <td data-col="{{ $_('operations') }}">
                        <div class="d-flex gap-1" style="gap:.25rem;">
                            @if(!empty($showBonusMalus))
                            <button class="btn btn-outline-info bonusmalus" data-tippy-content="{{ $_('bonusmalus') }}">
                                <i class="fa fa-layer-group"></i>
                            </button>
                            @endif
                            <button class="btn btn-outline-success competencies" data-tippy-content="{{ $_('competencies') }}">
                                <i class="fa fa-medal"></i>
                            </button>
                            <button class="btn btn-outline-primary relations" data-tippy-content="{{ $_('relations') }}">
                                <i class="fa fa-network-wired"></i>
                            </button>
                            <button class="btn btn-outline-warning datas" data-tippy-content="{{ $_('datas') }}">
                                <i class="fa fa-user-gear"></i>
                            </button>
                            @if(!empty($user->is_locked))
                                <button class="btn btn-outline-danger unlock-account" data-tippy-content="{{ __('admin/employees.unlock-account-tooltip') }}">
                                    <i class="fa fa-lock"></i>
                                </button>
                            @else
                                <button class="btn btn-outline-secondary password-reset" data-tippy-content="{{ __('admin/employees.password-reset') }}">
                                    <i class="fa fa-key"></i>
                                </button>
                            @endif
                            <button class="btn btn-outline-danger remove" data-tippy-content="{{ $_('remove') }}">
                                <i class="fa fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
                <tr class="no-employee @if($users->count() != 0) hidden @endif">
                    <td colspan="{{ !empty($showBonusMalus) ? '5' : '4' }}">{{ __('global.no-employee') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endif
@endsection

@section('scripts')
    @include('admin.modals.employee')
    @include('admin.modals.relations')
    @include('admin.modals.select')
    @include('admin.modals.user-competencies')
    @include('admin.modals.bonusmalus')
    @include('admin.modals.network')
    @include('admin.modals.employee-import')
    @includeWhen(!empty($enableMultiLevel), 'admin.modals.department')
    @includeWhen(!empty($enableMultiLevel), 'admin.modals.departmentuser')
@endsection