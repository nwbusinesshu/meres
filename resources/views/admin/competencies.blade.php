@extends('layouts.master')

@section('head-extra')
<style>
.translation-warning {
  font-size: 0.875rem;
  animation: warningPulse 2s infinite;
}

@keyframes warningPulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.translation-warning:hover {
  color: #e0a800 !important;
  animation: none;
}
</style>
@endsection

@section('content')
<h1>{{ __('titles.admin.competencies') }}</h1>

<div class="fixed-row">
  <div class="tile tile-info competency-search">
    <span>{{ $_('search') }}</span>
    <div>
      <input type="text" class="form-control competency-search-input" placeholder="{{ $_('search') }}...">
      <i class="fa fa-ban competency-clear-search" data-tippy-content="{{ $_('search-clear') }}"></i>
    </div>
  </div>
  <div class="tile tile-button create-competency">
    <span><i class="fa fa-circle-plus"></i>{{ $_('create-competency') }}</span>
  </div>
  <!-- NEW: Language Selection Button -->
  <div class="tile tile-button open-language-modal" onclick="initLanguageModal()">
    <span><i class="fa fa-language"></i>{{ __('admin/competencies.select-translation-languages') }}</span>
  </div>
</div>

@php
  // A controller ideális esetben 'globals' és 'orgCompetencies' változókat ad.
  // Hogy visszafelé kompatibilis legyen: ha csak $competencies érkezik, bontsuk szét itt.
  $globals = $globals ?? (isset($competencies) ? $competencies->where('organization_id', null) : collect());
  $orgCompetencies = $orgCompetencies ?? (isset($competencies) ? $competencies->where('organization_id', session('org_id')) : collect());
@endphp

{{-- ============================ --}}
{{-- Szervezeti kompetenciák (CRUD) --}}
{{-- ============================ --}}
<div class="competency-list competency-list--org">
  <div class="tile tile-info" style="margin-top: 20px;">
    <span><strong>Egyéni szervezeti kompetenciatár</strong></span>
  </div>

  @forelse ($orgCompetencies as $comp)
    @php
      // Check if translations are missing for this competency
      $hasIncompleteTranslations = false;
      if (isset($selectedLanguages) && count($selectedLanguages) > 1) {
        $translations = $comp->name_json ? json_decode($comp->name_json, true) : [];
        foreach ($selectedLanguages as $langCode) {
          if (empty($translations[$langCode])) {
            $hasIncompleteTranslations = true;
            break;
          }
        }
      }
    @endphp
    
    <div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->name }}">
      <div class="bar">
        <span>
          <i class="fa fa-caret-down"></i>{{ $comp->name }}
          @if($hasIncompleteTranslations)
            <i class="fa fa-exclamation-triangle translation-warning" 
               style="color: #ffc107; margin-left: 8px;" 
               data-tippy-content="{{ __('admin/competencies.incomplete-translations') }}"></i>
          @endif
        </span>
        <button class="btn btn-outline-danger remove-competency" data-tippy-content="{{ $_('remove-competency') }}"><i class="fa fa-trash-alt"></i></button>
        <button class="btn btn-outline-warning modify-competency" data-tippy-content="{{ $_('modify-competency') }}"><i class="fa fa-file-pen"></i></button>
      </div>

      <div class="questions hidden">
        <div class="tile tile-button create-question">{{ $_('create-question') }}</div>

        @foreach ($comp->questions as $q)
          <div class="question-item" data-id="{{ $q->id }}">
            <div>
              <span>{{ $_('question') }} #{{ $loop->index+1 }}</span>
              <div>
                <button class="btn btn-outline-danger remove-question" data-tippy-content="{{ $_('question-remove') }}"><i class="fa fa-trash-alt"></i></button>
                <button class="btn btn-outline-warning modify-question" data-tippy-content="{{ $_('question-modify') }}"><i class="fa fa-file-pen"></i></button>
              </div>
            </div>
            <div>
              <p>{{ $q->question }}</p>
              <p>{{ $q->question_self }}</p>
            </div>
            <div>
              <p>{{ $_('min-label') }}<span>{{ $q->min_label }}</span></p>
              <p>{{ $_('max-label') }}<span>{{ $q->max_label }}</span></p>
              <p>{{ $_('scale') }}<span>{{ $q->max_value }}</span></p>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @empty
    <div class="no-competency"><p>{{ $_('no-competency') }}</p></div>
  @endforelse
</div>

{{-- ============================ --}}
{{-- Globális kompetenciák (read-only) --}}
{{-- ============================ --}}
<div class="competency-list competency-list--global">
  <div class="tile tile-info" style="margin-top: 10px;">
    <span><strong>Globális kompetenciatár</strong> <small class="text-muted"></small></span>
  </div>

  @forelse ($globals as $comp)
    <div class="tile competency-item competency-item--global" data-id="{{ $comp->id }}" data-name="{{ $comp->name }}" data-readonly="1">
      <div class="bar">
        <span><i class="fa fa-caret-down"></i>{{ $comp->name }} </i></span>
        {{-- NINCSENEK műveleti gombok a globális blokknál --}}
      </div>

      <div class="questions hidden questions--readonly">
        {{-- NINCS kérdés-hozzáadás gomb a globális blokknál --}}
        @forelse ($comp->questions as $q)
          <div class="question-item question-item--readonly" data-id="{{ $q->id }}">
            <div>
              <span>{{ $_('question') }} #{{ $loop->index+1 }}</span>
              <div>
                {{-- NINCS szerkesztés/törlés gomb --}}
              </div>
            </div>
            <div>
              <p>{{ $q->question }}</p>
              <p>{{ $q->question_self }}</p>
            </div>
            <div>
              <p>{{ $_('min-label') }}<span>{{ $q->min_label }}</span></p>
              <p>{{ $_('max-label') }}<span>{{ $q->max_label }}</span></p>
              <p>{{ $_('scale') }}<span>{{ $q->max_value }}</span></p>
            </div>
          </div>
        @empty
          <div class="no-competency"><p>{{ $_('no-competency') }}</p></div>
        @endforelse
      </div>
    </div>
  @empty
    <div class="no-competency"><p>Nincs globális kompetencia.</p></div>
  @endforelse
</div>



@endsection

@section('scripts')
  @include('admin.modals.competency')
  @include('admin.modals.competencyq')
  @include('admin.modals.language-select')
  
  <script>
    $(document).ready(function() {
      // Initialize tooltips for translation warnings
      if (typeof tippy !== 'undefined') {
        tippy('[data-tippy-content]', {
          placement: 'top',
          theme: 'warning',
        });
      }
    });
  </script>
@endsection