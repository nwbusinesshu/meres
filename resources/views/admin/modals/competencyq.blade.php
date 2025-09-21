<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="competencyq-modal">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        
        <!-- Language Tabs -->
        <div class="language-tabs" style="display: none;">
          <nav class="nav nav-pills nav-fill mb-3" id="question-language-nav">
            <!-- Language tabs will be populated dynamically -->
          </nav>
        </div>

        <!-- Single Language Mode (default) -->
        <div class="single-language-mode">
          <div class="form-row">
            <div class="form-group full">
              <label>{{ __('admin/competencies.question') }}</label>
              <textarea class="form-control question" rows="4" maxlength="1024" style="resize: none;"></textarea>
              <small class="form-text text-muted">{{ __('admin/competencies.question-help') }}</small>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group full">
              <label>{{ __('admin/competencies.question-self') }}</label>
              <textarea class="form-control question-self" rows="4" maxlength="1024" style="resize: none;"></textarea>
              <small class="form-text text-muted">{{ __('admin/competencies.question-self-help') }}</small>
            </div>
          </div>
          <div class="form-row flex">
            <div class="form-group">
              <label>{{ __('admin/competencies.min-label') }}</label>
              <input type="text" class="form-control min-label"/>
            </div>
            <div class="form-group">
              <label>{{ __('admin/competencies.max-label') }}</label>
              <input type="text" class="form-control max-label"/>
            </div>
          </div>
          <div class="form-row centered">
            <div class="form-group">
              <label>{{ __('admin/competencies.scale') }}</label>
              <input class="form-control scale" type="number" step="1" min="3" max="10"/>
            </div>
          </div>
        </div>

        <!-- Multi Language Mode -->
        <div class="multi-language-mode" style="display: none;">
          <div class="tab-content" id="question-language-content">
            <!-- Language content will be populated dynamically -->
          </div>
          
          <!-- Scale setting (shared across languages) -->
          <div class="form-row centered mt-3">
            <div class="form-group">
              <label>{{ __('admin/competencies.scale') }}</label>
              <input class="form-control scale-shared" type="number" step="1" min="3" max="10"/>
              <small class="form-text text-muted">{{ __('admin/competencies.scale-shared-help') }}</small>
            </div>
          </div>
        </div>

        <!-- Translation Controls -->
        <div class="translation-controls" style="display: none;">
          <div class="form-row">
            <div class="col-md-6">
              <button class="btn btn-outline-primary btn-sm" id="create-question-translations-btn">
                <i class="fa fa-language"></i> {{ __('translations.create-translations') }}
              </button>
            </div>
            <div class="col-md-6 text-right">
              <button class="btn btn-outline-info btn-sm" id="translate-question-ai-btn" style="display: none;">
                <i class="fa fa-robot"></i> {{ __('translations.translate-with-ai') }}
              </button>
            </div>
          </div>
          
          <!-- Validation warnings -->
          <div class="translation-warnings mt-2" style="display: none;">
            <div class="alert alert-warning alert-sm" id="incomplete-translation-warning">
              <i class="fa fa-exclamation-triangle"></i>
              <span class="warning-text"></span>
            </div>
          </div>
        </div>

        <!-- Language Selection Modal for New Translations -->
        <div class="question-language-selection" style="display: none;">
          <h6>{{ __('translations.select-languages') }}</h6>
          <div class="form-check-list" id="question-language-checkboxes">
            <!-- Checkboxes will be populated dynamically -->
          </div>
          <div class="form-row mt-2">
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

        <!-- Action Buttons -->
        <div class="action-buttons">
          <button class="btn btn-primary save-competencyq">{{ __('admin/competencies.question-save') }}</button>
        </div>
      </div>
    </div>
  </div>
</div>

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

