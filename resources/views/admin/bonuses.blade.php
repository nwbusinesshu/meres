{{-- resources/views/admin/bonuses.blade.php --}}
@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('admin/bonuses.title') }}</h1>

@if ($assessment)
    {{-- Period Navigation --}}
    <div class="period-nav">
        <a class="nav-btn {{ $prevAssessment ? '' : 'is-disabled' }}"
           @if($prevAssessment) href="{{ route('admin.bonuses.index', $prevAssessment->id) }}" @endif
           aria-label="{{ __('admin/bonuses.previous-period') }}">
            <i class="fa fa-chevron-left" aria-hidden="true"></i>
        </a>

        <div class="period-chip" title="{{ __('admin/bonuses.assessment-period') }}">
            <i class="fa fa-calendar" aria-hidden="true"></i>
            <span>{{ \Carbon\Carbon::parse($assessment->closed_at)->translatedFormat('Y. m') }}</span>
        </div>

        <a class="nav-btn {{ $nextAssessment ? '' : 'is-disabled' }}"
           @if($nextAssessment) href="{{ route('admin.bonuses.index', $nextAssessment->id) }}" @endif
           aria-label="{{ __('admin/bonuses.next-period') }}">
            <i class="fa fa-chevron-right" aria-hidden="true"></i>
        </a>
    </div>

    {{-- Summary Stats --}}
    <h3>{{ __('admin/bonuses.bonus-summary') }}</h3>
    <div class="stats-grid">
        <div class="stat-card tile tile-info">
            <div class="stat-value">{{ number_format($totalBonus, 0, ',', ' ') }} HUF</div>
            <div class="stat-label">{{ __('admin/bonuses.total-bonuses') }}</div>
        </div>
        <div class="stat-card tile tile-info">
            <div class="stat-value">{{ $paidCount }}</div>
            <div class="stat-label">{{ __('admin/bonuses.paid') }}</div>
        </div>
        <div class="stat-card tile tile-info">
            <div class="stat-value">{{ $unpaidCount }}</div>
            <div class="stat-label">{{ __('admin/bonuses.unpaid') }}</div>
        </div>
    </div>

    {{-- Bonus List Table --}}
    @if($bonuses->isNotEmpty())
    <h3>{{ __('admin/bonuses.bonus-list') }}</h3>
        <div class="bonus-table-container">
            <div class="bonus-table-body">
                <div class="table-responsive">
                    <table class="bonus-table">
                        <thead>
                            <tr>
                                <th>{{ __('admin/bonuses.employee') }}</th>
                                @if($enableMultiLevel)
                                    <th>{{ __('admin/bonuses.department') }}</th>
                                @endif
                                <th>{{ __('admin/bonuses.position') }}</th>
                                <th>{{ __('admin/bonuses.bonus-malus-level') }}</th>
                                <th>{{ __('admin/bonuses.net-wage') }}</th>
                                <th>{{ __('admin/bonuses.multiplier') }}</th>
                                <th>{{ __('admin/bonuses.bonus-amount') }}</th>
                                <th>{{ __('admin/bonuses.payment-status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bonuses as $bonus)
                                <tr>
                                    <td>{{ $bonus->user->name }}</td>
                                    @if($enableMultiLevel)
                                        <td>{{ $bonus->user->department_name ?? '-' }}</td>
                                    @endif
                                    <td>{{ $bonus->user->position ?? '-' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $bonus->bonus_malus_level >= 6 ? 'success' : ($bonus->bonus_malus_level == 5 ? 'warning' : 'danger') }}">
                                            {{ __('global.bonus-malus.' . $bonus->bonus_malus_level) }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($bonus->net_wage, 0, ',', ' ') }} {{ $bonus->currency }}</td>
                                    <td>{{ $bonus->multiplier }}x</td>
                                    <td>
                                        <strong>{{ number_format($bonus->bonus_amount, 0, ',', ' ') }} {{ $bonus->currency }}</strong>
                                    </td>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" 
                                                   class="toggle-payment" 
                                                   data-bonus-id="{{ $bonus->id }}"
                                                   {{ $bonus->is_paid ? 'checked' : '' }}>
                                            <span class="slider"></span>
                                        </label>
                                        <span class="ml-2">{{ $bonus->is_paid ? __('admin/bonuses.paid') : __('admin/bonuses.unpaid') }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="tile tile-info">
            <i class="fa fa-inbox"></i>
            <p>{{ __('admin/bonuses.no-bonuses') }}</p>
        </div>
    @endif
@else
    {{-- No closed assessments --}}
    <div class="tile tile-info">
        <i class="fa fa-exclamation-triangle"></i>
        <strong>{{ __('admin/bonuses.no-closed-assessments') }}</strong>
        <p>{{ __('admin/bonuses.close-assessment-first') }}</p>
    </div>
@endif
@endsection

@section('scripts')
@endsection