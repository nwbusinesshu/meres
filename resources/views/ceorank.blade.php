@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<div>
<h2>{{ $_('ranking') }}</h2>
<div class="ranks">
@foreach ($ceoranks as $rank)
<div class="tile tile-primary rank">
  <div class="info">
    <div>
      <span>{{ $_('value') }}</span>
      <span>{{ $rank->value }}</span>
    </div>
    <div>
      <span>{{ $_('name') }}</span>
      <span>{{ $rank->name }}</span>
    </div>
    <div>
    @if (!is_null($rank->calcMin))
      <span>{{ $_('min') }}: {{ $rank->calcMin }} {{ $_('head') }}</span>
    @endif
    @if(!is_null($rank->calcMax))
      <span>{{ $_('max') }}: {{ $rank->calcMax }} {{ $_('head') }}</span>
    @endif
    </div> 
  </div>
  <div class="employees" data-id="{{ $rank->id }}" data-max="{{ $rank->calcMax ?? 'none' }}" data-min="{{ $rank->calcMin ?? 'none' }}">
  </div>
</div>
@endforeach
</div>
</div>
<div>
<h2>{{ $_('employees') }}</h2>
<div class="employee-list">
<div class="tile tile-button save-ranks hidden">
  <span>{{ $_('save-ranks') }}</span>
</div>
@foreach ($employees as $employee)
<div class="tile tile-info employee" data-id="{{ $employee->id }}" draggable="true">
  <p>{{ $employee->name }}</p>
</div>
@endforeach
</div>
</div>
<div class="tile tile-warning no-mobile">
  <p>{{ $_('no-mobile') }}</p>
</div>
@endsection

@section('scripts')
@endsection