function openCompetencyQModal(id = null, compId = null) {
  swal_loader.fire();
  
  // Reset modal state
  questionModalData.id = id;
  questionModalData.competencyId = compId;
  questionModalData.isEditMode = id !== null;
  questionModalData.isTranslationMode = false;
  questionModalData.translations = {};
  
  // Set modal title
  $('#competencyq-modal .modal-title').html(
    id == null ? '{{ __('admin/competencies.create-question') }}' : '{{ __('admin/competencies.modify-question') }}'
  );
  
  // Set competency ID
  $('#competencyq-modal').attr('data-id', id ?? 0);
  $('#competencyq-modal').attr('data-compid', compId ?? 0);
  
  if (questionModalData.isEditMode) {
    // Load existing question with translations
    loadQuestionWithTranslations(id);
  } else {
    // New question - show simple form
    showQuestionSingleLanguageMode();
    clearQuestionForm();
    questionModalData.currentLanguage = getCurrentAppLocale();
    swal_loader.close();
    $('#competencyq-modal').modal();
  }
}

function loadQuestionWithTranslations(questionId) {
  const routeName = isInSuperAdminContext() ? 
    '{{ route('superadmin.competency.question.translations.get') }}' : 
    '{{ route('admin.competency.question.translations.get') }}';
    
  $.ajax({
    url: routeName,
    method: 'POST',
    data: { id: questionId },
    success: function(response) {
      questionModalData.translations = response.translations;
      questionModalData.originalLanguage = response.original_language;
      questionModalData.availableLanguages = getSystemLanguages();
      
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
}

function loadQuestionFallback(questionId) {
  const routeName = isInSuperAdminContext() ? 
    '{{ route('superadmin.competency.q.get') }}' : 
    '{{ route('admin.competency.question.get') }}';
    
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
      <a class="nav-link ${isActive ? 'active' : ''}" 
         id="question-lang-${lang}-tab" 
         data-toggle="pill" 
         href="#question-lang-${lang}" 
         role="tab" 
         data-language="${lang}">
        ${langName}
        <span class="badge ${badgeClass} ml-1">${badgeText}</span>
      </a>
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
    const isOriginal = lang === questionModalData.originalLanguage;
    
    const content = $(`
      <div class="tab-pane fade ${isActive ? 'show active' : ''}" 
           id="question-lang-${lang}" 
           role="tabpanel">
        
        <div class="form-group">
          <label>
            {{ __('admin/competencies.question') }}
            ${isOriginal ? '<span class="badge badge-primary ml-1">{{ __('translations.original') }}</span>' : ''}
          </label>
          <textarea class="form-control question-translated" 
                    rows="4" 
                    maxlength="1024" 
                    style="resize: none;"
                    data-language="${lang}"
                    data-field="question"
                    ${isOriginal ? 'required' : ''}>${translation.question || ''}</textarea>
          <small class="form-text text-muted">{{ __('admin/competencies.question-help') }}</small>
        </div>
        
        <div class="form-group">
          <label>
            {{ __('admin/competencies.question-self') }}
            ${isOriginal ? '<span class="badge badge-primary ml-1">{{ __('translations.original') }}</span>' : ''}
          </label>
          <textarea class="form-control question-self-translated" 
                    rows="4" 
                    maxlength="1024" 
                    style="resize: none;"
                    data-language="${lang}"
                    data-field="question_self"
                    ${isOriginal ? 'required' : ''}>${translation.question_self || ''}</textarea>
          <small class="form-text text-muted">{{ __('admin/competencies.question-self-help') }}</small>
        </div>
        
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>
              {{ __('admin/competencies.min-label') }}
              ${isOriginal ? '<span class="badge badge-primary ml-1">{{ __('translations.original') }}</span>' : ''}
            </label>
            <input type="text" 
                   class="form-control min-label-translated" 
                   data-language="${lang}"
                   data-field="min_label"
                   value="${translation.min_label || ''}"
                   ${isOriginal ? 'required' : ''}/>
          </div>
          <div class="form-group col-md-6">
            <label>
              {{ __('admin/competencies.max-label') }}
              ${isOriginal ? '<span class="badge badge-primary ml-1">{{ __('translations.original') }}</span>' : ''}
            </label>
            <input type="text" 
                   class="form-control max-label-translated" 
                   data-language="${lang}"
                   data-field="max_label"
                   value="${translation.max_label || ''}"
                   ${isOriginal ? 'required' : ''}/>
          </div>
        </div>
        
        ${!isOriginal ? '<small class="form-text text-muted text-danger">{{ __('translations.all-fields-required-or-empty') }}</small>' : ''}
      </div>
    `);
    
    contentContainer.append(content);
  });
  
  // Set shared scale value
  const firstTranslation = Object.values(questionModalData.translations)[0] || {};
  questionModalData.maxValue = firstTranslation.max_value || 7;
  $('.scale-shared').val(questionModalData.maxValue);
}

function loadQuestionIntoSimpleForm(translations) {
  const currentLang = getCurrentAppLocale();
  const originalLang = questionModalData.originalLanguage;
  
  // Try current language first, then original, then first available
  let translation = translations[currentLang] || translations[originalLang];
  if (!translation) {
    translation = Object.values(translations)[0] || {};
  }
  
  $('#competencyq-modal .question').val(translation.question || '');
  $('#competencyq-modal .question-self').val(translation.question_self || '');
  $('#competencyq-modal .min-label').val(translation.min_label || '');
  $('#competencyq-modal .max-label').val(translation.max_label || '');
  $('#competencyq-modal .scale').val(translation.max_value || 7);
  questionModalData.maxValue = translation.max_value || 7;
}

function clearQuestionForm() {
  $('#competencyq-modal .question').val('');
  $('#competencyq-modal .question-self').val('');
  $('#competencyq-modal .min-label').val('');
  $('#competencyq-modal .max-label').val('');
  $('#competencyq-modal .scale').val(7);
  questionModalData.maxValue = 7;
}

// Validation functions
function validateAllTranslations() {
  const warnings = [];
  
  questionModalData.availableLanguages.forEach(lang => {
    const translation = questionModalData.translations[lang];
    if (translation && translation.is_partial) {
      const langName = questionModalData.languageNames[lang] || lang.toUpperCase();
      const missingFields = translation.missing_fields || [];
      warnings.push(`${langName}: missing ${missingFields.join(', ')}`);
    }
  });
  
  if (warnings.length > 0) {
    $('.translation-warnings').show();
    $('#incomplete-translation-warning .warning-text').text(
      '{{ __('translations.incomplete-translations') }}: ' + warnings.join('; ')
    );
  } else {
    $('.translation-warnings').hide();
  }
}

function validateCurrentTranslation() {
  if (!questionModalData.isTranslationMode) return true;
  
  const errors = [];
  
  // Check each language for completeness
  $('.tab-pane').each(function() {
    const lang = $(this).attr('id').replace('question-lang-', '');
    const fields = ['question', 'question_self', 'min_label', 'max_label'];
    const filledFields = [];
    const emptyFields = [];
    
    fields.forEach(field => {
      const input = $(this).find(`[data-field="${field}"]`);
      const value = input.val().trim();
      if (value) {
        filledFields.push(field);
      } else {
        emptyFields.push(field);
      }
    });
    
    // If some fields are filled but not all, it's an error
    if (filledFields.length > 0 && emptyFields.length > 0) {
      const langName = questionModalData.languageNames[lang] || lang.toUpperCase();
      errors.push(`${langName}: ${emptyFields.join(', ')} {{ __('translations.fields-required') }}`);
    }
    
    // Original language cannot be completely empty
    if (lang === questionModalData.originalLanguage && filledFields.length === 0) {
      errors.push('{{ __('translations.original-language-required') }}');
    }
  });
  
  if (errors.length > 0) {
    alert(errors.join('\n'));
    return false;
  }
  
  return true;
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
      showQuestionLanguageSelection();
    } else {
      // For new questions, switch to translation mode immediately
      questionModalData.isTranslationMode = true;
      showQuestionMultiLanguageMode();
      initializeEmptyQuestionTranslations();
    }
  });
  
  // AI translation button
  $('#translate-question-ai-btn').click(function() {
    showQuestionLanguageSelectionForAI();
  });
  
  // Language selection handlers for questions
  $('#confirm-question-language-selection').click(function() {
    const selectedLanguages = getSelectedQuestionLanguages();
    if (selectedLanguages.length > 0) {
      createQuestionTranslationsForLanguages(selectedLanguages);
    }
  });
  
  $('#confirm-and-translate-question').click(function() {
    const selectedLanguages = getSelectedQuestionLanguages();
    if (selectedLanguages.length > 0) {
      createQuestionTranslationsForLanguages(selectedLanguages, true);
    }
  });
  
  $('#cancel-question-language-selection').click(function() {
    $('.question-language-selection').hide();
    $('.action-buttons').show();
  });
  
  // Real-time validation for multi-language mode
  $(document).on('input', '.question-translated, .question-self-translated, .min-label-translated, .max-label-translated', function() {
    setTimeout(validateAllTranslations, 100);
  });
  
});

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
        '{{ route('superadmin.competency.q.save') }}' : 
        '{{ route('admin.competency.question.save') }}';
        
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
          scale: scale
        },
        successMessage: "{{ __('admin/competencies.question-save-success') }}",
      });
    }
  });
}

