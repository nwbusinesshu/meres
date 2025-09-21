@extends('layouts.master')

@section('head-extra')
<style>
.competency-translation-status {
  display: flex;
  gap: 0.25rem;
  margin-left: auto;
  margin-right: 1rem;
}

.language-indicator {
  display: inline-block;
  padding: 0.2rem 0.4rem;
  font-size: 0.7rem;
  font-weight: bold;
  border-radius: 3px;
  cursor: pointer;
  min-width: 2rem;
  text-align: center;
}

.language-indicator.original {
  background-color: #007bff;
  color: white;
}

.language-indicator.available {
  background-color: #28a745;
  color: white;
}

.language-indicator.missing {
  background-color: #6c757d;
  color: white;
  opacity: 0.6;
}

.language-indicator.partial {
  background-color: #ffc107;
  color: black;
}

.current-language-info {
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  padding: 0.5rem;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}

.competency-item .bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.competency-item .bar > span {
  cursor: pointer;
  flex: 1;
}
</style>
@endsection

@section('content')
<h1>{{ __('admin/competencies.global-competencies') }}</h1>

{{-- Language Context Information --}}
<div class="current-language-info">
  <i class="fas fa-info-circle"></i>
  <strong>{{ __('translations.current-language') }}:</strong> 
  {{ $languageNames[$currentLocale] ?? strtoupper($currentLocale) }}
  <small class="text-muted ml-2">
    {{ __('translations.viewing-in-language', ['language' => $languageNames[$currentLocale] ?? strtoupper($currentLocale)]) }}
  </small>
</div>

<div class="fixed-row">
  <div class="tile tile-info competency-search">
    <span>{{ __('admin/competencies.search') }}</span>
    <div>
      <input type="text" class="form-control competency-search-input" placeholder="{{ __('admin/competencies.search') }}...">
      <i class="fa fa-ban competency-clear-search" data-tippy-content="{{ __('admin/competencies.search-clear') }}"></i>
    </div>
  </div>
  <div class="tile tile-button create-competency">
    <span><i class="fa fa-circle-plus"></i>{{ __('admin/competencies.create-competency') }}</span>
  </div>
</div>

<div class="competency-list competency-list--global-crud">
  @forelse ($globals as $comp)
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
        </div>
        
        <div class="competency-actions" style="display: flex; gap: 0.5rem;">
          {{-- Translation Management Button --}}
          <button class="btn btn-outline-info btn-sm manage-translations" 
                  data-competency-id="{{ $comp->id }}"
                  data-tippy-content="{{ __('translations.manage-translations') }}">
            <i class="fas fa-language"></i>
          </button>
          
          <button class="btn btn-outline-danger remove-competency" 
                  data-tippy-content="{{ __('admin/competencies.remove-competency') }}">
            <i class="fa fa-trash-alt"></i>
          </button>
          <button class="btn btn-outline-warning modify-competency" 
                  data-tippy-content="{{ __('admin/competencies.modify-competency') }}">
            <i class="fa fa-file-pen"></i>
          </button>
        </div>
      </div>

      <div class="questions hidden">
        <div class="tile tile-button create-question">{{ __('admin/competencies.create-question') }}</div>

        @foreach ($comp->questions as $q)
          <div class="question-item" data-id="{{ $q->id }}">
            <div class="question-header">
              <span>{{ __('admin/competencies.question') }} #{{ $loop->index+1 }}</span>
              
              {{-- Question Translation Status --}}
              <div class="question-translation-status">
                @foreach($availableLanguages as $lang)
                  @php
                    $hasTranslation = $q->hasTranslation($lang);
                    $isOriginal = $lang === $q->original_language;
                    $isComplete = $q->isTranslationComplete($lang);
                    $isPartial = $q->hasPartialTranslation($lang);
                    
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
                  <span class="language-indicator {{ $statusClass }}" 
                        data-tippy-content="{{ $tooltip }} ({{ $languageNames[$lang] ?? strtoupper($lang) }})">
                    {{ strtoupper($lang) }}
                  </span>
                @endforeach
              </div>
              
              <div>
                <button class="btn btn-outline-danger remove-question" 
                        data-tippy-content="{{ __('admin/competencies.remove-question') }}">
                  <i class="fa fa-trash-alt"></i>
                </button>
                <button class="btn btn-outline-warning modify-question" 
                        data-tippy-content="{{ __('admin/competencies.modify-question') }}">
                  <i class="fa fa-file-pen"></i>
                </button>
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
      <span>{{ __('admin/competencies.no-competencies') }}</span>
    </div>
  @endforelse
</div>

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

@section('modals')
  @include('admin.modals.competency')
  @include('admin.modals.competencyq')
@endsection

@section('scripts')
@include('js.superadmin.global-competencies')

<script>
// Translation management functions for superadmin
function openCompetencyTranslationModal(competencyId) {
  swal_loader.fire();
  
  $.ajax({
    url: "{{ route('superadmin.competency.translations.get') }}",
    method: 'POST',
    data: { id: competencyId },
    success: function(response) {
      buildTranslationManagementContent(response);
      $('#translation-management-modal').modal('show');
      swal_loader.close();
    },
    error: function(xhr) {
      swal_loader.close();
      alert(xhr.responseJSON?.error || '{{ __('global.error-occurred') }}');
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
        ${!isOriginal ? '<small class="form-text text-muted">{{ __('translations.leave-empty-to-remove') }}</small>' : ''}
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
    url: "{{ route('superadmin.competency.translations.save') }}",
    method: 'POST',
    data: {
      id: competencyId,
      translations: translations
    },
    success: function(response) {
      if (response.success) {
        $('#translation-management-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('translations.translations-saved') }}'
        }).then(() => {
          location.reload();
        });
      }
    },
    error: function(xhr) {
      swal_loader.close();
      alert(xhr.responseJSON?.error || '{{ __('global.error-occurred') }}');
    }
  });
}

function translateCompetencyWithAI(competencyId) {
  const missingLanguages = [];
  $('.translation-input').each(function() {
    const lang = $(this).data('language');
    const value = $(this).val().trim();
    const isOriginal = $(this).prop('required');
    if (!value && !isOriginal) {
      missingLanguages.push(lang);
    }
  });
  
  if (missingLanguages.length === 0) {
    alert('{{ __('translations.no-missing-translations') }}');
    return;
  }
  
  swal_loader.fire();
  
  $.ajax({
    url: "{{ route('superadmin.competency.translations.ai') }}",
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
        
        swal_success.fire({
          title: '{{ __('translations.ai-translation-complete') }}'
        });
      } else {
        swal_loader.close();
        alert(response.error || '{{ __('translations.ai-translation-failed') }}');
      }
    },
    error: function(xhr) {
      swal_loader.close();
      alert(xhr.responseJSON?.error || '{{ __('translations.ai-translation-failed') }}');
    }
  });
}

// Bind translation management button click
$(document).ready(function() {
  $(document).on('click', '.manage-translations', function() {
    const competencyId = $(this).data('competency-id');
    openCompetencyTranslationModal(competencyId);
  });
  
  // Initialize tooltips for language indicators
  tippy('.language-indicator[data-tippy-content]');
});
</script>
@endsection