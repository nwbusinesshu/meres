@extends('layouts.master')

@section('head-extra')
  {{-- maradhat üresen, vagy tehetsz ide oldalspecifikus CSS-t --}}
@endsection

@section('content')
<h1>{{ __('titles.admin.employees') }}</h1>

<div>
  <div class="tile tile-info search-tile">
    <p>{{ $_('search') }}</p>
    <div>
      <input type="text" class="form-control search-input" @if ($users->count() < 5) readonly @endif>
      <i class="fa fa-ban clear-search" data-tippy-content="{{ $_('clear-search') }}"></i>
    </div>
  </div>

  <div class="tile tile-button trigger-new">
    <span><i class="fa fa-user-plus"></i>{{ $_('new-employee') }}</span>
  </div>
</div>

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

              {{-- ÚJ: Jelszó visszaállítás --}}
              <button class="btn btn-outline-secondary password-reset"
                      data-tippy-content="Jelszó visszaállító levél küldése (jelszó törlése)">
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
@endsection

@section('scripts')
  @parent
  @include('admin.modals.employee')
  @include('admin.modals.relations')
  @include('admin.modals.select')
  @include('admin.modals.user-competencies')
  @include('admin.modals.bonusmalus')

@endsection
