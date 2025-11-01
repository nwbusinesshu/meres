@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
{{-- 
    ADD THIS CODE AT THE TOP OF resources/views/admin/home.blade.php 
    Right after @extends and @section('content')
--}}

@php
    // Check for unpaid initial payment
    $unpaidInitialPayment = DB::table('payments')
        ->where('organization_id', session('org_id'))
        ->whereNull('assessment_id')
        ->where('status', '!=', 'paid')
        ->first();

    $showTrialBanner = false;
    $trialExpired = false;
    $remainingTime = '';
    $trialEndsAt = null;

    if ($unpaidInitialPayment) {
        $paymentCreatedAt = \Carbon\Carbon::parse($unpaidInitialPayment->created_at);
        $trialEndsAt = $paymentCreatedAt->copy()->addDays(5);
        $now = now();

        if ($now->lessThan($trialEndsAt)) {
            // Trial is active
            $showTrialBanner = true;
            $diff = $now->diff($trialEndsAt);
            
            if ($diff->days > 0) {
                $remainingTime = __('payment.trial.days_remaining', ['days' => $diff->days]);
            } else {
                $remainingTime = __('payment.trial.hours_remaining', ['hours' => $diff->h]);
            }
        } else {
            // Trial expired
            $trialExpired = true;
        }
    }
@endphp

@if($showTrialBanner)
<div class="alert alert-warning alert-dismissible fade show" role="alert" style="border-left: 4px solid #ffc107;">
    <div class="d-flex align-items-center">
        <i class="fas fa-clock fa-2x mr-3"></i>
        <div class="flex-grow-1">
            <h5 class="alert-heading mb-1">
                <i class="fas fa-hourglass-half"></i> {{ __('payment.trial.active_title') }}
            </h5>
            <p class="mb-2">{{ __('payment.trial.active_message', ['days' => 5]) }}</p>
            <p class="mb-0">
                <strong>{{ $remainingTime }}</strong> | 
                <a href="{{ route('admin.payments.index') }}" class="alert-link">
                    {{ __('payment.trial.pay_now') }} <i class="fas fa-arrow-right"></i>
                </a>
            </p>
        </div>
    </div>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif
<div class="double-tiles">
  @if (!is_null($assessment))
    <div class="tile tile-warning">
      <p>{{ $_('assessment-running') }}</p>
      <p>{{ formatDateTime($assessment->started_at) }} - {{ formatDateTime($assessment->due_at) }}</p>
      @if($hasOpenPayments)
    <a href="{{ route('admin.payments.index') }}" class="btn btn-warning">
        {{ $_('pay-before-closing') }}
    </a>
@else
    <button class="btn {{ $assessed == $neededAssessment ? "btn-success" : "btn-outline-danger" }} close-assessment"
            data-id="{{ $assessment->id }}">
        {{ $_('close-assessment') }}
    </button>
@endif
    </div>
    <div class="tile tile-button modify-assessment" data-id="{{ $assessment->id }}">
      <span><i class="fa fa-file-pen"></i>{{ $_('change-due') }}</span>
    </div>
  @else
  <div class="tile tile-info">
    <p>{{ $_('no-assessment-running') }}</p>
  </div>    
  <div class="tile tile-button create-assessment">
    <span><i class="fa fa-file-pen"></i>{{ $_('create-assessment') }}</span>
  </div>
  @endif
</div>

@if (is_null($assessment))
<div class="tile tile-empty-info">
  <img src="{{ asset('assets/img/monster-info-tile.svg') }}" alt="No assessment" class="empty-tile-monster">
  <div class="empty-tile-text">
    <p class="empty-tile-title">{{ $_('no-assessment-running') }}</p>
    <p class="empty-tile-subtitle">{{ $_('no-assessment-running-info') }}</p>
    <p class="empty-tile-tasks">{!! $_('no-assessment-running-tasks') !!}</p>
  </div>
</div>
@endif

@if (!is_null($assessment))
<div class="double-tiles stats">
  {{-- Assessment stat tile with progress bar --}}
  <div class="tile {{ $assessed == $neededAssessment ? "tile-success" : "tile-info" }} tile-with-progress">
    <p>{{ $_('assessment-stat') }}</p>
    <p>
      <span>{{ $assessed }}</span>/<span>{{ $neededAssessment }}</span>
    </p>
    {{-- ✅ FIXED: Progress bar with percentage positioned correctly --}}
    <div class="tile-progress-wrapper">
      <div class="tile-progress-bar {{ $assessed == $neededAssessment ? "progress-success" : "progress-info" }}" 
           style="--progress: {{ $assessmentPercent }}%"></div>
      <span class="tile-progress-percent">{{ $assessmentPercent }}%</span>
    </div>
  </div>

  {{-- CEO rank stat tile with progress bar --}}
  <div class="tile {{ $ceoRanks == $neededCeoRanks ? "tile-success" : "tile-info" }} tile-with-progress">
    <p>{{ $_('ceo-stat') }}</p>
    <p>
      <span>{{ $ceoRanks }}</span>/<span>{{ $neededCeoRanks }}</span>
    </p>
    {{-- ✅ FIXED: Progress bar with percentage positioned correctly --}}
    <div class="tile-progress-wrapper">
      <div class="tile-progress-bar {{ $ceoRanks == $neededCeoRanks ? "progress-success" : "progress-info" }}" 
           style="--progress: {{ $ceoRankPercent }}%"></div>
      <span class="tile-progress-percent">{{ $ceoRankPercent }}%</span>
    </div>
  </div>
</div>

<h2>{{ $_('employees-detailed') }}</h2>
<div class="person-stats">
  @foreach ($employees as $employee)
    <div class="tile person-stat tile-with-progress
    @if($employee->self_competency_submit_count && $employee->relations_count == $employee->competency_submits_count) 
      tile-success
    @else
      tile-danger
    @endif">
      <p>{{ $employee->name }}</p>
      <div>
        <div>
          <span>{{ $_('persons-to-be-assessed') }}</span>
          <span>{{ $employee->relations_count }}</span>
        </div>
        <div>
          <span>{{ $_('persons-assessed') }}</span>
          <span>{{ $employee->competency_submits_count }}</span>
        </div>
        <div>
          <span>{{ $_('self-assessment') }}</span>
          @if ($employee->self_competency_submit_count)
          <span><i class="fa fa-square-check"></i></span>
          @else
          <span><i class="fa fa-square-xmark"></i></span>
          @endif
        </div>
      </div>
      {{-- ✅ FIXED: Progress bar with percentage positioned at the bar's end --}}
      <div class="tile-progress-wrapper">
        <div class="tile-progress-bar 
          @if($employee->self_competency_submit_count && $employee->relations_count == $employee->competency_submits_count)
            progress-success
          @else
            progress-danger
          @endif" 
          style="--progress: {{ $employee->progressPercent }}%"></div>
        <span class="tile-progress-percent" style="--progress: {{ $employee->progressPercent }}%">{{ $employee->progressPercent }}%</span>
      </div>
    </div>
  @endforeach
</div>
@endif
@endsection

@section('scripts')
@include('admin.modals.assessment')
@endsection