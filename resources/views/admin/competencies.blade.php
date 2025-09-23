@extends('layouts.master')

@section('head-extra')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin.competencies.css') }}">
@endsection

@section('content')
<h1>{{ __('admin/competencies.title') }}</h1>

{{-- Language Setup Tile --}}
<div class="fixed-row">
  <div class="tile tile-info language-setup">
    <div class="language-setup-content">
      <span>{{ __('admin/competencies.selected-languages') }}</span>
      <div class="selected-languages">
        @foreach($selectedLanguages as $lang)
          <span class="language-badge" data-lang="{{ $lang }}">
            {{ $languageNames[$lang] ?? strtoupper($lang) }}
            @if($lang !== $currentLocale)
              <i class="fa fa-times remove-language" data-lang="{{ $lang }}"></i>
            @endif
          </span>
        @endforeach
      </div>
      <button class="btn btn-sm btn-outline-primary manage-languages">
        <i class="fa fa-cog"></i> {{ __('admin/competencies.manage-languages') }}
      </button>
    </div>
  </div>
</div>

<div class="fixed-row">
  <div class="tile tile-info competency-search">
    <span>{{ __('admin/competencies.search') }}</span>
    <div>
      <input type="text" class="form-control competency-search-input">
      <i class="fa fa-ban competency-clear-search" data-tippy-content="{{ __('admin/competencies.search-clear') }}"></i>
    </div>
  </div>
  <div class="tile tile-button create-competency"><span><i class="fa fa-circle-plus"></i>{{ __('admin/competencies.create-competency') }}</span></div>
</div>

{{-- ============================ --}}
{{-- Organization Competencies --}}
{{-- ============================ --}}
<div class="section-header">
  <h3>{{ __('admin/competencies.organization-competencies') }}</h3>
  <small class="text-muted">{{ __('admin/competencies.organization-competencies-help') }}</small>
</div>

<div class="competency-list competency-list--org-crud">
@foreach ($competencies as $comp)
<div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->getTranslatedName() }}">
  <div class="bar">
    <span>
      {{-- Warning icon for missing translations --}}
      @if(!$comp->hasCompleteTranslations($selectedLanguages))
        <i class="fa fa-exclamation-triangle warning-icon" data-tippy-content="{{ __('admin/competencies.missing-translations') }}"></i>
      @endif
      <i class="fa fa-caret-down"></i>{{ $comp->getTranslatedName() }}
      
      {{-- Language indicators --}}
      <div class="translation-indicators">
        @foreach($selectedLanguages as $lang)
          <span class="lang-indicator {{ $comp->hasTranslation($lang) ? 'available' : 'missing' }} {{ $lang === $comp->original_language ? 'original' : '' }}" 
                data-tippy-content="{{ $languageNames[$lang] ?? strtoupper($lang) }} - {{ $comp->hasTranslation($lang) ? __('admin/competencies.available') : __('admin/competencies.missing') }}">
            {{ strtoupper($lang) }}
          </span>
        @endforeach
      </div>
    </span>
    <button class="btn btn-outline-info manage-translations" data-id="{{ $comp->id }}" data-tippy-content="{{ __('admin/competencies.manage-translations') }}"><i class="fa fa-language"></i></button>
    <button class="btn btn-outline-danger remove-competency" data-tippy-content="{{ __('admin/competencies.remove-competency') }}"><i class="fa fa-trash-alt"></i></button>
    <button class="btn btn-outline-warning modify-competency" data-tippy-content="{{ __('admin/competencies.modify-competency') }}"><i class="fa fa-file-pen"></i></button>
  </div>
  <div class="questions hidden">
    <div class="tile tile-button create-question">{{ __('admin/competencies.create-question') }}</div>
    @foreach ($comp->questions as $q)
    <div class="question-item" data-id="{{ $q->id }}">
      <div>
        <span>{{ __('admin/competencies.question') }} #{{ $loop->index+1 }}</span>
        <div>
          <button class="btn btn-outline-info manage-question-translations" data-id="{{ $q->id }}" data-tippy-content="{{ __('admin/competencies.manage-translations') }}"><i class="fa fa-language"></i></button>
          <button class="btn btn-outline-danger remove-question" data-tippy-content="{{ __('admin/competencies.question-remove') }}"><i class="fa fa-trash-alt"></i></button>
          <button class="btn btn-outline-warning modify-question" data-tippy-content="{{ __('admin/competencies.question-modify') }}"><i class="fa fa-file-pen"></i></button>
        </div>
      </div>
      <div>
        <p>{{ $q->getTranslatedQuestion() }}</p>
        <p>{{ $q->getTranslatedQuestionSelf() }}</p>
      </div>
      <div>
        <p>{{ __('admin/competencies.min-label') }}<span>{{ $q->getTranslatedMinLabel() }}</span></p>
        <p>{{ __('admin/competencies.max-label') }}<span>{{ $q->getTranslatedMaxLabel() }}</span></p>
        <p>{{ __('admin/competencies.scale') }}<span>{{ $q->max_value }}</span></p>
        
        {{-- Question translation indicators --}}
        <div class="question-translation-indicators">
          @foreach($selectedLanguages as $lang)
            @if(!$q->hasCompleteTranslation($lang))
              <i class="fa fa-exclamation-triangle warning-icon small" data-tippy-content="{{ __('admin/competencies.missing-translation-for') }} {{ $languageNames[$lang] ?? strtoupper($lang) }}"></i>
            @endif
          @endforeach
        </div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endforeach