function saveQuestionWithTranslations() {
  if (!validateCurrentTranslation()) {
    return;
  }
  
  // Collect all translations
  const translations = {};
  const scale = $('.scale-shared').val();
  questionModalData.maxValue = scale;
  
  $('.tab-pane').each(function() {
    const lang = $(this).attr('id').replace('question-lang-', '');
    const fields = ['question', 'question_self', 'min_label', 'max_label'];
    const languageData = {};
    let hasAnyContent = false;
    
    fields.forEach(field => {
      const input = $(this).find(`[data-field="${field}"]`);
      const value = input.val().trim();
      languageData[field] = value;
      if (value) hasAnyContent = true;
    });
    
    if (hasAnyContent) {
      translations[lang] = languageData;
    }
  });
  
  // Validate original language
  if (!translations[questionModalData.originalLanguage]) {
    alert('{{ __('translations.original-language-required') }}');
    return;
  }
  
  swal_confirm.fire({
    title: '{{ __('admin/competencies.question-save-confirm') }}'
  }).then((result) => {
    if (result.isConfirmed) {
      swal_loader.fire();
      
      if (questionModalData.isEditMode) {
        // Save translations for existing question
        const routeName = isInSuperAdminContext() ? 
          '{{ route('superadmin.competency.question.translations.save') }}' : 
          '{{ route('admin.competency.question.translations.save') }}';
          
        $.ajax({
          url: routeName,
          method: 'POST',
          data: {
            id: questionModalData.id,
            translations: translations,
            scale: scale
          },
          successMessage: "{{ __('admin/competencies.question-save-success') }}",
        });
      } else {
        // Create new question with translation
        const originalTranslation = translations[questionModalData.originalLanguage];
        const routeName = isInSuperAdminContext() ? 
          '{{ route('superadmin.competency.q.save') }}' : 
          '{{ route('admin.competency.question.save') }}';
          
        $.ajax({
          url: routeName,
          method: 'POST',
          data: {
            compId: questionModalData.competencyId,
            question: originalTranslation.question,
            questionSelf: originalTranslation.question_self,
            minLabel: originalTranslation.min_label,
            maxLabel: originalTranslation.max_label,
            scale: scale
          },
          success: function(response) {
            if (response.success || response.ok) {
              // Now save additional translations if any
              const additionalTranslations = {...translations};
              delete additionalTranslations[questionModalData.originalLanguage];
              
              if (Object.keys(additionalTranslations).length > 0) {
                const translationRoute = isInSuperAdminContext() ? 
                  '{{ route('superadmin.competency.question.translations.save') }}' : 
                  '{{ route('admin.competency.question.translations.save') }}';
                  
                $.ajax({
                  url: translationRoute,
                  method: 'POST',
                  data: {
                    id: response.id,
                    translations: additionalTranslations
                  },
                  successMessage: "{{ __('admin/competencies.question-save-success') }}",
                });
              } else {
                // Just show success and reload
                swal_success.fire({
                  title: "{{ __('admin/competencies.question-save-success') }}"
                }).then(() => location.reload());
              }
            }
          },
          error: function() {
            swal_loader.close();
            alert('{{ __('global.error-occurred') }}');
          }
        });
      }
    }
  });
}

