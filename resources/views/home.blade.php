@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h2>{{ $welcomeMessage }}</h2>
@if (is_null($assessment))
<div class="tile tile-info">
  <p>{{ $_('no-assessment-running') }}</p>
</div>  
@else
<div class="assessment-running">
  <div class="tile tile-info">
    <p>{{ $_('assessment-running') }}</p>
  </div>
  <div class="tile tile-warning">
    <p>{{ $_('due') }}: <span>{{ formatDateTime($assessment->due_at) }}</span></p>
  </div>
</div>
@if(MyAuth::isAuthorized(UserType::CEO) || MyAuth::isAuthorized(UserType::MANAGER))
<h2>{{ $_('ceo-rank') }}</h2>
@if (!$madeCeoRank)
<div class="tile tile-button rank-users">
  <span>{{ $_('do-ceo-rank') }}</span>
</div>
@else
<div class="tile tile-success rank-done">
  <p>{{ $_('ceo-rank-done') }}</p>
</div>
@endif
@endif

<h2>{{ $_('to-fill-out') }}</h2>
<div class="person-list">
  @if (!$selfAssessed)
    <div class="tile person" data-id="{{ session('uid') }}">
      <p>{{ $_('assess') }}:</p>
      <p>{{ __('assessment.self') }}</p>
      <p>{{ $_('click-to-fill') }}</p>
    </div>
  @endif
  @foreach ($relations as $relation)
    <div class="tile person" data-id="{{ $relation->target->id }}">
      <p>{{ $_('assess') }} {{ __('userrelationtypes.for-you.'.$relation->type )}}:</p>
      <p>{{ $relation->target->name }}</p>
      <p>{{ $_('click-to-fill') }}</p>
    </div>
  @endforeach
  @if($selfAssessed && $relations->count() == 0)
    <div class="tile tile-success no-more">
      <p>{{ $_('no-more-to-assess') }}</p>
    </div>
  @endif
</div>
@endif

@endsection

@section('scripts')
@endsection