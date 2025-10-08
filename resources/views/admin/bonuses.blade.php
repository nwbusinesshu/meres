@extends('layouts.master')

@section('content')
<div class="container-fluid">
    <h2>{{ __('admin/bonuses.title') }}</h2>

    {{-- Assessment Selector --}}
    <div class="tile">
        <div class="tile-header">
            <h3>{{ __('admin/bonuses.select-assessment') }}</h3>
        </div>
        <div class="tile-body">
            <div class="form-group">
                <label>{{ __('admin/bonuses.assessment-period') }}</label>
                <select id="assessment-selector" class="form-control">
                    @forelse($assessments as $assessment)
                        <option value="{{ $assessment->id }}" 
                            {{ $selectedAssessmentId == $assessment->id ? 'selected' : '' }}>
                            {{ __('admin/bonuses.period-format', [
                                'start' => $assessment->started_at->format('Y-m-d'),
                                'end' => $assessment->closed_at->format('Y-m-d')
                            ]) }}
                        </option>
                    @empty
                        <option value="">{{ __('admin/bonuses.no-closed-assessments') }}</option>
                    @endforelse
                </select>
            </div>
            
            <div class="tile-actions mt-3">
                <button class="btn btn-primary trigger-config-multipliers">
                    <i class="fa fa-cog"></i> {{ __('admin/bonuses.configure-multipliers') }}
                </button>
            </div>
        </div>
    </div>

    @if($selectedAssessmentId)
        {{-- Summary Stats --}}
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="tile stat-card">
                    <div class="stat-value">{{ number_format($totalBonus, 0, ',', ' ') }} HUF</div>
                    <div class="stat-label">{{ __('admin/bonuses.total-bonuses') }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="tile stat-card">
                    <div class="stat-value text-success">{{ $paidCount }}</div>
                    <div class="stat-label">{{ __('admin/bonuses.paid') }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="tile stat-card">
                    <div class="stat-value text-warning">{{ $unpaidCount }}</div>
                    <div class="stat-label">{{ __('admin/bonuses.unpaid') }}</div>
                </div>
            </div>
        </div>

        {{-- Bonuses Table --}}
        <div class="tile">
            <div class="tile-header">
                <h3>{{ __('admin/bonuses.bonus-list') }}</h3>
            </div>
            <div class="tile-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('global.name') }}</th>
                                @if($enableMultiLevel)
                                <th>{{ __('admin/bonuses.department') }}</th>
                                @endif
                                <th>{{ __('admin/bonuses.bonus-malus-level') }}</th>
                                <th>{{ __('admin/bonuses.net-wage') }}</th>
                                <th>{{ __('admin/bonuses.multiplier') }}</th>
                                <th>{{ __('admin/bonuses.bonus-amount') }}</th>
                                <th>{{ __('admin/bonuses.paid') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bonuses as $bonus)
                            <tr>
                                <td>{{ $bonus->user->name }}</td>
                                @if($enableMultiLevel)
                                <td>{{ $bonus->user->department ?? '—' }}</td>
                                @endif
                                <td>
                                    <span class="badge badge-{{ $bonus->bonus_malus_level >= 5 ? 'success' : 'warning' }}">
                                        {{ __('global.bonus-malus.' . $bonus->bonus_malus_level) }}
                                    </span>
                                </td>
                                <td>{{ number_format($bonus->net_wage, 0, ',', ' ') }} {{ $bonus->currency }}</td>
                                <td><strong>{{ $bonus->multiplier }}</strong></td>
                                <td class="text-primary"><strong>{{ number_format($bonus->bonus_amount, 0, ',', ' ') }} {{ $bonus->currency }}</strong></td>
                                <td>
                                    <label class="switch">
                                        <input type="checkbox" 
                                               class="toggle-payment" 
                                               data-bonus-id="{{ $bonus->id }}"
                                               {{ $bonus->is_paid ? 'checked' : '' }}>
                                        <span class="slider round"></span>
                                    </label>
                                    @if($bonus->paid_at)
                                        <small class="text-muted d-block">{{ $bonus->paid_at->format('Y-m-d') }}</small>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $enableMultiLevel ? '7' : '6' }}" class="text-center text-muted">
                                    {{ __('admin/bonuses.no-bonuses') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Modals --}}
@include('admin.modals.bonus-config')
@endsection

@section('scripts')
@endsection