@extends('layouts.master')

@section('head-extra')
<style>
.translation-badge {
  font-size: 0.7em;
  margin-left: 5px;
}

.competency-item {
  position: relative;
}

.competency-translation-status {
  position: absolute;
  top: 10px;
  right: 50px;
  font-size: 0.8em;
}

.question-translation-status {
  margin-left: 10px;
  font-size: 0.8em;
}

.language-indicator {
  display: inline-block;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  text-align: center;
  line-height: 20px;
  font-size: 0.7em;
  font-weight: bold;
  margin-right: 3px;
}

.language-indicator.available { background-color: #28a745; color: white; }
.language-indicator.partial { background-color: #ffc107; color: black; }
.language-indicator.missing { background-color: #dc3545; color: white; }
.language-indicator.original { background-color: #007bff; color: white; }
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
</div>

@php
  // The controller should provide 'globals' and 'orgCompetencies' variables.
  // For backward compatibility: if only $competencies exists, split here.
  $globals = $globals ?? (isset($competencies) ? $competencies->where('organization_id', null) : collect());
  $orgCompetencies = $orgCompetencies ?? (isset($competencies) ? $competencies->where('organization_id', session('org_id')) : collect());
  
  // Get available languages for display
  $availableLanguages = \App\Services\LanguageService::getAvailableLanguages();
  $languageNames = \App\Services\LanguageService::getLanguageNames();
  $currentLocale = app()->getLocale();
@endphp

{{-- ============================ --}}
{{-- Organization Competencies (CRUD) --}}
{{-- ============================ --}}
<div class="competency-list competency-list--org">
  <div class="tile tile-info" style="margin-top: 20px;">
    <span><strong>{{ __('admin/competencies.org-competencies') }}</strong></span>
    <div class="float-right">
      <small>
        {{ __('translations.current-language') }}: 
        <span class="badge badge-primary">{{ $languageNames[$currentLocale] ?? strtoupper($currentLocale) }}</span>
      </small>
    </div>
  </div>

  @forelse ($orgCompetencies as $comp)
    <div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->getTranslatedName() }}">
      <div class="bar">
        <span><i class="fa fa-caret-down"></i>{{ $comp->getTranslatedName() }}</span>
        
        {{-- Translation Status Indicators --}}
        <div class="competency-translation-status">
          @foreach($availableLanguages as $lang)
            @php
              $hasTranslation = $comp->hasTranslation($lang);
              $isOriginal = $lang === $comp->original_language;
              $statusClass = $isOriginal ? 'original' : ($hasTranslation ? 'available' : 'missing');
              $tooltip = $isOriginal ? __('translations.original') : 
                        ($hasTranslation ? __('translations.available') : __('translations.missing'));
            @endphp
            <span class="language-indicator {{ $statusClass }}" 
                  data-tippy-content="{{ $tooltip }} ({{ $languageNames[$lang] ?? strtoupper($lang) }})">
              {{ strtoupper($lang) }}
            </span>
          @endforeach
          <button class="btn btn-outline-info btn-sm ml-2" 
                  onclick="openCompetencyTranslationModal({{ $comp->id }})"
                  data-tippy-content="{{ __('translations.manage-translations') }}">
            <i class="fa fa-language"></i>
          </button>
        </div>
        
        <button class="btn btn-outline-danger remove-competency" data-tippy-content="{{ $_('remove-competency') }}"><i class="fa fa-trash-alt"></i></button>
        <button class="btn btn-outline-warning modify-competency" data-tippy-content="{{ $_('modify-competency') }}"><i class="fa fa-file-pen"></i></button>
      </div>

      <div class="questions hidden">
        <div class="tile tile-button create-question">{{ $_('create-question') }}</div>

        @foreach ($comp->questions as $q)
          <div class="question-item" data-id="{{ $q->id }}">
            <div>
              <span>{{ $_('question') }} #{{ $loop->index+1 }}</span>
              
              {{-- Question Translation Status --}}
              <div class="question-translation-status">
                @foreach($availableLanguages as $lang)
                  @php
                    $isComplete = $q->isTranslationComplete($lang);
                    $isPartial = $q->hasPartialTranslation($lang);
                    $isOriginal = $lang === $q->original_language;
                    
                    if ($isOriginal) {
                      $statusClass = 'original';
                      $tooltip = __('translations.original');
                    } elseif ($isComplete) {
                      $statusClass = 'available';
                      $tooltip = __('translations.complete');
                    } elseif ($isPartial) {
                      $statusClass = 'partial';
                      $tooltip = __('translations.incomplete');
                    } else {
                      $statusClass = 'missing';
                      $tooltip = __('translations.missing');
                    }
                  @endphp
                  <span class="language-indicator {{ $statusClass }}" 
                        data-tippy-content="{{ $tooltip }} ({{ $languageNames[$lang] ?? strtoupper($lang) }})">
                    {{ strtoupper($lang) }}
                  </span>
                @endforeach
              </div>
              
              <div>
                <button class="btn btn-outline-danger remove-question" data-tippy-content="{{ $_('remove-question') }}"><i class="fa fa-trash-alt"></i></button>
                <button class="btn btn-outline-warning modify-question" data-tippy-content="{{ $_('modify-question') }}"><i class="fa fa-file-pen"></i></button>
              </div>
            </div>

            <div class="question-details">
              <p><strong>{{ __('admin/competencies.question') }}:</strong> {{ $q->getTranslatedQuestion() }}</p>
              <p><strong>{{ __('admin/competencies.question-self') }}:</strong> {{ $q->getTranslatedQuestionSelf() }}</p>
              <div class="scale-info">
                <span class="badge badge-info">{{ $q->getTranslatedMinLabel() }}</span>
                <span class="mx-2">1 - {{ $q->max_value }}</span>
                <span class="badge badge-success">{{ $q->getTranslatedMaxLabel() }}</span>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @empty
    <div class="tile no-competency">
      <span>{{ $_('no-competencies') }}</span>
    </div>
  @endforelse
</div>

{{-- ============================ --}}
{{-- Global Competencies (Read-only) --}}
{{-- ============================ --}}
<div class="competency-list competency-list--global">
  <div class="tile tile-info" style="margin-top: 40px;">
    <span><strong>{{ __('admin/competencies.global-competencies') }}</strong></span>
    <small class="text-muted ml-2">{{ __('admin/competencies.global-competencies-help') }}</small>
  </div>

  @forelse ($globals as $comp)
    <div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->getTranslatedName() }}">
      <div class="bar">
        <span><i class="fa fa-caret-down"></i>{{ $comp->getTranslatedName() }}</span>
        
        {{-- Translation Status for Global Competencies --}}
        <div class="competency-translation-status">
          @foreach($availableLanguages as $lang)
            @php
              $hasTranslation = $comp->hasTranslation($lang);
              $isOriginal = $lang === $comp->original_language;
              $statusClass = $isOriginal ? 'original' : ($hasTranslation ? 'available' : 'missing');
            @endphp
            <span class="language-indicator {{ $statusClass }}" 
                  data-tippy-content="{{ $languageNames[$lang] ?? strtoupper($lang) }}">
              {{ strtoupper($lang) }}
            </span>
          @endforeach
        </div>
      </div>

      <div class="questions hidden">
        @foreach ($comp->questions as $q)
          <div class="question-item" data-id="{{ $q->id }}">
            <div>
              <span>{{ $_('question') }} #{{ $loop->index+1 }}</span>
              
              {{-- Global Question Translation Status --}}
              <div class="question-translation-status">
                @foreach($availableLanguages as $lang)
                  @php
                    $isComplete = $q->isTranslationComplete($lang);
                    $isPartial = $q->hasPartialTranslation($lang);
                    $isOriginal = $lang === $q->original_language;
                    
                    if ($isOriginal) {
                      $statusClass = 'original';
                    } elseif ($isComplete) {
                      $statusClass = 'available';
                    } elseif ($isPartial) {
                      $statusClass = 'partial';
                    } else {
                      $statusClass = 'missing';
                    }
                  @endphp
                  <span class="language-indicator {{ $statusClass }}">
                    {{ strtoupper($lang) }}
                  </span>
                @endforeach
              </div>
            </div>

            <div class="question-details">
              <p><strong>{{ __('admin/competencies.question') }}:</strong> {{ $q->getTranslatedQuestion() }}</p>
              <p><strong>{{ __('admin/competencies.question-self') }}:</strong> {{ $q->getTranslatedQuestionSelf() }}</p>
              <div class="scale-info">
                <span class="badge badge-info">{{ $q->getTranslatedMinLabel() }}</span>
                <span class="mx-2">1 - {{ $q->max_value }}</span>
                <span class="badge badge-success">{{ $q->getTranslatedMaxLabel() }}</span>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @empty
    <div class="tile no-competency">
      <span>{{ __('admin/competencies.no-global-competencies') }}</span>
    </div>
  @endforelse
