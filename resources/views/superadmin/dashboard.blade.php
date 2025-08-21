@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('titles.superadmin.dashboard') }}</h1>

<div>
  <div class="tile tile-info search-tile">
    <p>{{ __('global.search') }}</p>
    <div>
      <input type="text" class="form-control search-input" @if ($organizations->count() < 5) readonly @endif>
      <i class="fa fa-ban clear-search" data-tippy-content="{{ __('global.clear-search') }}"></i>
    </div>
  </div>
  <div class="tile tile-button trigger-new">
    <span><i class="fa fa-plus"></i>{{ __('titles.superadmin.new-org') }}</span>
  </div>
</div>

<div class="tile orglist">
  <table class="table table-hover org-info-table">
    <thead>
      <tr>
        <th>{{ __('global.name') }}</th>
        <th>{{ __('global.admin') }}</th>
        <th>{{ __('global.profile') }}</th>
        <th>{{ __('global.employees') }}</th>
        <th>{{ __('global.created') }}</th>
        <th>{{ __('global.actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($organizations as $org)
        <tr data-id="{{ $org->id }}">
          <td data-col="{{ __('global.name') }}">
            <strong>{{ $org->name }}</strong>
          </td>

          <td data-col="{{ __('global.admin') }}">
            @if($org->admin_name)
              <strong>{{ $org->admin_name }}</strong><br>
              <small>{{ $org->admin_email }}</small>
            @else
              <span class="text-muted">-</span>
            @endif
          </td>

          <td data-col="{{ __('global.profile') }}">
            @if ($org->profile)
              <div class="profile-block"><span class="profile-label">Adószám:</span> {{ $org->profile->tax_number ?? '-' }}</div>
              <div class="profile-block"><span class="profile-label">Számlázási cím:</span> {{ $org->profile->billing_address ?? '-' }}</div>
              <div class="profile-block"><span class="profile-label">Előfizetés:</span> {{ ucfirst($org->profile->subscription_type ?? '-') }}</div>
            @else
              <span class="text-muted">Nincs megadva</span>
            @endif
          </td>

          <td data-col="{{ __('global.employees') }}">{{ $org->employee_count }}</td>

          <td data-col="{{ __('global.created') }}">{{ $org->created_at->format('Y-m-d') }}</td>

          <td data-col="{{ __('global.actions') }}" class="org-actions">
            <button class="btn btn-outline-primary edit-org" data-tippy-content="{{ __('global.edit') }}">
              <i class="fa fa-pen"></i>
            </button>
            <button class="btn btn-outline-danger remove-org" data-tippy-content="{{ __('global.delete') }}">
              <i class="fa fa-trash"></i>
            </button>
          </td>
        </tr>
      @endforeach

      <tr class="no-org @if($organizations->count() != 0) hidden @endif">
        <td colspan="6">{{ __('global.no-org') }}</td>
      </tr>
    </tbody>
  </table>
</div>

@include('superadmin.modals.org-create')

@endsection

@foreach ($organizations as $org)
  @php
    $admin = (object)[
      'name' => $org->admin_name ?? '',
      'email' => $org->admin_email ?? '',
    ];
  @endphp
  @include('superadmin.modals.org-edit', compact('org', 'admin'))
@endforeach
