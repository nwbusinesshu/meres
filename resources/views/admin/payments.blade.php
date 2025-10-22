@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('payment.title') }}</h1>

{{-- Billing Data Button --}}
<div class="mb-3">
  <button class="btn btn-outline-primary btn-sm" id="open-billing-data-modal">
    <i class="fa fa-building"></i> {{ __('payment.billing_data.button') }}
  </button>
</div>
<h3>{{ __('payment.sections.open') }}</h3>

{{-- Nyitott tartozások --}}
<div class="tile">
  
  
  {{-- Desktop Table --}}
  <table class="table table-hover payment-table">
    <thead>
      <tr>
        <th>{{ __('payment.columns.created_date') }}</th>
        <th>{{ __('payment.columns.due_date') }}</th>
        <th class="text-right">{{ __('payment.columns.amount') }}</th>
        <th>{{ __('payment.columns.status') }}</th>
        <th>{{ __('payment.columns.actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($open as $row)
        <tr data-id="{{ $row->id }}">
          {{-- Létrehozás dátuma: év.hó.nap --}}
          <td>
            @if(!empty($row->created_at))
              {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('Y.m.d') }}
            @else
              <span class="text-muted">-</span>
            @endif
          </td>
          
          {{-- Esedékesség dátuma --}}
          <td>
            @if(!empty($row->due_at))
              {{ \Illuminate\Support\Carbon::parse($row->due_at)->format('Y.m.d') }}
            @else
              <span class="text-muted">-</span>
            @endif
          </td>

          {{-- Összeg --}}
          <td class="text-right">
            {{ number_format((int)$row->amount_huf, 0, ',', ' ') }} Ft
          </td>

          {{-- Státusz (HU, színezve) --}}
          <td>
            @php
              $label = $row->status === 'pending' ? __('payment.status.pending')
                      : ($row->status === 'failed' ? __('payment.status.failed') : $row->status);
              $cls = $row->status === 'pending' ? 'badge badge-warning'
                   : ($row->status === 'failed' ? 'badge badge-danger' : 'badge badge-secondary');
            @endphp
            <span class="{{ $cls }}">{{ $label }}</span>
          </td>

          {{-- Művelet: fizetés indítása --}}
          <td>
            @if(!empty($row->is_blocked) && $row->is_blocked)
              <button class="btn btn-sm btn-secondary btn-start-payment"
                data-id="{{ $row->id }}"
                data-blocked="true"
                data-remaining-minutes="{{ $row->remaining_minutes ?? 0 }}"
                title="{{ __('payment.swal.payment_blocked_text') }}">
                <i class="fa fa-clock"></i> {{ __('payment.actions.pay_now') }}
              </button>
            @else
              <button class="btn btn-primary btn-start-payment"
                data-id="{{ $row->id }}"
                data-blocked="false">
                <i class="fa fa-credit-card"></i> {{ __('payment.actions.pay_now') }}
              </button>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-muted">{{ __('payment.empty.open') }}</td></tr>
      @endforelse
    </tbody>
  </table>

  {{-- Mobile Cards --}}
  <div class="payment-mobile-cards">
    @forelse ($open as $row)
      <div class="payment-card">
        <div class="payment-card-header">
          <div class="payment-card-amount">
            {{ number_format((int)$row->amount_huf, 0, ',', ' ') }} Ft
          </div>
          <div>
            @php
              $label = $row->status === 'pending' ? __('payment.status.pending')
                      : ($row->status === 'failed' ? __('payment.status.failed') : $row->status);
              $cls = $row->status === 'pending' ? 'badge badge-warning'
                   : ($row->status === 'failed' ? 'badge badge-danger' : 'badge badge-secondary');
            @endphp
            <span class="{{ $cls }}">{{ $label }}</span>
          </div>
        </div>
        
        <div class="payment-card-details">
          <div class="payment-card-row">
            <span class="payment-card-label">{{ __('payment.columns.created_date') }}:</span>
            <span class="payment-card-value">
              @if(!empty($row->created_at))
                {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('Y.m.d') }}
              @else
                <span class="text-muted">-</span>
              @endif
            </span>
          </div>
          
          <div class="payment-card-row">
            <span class="payment-card-label">{{ __('payment.columns.due_date') }}:</span>
            <span class="payment-card-value">
              @if(!empty($row->due_at))
                {{ \Illuminate\Support\Carbon::parse($row->due_at)->format('Y.m.d') }}
              @else
                <span class="text-muted">-</span>
              @endif
            </span>
          </div>
        </div>
        
        <div class="payment-card-actions">
          @if(!empty($row->is_blocked) && $row->is_blocked)
            <button class="btn btn-secondary btn-start-payment"
              data-id="{{ $row->id }}"
              data-blocked="true"
              data-remaining-minutes="{{ $row->remaining_minutes ?? 0 }}"
              title="{{ __('payment.swal.payment_blocked_text') }}">
              <i class="fa fa-clock"></i> {{ __('payment.actions.pay_now') }}
            </button>
          @else
            <button class="btn btn-primary btn-start-payment"
              data-id="{{ $row->id }}"
              data-blocked="false">
              <i class="fa fa-credit-card"></i> {{ __('payment.actions.pay_now') }}
            </button>
          @endif
        </div>
      </div>
    @empty
      <div class="payment-empty-mobile">
        {{ __('payment.empty.open') }}
      </div>
    @endforelse
  </div>
</div>

{{-- Korábban rendezettek --}}
<h3>{{ __('payment.sections.settled') }}</h3>
<div class="tile">
  
  
  {{-- Desktop Table --}}
  <table class="table table-hover payment-table">
    <thead>
      <tr>
        <th>{{ __('payment.columns.issue_date') }}</th>
        <th>{{ __('payment.columns.payment_date') }}</th>
        <th>{{ __('payment.columns.invoice_number') }}</th>
        <th class="text-right">{{ __('payment.columns.amount') }}</th>
        <th>{{ __('payment.columns.actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($settled as $row)
        <tr>
          {{-- Kiállítás dátuma (payments.billingo_issue_date) --}}
          <td>
            @if(!empty($row->billingo_issue_date))
              {{ \Illuminate\Support\Carbon::parse($row->billingo_issue_date)->format('Y.m.d') }}
            @else
              <span class="text-muted">-</span>
            @endif
          </td>

          {{-- Fizetés dátuma (payments.paid_at) --}}
          <td>
            @if(!empty($row->paid_at))
              {{ \Carbon\Carbon::parse($row->paid_at)->format('Y.m.d') }}
            @else
              <span class="text-muted">-</span>
            @endif
          </td>

          {{-- Számlaszám --}}
          <td>{{ $row->billingo_invoice_number ?? '—' }}</td>

          {{-- Összeg --}}
          <td class="text-right">
            {{ number_format((int)$row->amount_huf, 0, ',', ' ') }} Ft
          </td>

          {{-- Művelet: letöltés --}}
          <td>
            @if(!empty($row->billingo_document_id))
              <a href="{{ route('admin.payments.invoice', $row->id) }}"
                 class="btn btn-sm btn-outline-primary no-loader"
                 target="_blank">
                <i class="fa fa-file-pdf"></i> {{ __('payment.actions.download_invoice') }}
              </a>
            @else
              <span class="text-muted">{{ __('payment.invoice.processing') }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-muted">{{ __('payment.empty.settled') }}</td></tr>
      @endforelse
    </tbody>
  </table>

  {{-- Mobile Cards --}}
  <div class="payment-mobile-cards">
    @forelse ($settled as $row)
      <div class="payment-card">
        <div class="payment-card-header">
          <div class="payment-card-amount">
            {{ number_format((int)$row->amount_huf, 0, ',', ' ') }} Ft
          </div>
          <div>
            <span class="badge badge-success">{{ __('payment.status.paid') }}</span>
          </div>
        </div>
        
        <div class="payment-card-details">
          <div class="payment-card-row">
            <span class="payment-card-label">{{ __('payment.columns.issue_date') }}:</span>
            <span class="payment-card-value">
              @if(!empty($row->billingo_issue_date))
                {{ \Illuminate\Support\Carbon::parse($row->billingo_issue_date)->format('Y.m.d') }}
              @else
                <span class="text-muted">-</span>
              @endif
            </span>
          </div>
          
          <div class="payment-card-row">
            <span class="payment-card-label">{{ __('payment.columns.payment_date') }}:</span>
            <span class="payment-card-value">
              @if(!empty($row->paid_at))
                {{ \Carbon\Carbon::parse($row->paid_at)->format('Y.m.d') }}
              @else
                <span class="text-muted">-</span>
              @endif
            </span>
          </div>
          
          @if($row->billingo_invoice_number)
          <div class="payment-card-row">
            <span class="payment-card-label">{{ __('payment.columns.invoice_number') }}:</span>
            <span class="payment-card-value">{{ $row->billingo_invoice_number }}</span>
          </div>
          @endif
        </div>
        
        <div class="payment-card-actions">
          @if(!empty($row->billingo_document_id))
            <a href="{{ route('admin.payments.invoice', $row->id) }}"
               class="btn btn-outline-primary" target="_blank">
              <i class="fa fa-file-pdf"></i> {{ __('payment.actions.download_invoice') }}
            </a>
          @else
            <div class="text-muted text-center py-2">{{ __('payment.invoice.processing') }}</div>
          @endif
        </div>
      </div>
    @empty
      <div class="payment-empty-mobile">
        {{ __('payment.empty.settled') }}
      </div>
    @endforelse
  </div>
</div>
@endsection

@section('scripts')
@include('admin.modals.billing-data')
@endsection