@if($competencies->isEmpty())
<div class="no-competency"><p>{{ __('admin/competencies.no-organization-competencies') }}</p></div>
@endif
</div>

{{-- ============================ --}}
{{-- Global Competencies (Read-only) --}}
{{-- ============================ --}}
<div class="section-header" style="margin-top: 3rem;">
  <h3>{{ __('admin/competencies.global-competencies') }}</h3>
  <small class="text-muted">{{ __('admin/competencies.global-competencies-help') }}</small>
</div>

<div class="competency-list competency-list--global">
@foreach ($globals as $comp)
<div class="tile competency-item global-competency" data-id="{{ $comp->id }}" data-name="{{ $comp->getTranslatedName() }}">
  <div class="bar">
    <span>
      <i class="fa fa-globe text-primary" data-tippy-content="{{ __('admin/competencies.global-competency-indicator') }}"></i>
      <i class="fa fa-caret-down"></i>{{ $comp->getTranslatedName() }}
      
      {{-- Language indicators for global competencies --}}
      <div class="translation-indicators">
        @foreach($selectedLanguages as $lang)
          <span class="lang-indicator {{ $comp->hasTranslation($lang) ? 'available' : 'missing' }} {{ $lang === $comp->original_language ? 'original' : '' }}" 
                data-tippy-content="{{ $languageNames[$lang] ?? strtoupper($lang) }} - {{ $comp->hasTranslation($lang) ? __('admin/competencies.available') : __('admin/competencies.missing') }}">
            {{ strtoupper($lang) }}
          </span>
        @endforeach
      </div>
    </span>
    {{-- No edit/delete buttons for global competencies in admin view --}}
    <span class="badge badge-secondary">{{ __('admin/competencies.read-only') }}</span>
  </div>
  <div class="questions hidden">
    @foreach ($comp->questions as $q)
    <div class="question-item global-question" data-id="{{ $q->id }}">
      <div>
        <span>{{ __('admin/competencies.question') }} #{{ $loop->index+1 }}</span>
        <div>
          {{-- No edit/delete buttons for global questions in admin view --}}
          <span class="badge badge-secondary">{{ __('admin/competencies.global') }}</span>
        </div>
      </div>
      <div>
        <p>{{ $q->getTranslatedQuestion() }}</p>
        <p>{{ $q->getTranslatedQuestionSelf() }}</p>
      </div>
      <div>
        <p>{{ __('admin/competencies.min-label') }}<span>{{ $q->getTranslatedMinLabel() }}</span></p>
        <p>{{ __('admin/competencies.max-label') }}<span>{{ $q->getTranslatedMaxLabel() }}</span></p>
        <p>{{ __('admin/competencies.scale') }}<span>{{ $q->max_value }}</span></p>
        
        {{-- Question translation indicators for global questions --}}
        <div class="question-translation-indicators">
          @foreach($selectedLanguages as $lang)
            @if(!$q->hasCompleteTranslation($lang))
              <i class="fa fa-exclamation-triangle warning-icon small" data-tippy-content="{{ __('admin/competencies.missing-translation-for') }} {{ $languageNames[$lang] ?? strtoupper($lang) }}"></i>
            @endif
          @endforeach
        </div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endforeach
@if($globals->isEmpty())
<div class="no-competency"><p>{{ __('admin/competencies.no-global-competencies') }}</p></div>
@endif
</div>

@endsection

@section('scripts')
@include('admin.modals.competency')
@include('admin.modals.competencyq')
@include('admin.modals.language-setup')
@endsection