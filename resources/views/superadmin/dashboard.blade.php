@extends('layouts.master')

@section('head-extra')
<link rel="stylesheet" href="{{ asset('assets/css/pages/superadmin.dashboard.css') }}">
@endsection

@section('content')
<h1>{{ __('titles.superadmin.dashboard') }}</h1>

{{-- Maintenance Mode Toggle - Compact Version --}}
<div class="tile maintenance-toggle-tile">
    <div class="maintenance-content">
        <div class="maintenance-info">
            <h4><i class="fa fa-wrench"></i> {{ __('maintenance.toggle-title') }}</h4>
            <p>{{ __('maintenance.toggle-description') }}</p>
        </div>
        
        <div class="maintenance-controls">
            <div class="status-indicator">
                <span class="status-label">{{ __('global.status') }}:</span>
                <span id="maintenance-status-text" class="status-badge">
                    <i class="fa fa-spinner fa-spin"></i> <span>{{ __('global.loading') }}...</span>
                </span>
            </div>
            
            <button type="button" 
                    id="btn-toggle-maintenance" 
                    class="btn maintenance-toggle-btn"
                    disabled>
                <i class="fa fa-spinner fa-spin"></i>
                <span class="btn-text">{{ __('global.loading') }}...</span>
            </button>
        </div>
    </div>
</div>

<style>
.maintenance-toggle-tile {
    margin-bottom: 1.5rem;
    background: var(--white);
    border-radius: 8px;
    padding: 1rem 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.maintenance-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
}

.maintenance-info {
    flex: 1;
}

.maintenance-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
    color: var(--mine_shaft);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.maintenance-info p {
    margin: 0;
    color: var(--silver_chalice);
    font-size: 0.875rem;
}

.maintenance-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-label {
    font-weight: 600;
    color: var(--mine_shaft);
    font-size: 0.9rem;
}

.status-badge {
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}

.status-badge.status-enabled {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.status-disabled {
    background: #d1fae5;
    color: #065f46;
}

.maintenance-toggle-btn {
    min-width: 140px;
    font-weight: 600;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}

.maintenance-toggle-btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.maintenance-toggle-btn.btn-enable {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    border: none;
}

.maintenance-toggle-btn.btn-enable:hover:not(:disabled) {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(245, 158, 11, 0.3);
}

.maintenance-toggle-btn.btn-disable {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
}

.maintenance-toggle-btn.btn-disable:hover:not(:disabled) {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(16, 185, 129, 0.3);
}

@media (max-width: 992px) {
    .maintenance-content {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .maintenance-controls {
        justify-content: space-between;
    }
}

@media (max-width: 576px) {
    .maintenance-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    
    .maintenance-toggle-btn {
        width: 100%;
    }
}
</style>

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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusText = document.getElementById('maintenance-status-text');
    const toggleBtn = document.getElementById('btn-toggle-maintenance');
    
    if (!statusText || !toggleBtn) {
        return; // Elements not found
    }

    const T = {
        loading: @json(__('global.loading')),
        enabled: @json(__('maintenance.enabled-success')),
        disabled: @json(__('maintenance.disabled-success')),
        error: @json(__('maintenance.toggle-error')),
        confirm_enable_title: @json(__('maintenance.confirm-enable-title')),
        confirm_enable_text: @json(__('maintenance.confirm-enable-text')),
        confirm_disable_title: @json(__('maintenance.confirm-disable-title')),
        confirm_disable_text: @json(__('maintenance.confirm-disable-text')),
        enable_button: @json(__('maintenance.enable-button')),
        disable_button: @json(__('maintenance.disable-button')),
        yes: @json(__('global.swal-confirm')),
        no: @json(__('global.swal-cancel')),
        status_enabled: @json(__('maintenance.banner-title')),
        status_disabled: 'ACTIVE'
    };

    let currentStatus = null;

    // Fetch current status
    function fetchStatus() {
        toggleBtn.disabled = true;
        statusText.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <span>' + T.loading + '...</span>';

        fetch('{{ route("superadmin.maintenance.status") }}', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                currentStatus = data.is_down;
                updateUI(data.is_down);
            }
        })
        .catch(error => {
            console.error('Error fetching maintenance status:', error);
            statusText.innerHTML = '<i class="fa fa-exclamation-triangle"></i> <span>Error</span>';
            toggleBtn.disabled = false;
        });
    }

    // Update UI based on status
    function updateUI(isDown) {
        // Update status badge
        statusText.className = 'status-badge ' + (isDown ? 'status-enabled' : 'status-disabled');
        statusText.innerHTML = isDown ? 
            '<i class="fa fa-wrench"></i> <span>' + T.status_enabled + '</span>' : 
            '<i class="fa fa-check-circle"></i> <span>' + T.status_disabled + '</span>';

        // Update button
        toggleBtn.disabled = false;
        toggleBtn.className = 'btn maintenance-toggle-btn ' + (isDown ? 'btn-disable' : 'btn-enable');
        toggleBtn.innerHTML = '<i class="fa fa-power-off"></i> <span class="btn-text">' + 
            (isDown ? T.disable_button : T.enable_button) + '</span>';
    }

    // Toggle maintenance mode
    function toggleMaintenance() {
        const isDown = currentStatus;
        
        // Use Swal (capital S) - SweetAlert2
        Swal.fire({
            title: isDown ? T.confirm_disable_title : T.confirm_enable_title,
            text: isDown ? T.confirm_disable_text : T.confirm_enable_text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: T.yes,
            cancelButtonText: T.no,
            confirmButtonColor: COLOR_SUCCESS,
            cancelButtonColor: COLOR_SECONDARY,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                performToggle();
            }
        });
    }

    // Perform the actual toggle
    function performToggle() {
        toggleBtn.disabled = true;
        toggleBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <span class="btn-text">' + T.loading + '...</span>';

        fetch('{{ route("superadmin.maintenance.toggle") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                currentStatus = data.status === 'enabled';
                updateUI(currentStatus);
                
                toast('success', data.message);
                
                // Refresh page after a short delay to show updated banner
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                toast('error', data.message || T.error);
                toggleBtn.disabled = false;
                updateUI(currentStatus);
            }
        })
        .catch(error => {
            console.error('Error toggling maintenance mode:', error);
            toast('error', T.error);
            toggleBtn.disabled = false;
            updateUI(currentStatus);
        });
    }

    // Attach event listener
    toggleBtn.addEventListener('click', toggleMaintenance);

    // Initial status fetch
    fetchStatus();
});
</script>
@endsection