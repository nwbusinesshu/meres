@extends('layouts.master')

@section('head-extra')
<link rel="stylesheet" href="{{ asset('assets/css/pages/superadmin.dashboard.css') }}">
@endsection

@section('content')
<h1>{{ __('titles.superadmin.dashboard') }}</h1>

{{-- Global Pricing Settings --}}
<div class="mb-4">
  <div class="tile tile-info pricing-tile">
    <div class="pricing-header">
      <h5><i class="fa fa-coins"></i> Globális Ár Beállítások</h5>
    </div>
    <form id="pricing-form" class="pricing-form">
      @csrf
      <div class="row">
        <div class="col-md-5">
          <div class="form-group">
            <label for="global_price_huf">Alap Ár (HUF)</label>
            <div class="input-group">
              <input type="number" step="0.01" class="form-control" id="global_price_huf" name="global_price_huf" required>
              <div class="input-group-append">
                <span class="input-group-text">HUF</span>
              </div>
            </div>
            <small class="form-text text-muted">Magyarországi szervezetek számára</small>
          </div>
        </div>
        <div class="col-md-5">
          <div class="form-group">
            <label for="global_price_eur">Alap Ár (EUR)</label>
            <div class="input-group">
              <input type="number" step="0.01" class="form-control" id="global_price_eur" name="global_price_eur" required>
              <div class="input-group-append">
                <span class="input-group-text">EUR</span>
              </div>
            </div>
            <small class="form-text text-muted">Magyarországon kívüli szervezetek számára</small>
          </div>
        </div>
        <div class="col-md-2 d-flex align-items-center">
          <button type="submit" class="btn btn-primary btn-block" style="margin-top: 8px;">
            <i class="fa fa-save"></i> Mentés
          </button>
        </div>
      </div>
    </form>
  </div>
</div>


<div>
  <div class="tile tile-info search-tile org-search">
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
              @php
                $cc     = $org->profile->country_code ?? null;
                $pc     = $org->profile->postal_code ?? null;
                $city   = $org->profile->city ?? null;
                $region = $org->profile->region ?? null;
                $street = $org->profile->street ?? null;
                $house  = $org->profile->house_number ?? null;

                // Address assembly: "HU-6500 Baja, Dózsa György u. 12"
                $addressCode = '';
                  if ($cc && $pc) {
                      $addressCode = $cc . '-' . $pc;
                  } elseif ($pc) {
                      $addressCode = $pc;
                  }
                $line1 = trim(implode(' ', array_filter([$addressCode, $city])));
                $line2 = trim(implode(' ', array_filter([$street, $house])));
                $fullAddress = trim(implode(', ', array_filter([$line1, $line2])));
                if ($region) { $fullAddress .= ' (' . $region . ')'; }
              @endphp

              <div class="profile-block">
                <span class="profile-label">{{ __('global.tax-number-label') }}</span>
                {{ $org->profile->tax_number ?: '-' }}
              </div>

              <div class="profile-block">
                <span class="profile-label">{{ __('global.eu-vat-number-label') }}</span>
                {{ $org->profile->eu_vat_number ?: '-' }}
              </div>

              <div class="profile-block">
                <span class="profile-label">{{ __('global.billing-address-label') }}</span>
                {{ $fullAddress !== '' ? $fullAddress : '-' }}
              </div>

              <div class="profile-block">
                <span class="profile-label">{{ __('global.subscription-label') }}</span>
                {{ $org->profile->subscription_type ? ucfirst($org->profile->subscription_type) : '-' }}
              </div>
            @else
              <span class="text-muted">{{ __('global.not-provided') }}</span>
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

@endsection

@section('modals')
@include('superadmin.modals.org-create')

@foreach ($organizations as $org)
  @php
    $admin = (object)[
      'name' => $org->admin_name ?? '',
      'email' => $org->admin_email ?? '',
    ];
  @endphp
  @include('superadmin.modals.org-edit', compact('org', 'admin'))
@endforeach
@endsection

@section('scripts')
{{-- Pricing Form Script --}}
<script>
// Load current pricing on page load
$(document).ready(function() {
  $.get('{{ route('superadmin.pricing.get') }}')
    .done(function(response) {
      if (response.success && response.data) {
        $('#global_price_huf').val(response.data.global_price_huf);
        $('#global_price_eur').val(response.data.global_price_eur);
      }
    })
    .fail(function() {
      console.error('Failed to load pricing data');
    });
});

// Handle pricing form submission
$('#pricing-form').on('submit', function(e) {
  e.preventDefault();
  
  const data = {
    _token: '{{ csrf_token() }}',
    global_price_huf: $('#global_price_huf').val(),
    global_price_eur: $('#global_price_eur').val()
  };
  
  $.post('{{ route('superadmin.pricing.update') }}', data)
    .done(function(response) {
      if (response.success) {
        alert(response.message || 'Árak sikeresen frissítve!');
      }
    })
    .fail(function(xhr) {
      const errors = (xhr.responseJSON && xhr.responseJSON.errors) ? xhr.responseJSON.errors : null;
      let msg = '';
      if (errors) {
        for (let field in errors) { msg += errors[field][0] + '\n'; }
      }
      alert(msg || 'Hiba történt az árak mentése során.');
    });
});
</script>
@endsection