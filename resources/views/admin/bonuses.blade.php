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
            {{ $assessment->started_at->format('Y-m-d') }} - {{ $assessment->closed_at->format('Y-m-d') }}
                        </option>
                    @empty
                        <option value="">{{ __('admin/bonuses.no-closed-assessments') }}</option>
                    @endforelse
                </select>
            </div>
            
            {{-- ✅ REMOVED: Configure multipliers button moved to settings page --}}
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
                    <div class="stat-value">{{ $paidCount }}</div>
                    <div class="stat-label">{{ __('admin/bonuses.paid') }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="tile stat-card">
                    <div class="stat-value">{{ $unpaidCount }}</div>
                    <div class="stat-label">{{ __('admin/bonuses.unpaid') }}</div>
                </div>
            </div>
        </div>

        {{-- Bonus List Table --}}
        <div class="tile">
            <div class="tile-header">
                <h3>{{ __('admin/bonuses.bonus-list') }}</h3>
            </div>
            <div class="tile-body">
                <div class="table-responsive">
                    <table class="table table-hover">
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
                            @forelse($bonuses as $bonus)
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
                            @empty
                                <tr>
                                    <td colspan="{{ $enableMultiLevel ? '8' : '7' }}" class="text-center text-muted">
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

{{-- ✅ REMOVED: Modal include - now in settings page --}}
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Assessment selector change
    $('#assessment-selector').on('change', function() {
        const assessmentId = $(this).val();
        if (assessmentId) {
            window.location.href = "{{ route('admin.bonuses.index') }}?assessment_id=" + assessmentId;
        }
    });

    // ✅ REMOVED: Configure multipliers button handler - moved to settings

    // Toggle payment status
    $('.toggle-payment').on('change', function() {
        const bonusId = $(this).data('bonus-id');
        const isPaid = $(this).is(':checked');
        
        $.ajax({
            url: "{{ route('admin.bonuses.payment.toggle') }}",
            method: 'POST',
            data: {
                bonus_id: bonusId,
                is_paid: isPaid ? 1 : 0,
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.ok) {
                    // Reload to update stats
                    window.location.reload();
                }
            },
            error: function() {
                // Revert checkbox on error
                $(this).prop('checked', !isPaid);
                Swal.fire('Error', '{{ __('admin/bonuses.payment-update-error') }}', 'error');
            }
        });
    });
});
</script>
@endsection