// Utility functions for questions
function showQuestionLanguageSelection() {
  $('.action-buttons').hide();
  $('.question-language-selection').show();
  
  // Build language checkboxes
  const container = $('#question-language-checkboxes');
  container.empty();
  
  const availableLanguages = getSystemLanguages();
  const existingLanguages = Object.keys(questionModalData.translations).filter(lang => 
    questionModalData.translations[lang].exists
  );
  
  availableLanguages.forEach(lang => {
    if (!existingLanguages.includes(lang)) {
      const langName = questionModalData.languageNames[lang] || lang.toUpperCase();
      const checkbox = $(`
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="${lang}" id="question-lang-check-${lang}">
          <label class="form-check-label" for="question-lang-check-${lang}">
            ${langName}
          </label>
        </div>
      `);
      container.append(checkbox);
    }
  });
}

function getSelectedQuestionLanguages() {
  const selected = [];
  $('#question-language-checkboxes input:checked').each(function() {
    selected.push($(this).val());
  });
  return selected;
}

function createQuestionTranslationsForLanguages(languages, autoTranslate = false) {
  // Add empty translations for selected languages
  languages.forEach(lang => {
    if (!questionModalData.translations[lang]) {
      questionModalData.translations[lang] = {
        question: '',
        question_self: '',
        min_label: '',
        max_label: '',
        exists: false,
        is_complete: false,
        is_partial: false,
        missing_fields: ['question', 'question_self', 'min_label', 'max_label'],
        is_original: false
      };
    }
  });
  
  // Switch to multi-language mode
  questionModalData.isTranslationMode = true;
  showQuestionMultiLanguageMode();
  
  // Hide language selection and show AI button
  $('.question-language-selection').hide();
  $('.action-buttons').show();
  $('#translate-question-ai-btn').show();
  
  if (autoTranslate) {
    // Trigger AI translation immediately
    setTimeout(() => {
      translateQuestionWithAI(languages);
    }, 500);
  }
}

