@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
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
@if (!is_null($assessment))
<div class="double-tiles stats">
  <div class="tile {{ $assessed == $neededAssessment ? "tile-success" : "tile-info" }}">
    <p>{{ $_('assessment-stat') }}</p>
    <p>
      <span>{{ $assessed }}</span>/<span>{{ $neededAssessment }}</span>
    </p>
  </div>
  <div class="tile {{ $ceoRanks == $neededCeoRanks ? "tile-success" : "tile-info" }}">
    <p>{{ $_('ceo-stat') }}</p>
    <p>
      <span>{{ $ceoRanks }}</span>/<span>{{ $neededCeoRanks }}</span>
    </p>
  </div>
</div>
<h2>{{ $_('employees-detailed') }}</h2>
<div class="person-stats">
  @foreach ($employees as $employee)
    <div class="tile person-stat
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
    </div>
  @endforeach
</div>
@endif
@endsection

@section('scripts')
@include('admin.modals.assessment')
@endsection