</div>

{{-- Include the modals --}}
@include('admin.modals.competency')
@include('admin.modals.competencyq')

{{-- Translation Management Modal --}}
<div class="modal fade" id="translation-management-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('translations.manage-translations') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div id="translation-content">
          <!-- Content will be loaded dynamically -->
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@section('scripts')
@include('js.admin.competencies')

<script>
function openCompetencyTranslationModal(competencyId) {
  swal_loader.fire();
  
  $.ajax({
    url: "{{ route('admin.competency.translations.get') }}",
    method: 'POST',
    data: { id: competencyId },
    success: function(response) {
      buildTranslationManagementContent(response);
      $('#translation-management-modal').modal('show');
      swal_loader.close();
    },
    error: function() {
      swal_loader.close();
      alert('{{ __('global.error-occurred') }}');
    }
  });
}

function buildTranslationManagementContent(data) {
  const availableLanguages = {!! json_encode($availableLanguages) !!};
  const languageNames = {!! json_encode($languageNames) !!};
  
  let content = `
    <div class="competency-translation-manager">
      <h6>{{ __('admin/competencies.competency-translations') }}</h6>
      <div class="translation-grid">
  `;
  
  availableLanguages.forEach(lang => {
    const translation = data.translations[lang] || {};
    const isOriginal = lang === data.original_language;
    const langName = languageNames[lang] || lang.toUpperCase();
    
    content += `
      <div class="form-group">
        <label>
          ${langName}
          ${isOriginal ? '<span class="badge badge-primary ml-1">{{ __('translations.original') }}</span>' : ''}
        </label>
        <input type="text" 
               class="form-control translation-input" 
               data-language="${lang}"
               value="${translation.name || ''}"
               ${isOriginal ? 'required' : ''}>
      </div>
    `;
  });
  
  content += `
      </div>
      <div class="mt-3">
        <button class="btn btn-primary btn-sm" onclick="saveCompetencyTranslations(${data.competency_id || 'null'})">
          {{ __('translations.save') }}
        </button>
        <button class="btn btn-info btn-sm ml-2" onclick="translateCompetencyWithAI(${data.competency_id || 'null'})">
          <i class="fa fa-robot"></i> {{ __('translations.translate-with-ai') }}
        </button>
      </div>
    </div>
  `;
  
  $('#translation-content').html(content);
}