function initializeEmptyQuestionTranslations() {
  const currentLang = getCurrentAppLocale();
  const currentData = {
    question: $('#competencyq-modal .question').val(),
    question_self: $('#competencyq-modal .question-self').val(),
    min_label: $('#competencyq-modal .min-label').val(),
    max_label: $('#competencyq-modal .max-label').val(),
  };
  
  questionModalData.originalLanguage = currentLang;
  questionModalData.translations = {};
  
  getSystemLanguages().forEach(lang => {
    if (lang === currentLang) {
      questionModalData.translations[lang] = {
        ...currentData,
        exists: true,
        is_complete: true,
        is_partial: false,
        missing_fields: [],
        is_original: true
      };
    } else {
      questionModalData.translations[lang] = {
        question: '',
        question_self: '',
        min_label: '',
        max_label: '',
        exists: false,
        is_complete: false,
        is_partial: false,
        missing_fields: ['question', 'question_self', 'min_label', 'max_label'],
        is_original: false
      };
    }
  });
}

function showQuestionLanguageSelectionForAI() {
  showQuestionLanguageSelection();
  // Show the "Confirm and Translate" button
  $('#confirm-and-translate-question').show();
}

function translateQuestionWithAI(targetLanguages) {
  if (!questionModalData.isEditMode) {
    alert('{{ __('translations.save-question-first') }}');
    return;
  }
  
  swal_loader.fire();
  
  const routeName = isInSuperAdminContext() ? 
    '{{ route('superadmin.competency.question.translations.ai') }}' : 
    '{{ route('admin.competency.question.translations.ai') }}';
    
  $.ajax({
    url: routeName,
    method: 'POST',
    data: {
      id: questionModalData.id,
      languages: targetLanguages
    },
    success: function(response) {
      if (response.success && response.translations) {
        // Apply AI translations to the form
        Object.keys(response.translations).forEach(lang => {
          const translation = response.translations[lang];
          
          // Update the form fields
          $(`#question-lang-${lang} [data-field="question"]`).val(translation.question || '');
          $(`#question-lang-${lang} [data-field="question_self"]`).val(translation.question_self || '');
          $(`#question-lang-${lang} [data-field="min_label"]`).val(translation.min_label || '');
          $(`#question-lang-${lang} [data-field="max_label"]`).val(translation.max_label || '');
          
          // Update internal state
          questionModalData.translations[lang] = {
            ...questionModalData.translations[lang],
            ...translation,
            exists: true,
            is_complete: true,
            is_partial: false,
            missing_fields: []
          };
        });
        
        // Rebuild tabs to show updated status
        buildQuestionLanguageTabs();
        validateAllTranslations();
        
        swal_success.fire({
          title: '{{ __('translations.ai-translation-complete') }}'
        });
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
</script>