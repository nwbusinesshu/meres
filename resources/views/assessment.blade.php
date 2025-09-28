@extends('layouts.master')

@section('head-extra')
<style>
/* NEW: Styling for fallback text when translation is not available */
.fallback-text {
  color: #dc3545 !important;
  font-style: italic;
}

.fallback-text::after {
  content: ' (!)';
  font-size: 0.8em;
  opacity: 0.7;
}
</style>
@endsection

@section('content')
<div class="title">
  @if (session('uid') == $target->id)
  <p>{{ $_('assess') }}:</p>
  @else
  <p>{{ $_('assess') }} {{ __('userrelationtypes.for-you.'.$relation->type )}}:</p>
  @endif
  <h2>{{ session('uid') == $target->id ? $_('self') : $target->name }}</h2>
</div>
<div class="tile tile-info">
  <p><span>{{ $_('info') }}</span> {{ $_('info-1') }}</p>
</div>
<div class="warnings">
  <div class="tile tile-warning">
    <p><span>{{ $_('warning') }}</span> {{ $_('warning-1') }}</p>
  </div>
  <div class="tile tile-warning">
    <p><span>{{ $_('warning') }}</span> {{ $_('warning-2') }}</p>
  </div>
</div>
@php
$counter = 1;
@endphp
@foreach ($questions as $competencyName => $question)
<div class="competency">
  <p class="{{ $question->first()->competency_name_is_fallback ? 'fallback-text' : '' }}">{{ $competencyName }}</p>
  @foreach ($question->shuffle() as $q)
   @php
    $qText  = session('uid') == $target->id ? $q->question_self : $q->question;
    $qPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($qText)));
    $qChars = mb_strlen($qPlain, 'UTF-8');
  @endphp
  <div class="tile tile-secondary question" data-id="{{ $q->id }}" data-chars="{{ $qChars }}">
    <p>{{ $_('question') }} {{ $counter++ }}/{{ $questionsCount }}</p>
    <p>{{ session('uid') == $target->id ? $q->question_self : $q->question }}</p>
    <div>
      <span>{{ $q->min_label }}</span>
      <span>{{ $q->max_label }}</span>
    </div>
    <div class="values" data-target-id="{{ $target->id }}">
      @for ($i = 0; $i <= $q->max_value; $i++)
        <div class="value" data-value="{{ $i }}">
          <span>{{ $i }}</span>
        </div>
      @endfor
    </div>
  </div>
  @endforeach
</div>
<hr>
@endforeach

<button class="btn btn-primary send-in">{{ $_('send-in') }}</button>
@endsection

@section('scripts')
@endsection