<script>
// Admin Competencies JavaScript with Translation Support - FIXED VERSION
// This file handles all competency management and translation functionality

$(document).ready(function(){
  const url = new URL(window.location.href);

  // Initialize selected languages from session or default
  let selectedLanguages = @json($selectedLanguages ?? [$currentLocale]);
  let currentLocale = '{{ $currentLocale }}';
  let availableLanguages = @json($availableLanguages ?? ['hu', 'en']);
  let languageNames = @json($languageNames ?? ['hu' => 'Magyar', 'en' => 'English']);
  
  // Make sure window.languageNames is available
  window.languageNames = languageNames;
  
  console.log('Initialized with:', { selectedLanguages, currentLocale, availableLanguages, languageNames });
  
  // Basic competency management
  $('.create-competency').click(function(){
    openCompetencyModal();
  });

  $('.modify-competency').click(function(){
    const id = $(this).closest('.competency-item').attr('data-id')*1;
    const name = $(this).closest('.competency-item').attr('data-name');
    openCompetencyModal(id, name);
  });

  // Arrow toggle functionality
  $(document).on('click', '.competency-item .bar > span', function(e){
    e.stopPropagation();
    
    const $competencyItem = $(this).closest('.competency-item');
    const $questions = $competencyItem.find('.questions');
    const $icon = $(this).find('i.fa-caret-down, i.fa-caret-up');
    const show = $questions.hasClass('hidden');
    
    // Close all other questions first
    $('.competency-item .questions').addClass('hidden');
    $('.competency-item .bar span i.fa-caret-up').removeClass('fa-caret-up').addClass('fa-caret-down');
    
    if (show) {
      $questions.removeClass('hidden');
      $icon.removeClass('fa-caret-down').addClass('fa-caret-up');
      url.searchParams.set('open', $competencyItem.attr('data-id'));
      window.history.replaceState(null, null, url);
    } else {
      url.searchParams.delete('open');
      window.history.replaceState(null, null, url);
    }
  });

  // Auto-open from URL parameter
  if(url.searchParams.has('open')){
    const openId = url.searchParams.get('open');
    $('.competency-item[data-id="'+openId+'"] .bar > span').trigger('click');
  }

  // Question management
  $(document).on('click', '.create-question', function(){
    const compId = $(this).closest('.competency-item').attr('data-id')*1;
    openCompetencyQModal(null, compId);
  });

  $(document).on('click', '.modify-question', function(){
    const id = $(this).attr('data-id')*1;
    const compId = $(this).closest('.competency-item').attr('data-id')*1;
    openCompetencyQModal(id, compId);
  });

  // Remove functions
  $(document).on('click', '.remove-competency', function(){
    const id = $(this).closest('.competency-item').attr('data-id')*1;
    const name = $(this).closest('.competency-item').attr('data-name');
    
    swal_confirm.fire({
      title: '{{ __('admin/competencies.remove-competency-confirm') }}',
      text: name
    }).then((result) => {
      if (result.isConfirmed) {
        removeCompetency(id);
      }
    });
  });

  $(document).on('click', '.remove-question', function(){
    const id = $(this).attr('data-id')*1;
    const question = $(this).attr('data-question');
    
    swal_confirm.fire({
      title: '{{ __('admin/competencies.remove-question-confirm') }}',
      text: question
    }).then((result) => {
      if (result.isConfirmed) {
        removeCompetencyQuestion(id);
      }
    });
  });

  // FIXED: Translation modal handlers
  $(document).on('click', '.open-translations', function(){
    const competencyId = $(this).closest('.competency-item').attr('data-id')*1;
    openTranslationModal(competencyId);
  });

  $(document).on('click', '.open-question-translations', function(){
    const questionId = $(this).attr('data-id')*1;
    openQuestionTranslationModal(questionId);
  });

  // Language setup modal
  $(document).on('click', '.setup-languages', function(){
    openLanguageSetupModal();
  });

  // ===================================
  // BASIC COMPETENCY FUNCTIONS
  // ===================================

  function openCompetencyModal(id = null, name = null) {
    // Reset and show modal
    $('#competency-modal .modal-title').text(
      id ? '{{ __('admin/competencies.modify-competency') }}' : '{{ __('admin/competencies.create-competency') }}'
    );
    
    $('#competency-modal input[name="id"]').val(id || '');
    $('#competency-modal input[name="name"]').val(name || '');
    
    $('#competency-modal').modal('show');
  }

  function openCompetencyQModal(id = null, compId = null) {
    if (id) {
      // Load existing question
      swal_loader.fire();
      
      $.ajax({
        url: '{{ route('admin.competency.question.get') }}',
        method: 'GET',
        data: { id: id },
        success: function(response) {
          // FIXED: Handle response safely
          if (response.success && response.id) {
            populateQuestionModal(response);
          } else {
            console.error('Invalid question response:', response);
            swal_error.fire({
              title: '{{ __('global.error-occurred') }}'
            });
          }
          swal_loader.close();
        },
        error: function(xhr) {
          swal_loader.close();
          console.error('Question load error:', xhr);
          swal_error.fire({
            title: '{{ __('global.error-occurred') }}'
          });
        }
      });
    } else {
      // New question
      $('#competencyq-modal .modal-title').text('{{ __('admin/competencies.create-question') }}');
      $('#competencyq-modal input[name="id"]').val('');
      $('#competencyq-modal input[name="competency_id"]').val(compId);
      $('#competencyq-modal input[name="question"]').val('');
      $('#competencyq-modal input[name="question_self"]').val('');
      $('#competencyq-modal input[name="min_label"]').val('');
      $('#competencyq-modal input[name="max_label"]').val('');
      $('#competencyq-modal input[name="max_value"]').val('7');
      
      $('#competencyq-modal').modal('show');
    }
  }

  // FIXED: Safer question modal population
  function populateQuestionModal(data) {
    $('#competencyq-modal .modal-title').text('{{ __('admin/competencies.modify-question') }}');
    $('#competencyq-modal input[name="id"]').val(data.id || '');
    $('#competencyq-modal input[name="competency_id"]').val(data.competency_id || '');
    $('#competencyq-modal input[name="question"]').val(data.question || '');
    $('#competencyq-modal input[name="question_self"]').val(data.question_self || '');
    $('#competencyq-modal input[name="min_label"]').val(data.min_label || '');
    $('#competencyq-modal input[name="max_label"]').val(data.max_label || '');
    $('#competencyq-modal input[name="max_value"]').val(data.max_value || '7');
    
    $('#competencyq-modal').modal('show');
  }

  // ===================================
  // TRANSLATION FUNCTIONS - FIXED
  // ===================================

  function openLanguageSetupModal() {
    // FIXED: Filter available languages correctly
    const $modal = $('#language-setup-modal');
    const $languageList = $modal.find('.language-selection');
    
    $languageList.empty();
    
    availableLanguages.forEach(lang => {
      // Skip current locale (it's always selected and disabled)
      if (lang === currentLocale) {
        $languageList.append(`
          <label class="language-option required">
            <input type="checkbox" value="${lang}" checked disabled>
            <span>${languageNames[lang] || lang.toUpperCase()} ({{ __('admin/competencies.current-language') }})</span>
          </label>
        `);
      } else {
        const isSelected = selectedLanguages.includes(lang);
        $languageList.append(`
          <label class="language-option">
            <input type="checkbox" value="${lang}" ${isSelected ? 'checked' : ''}>
            <span>${languageNames[lang] || lang.toUpperCase()}</span>
          </label>
        `);
      }
    });
    
    $modal.modal('show');
  }

  function openTranslationModal(competencyId) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.translations.get') }}',
      method: 'POST',
      data: {
        id: competencyId,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        console.log('Translation response:', response);
        showTranslationModal(competencyId, response);
        swal_loader.close();
      },
      error: function(xhr) {
        swal_loader.close();
        console.error('Translation load error:', xhr);
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  // FIXED: Translation modal display
  function showTranslationModal(competencyId, data) {
    const $modal = $('#translation-modal');
    $modal.modal('show');
    $modal.attr('data-competency-id', competencyId);
    
    const $form = $modal.find('.translation-form');
    $form.empty();
    
    // FIXED: Safely handle the translation data
    availableLanguages.forEach(lang => {
      const name = languageNames[lang] || lang.toUpperCase();
      const isOriginal = lang === (data.original_language || 'hu');
      
      // FIXED: Safe access to translation data
      let value = '';
      if (data.translations && data.translations[lang] && data.translations[lang].name) {
        value = data.translations[lang].name;
      }
      
      $form.append(`
        <div class="translation-field">
          <label ${isOriginal ? 'class="original-language"' : ''}>
            ${name} ${isOriginal ? '({{ __('admin/competencies.original') }})' : ''}
          </label>
          <input type="text" class="form-control" data-lang="${lang}" value="${value}" ${isOriginal ? 'readonly' : ''}>
        </div>
      `);
    });
    
    // Add AI translate button
    $form.append(`
      <div class="ai-translate-section">
        <button type="button" class="btn btn-info ai-translate-competency">
          <i class="fa fa-robot"></i> {{ __('admin/competencies.ai-translate-all') }}
        </button>
      </div>
    `);
  }

  function openQuestionTranslationModal(questionId) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.question.translations.get') }}',
      method: 'POST',
      data: {
        id: questionId,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        console.log('Question translation response:', response);
        showQuestionTranslationModal(questionId, response);
        swal_loader.close();
      },
      error: function(xhr) {
        swal_loader.close();
        console.error('Question translation load error:', xhr);
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  // FIXED: Question translation modal display
  function showQuestionTranslationModal(questionId, data) {
    const $modal = $('#question-translation-modal');
    $modal.modal('show');
    $modal.attr('data-question-id', questionId);
    
    const $tabs = $modal.find('.language-tabs');
    const $content = $modal.find('.question-translation-content');
    
    $tabs.empty();
    $content.empty();
    
    // Create tabs for each language
    availableLanguages.forEach((lang, index) => {
      const name = languageNames[lang] || lang.toUpperCase();
      const isOriginal = lang === (data.original_language || 'hu');
      const isActive = index === 0;
      
      $tabs.append(`
        <button type="button" class="btn btn-outline-primary language-tab ${isActive ? 'active' : ''}" data-lang="${lang}">
          ${name} ${isOriginal ? '({{ __('admin/competencies.original') }})' : ''}
        </button>
      `);
      
      // FIXED: Safe access to question translation data
      let translation = {
        question: '',
        question_self: '',
        min_label: '',
        max_label: ''
      };
      
      if (data.translations && data.translations[lang]) {
        translation = {
          question: data.translations[lang].question || '',
          question_self: data.translations[lang].question_self || '',
          min_label: data.translations[lang].min_label || '',
          max_label: data.translations[lang].max_label || ''
        };
      }
      
      $content.append(`
        <div class="question-fields ${isActive ? '' : 'hidden'}" data-lang="${lang}">
          <div class="row">
            <div class="col-12">
              <div class="field-group">
                <label class="field-label">{{ __('admin/competencies.question') }}</label>
                <textarea class="form-control" data-field="question" ${isOriginal ? 'readonly' : ''}>${translation.question}</textarea>
              </div>
            </div>
            <div class="col-12">
              <div class="field-group">
                <label class="field-label">{{ __('admin/competencies.question-self') }}</label>
                <textarea class="form-control" data-field="question_self" ${isOriginal ? 'readonly' : ''}>${translation.question_self}</textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="field-group">
                <label class="field-label">{{ __('admin/competencies.min-label') }}</label>
                <input type="text" class="form-control" data-field="min_label" value="${translation.min_label}" ${isOriginal ? 'readonly' : ''}>
              </div>
            </div>
            <div class="col-md-6">
              <div class="field-group">
                <label class="field-label">{{ __('admin/competencies.max-label') }}</label>
                <input type="text" class="form-control" data-field="max_label" value="${translation.max_label}" ${isOriginal ? 'readonly' : ''}>
              </div>
            </div>
          </div>
        </div>
      `);
    });
    
    // Add AI translate button
    $content.append(`
      <div class="ai-translate-section">
        <button type="button" class="btn btn-info ai-translate-question">
          <i class="fa fa-robot"></i> {{ __('admin/competencies.ai-translate-all') }}
        </button>
      </div>
    `);
  }

  // ===================================
  // EVENT HANDLERS
  // ===================================

  // Language selection change
  $(document).on('change', '#language-setup-modal input[type="checkbox"]', function() {
    const lang = $(this).val();
    const isChecked = $(this).is(':checked');
    
    if (isChecked && !selectedLanguages.includes(lang)) {
      selectedLanguages.push(lang);
    } else if (!isChecked && lang !== currentLocale) {
      selectedLanguages = selectedLanguages.filter(l => l !== lang);
    }
  });

  // Save language selection
  $(document).on('click', '#language-setup-modal .save-language-selection', function() {
    const checkedLanguages = [];
    $('#language-setup-modal input[type="checkbox"]:checked').each(function() {
      checkedLanguages.push($(this).val());
    });
    
    $.ajax({
      url: '{{ route('admin.competency.save-language-selection') }}',
      method: 'POST',
      data: {
        languages: checkedLanguages,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        selectedLanguages = checkedLanguages;
        $('#language-setup-modal').modal('hide');
        
        swal_success.fire({
          title: '{{ __('admin/competencies.languages-updated') }}'
        }).then(() => {
          location.reload();
        });
      },
      error: function() {
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  });

  // Language tab switching
  $(document).on('click', '.language-tab', function() {
    const lang = $(this).data('lang');
    
    // Update tab appearance
    $('.language-tab').removeClass('active');
    $(this).addClass('active');
    
    // Show corresponding form
    $('.question-fields').addClass('hidden');
    $(`.question-fields[data-lang="${lang}"]`).removeClass('hidden');
  });

  // Save translations
  $(document).on('click', '.save-translations', function() {
    const competencyId = $('#translation-modal').data('competency-id');
    const translations = {};
    
    $('#translation-modal .translation-field input').each(function() {
      const lang = $(this).data('lang');
      const value = $(this).val().trim();
      if (value) {
        translations[lang] = value;
      }
    });
    
    $.ajax({
      url: '{{ route('admin.competency.translations.save') }}',
      method: 'POST',
      data: {
        id: competencyId,
        translations: translations,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        $('#translation-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('admin/competencies.translations-saved') }}'
        });
      },
      error: function() {
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  });

  // Save question translations
  $(document).on('click', '.save-question-translations', function() {
    const questionId = $('#question-translation-modal').data('question-id');
    const translations = {};
    
    availableLanguages.forEach(lang => {
      const $fields = $(`.question-fields[data-lang="${lang}"]`);
      const fields = {};
      
      $fields.find('input, textarea').each(function() {
        const field = $(this).data('field');
        const value = $(this).val().trim();
        if (field && value) {
          fields[field] = value;
        }
      });
      
      if (Object.keys(fields).length > 0) {
        translations[lang] = fields;
      }
    });
    
    $.ajax({
      url: '{{ route('admin.competency.question.translations.save') }}',
      method: 'POST',
      data: {
        id: questionId,
        translations: translations,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        $('#question-translation-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('admin/competencies.translations-saved') }}'
        });
      },
      error: function() {
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  });

  // ===================================
  // SAVE FUNCTIONS
  // ===================================

  $(document).on('click', '.save-competency', function(){
    const id = $('#competency-modal input[name="id"]').val();
    const name = $('#competency-modal input[name="name"]').val();
    
    if (!name.trim()) {
      swal_error.fire({
        title: '{{ __('admin/competencies.name-required') }}'
      });
      return;
    }
    
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.save') }}',
      method: 'POST',
      data: {
        id: id,
        name: name.trim(),
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        swal_loader.close();
        $('#competency-modal').modal('hide');
        
        if (response.success) {
          swal_success.fire({
            title: response.message || '{{ __('global.saved') }}'
          }).then(() => {
            location.reload();
          });
        } else {
          swal_error.fire({
            title: response.error || '{{ __('global.error-occurred') }}'
          });
        }
      },
      error: function(xhr) {
        swal_loader.close();
        console.error('Save competency error:', xhr);
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  });

  $(document).on('click', '.save-competencyq', function(){
    const data = {
      id: $('#competencyq-modal input[name="id"]').val(),
      competency_id: $('#competencyq-modal input[name="competency_id"]').val(),
      question: $('#competencyq-modal input[name="question"]').val(),
      question_self: $('#competencyq-modal input[name="question_self"]').val(),
      min_label: $('#competencyq-modal input[name="min_label"]').val(),
      max_label: $('#competencyq-modal input[name="max_label"]').val(),
      max_value: $('#competencyq-modal input[name="max_value"]').val(),
      _token: $('meta[name="csrf-token"]').attr('content')
    };
    
    // Validation
    if (!data.question.trim() || !data.question_self.trim() || !data.min_label.trim() || !data.max_label.trim()) {
      swal_error.fire({
        title: '{{ __('admin/competencies.all-fields-required') }}'
      });
      return;
    }
    
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.question.save') }}',
      method: 'POST',
      data: data,
      success: function(response) {
        swal_loader.close();
        $('#competencyq-modal').modal('hide');
        
        if (response.success) {
          swal_success.fire({
            title: response.message || '{{ __('global.saved') }}'
          }).then(() => {
            location.reload();
          });
        } else {
          swal_error.fire({
            title: response.error || '{{ __('global.error-occurred') }}'
          });
        }
      },
      error: function(xhr) {
        swal_loader.close();
        console.error('Save question error:', xhr);
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  });

  // ===================================
  // REMOVE FUNCTIONS
  // ===================================

  function removeCompetency(id) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.remove') }}',
      method: 'POST',
      data: {
        id: id,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        swal_loader.close();
        if (response.success) {
          swal_success.fire({
            title: '{{ __('admin/competencies.competency-removed') }}'
          }).then(() => {
            location.reload();
          });
        } else {
          swal_error.fire({
            title: response.error || '{{ __('global.error-occurred') }}'
          });
        }
      },
      error: function(xhr) {
        swal_loader.close();
        console.error('Remove competency error:', xhr);
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  function removeCompetencyQuestion(id) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.question.remove') }}',
      method: 'POST',
      data: {
        id: id,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        swal_loader.close();
        if (response.success) {
          swal_success.fire({
            title: '{{ __('admin/competencies.question-removed') }}'
          }).then(() => {
            location.reload();
          });
        } else {
          swal_error.fire({
            title: response.error || '{{ __('global.error-occurred') }}'
          });
        }
      },
      error: function(xhr) {
        swal_loader.close();
        console.error('Remove question error:', xhr);
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }
});
</script>