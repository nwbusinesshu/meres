@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('payment.title') }}</h1>

{{-- Nyitott tartozások --}}
<div class="tile">
  <h3>{{ __('payment.sections.open') }}</h3>
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
          <td>  @if(!empty($row->created_at))
    {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('Y.m.d') }}
  @else
    <span class="text-muted">-</span>
  @endif
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
            <button class="btn btn-sm btn-primary btn-start-payment"
              data-id="{{ $row->id }}">
              <i class="fa fa-credit-card"></i> {{ __('payment.actions.pay_now') }}
            </button>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-muted">{{ __('payment.empty.open') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- Korábban rendezettek --}}
<div class="tile">
  <h3>{{ __('payment.sections.settled') }}</h3>
  <table class="table table-hover">
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
                 class="btn btn-sm btn-outline-primary" target="_blank">
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
</div>
@endsection

@section('scripts')
  @include('js.admin.payments')
@endsection
