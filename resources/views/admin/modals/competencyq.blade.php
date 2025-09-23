{{-- Competency Question Modal --}}
<div class="modal fade" id="competencyq-modal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-drawer">
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
                <input type="number" class="form-control scale" value="7" min="2" max="10" required>
              </div>
            </div>
          </div>

          <small class="form-text text-muted mb-3">
            {{ __('admin/competencies.current-language') }}: {{ $languageNames[$currentLocale] ?? strtoupper($currentLocale) }}
          </small>
          
          {{-- Add Translations Button (appears when fields are filled) --}}
          <div class="add-translations-section" style="display: none;">
            <button type="button" class="btn btn-outline-info btn-sm add-question-translations">
              <i class="fa fa-plus"></i> {{ __('admin/competencies.add-translations') }}
            </button>
          </div>
        </div>

        {{-- Multi-Language Mode (Hidden by default) --}}
        <div class="multi-language-mode" style="display: none;">
          {{-- Language tabs --}}
          <div class="language-tabs">
            {{-- Tabs will be populated via JavaScript --}}
          </div>

          {{-- Translation fields for each language --}}
          <div class="translation-content">
            {{-- Content will be populated via JavaScript --}}
          </div>

          {{-- AI Translate Section --}}
          <div class="ai-translate-section">
            <hr>
            <div class="text-center">
              <button type="button" class="btn btn-info ai-translate-all-questions">
                <i class="fa fa-robot"></i> {{ __('admin/competencies.ai-translate-all') }}
              </button>
              <p class="small text-muted mt-2">{{ __('admin/competencies.ai-translate-help') }}</p>
            </div>
          </div>

          {{-- Back to simple mode --}}
          <div class="text-center mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary back-to-simple-question">
              <i class="fa fa-arrow-left"></i> {{ __('admin/competencies.back-to-simple') }}
            </button>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          {{ __('global.cancel') }}
        </button>
        <button type="button" class="btn btn-primary save-competencyq">
          {{ __('admin/competencies.question-save') }}
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Competency Question Modal JavaScript
$(document).ready(function() {
  let questionModalData = {
    id: null,
    competencyId: null,
    isEditMode: false,
    isMultiLanguageMode: false,
    selectedLanguages: @json($selectedLanguages ?? [$currentLocale]),
    currentLocale: '{{ $currentLocale }}',
    translations: {}
  };

  // Initialize question modal
  function openCompetencyQModal(id = null, compId = null) {
    // Reset modal state
    questionModalData.id = id;
    questionModalData.competencyId = compId;
    questionModalData.isEditMode = id !== null;
    questionModalData.isMultiLanguageMode = false;
    questionModalData.translations = {};
    
    // Set modal title
    const title = id ? '{{ __('admin/competencies.modify-question') }}' : '{{ __('admin/competencies.create-question') }}';
    $('#competencyq-modal .modal-title').text(title);
    
    // Set data attributes for compatibility
    $('#competencyq-modal').attr('data-id', id || 0);
    $('#competencyq-modal').attr('data-compid', compId || 0);
    
    // Reset modal display
    showQuestionSingleLanguageMode();
    
    if (id) {
      // Load existing question
      loadQuestionData(id);
    } else {
      // New question - clear form
      clearQuestionForm();
      $('#competencyq-modal').modal('show');
    }
  }

  function loadQuestionData(id) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.question.translations.get') }}',
      method: 'POST',
      data: {
        id: id,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        questionModalData.translations = response.translations;
        
        // Check if we have multiple languages
        const hasMultipleTranslations = Object.keys(response.translations).filter(lang => 
          response.translations[lang].exists
        ).length > 1;
        
        if (hasMultipleTranslations) {
          showQuestionMultiLanguageMode(response);
        } else {
          // Show in simple mode
          loadQuestionIntoSimpleForm(response.translations);
        }
        
        swal_loader.close();
        $('#competencyq-modal').modal('show');
      },
      error: function() {
        // Fallback to old method
        loadQuestionFallback(id);
      }
    });
  }

  function loadQuestionFallback(id) {
    $.ajax({
      url: '{{ route('admin.competency.question.get') }}',
      method: 'GET',
      data: { id: id },
      success: function(question) {
        loadSimpleQuestionData(question);
        swal_loader.close();
        $('#competencyq-modal').modal('show');
      },
      error: function() {
        swal_loader.close();
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  function loadQuestionIntoSimpleForm(translations) {
    const currentTranslation = translations[questionModalData.currentLocale] || 
                              Object.values(translations).find(t => t.exists) || {};
    
    $('.question').val(currentTranslation.question || '');
    $('.question-self').val(currentTranslation.question_self || '');
    $('.min-label').val(currentTranslation.min_label || '');
    $('.max-label').val(currentTranslation.max_label || '');
    $('.scale').val(currentTranslation.max_value || 7);
  }

  function loadSimpleQuestionData(question) {
    $('.question').val(question.question);
    $('.question-self').val(question.question_self);
    $('.min-label').val(question.min_label);
    $('.max-label').val(question.max_label);
    $('.scale').val(question.max_value);
  }

  function clearQuestionForm() {
    $('.question').val('');
    $('.question-self').val('');
    $('.min-label').val('');
    $('.max-label').val('');
    $('.scale').val(7);
  }

  function showQuestionSingleLanguageMode() {
    $('.single-language-mode').show();
    $('.multi-language-mode').hide();
    $('.add-translations-section').hide();
    questionModalData.isMultiLanguageMode = false;
  }

  function showQuestionMultiLanguageMode(data = null) {
    $('.single-language-mode').hide();
    $('.multi-language-mode').show();
    questionModalData.isMultiLanguageMode = true;
    
    if (data) {
      populateQuestionTabs(data.translations, data.original_language);
      populateQuestionTranslationContent(data.translations, data.original_language);
    } else {
      // Creating translations from simple mode
      const originalData = collectOriginalQuestionData();
      const emptyTranslations = {};
      
      questionModalData.selectedLanguages.forEach(lang => {
        if (lang === questionModalData.currentLocale) {
          emptyTranslations[lang] = {
            ...originalData,
            exists: true
          };
        } else {
          emptyTranslations[lang] = {
            question: '',
            question_self: '',
            min_label: '',
            max_label: '',
            exists: false
          };
        }
      });
      
      populateQuestionTabs(emptyTranslations, questionModalData.currentLocale);
      populateQuestionTranslationContent(emptyTranslations, questionModalData.currentLocale);
    }
  }

  function collectOriginalQuestionData() {
    return {
      question: $('.question').val(),
      question_self: $('.question-self').val(),
      min_label: $('.min-label').val(),
      max_label: $('.max-label').val(),
      max_value: $('.scale').val()
    };
  }

  function populateQuestionTabs(translations, originalLang) {
    const $tabs = $('.language-tabs');
    $tabs.empty();
    
    questionModalData.selectedLanguages.forEach((lang, index) => {
      const langName = window.languageNames[lang] || lang.toUpperCase();
      const isOriginal = lang === originalLang;
      const isActive = index === 0;
      
      $tabs.append(`
        <div class="language-tab ${isActive ? 'active' : ''} ${isOriginal ? 'original' : ''}" data-lang="${lang}">
          ${langName} ${isOriginal ? '({{ __('admin/competencies.original') }})' : ''}
        </div>
      `);
    });
  }

  function populateQuestionTranslationContent(translations, originalLang) {
    const $content = $('.translation-content');
    $content.empty();
    
    questionModalData.selectedLanguages.forEach((lang, index) => {
      const isActive = index === 0;
      const isOriginal = lang === originalLang;
      const translation = translations[lang] || {};
      
      $content.append(`
        <div class="question-fields ${isActive ? '' : 'hidden'}" data-lang="${lang}">
          <div class="form-group">
            <label>{{ __('admin/competencies.question') }} <span class="text-danger">*</span></label>
            <textarea class="form-control" data-field="question" rows="2" ${isOriginal ? 'readonly' : ''}>${translation.question || ''}</textarea>
          </div>
          <div class="form-group">
            <label>{{ __('admin/competencies.question-self') }} <span class="text-danger">*</span></label>
            <textarea class="form-control" data-field="question_self" rows="2" ${isOriginal ? 'readonly' : ''}>${translation.question_self || ''}</textarea>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>{{ __('admin/competencies.min-label') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" data-field="min_label" value="${translation.min_label || ''}" ${isOriginal ? 'readonly' : ''}>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>{{ __('admin/competencies.max-label') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control" data-field="max_label" value="${translation.max_label || ''}" ${isOriginal ? 'readonly' : ''}>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>{{ __('admin/competencies.scale') }} <span class="text-danger">*</span></label>
                <input type="number" class="form-control" data-field="max_value" value="${translation.max_value || 7}" min="2" max="10" readonly>
              </div>
            </div>
          </div>
        </div>
      `);
    });
  }

  // Event handlers
  $(document).on('input', '.question, .question-self, .min-label, .max-label', function() {
    const hasContent = $('.question').val().trim() && 
                      $('.question-self').val().trim() && 
                      $('.min-label').val().trim() && 
                      $('.max-label').val().trim();
    $('.add-translations-section').toggle(hasContent && !questionModalData.isEditMode);
  });

  $(document).on('click', '.add-question-translations', function() {
    showQuestionMultiLanguageMode();
  });

  $(document).on('click', '.back-to-simple-question', function() {
    showQuestionSingleLanguageMode();
  });

  $(document).on('click', '.language-tab', function() {
    const lang = $(this).data('lang');
    
    // Update tab appearance
    $('.language-tab').removeClass('active');
    $(this).addClass('active');
    
    // Show corresponding form
    $('.question-fields').addClass('hidden');
    $(`.question-fields[data-lang="${lang}"]`).removeClass('hidden');
  });

  $(document).on('click', '.ai-translate-all-questions', function() {
    const originalData = collectCurrentOriginalData();
    const targetLanguages = questionModalData.selectedLanguages.filter(lang => 
      lang !== questionModalData.currentLocale
    );
    
    if (!validateOriginalData(originalData)) {
      alert('{{ __('admin/competencies.please-fill-all-fields') }}');
      return;
    }
    
    if (targetLanguages.length === 0) {
      alert('{{ __('admin/competencies.no-target-languages') }}');
      return;
    }
    
    // Create temporary question for AI translation if in create mode
    if (!questionModalData.isEditMode) {
      createTempQuestionForTranslation(originalData, targetLanguages);
    } else {
      translateExistingQuestion(questionModalData.id, targetLanguages);
    }
  });

  function collectCurrentOriginalData() {
    if (questionModalData.isMultiLanguageMode) {
      const originalLangData = $(`.question-fields[data-lang="${questionModalData.currentLocale}"]`);
      return {
        question: originalLangData.find('[data-field="question"]').val(),
        question_self: originalLangData.find('[data-field="question_self"]').val(),
        min_label: originalLangData.find('[data-field="min_label"]').val(),
        max_label: originalLangData.find('[data-field="max_label"]').val(),
        max_value: originalLangData.find('[data-field="max_value"]').val()
      };
    } else {
      return collectOriginalQuestionData();
    }
  }

  function validateOriginalData(data) {
    return data.question.trim() && data.question_self.trim() && 
           data.min_label.trim() && data.max_label.trim();
  }

  function createTempQuestionForTranslation(originalData, targetLanguages) {
    swal_loader.fire();
    
    // First create the question
    $.ajax({
      url: '{{ route('admin.competency.question.save') }}',
      method: 'POST',
      data: {
        competency_id: questionModalData.competencyId,
        question: originalData.question,
        question_self: originalData.question_self,
        min_label: originalData.min_label,
        max_label: originalData.max_label,
        max_value: originalData.max_value,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        questionModalData.id = response.id;
        questionModalData.isEditMode = true;
        
        // Now translate it
        translateExistingQuestion(response.id, targetLanguages);
      },
      error: function() {
        swal_loader.close();
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  function translateExistingQuestion(questionId, targetLanguages) {
    $.ajax({
      url: '{{ route('admin.competency.question.translations.ai') }}',
      method: 'POST',
      data: {
        id: questionId,
        languages: targetLanguages,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        swal_loader.close();
        
        if (response.translations) {
          // Update the translation input fields
          Object.keys(response.translations).forEach(lang => {
            const fields = response.translations[lang];
            Object.keys(fields).forEach(field => {
              const $input = $(`.question-fields[data-lang="${lang}"] [data-field="${field}"]`);
              if (field !== 'max_value') { // Don't update max_value as it's readonly
                $input.val(fields[field]);
                $input.addClass('translation-success');
                setTimeout(() => $input.removeClass('translation-success'), 2000);
              }
            });
          });
          
          swal_success.fire({
            title: '{{ __('admin/competencies.ai-translation-complete') }}'
          });
        }
      },
      error: function(xhr) {
        swal_loader.close();
        swal_error.fire({
          title: xhr.responseJSON?.error || '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  $(document).on('click', '.save-competencyq', function() {
    if (questionModalData.isMultiLanguageMode) {
      saveQuestionWithTranslations();
    } else {
      saveSingleLanguageQuestion();
    }
  });

  function saveSingleLanguageQuestion() {
    const data = collectOriginalQuestionData();
    
    if (!validateOriginalData(data)) {
      alert('{{ __('admin/competencies.please-fill-all-fields') }}');
      return;
    }
    
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.question.save') }}',
      method: 'POST',
      data: {
        id: questionModalData.id,
        competency_id: questionModalData.competencyId,
        question: data.question,
        question_self: data.question_self,
        min_label: data.min_label,
        max_label: data.max_label,
        max_value: data.max_value,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        swal_loader.close();
        $('#competencyq-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('admin/competencies.question-saved') }}'
        }).then(() => {
          location.reload();
        });
      },
      error: function() {
        swal_loader.close();
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  function saveQuestionWithTranslations() {
    const translations = {};
    const maxValue = $('.question-fields [data-field="max_value"]').first().val();
    
    questionModalData.selectedLanguages.forEach(lang => {
      const $fields = $(`.question-fields[data-lang="${lang}"]`);
      translations[lang] = {
        question: $fields.find('[data-field="question"]').val().trim(),
        question_self: $fields.find('[data-field="question_self"]').val().trim(),
        min_label: $fields.find('[data-field="min_label"]').val().trim(),
        max_label: $fields.find('[data-field="max_label"]').val().trim(),
        max_value: maxValue
      };
    });
    
    // Validate original language data
    const originalData = translations[questionModalData.currentLocale];
    if (!validateOriginalData(originalData)) {
      alert('{{ __('admin/competencies.please-fill-all-fields') }}');
      return;
    }
    
    swal_loader.fire();
    
    if (!questionModalData.isEditMode) {
      $.ajax({
        url: '{{ route('admin.competency.question.save') }}',
        method: 'POST',
        data: {
          competency_id: questionModalData.competencyId,
          question: originalData.question,
          question_self: originalData.question_self,
          min_label: originalData.min_label,
          max_label: originalData.max_label,
          max_value: originalData.max_value,
          _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
          questionModalData.id = response.id;
          saveQuestionTranslations(translations);
        },
        error: function() {
          swal_loader.close();
          swal_error.fire({
            title: '{{ __('global.error-occurred') }}'
          });
        }
      });
    } else {
      saveQuestionTranslations(translations);
    }
  }

  function saveQuestionTranslations(translations) {
    $.ajax({
      url: '{{ route('admin.competency.question.translations.save') }}',
      method: 'POST',
      data: {
        id: questionModalData.id,
        translations: translations,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        swal_loader.close();
        $('#competencyq-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('admin/competencies.question-saved') }}'
        }).then(() => {
          location.reload();
        });
      },
      error: function() {
        swal_loader.close();
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  // Make function globally available
  window.openCompetencyQModal = openCompetencyQModal;
});
</script>