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

/* ADDED: Competency description styling */
.competency-description {
  background-color: #f6f5f2;
  border-left: 3px solid #007bff;
  padding: 12px 16px;
  margin-bottom: 15px;
  font-style: italic;
  color: #495057;
}

.competency-description.fallback-text {
  border-left-color: #dc3545;
  background-color: #fdf2f2;
}

.competency-description-label {
  font-weight: 600;
  font-size: 0.875rem;
  color: #6c757d;
  text-transform: uppercase;
  margin-bottom: 4px;
  display: block;
}

/* NEW: Competency Groups Styling - MINIMAL blue styling */
.competency-group-item {
  background: linear-gradient(135deg, #f0f4ff 0%, #e8f1ff 100%);
  border-left: 4px solid #007bff;
}

.competency-group-count {
  border-radius: 50%;
  padding: 0.125rem 0.5rem;
  margin-left: 0.5rem;
}

.group-competencies {
  padding: 1rem;
  background-color: #f8f9fa;
  border-top: 1px solid #dee2e6;
}

.group-competency-badge {
  background-color: #e9ecef;
  border: 1px solid #ced4da;
  padding: 0.25rem 0.75rem;
  font-size: 0.875rem;
  color: #495057;
  margin-right: 0.5rem;
  margin-bottom: 0.5rem;
  display: inline-block;
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
  {{-- NEW: Create Group Button - ONLY ADDITION --}}
  <div class="tile tile-button create-competency-group" onclick="initCreateCompetencyGroupModal()">
    <span><i class="fa fa-layer-group"></i>{{ __('admin/competencies.create-group') }}</span>
  </div>
  <!-- Language Selection Button -->
  <div class="tile tile-button open-language-modal" onclick="initLanguageModal()">
    <span><i class="fa fa-language"></i>{{ __('admin/competencies.select-translation-languages') }}</span>
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

  // A controller ideális esetben 'globals' és 'orgCompetencies' változókat ad.
  // Hogy visszafelé kompatibilis legyen: ha csak $competencies érkezik, bontsuk szét itt.
  $globals = $globals ?? (isset($competencies) ? $competencies->where('organization_id', null) : collect());
  $orgCompetencies = $orgCompetencies ?? (isset($competencies) ? $competencies->where('organization_id', session('org_id')) : collect());
  
  // NEW: Get competency groups (minimal addition)
  $competencyGroups = $competencyGroups ?? collect();
@endphp

{{-- ============================ --}}
{{-- Szervezeti kompetenciák (CRUD) --}}
{{-- ============================ --}}
<div class="competency-list competency-list--org">
  <div class="tile tile-info" style="margin-top: 20px;">
    <span><strong>{{ $_('org-competencies') }}</strong></span>
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
      
      // Get translated competency name
      $competencyName = $getTranslated(
        $comp->name, 
        $comp->name_json, 
        $comp->original_language ?? 'hu', 
        $currentLocale
      );

      // ADDED: Get translated competency description
      $competencyDescription = null;
      if (!empty($comp->description)) {
        $competencyDescription = $getTranslated(
          $comp->description, 
          $comp->description_json, 
          $comp->original_language ?? 'hu', 
          $currentLocale
        );
      }
    @endphp
    
    <div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->name }}">
      <div class="bar">
        <span class="{{ $competencyName['is_fallback'] ? 'fallback-text' : '' }}">
          <i class="fa fa-caret-down"></i>{{ $competencyName['text'] }}
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
        {{-- ADDED: Display competency description if it exists --}}
        @if($competencyDescription)
          <div class="competency-description {{ $competencyDescription['is_fallback'] ? 'fallback-text' : '' }}">
            <span class="competency-description-label">{{ __('global.description') }}</span>
            {{ $competencyDescription['text'] }}
          </div>
        @endif

        <div class="tile tile-button create-question">{{ $_('create-question') }}</div>

        @foreach ($comp->questions as $q)
          @php
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
              <span>{{ $_('question') }} #{{ $loop->index+1 }}</span>
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

