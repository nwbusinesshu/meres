{{-- Competency Question Modal --}}
<div class="modal fade" id="competencyq-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/competencies.create-question') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        
        {{-- Single Language Mode (Default) --}}
        <div class="single-language-mode">
          <div class="form-group">
            <label>{{ __('admin/competencies.question') }} <span class="text-danger">*</span></label>
            <textarea class="form-control question" rows="2" required 
                      placeholder="{{ __('admin/competencies.question-placeholder') }}"></textarea>
            <small class="form-text text-muted">{{ __('admin/competencies.question-help') }}</small>
          </div>

          <div class="form-group">
            <label>{{ __('admin/competencies.question-self') }} <span class="text-danger">*</span></label>
            <textarea class="form-control question-self" rows="2" required 
                      placeholder="{{ __('admin/competencies.question-self-placeholder') }}"></textarea>
            <small class="form-text text-muted">{{ __('admin/competencies.question-self-help') }}</small>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>{{ __('admin/competencies.min-label') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control min-label" required 
                       placeholder="{{ __('admin/competencies.min-label-placeholder') }}">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>{{ __('admin/competencies.max-label') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control max-label" required 
                       placeholder="{{ __('admin/competencies.max-label-placeholder') }}">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>{{ __('admin/competencies.scale') }} <span class="text-danger">*</span></label>
                <select class="form-control scale" required>
                  <option value="">{{ __('admin/competencies.select-scale') }}</option>
                  @for($i = 3; $i <= 10; $i++)
                    <option value="{{ $i }}" @if($i == 7) selected @endif>1 - {{ $i }}</option>
                  @endfor
                </select>
              </div>
            </div>
          </div>
        </div>

        {{-- Multi Language Mode (Translation Mode) --}}
        <div class="multi-language-mode" style="display: none;">
          
          {{-- Language Tabs --}}
          <div class="language-tabs">
            <ul class="nav nav-tabs" id="question-language-nav">
              {{-- Language tabs will be generated dynamically --}}
            </ul>
          </div>

          {{-- Tab Content --}}
          <div class="tab-content" id="question-language-content">
            {{-- Language content will be generated dynamically --}}
          </div>

          {{-- Shared Scale Field --}}
          <div class="form-group mt-3">
            <label>{{ __('admin/competencies.scale') }} <span class="text-danger">*</span></label>
            <select class="form-control scale-shared" required>
              <option value="">{{ __('admin/competencies.select-scale') }}</option>
              @for($i = 3; $i <= 10; $i++)
                <option value="{{ $i }}" @if($i == 7) selected @endif>1 - {{ $i }}</option>
              @endfor
            </select>
          </div>

          {{-- Translation Warnings --}}
          <div class="translation-warnings mt-3" style="display: none;">
            <div class="alert alert-warning">
              <ul id="translation-warning-list"></ul>
            </div>
          </div>
        </div>

        {{-- Translation Controls --}}
        <div class="translation-controls mt-3">
          <div class="row">
            <div class="col-md-6">
              <button type="button" class="btn btn-outline-info btn-sm" id="create-question-translations-btn">
                <i class="fa fa-language"></i> {{ __('translations.create-translations') }}
              </button>
            </div>
            <div class="col-md-6 text-right">
              <button type="button" class="btn btn-outline-primary btn-sm" id="translate-question-ai-btn">
                <i class="fa fa-robot"></i> {{ __('translations.ai-translate') }}
              </button>
            </div>
          </div>
        </div>

        {{-- Language Selection Interface --}}
        <div class="question-language-selection mt-3" style="display: none;">
          <h6>{{ __('translations.select-languages-to-create') }}</h6>
          <div class="row" id="question-language-checkboxes">
            {{-- Language checkboxes will be generated dynamically --}}
          </div>
          <div class="row mt-3">
            <div class="col-md-6">
              <button class="btn btn-secondary btn-sm" id="cancel-question-language-selection">
                {{ __('global.cancel') }}
              </button>
            </div>
            <div class="col-md-6 text-right">
              <button class="btn btn-primary btn-sm" id="confirm-question-language-selection">
                {{ __('global.confirm') }}
              </button>
              <button class="btn btn-info btn-sm ml-1" id="confirm-and-translate-question">
                <i class="fa fa-robot"></i> {{ __('translations.confirm-and-translate') }}
              </button>
            </div>
          </div>
        </div>

        {{-- Action Buttons --}}
        <div class="action-buttons mt-4">
          <button class="btn btn-primary save-competencyq">{{ __('admin/competencies.question-save') }}</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- JavaScript for Competency Question Modal --}}
<script>
// Global variables for competency question modal
let questionModalData = {
  id: null,
  competencyId: null,
  isEditMode: false,
  currentLanguage: 'hu',
  availableLanguages: ['hu', 'en'],
  languageNames: { 'hu': 'Magyar', 'en': 'English' },
  translations: {},
  originalLanguage: 'hu',
  isTranslationMode: false,
  maxValue: 7
};

// Helper functions
function isInSuperAdminContext() {
  return window.location.pathname.includes('/superadmin');
}

function getCurrentAppLocale() {
  return window.currentLocale || 'hu';
}

function getSystemLanguages() {
  return window.availableLanguages || ['hu', 'en'];
}

// Main modal functions
function openCompetencyQModal(id = null, compId = null) {
  swal_loader.fire();
  
  // Reset modal state
  questionModalData.id = id;
  questionModalData.competencyId = compId;
  questionModalData.isEditMode = id !== null;
  questionModalData.isTranslationMode = false;
  questionModalData.translations = {};
  questionModalData.availableLanguages = getSystemLanguages();
  questionModalData.languageNames = window.languageNames || {'hu': 'Magyar', 'en': 'English'};
  
  // Set modal title
  $('#competencyq-modal .modal-title').html(
    id == null ? '{{ __('admin/competencies.create-question') }}' : '{{ __('admin/competencies.modify-question') }}'
  );
  
  // Set data attributes
  $('#competencyq-modal').attr('data-id', id ?? 0);
  $('#competencyq-modal').attr('data-compid', compId ?? 0);
  
  if (questionModalData.isEditMode) {
    // Load existing question
    loadQuestionData(id);
  } else {
    // New question - show simple form
    showQuestionSingleLanguageMode();
    clearQuestionForm();
    questionModalData.currentLanguage = getCurrentAppLocale();
    swal_loader.close();
    $('#competencyq-modal').modal();
  }
}

function loadQuestionData(questionId) {
  // Try to load with translations first, fallback to simple load
  const routeName = isInSuperAdminContext() ? 
    window.routes?.superadmin_competency_question_translations_get : 
    window.routes?.admin_competency_question_translations_get;
    
  if (routeName) {
    $.ajax({
      url: routeName,
      method: 'POST',
      data: { 
        id: questionId,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        questionModalData.translations = response.translations;
        questionModalData.originalLanguage = response.original_language;
        
        // Check if we have multiple languages to show
        const hasMultipleLanguages = Object.keys(response.translations).filter(lang => 
          response.translations[lang].exists
        ).length > 1;
        
        if (hasMultipleLanguages) {
          showQuestionMultiLanguageMode();
        } else {
          showQuestionSingleLanguageMode();
          loadQuestionIntoSimpleForm(response.translations);
        }
        
        swal_loader.close();
        $('#competencyq-modal').modal();
      },
      error: function() {
        // Fallback - load question data the old way
        loadQuestionFallback(questionId);
      }
    });
  } else {
    loadQuestionFallback(questionId);
  }
}

function loadQuestionFallback(questionId) {
  const routeName = isInSuperAdminContext() ? 
    window.routes?.superadmin_competency_q_get : 
    window.routes?.admin_competency_question_get;
    
  if (routeName) {
    $.ajax({
      url: routeName,
      method: 'GET',
      data: { id: questionId },
      success: function(question) {
        showQuestionSingleLanguageMode();
        $('#competencyq-modal .question').val(question.question);
        $('#competencyq-modal .question-self').val(question.question_self);
        $('#competencyq-modal .min-label').val(question.min_label);
        $('#competencyq-modal .max-label').val(question.max_label);
        $('#competencyq-modal .scale').val(question.max_value);
        questionModalData.maxValue = question.max_value;
        
        swal_loader.close();
        $('#competencyq-modal').modal();
      },
      error: function() {
        swal_loader.close();
        alert('{{ __('global.error-occurred') }}');
      }
    });
  } else {
    swal_loader.close();
    alert('Route not configured');
  }
}

function loadQuestionIntoSimpleForm(translations) {
  const currentLang = getCurrentAppLocale();
  const translation = translations[currentLang] || translations[questionModalData.originalLanguage] || {};
  
  $('#competencyq-modal .question').val(translation.question || '');
  $('#competencyq-modal .question-self').val(translation.question_self || '');
  $('#competencyq-modal .min-label').val(translation.min_label || '');
  $('#competencyq-modal .max-label').val(translation.max_label || '');
  $('#competencyq-modal .scale').val(questionModalData.maxValue || 7);
}

function showQuestionSingleLanguageMode() {
  $('.single-language-mode').show();
  $('.multi-language-mode').hide();
  $('.language-tabs').hide();
  $('.translation-controls').show();
  $('.translation-warnings').hide();
  questionModalData.isTranslationMode = false;
}

function showQuestionMultiLanguageMode() {
  $('.single-language-mode').hide();
  $('.multi-language-mode').show();
  $('.language-tabs').show();
  $('.translation-controls').show();
  questionModalData.isTranslationMode = true;
  
  buildQuestionLanguageTabs();
  buildQuestionLanguageContent();
  validateAllTranslations();
}

function clearQuestionForm() {
  $('#competencyq-modal input, #competencyq-modal textarea, #competencyq-modal select').val('');
  $('#competencyq-modal .scale').val('7');
}

function buildQuestionLanguageTabs() {
  const navContainer = $('#question-language-nav');
  navContainer.empty();
  
  questionModalData.availableLanguages.forEach((lang, index) => {
    const isActive = index === 0;
    const isOriginal = lang === questionModalData.originalLanguage;
    const translation = questionModalData.translations[lang] || {};
    const isComplete = translation.is_complete || false;
    const isPartial = translation.is_partial || false;
    const langName = questionModalData.languageNames[lang] || lang.toUpperCase();
    
    let badgeClass = 'badge-secondary';
    let badgeText = 'MISS';
    
    if (isOriginal) {
      badgeClass = 'badge-primary';
      badgeText = 'ORIG';
    } else if (isComplete) {
      badgeClass = 'badge-success';
      badgeText = 'OK';
    } else if (isPartial) {
      badgeClass = 'badge-warning';
      badgeText = 'PART';
    }
    
    const tab = $(`
      <li class="nav-item">
        <a class="nav-link ${isActive ? 'active' : ''}" 
           data-toggle="tab" 
           href="#question-lang-${lang}">
          ${langName}
          <span class="badge ${badgeClass} ml-1">${badgeText}</span>
        </a>
      </li>
    `);
    
    navContainer.append(tab);
  });
}

function buildQuestionLanguageContent() {
  const contentContainer = $('#question-language-content');
  contentContainer.empty();
  
  questionModalData.availableLanguages.forEach((lang, index) => {
    const isActive = index === 0;
    const translation = questionModalData.translations[lang] || {};
    
    const content = $(`
      <div class="tab-pane ${isActive ? 'active' : ''}" id="question-lang-${lang}">
        <div class="form-group">
          <label>{{ __('admin/competencies.question') }} <span class="text-danger">*</span></label>
          <textarea class="form-control question-translated" 
                    data-field="question" 
                    rows="2">${translation.question || ''}</textarea>
        </div>
        <div class="form-group">
          <label>{{ __('admin/competencies.question-self') }} <span class="text-danger">*</span></label>
          <textarea class="form-control question-self-translated" 
                    data-field="question_self" 
                    rows="2">${translation.question_self || ''}</textarea>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label>{{ __('admin/competencies.min-label') }} <span class="text-danger">*</span></label>
              <input type="text" 
                     class="form-control min-label-translated" 
                     data-field="min_label"
                     value="${translation.min_label || ''}">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label>{{ __('admin/competencies.max-label') }} <span class="text-danger">*</span></label>
              <input type="text" 
                     class="form-control max-label-translated" 
                     data-field="max_label"
                     value="${translation.max_label || ''}">
            </div>
          </div>
        </div>
      </div>
    `);
    
    contentContainer.append(content);
  });
}

function validateAllTranslations() {
  // Validation logic will be implemented based on needs
  return true;
}

// Save functions
function saveQuestionSimple() {
  const question = $('#competencyq-modal .question').val().trim();
  const questionSelf = $('#competencyq-modal .question-self').val().trim();
  const minLabel = $('#competencyq-modal .min-label').val().trim();
  const maxLabel = $('#competencyq-modal .max-label').val().trim();
  const scale = $('#competencyq-modal .scale').val();
  
  if (!question || !questionSelf || !minLabel || !maxLabel || !scale) {
    alert('{{ __('admin/competencies.all-fields-required') }}');
    return;
  }
  
  swal_confirm.fire({
    title: '{{ __('admin/competencies.question-save-confirm') }}'
  }).then((result) => {
    if (result.isConfirmed) {
      swal_loader.fire();
      const routeName = isInSuperAdminContext() ? 
        window.routes?.superadmin_competency_q_save : 
        window.routes?.admin_competency_question_save;
        
      if (routeName) {
        $.ajax({
          url: routeName,
          method: 'POST',
          data: {
            id: questionModalData.id,
            compId: questionModalData.competencyId,
            question: question,
            questionSelf: questionSelf,
            minLabel: minLabel,
            maxLabel: maxLabel,
            scale: scale,
            _token: $('meta[name="csrf-token"]').attr('content')
          },
          success: function(response) {
            swal_loader.close();
            $('#competencyq-modal').modal('hide');
            swal_success.fire({
              title: "{{ __('admin/competencies.question-save-success') }}"
            }).then(() => {
              location.reload();
            });
          },
          error: function() {
            swal_loader.close();
            alert('{{ __('global.error-occurred') }}');
          }
        });
      } else {
        swal_loader.close();
        alert('Route not configured');
      }
    }
  });
}

function saveQuestionWithTranslations() {
  // This function will handle saving with translations
  // Implementation depends on the specific requirements
  alert('Translation save function not yet implemented');
}

// Event handlers
$(document).ready(function() {
  
  // Save question (updated to handle translations)
  $('#competencyq-modal .save-competencyq').click(function() {
    if (questionModalData.isTranslationMode) {
      saveQuestionWithTranslations();
    } else {
      saveQuestionSimple();
    }
  });
  
  // Create translations button
  $('#create-question-translations-btn').click(function() {
    if (questionModalData.isEditMode) {
      // For existing questions, show language selection
      alert('Translation creation for existing questions not yet implemented');
    } else {
      // For new questions, switch to translation mode immediately
      questionModalData.isTranslationMode = true;
      showQuestionMultiLanguageMode();
      // Initialize empty translations logic here
    }
  });
  
  // AI translation button
  $('#translate-question-ai-btn').click(function() {
    alert('AI translation not yet implemented');
  });
  
});
</script>