function saveCompetencyTranslations(competencyId) {
  const translations = {};
  
  $('.translation-input').each(function() {
    const lang = $(this).data('language');
    const value = $(this).val().trim();
    if (value) {
      translations[lang] = value;
    }
  });
  
  swal_loader.fire();
  
  $.ajax({
    url: "{{ route('admin.competency.translations.save') }}",
    method: 'POST',
    data: {
      id: competencyId,
      translations: translations
    },
    success: function(response) {
      if (response.success) {
        $('#translation-management-modal').modal('hide');
        location.reload();
      }
    },
    error: function() {
      swal_loader.close();
      alert('{{ __('global.error-occurred') }}');
    }
  });
}

function translateCompetencyWithAI(competencyId) {
  const missingLanguages = [];
  $('.translation-input').each(function() {
    const lang = $(this).data('language');
    const value = $(this).val().trim();
    if (!value && lang !== 'hu') { // Don't include original language
      missingLanguages.push(lang);
    }
  });
  
  if (missingLanguages.length === 0) {
    alert('{{ __('translations.no-missing-translations') }}');
    return;
  }
  
  swal_loader.fire();
  
  $.ajax({
    url: "{{ route('admin.competency.translations.ai') }}",
    method: 'POST',
    data: {
      id: competencyId,
      languages: missingLanguages
    },
    success: function(response) {
      if (response.success && response.translations) {
        // Apply AI translations to inputs
        Object.keys(response.translations).forEach(lang => {
          $(`.translation-input[data-language="${lang}"]`).val(response.translations[lang]);
        });
        swal_loader.close();
        alert('{{ __('translations.ai-translation-complete') }}');
      } else {
        swal_loader.close();
        alert(response.error || '{{ __('translations.ai-translation-failed') }}');
      }
    },
    error: function() {
      swal_loader.close();
      alert('{{ __('translations.ai-translation-failed') }}');
    }
  });
}

// Initialize tooltips for language indicators
$(document).ready(function() {
  tippy('.language-indicator[data-tippy-content]');
});
</script>
@endsection