{{-- NEW: Competency Groups Section - WITH PROPER TITLE --}}
<div class="competency-list competency-list--groups">
  <div class="tile tile-info" style="margin-top: 20px;">
    <span><strong>{{ __('admin/competencies.competency-groups') }}</strong></span>
  </div>

  @forelse ($competencyGroups as $group)
    @php
      $groupCompetencies = $group->competencies();
      $assignedUsers = $group->assignedUsers(); // NEW: Get assigned users
    @endphp
    
    <div class="tile competency-item competency-group-item" data-id="{{ $group->id }}" data-name="{{ $group->name }}">
      <div class="bar">
        <span>
          <i class="fa fa-caret-down"></i>{{ $group->name }}
          <span class="competency-group-count">{{ $groupCompetencies->count() }}</span>
          {{-- NEW: Show assigned users count --}}
          @if($assignedUsers->count() > 0)
            <span class="competency-group-users-count" style="color: #28a745; margin-left: 0.5rem;">
              <i class="fa fa-users"></i> {{ $assignedUsers->count() }}
            </span>
          @endif
        </span>
      </div>

      <div class="questions group-competencies hidden">
        <h6>{{ __('admin/competencies.competencies-in-group') }}:</h6>
        @if($groupCompetencies->count() > 0)
          @foreach($groupCompetencies as $comp)
            @php
              $competencyName = $getTranslated(
                $comp->name, 
                $comp->name_json, 
                $comp->original_language ?? 'hu', 
                $currentLocale
              );
            @endphp
            <span class="group-competency-badge {{ $competencyName['is_fallback'] ? 'fallback-text' : '' }}">
              {{ $competencyName['text'] }}
            </span>
          @endforeach
        @else
          <p class="text-muted">{{ __('admin/competencies.no-competencies-in-group') }}</p>
        @endif

        {{-- NEW: Assigned Users Section --}}
        <h6 style="margin-top: 1.5rem;">{{ __('admin/competencies.assigned-users') }}:</h6>
        @if($assignedUsers->count() > 0)
          <div class="assigned-users-list" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            @foreach($assignedUsers as $user)
              <span class="assigned-user-badge" style="background-color: #e8f4fd; border: 1px solid #007bff; padding: 0.25rem 0.75rem; font-size: 0.875rem; color: #007bff; border-radius: 0.375rem;">
                <i class="fa fa-user"></i> {{ $user->name }}
              </span>
            @endforeach
          </div>
        @else
          <p class="text-muted">{{ __('admin/competencies.no-users-assigned') }}</p>
        @endif
        <div> <button class="btn btn-outline-danger remove-competency-group" data-tippy-content="{{ __('admin/competencies.remove-group') }}"><i class="fa fa-trash-alt"></i></button>
        <button class="btn btn-outline-warning modify-competency-group" data-tippy-content="{{ __('admin/competencies.modify-group') }}"><i class="fa fa-file-pen"></i></button>
        {{-- NEW: User Assignment Button --}}
        <button class="btn btn-outline-info assign-group-users" data-tippy-content="{{ __('admin/competencies.assign-users') }}" data-group-id="{{ $group->id }}" data-group-name="{{ $group->name }}"><i class="fa fa-user-plus"></i></button></div>
      </div>
    </div>
  @empty
    <div class="no-competency"><p>{{ __('admin/competencies.no-groups') }}</p></div>
  @endforelse
</div>

{{-- ============================ --}}
{{-- Globális kompetenciák (read-only) --}}
{{-- ============================ --}}
<div class="competency-list competency-list--global">
  <div class="tile tile-info" style="margin-top: 10px;">
    <span><strong>{{ $_('glob-competencies') }}</strong> <small class="text-muted"></small></span>
  </div>

  @forelse ($globals as $comp)
    @php
      // Get translated global competency name
      $globalCompetencyName = $getTranslated(
        $comp->name, 
        $comp->name_json, 
        $comp->original_language ?? 'hu', 
        $currentLocale
      );

      // ADDED: Get translated global competency description
      $globalCompetencyDescription = null;
      if (!empty($comp->description)) {
        $globalCompetencyDescription = $getTranslated(
          $comp->description, 
          $comp->description_json, 
          $comp->original_language ?? 'hu', 
          $currentLocale
        );
      }
    @endphp
    
    <div class="tile competency-item competency-item--global" data-id="{{ $comp->id }}" data-name="{{ $comp->name }}" data-readonly="1">
      <div class="bar">
        <span class="{{ $globalCompetencyName['is_fallback'] ? 'fallback-text' : '' }}">
          <i class="fa fa-caret-down"></i>{{ $globalCompetencyName['text'] }}
        </span>
      </div>

      <div class="questions hidden">
        {{-- ADDED: Display global competency description if it exists --}}
        @if($globalCompetencyDescription)
          <div class="competency-description {{ $globalCompetencyDescription['is_fallback'] ? 'fallback-text' : '' }}">
            <span class="competency-description-label">{{ __('global.description') }}</span>
            {{ $globalCompetencyDescription['text'] }}
          </div>
        @endif

        @foreach ($comp->questions as $q)
          @php
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
              <span>{{ $_('question') }} #{{ $loop->index+1 }}</span>
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
  @include('admin.modals.language-select')
  @include('admin.modals.select')
  @include('admin.modals.competency-group')
  @include('admin.modals.group-user-selector') 
@endsection

@section('scripts')
@endsection