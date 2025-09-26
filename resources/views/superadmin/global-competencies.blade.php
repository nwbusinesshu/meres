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
<h1>Globális kompetenciák</h1>

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
</div>

@php
  // Get current locale
  $currentLocale = app()->getLocale();
  
  // Helper closure to get translated text with fallback
  $getTranslated = function($originalText, $translationsJson, $originalLanguage, $currentLocale) {
    // If no translations or we're in the original language, return original text
    if (empty($translationsJson) || $currentLocale === $originalLanguage) {
      return ['text' => $originalText, 'is_fallback' => false];
    }
    
    $translations = json_decode($translationsJson, true);
    if (!$translations || !is_array($translations)) {
      return ['text' => $originalText, 'is_fallback' => true];
    }
    
    // Check if translation exists for current locale
    if (isset($translations[$currentLocale]) && !empty(trim($translations[$currentLocale]))) {
      return ['text' => $translations[$currentLocale], 'is_fallback' => false];
    }
    
    // Fallback to original text
    return ['text' => $originalText, 'is_fallback' => true];
  };
@endphp

<div class="competency-list competency-list--global-crud">
  @forelse ($globals as $comp)
    @php
      // Check if translations are missing for this competency
      $hasIncompleteTranslations = false;
      if (isset($selectedLanguages) && count($selectedLanguages) > 1) {
        $translations = $comp->name_json ? json_decode($comp->name_json, true) : [];
        foreach ($selectedLanguages as $lang) {
          if (!isset($translations[$lang]) || empty(trim($translations[$lang]))) {
            $hasIncompleteTranslations = true;
            break;
          }
        }
      }
      
      // Get translated competency name
      $competencyName = $getTranslated(
        $comp->name, 
        $comp->name_json, 
        $comp->original_language ?? 'hu', 
        $currentLocale
      );
    @endphp
    
    <div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->name }}">
      <div class="bar">
        <span class="{{ $competencyName['is_fallback'] ? 'fallback-text' : '' }}">
          <i class="fa fa-caret-down"></i>{{ $competencyName['text'] }}
          @if($hasIncompleteTranslations)
            <i class="fa fa-exclamation-triangle translation-warning text-warning ml-2" 
               data-tippy-content="{{ __('admin/competencies.translation-missing') }}"
               style="color: #ffc107 !important;"></i>
          @endif
        </span>
        <button class="btn btn-outline-danger remove-competency" data-tippy-content="{{ $_('remove-competency') }}"><i class="fa fa-trash-alt"></i></button>
        <button class="btn btn-outline-warning modify-competency" data-tippy-content="{{ $_('modify-competency') }}"><i class="fa fa-file-pen"></i></button>
      </div>

      <div class="questions hidden">
        <div class="tile tile-button create-question">{{ $_('create-question') }}</div>

        @foreach ($comp->questions as $q)
          @php
            // Check if question translations are missing
            $hasIncompleteQuestionTranslations = false;
            if (isset($selectedLanguages) && count($selectedLanguages) > 1) {
              $questionTranslations = $q->question_json ? json_decode($q->question_json, true) : [];
              $questionSelfTranslations = $q->question_self_json ? json_decode($q->question_self_json, true) : [];
              $minLabelTranslations = $q->min_label_json ? json_decode($q->min_label_json, true) : [];
              $maxLabelTranslations = $q->max_label_json ? json_decode($q->max_label_json, true) : [];
              
              foreach ($selectedLanguages as $lang) {
                if (!isset($questionTranslations[$lang]) || empty(trim($questionTranslations[$lang])) ||
                    !isset($questionSelfTranslations[$lang]) || empty(trim($questionSelfTranslations[$lang])) ||
                    !isset($minLabelTranslations[$lang]) || empty(trim($minLabelTranslations[$lang])) ||
                    !isset($maxLabelTranslations[$lang]) || empty(trim($maxLabelTranslations[$lang]))) {
                  $hasIncompleteQuestionTranslations = true;
                  break;
                }
              }
            }
            
            // Get translated question texts
            $questionText = $getTranslated(
              $q->question, 
              $q->question_json, 
              $q->original_language ?? 'hu', 
              $currentLocale
            );
            
            $questionSelfText = $getTranslated(
              $q->question_self, 
              $q->question_self_json, 
              $q->original_language ?? 'hu', 
              $currentLocale
            );
            
            $minLabelText = $getTranslated(
              $q->min_label, 
              $q->min_label_json, 
              $q->original_language ?? 'hu', 
              $currentLocale
            );
            
            $maxLabelText = $getTranslated(
              $q->max_label, 
              $q->max_label_json, 
              $q->original_language ?? 'hu', 
              $currentLocale
            );
          @endphp
          
          <div class="question-item" data-id="{{ $q->id }}">
            <div>
              <span>
                {{ $_('question') }} #{{ $loop->index+1 }}
                @if($hasIncompleteQuestionTranslations)
                  <i class="fa fa-exclamation-triangle translation-warning text-warning ml-1" 
                     data-tippy-content="{{ __('admin/competencies.question-translation-missing') }}"
                     style="color: #ffc107 !important; font-size: 0.8em;"></i>
                @endif
              </span>
              <div>
                <button class="btn btn-outline-danger remove-question" data-tippy-content="{{ $_('question-remove') }}"><i class="fa fa-trash-alt"></i></button>
                <button class="btn btn-outline-warning modify-question" data-tippy-content="{{ $_('question-modify') }}"><i class="fa fa-file-pen"></i></button>
              </div>
            </div>
            <div>
              <p class="{{ $questionText['is_fallback'] ? 'fallback-text' : '' }}">{{ $questionText['text'] }}</p>
              <p class="{{ $questionSelfText['is_fallback'] ? 'fallback-text' : '' }}">{{ $questionSelfText['text'] }}</p>
            </div>
            <div>
              <p>{{ $_('min-label') }}<span class="{{ $minLabelText['is_fallback'] ? 'fallback-text' : '' }}">{{ $minLabelText['text'] }}</span></p>
              <p>{{ $_('max-label') }}<span class="{{ $maxLabelText['is_fallback'] ? 'fallback-text' : '' }}">{{ $maxLabelText['text'] }}</span></p>
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

@endsection

@section('modals')
  @include('admin.modals.competency')
  @include('admin.modals.competencyq')
@endsection

@section('scripts')
@endsection