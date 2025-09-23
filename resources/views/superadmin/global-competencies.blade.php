@extends('layouts.master')

@section('head-extra')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin.competencies.css') }}">
@endsection

@section('content')
<h1>{{ __('superadmin/global-competencies.title') }}</h1>

{{-- Global competencies note --}}
<div class="alert alert-info">
  <i class="fa fa-globe"></i>
  {{ __('superadmin/global-competencies.global-note') }}
  <small class="d-block mt-1">
    {{ __('superadmin/global-competencies.current-language') }}: {{ $languageNames[$currentLocale] ?? strtoupper($currentLocale) }}
  </small>
</div>

<div class="fixed-row">
  <div class="tile tile-info competency-search">
    <span>{{ __('superadmin/global-competencies.search') }}</span>
    <div>
      <input type="text" class="form-control competency-search-input">
      <i class="fa fa-ban competency-clear-search" data-tippy-content="{{ __('superadmin/global-competencies.search-clear') }}"></i>
    </div>
  </div>
  <div class="tile tile-button create-competency"><span><i class="fa fa-circle-plus"></i>{{ __('superadmin/global-competencies.create-competency') }}</span></div>
</div>

<div class="competency-list">
@foreach ($globals as $comp)
<div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->getTranslatedName() }}">
  <div class="bar">
    <span>
      {{-- Warning icon for missing translations --}}
      @if(!$comp->hasCompleteTranslations($availableLanguages))
        <i class="fa fa-exclamation-triangle warning-icon" data-tippy-content="{{ __('superadmin/global-competencies.missing-translations') }}"></i>
      @endif
      <i class="fa fa-caret-down"></i>{{ $comp->getTranslatedName() }}
      
      {{-- Language indicators --}}
      <div class="translation-indicators">
        @foreach($availableLanguages as $lang)
          <span class="lang-indicator {{ $comp->hasTranslation($lang) ? 'available' : 'missing' }} {{ $lang === $comp->original_language ? 'original' : '' }}" 
                data-tippy-content="{{ $languageNames[$lang] ?? strtoupper($lang) }} - {{ $comp->hasTranslation($lang) ? __('superadmin/global-competencies.available') : __('superadmin/global-competencies.missing') }}">
            {{ strtoupper($lang) }}
          </span>
        @endforeach
      </div>
    </span>
    <button class="btn btn-outline-info manage-translations" data-id="{{ $comp->id }}" data-tippy-content="{{ __('superadmin/global-competencies.manage-translations') }}"><i class="fa fa-language"></i></button>
    <button class="btn btn-outline-danger remove-competency" data-tippy-content="{{ __('superadmin/global-competencies.remove-competency') }}"><i class="fa fa-trash-alt"></i></button>
    <button class="btn btn-outline-warning modify-competency" data-tippy-content="{{ __('superadmin/global-competencies.modify-competency') }}"><i class="fa fa-file-pen"></i></button>
  </div>
  <div class="questions hidden">
    <div class="tile tile-button create-question">{{ __('superadmin/global-competencies.create-question') }}</div>
    @foreach ($comp->questions as $q)
    <div class="question-item" data-id="{{ $q->id }}">
      <div>
        <span>{{ __('superadmin/global-competencies.question') }} #{{ $loop->index+1 }}</span>
        <div>
          <button class="btn btn-outline-info manage-question-translations" data-id="{{ $q->id }}" data-tippy-content="{{ __('superadmin/global-competencies.manage-translations') }}"><i class="fa fa-language"></i></button>
          <button class="btn btn-outline-danger remove-question" data-tippy-content="{{ __('superadmin/global-competencies.question-remove') }}"><i class="fa fa-trash-alt"></i></button>
          <button class="btn btn-outline-warning modify-question" data-tippy-content="{{ __('superadmin/global-competencies.question-modify') }}"><i class="fa fa-file-pen"></i></button>
        </div>
      </div>
      <div>
        <p>{{ $q->getTranslatedQuestion() }}</p>
        <p>{{ $q->getTranslatedQuestionSelf() }}</p>
      </div>
      <div>
        <p>{{ __('superadmin/global-competencies.min-label') }}<span>{{ $q->getTranslatedMinLabel() }}</span></p>
        <p>{{ __('superadmin/global-competencies.max-label') }}<span>{{ $q->getTranslatedMaxLabel() }}</span></p>
        <p>{{ __('superadmin/global-competencies.scale') }}<span>{{ $q->max_value }}</span></p>
        
        {{-- Question translation indicators --}}
        <div class="question-translation-indicators">
          @foreach($availableLanguages as $lang)
            @if(!$q->hasCompleteTranslation($lang))
              <i class="fa fa-exclamation-triangle warning-icon small" data-tippy-content="{{ __('superadmin/global-competencies.missing-translation-for') }} {{ $languageNames[$lang] ?? strtoupper($lang) }}"></i>
            @endif
          @endforeach
        </div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endforeach
<div class="no-competency @if(!$globals->isEmpty()) hidden @endif"><p>{{ __('superadmin/global-competencies.no-competency') }}</p></div>
</div>

@endsection

@section('scripts')
@include('admin.modals.competency')
@include('admin.modals.competencyq')
@endsection