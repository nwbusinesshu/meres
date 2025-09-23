<script>
// Superadmin Global Competencies JavaScript with Translation Support
// This file handles all global competency management and translation functionality

$(document).ready(function(){
  const url = new URL(window.location.href);

  // For superadmin - all available languages are always used
  let availableLanguages = @json($availableLanguages);
  let currentLocale = '{{ $currentLocale }}';
  
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
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(null, compId);
  });

  $(document).on('click', '.modify-question', function(){
    const id = $(this).closest('.question-item').attr('data-id');
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(id, compId);
  });

  // Remove competency
  $(document).on('click', '.remove-competency', function(){
    const id = $(this).closest('.competency-item').attr('data-id');
    const name = $(this).closest('.competency-item').attr('data-name');
    
    swal_confirm.fire({
      title: '{{ __('superadmin/global-competencies.confirm-remove-competency') }}',
      text: name,
    }).then((result) => {
      if (result.isConfirmed) {
        removeCompetency(id);
      }
    });
  });

  // Remove question
  $(document).on('click', '.remove-question', function(){
    const id = $(this).closest('.question-item').attr('data-id');
    
    swal_confirm.fire({
      title: '{{ __('superadmin/global-competencies.confirm-remove-question') }}',
    }).then((result) => {
      if (result.isConfirmed) {
        removeQuestion(id);
      }
    });
  });

  // Translation management
  $(document).on('click', '.manage-translations', function(){
    const competencyId = $(this).data('id');
    openTranslationModal(competencyId);
  });

  $(document).on('click', '.manage-question-translations', function(){
    const questionId = $(this).data('id');
    openQuestionTranslationModal(questionId);
  });

  // Search functionality
  $('.competency-search-input').keyup(function(e){
    if(e.keyCode != 13) return;

    swal_loader.fire();
    const search = $(this).val().toLowerCase();

    $('.competency-item').each(function(){
      const name = $(this).attr('data-name').toLowerCase();
      if(name.includes(search) || search === ''){
        $(this).removeClass('hidden');
      } else {
        $(this).addClass('hidden');
      }
    });

    const visibleItems = $('.competency-item').not('.hidden').length;
    if(visibleItems === 0){
      $('.no-competency').removeClass('hidden');
    } else {
      $('.no-competency').addClass('hidden');
    }

    swal_loader.close();
  });

  $('.competency-clear-search').click(function(){
    $('.competency-search-input').val('');
    $('.competency-item').removeClass('hidden');
    $('.no-competency').addClass('hidden');
  });

  // Helper Functions
  function removeCompetency(id) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('superadmin.competency.remove') }}',
      method: 'POST',
      data: {
        id: id,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        swal_loader.close();
        swal_success.fire({
          title: '{{ __('superadmin/global-competencies.competency-removed') }}'
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

  function removeQuestion(id) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('superadmin.competency.question.remove') }}',
      method: 'POST',
      data: {
        id: id,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        swal_loader.close();
        swal_success.fire({
          title: '{{ __('superadmin/global-competencies.question-removed') }}'
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

  function openTranslationModal(competencyId) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('superadmin.competency.translations.get') }}',
      method: 'POST',
      data: {
        id: competencyId,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        showTranslationModal(competencyId, response);
        swal_loader.close();
      },
      error: function() {
        swal_loader.close();
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  function showTranslationModal(competencyId, data) {
    $('#translation-modal').modal('show');
    $('#translation-modal').attr('data-competency-id', competencyId);
    
    const $form = $('#translation-modal .translation-form');
    $form.empty();
    
    availableLanguages.forEach(lang => {
      const name = window.languageNames[lang] || lang.toUpperCase();
      const isOriginal = lang === data.original_language;
      // Fix: Safely access the translation data
      const translationData = data.translations && data.translations[lang] ? data.translations[lang] : {};
      const value = translationData.name || '';
      
      $form.append(`
        <div class="translation-field">
          <label ${isOriginal ? 'class="original-language"' : ''}>
            ${name} ${isOriginal ? '({{ __('superadmin/global-competencies.original') }})' : ''}
          </label>
          <input type="text" class="form-control" data-lang="${lang}" value="${value}" ${isOriginal ? 'readonly' : ''}>
        </div>
      `);
    });
    
    // Add AI translate button
    $form.append(`
      <div class="ai-translate-section">
        <button type="button" class="btn btn-info ai-translate-competency">
          <i class="fa fa-robot"></i> {{ __('superadmin/global-competencies.ai-translate-all') }}
        </button>
      </div>
    `);
  }

  function openQuestionTranslationModal(questionId) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('superadmin.competency.question.translations.get') }}',
      method: 'POST',
      data: {
        id: questionId,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        showQuestionTranslationModal(questionId, response);
        swal_loader.close();
      },
      error: function() {
        swal_loader.close();
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  function showQuestionTranslationModal(questionId, data) {
    $('#question-translation-modal').modal('show');
    $('#question-translation-modal').attr('data-question-id', questionId);
    
    // Create language tabs
    const $tabs = $('#question-translation-modal .language-tabs');
    $tabs.empty();
    
    availableLanguages.forEach(lang => {
      const name = window.languageNames[lang] || lang.toUpperCase();
      const isOriginal = lang === data.original_language;
      const isActive = lang === availableLanguages[0];
      
      $tabs.append(`
        <div class="language-tab ${isActive ? 'active' : ''} ${isOriginal ? 'original' : ''}" data-lang="${lang}">
          ${name} ${isOriginal ? '({{ __('superadmin/global-competencies.original') }})' : ''}
        </div>
      `);
    });
    
    // Create form fields for each language
    const $content = $('#question-translation-modal .question-translation-content');
    $content.empty();
    
    availableLanguages.forEach((lang, index) => {
      const isActive = index === 0;
      const isOriginal = lang === data.original_language;
      // Fix: Safely access the translation data
      const translationData = data.translations && data.translations[lang] ? data.translations[lang] : {};
      
      $content.append(`
        <div class="question-fields ${isActive ? '' : 'hidden'}" data-lang="${lang}">
          <div class="field-group">
            <label class="field-label">{{ __('superadmin/global-competencies.question') }}</label>
            <textarea class="form-control" data-field="question" ${isOriginal ? 'readonly' : ''}>${translationData.question || ''}</textarea>
          </div>
          <div class="field-group">
            <label class="field-label">{{ __('superadmin/global-competencies.question-self') }}</label>
            <textarea class="form-control" data-field="question_self" ${isOriginal ? 'readonly' : ''}>${translationData.question_self || ''}</textarea>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="field-group">
                <label class="field-label">{{ __('superadmin/global-competencies.min-label') }}</label>
                <input type="text" class="form-control" data-field="min_label" value="${translationData.min_label || ''}" ${isOriginal ? 'readonly' : ''}>
              </div>
            </div>
            <div class="col-md-6">
              <div class="field-group">
                <label class="field-label">{{ __('superadmin/global-competencies.max-label') }}</label>
                <input type="text" class="form-control" data-field="max_label" value="${translationData.max_label || ''}" ${isOriginal ? 'readonly' : ''}>
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
          <i class="fa fa-robot"></i> {{ __('superadmin/global-competencies.ai-translate-all') }}
        </button>
      </div>
    `);
  }

  // Modal event handlers
  $(document).on('click', '.language-tab', function() {
    const lang = $(this).data('lang');
    
    // Update tab appearance
    $('.language-tab').removeClass('active');
    $(this).addClass('active');
    
    // Show corresponding form
    $('.question-fields').addClass('hidden');
    $(`.question-fields[data-lang="${lang}"]`).removeClass('hidden');
  });

  $(document).on('click', '.ai-translate-competency', function() {
    const competencyId = $('#translation-modal').data('competency-id');
    const missingLanguages = availableLanguages.filter(lang => 
      !$(`input[data-lang="${lang}"]`).prop('readonly')
    );
    
    if (missingLanguages.length === 0) {
      alert('{{ __('superadmin/global-competencies.no-translations-needed') }}');
      return;
    }
    
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('superadmin.competency.translations.ai') }}',
      method: 'POST',
      data: {
        id: competencyId,
        languages: missingLanguages,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        swal_loader.close();
        
        if (response.translations) {
          // Update form fields with translations
          Object.keys(response.translations).forEach(lang => {
            const $input = $(`input[data-lang="${lang}"]`);
            $input.val(response.translations[lang]);
            $input.addClass('translation-success');
            setTimeout(() => $input.removeClass('translation-success'), 2000);
          });
          
          swal_success.fire({
            title: '{{ __('superadmin/global-competencies.ai-translation-complete') }}'
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
  });

  $(document).on('click', '.ai-translate-question', function() {
    const questionId = $('#question-translation-modal').data('question-id');
    const missingLanguages = availableLanguages.filter(lang => 
      !$(`.question-fields[data-lang="${lang}"] [data-field="question"]`).prop('readonly')
    );
    
    if (missingLanguages.length === 0) {
      alert('{{ __('superadmin/global-competencies.no-translations-needed') }}');
      return;
    }
    
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('superadmin.competency.question.translations.ai') }}',
      method: 'POST',
      data: {
        id: questionId,
        languages: missingLanguages,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        swal_loader.close();
        
        if (response.translations) {
          // Update form fields with translations
          Object.keys(response.translations).forEach(lang => {
            const fields = response.translations[lang];
            Object.keys(fields).forEach(field => {
              if (field !== 'max_value') { // Don't update max_value as it's readonly
                const $input = $(`.question-fields[data-lang="${lang}"] [data-field="${field}"]`);
                $input.val(fields[field]);
                $input.addClass('translation-success');
                setTimeout(() => $input.removeClass('translation-success'), 2000);
              }
            });
          });
          
          swal_success.fire({
            title: '{{ __('superadmin/global-competencies.ai-translation-complete') }}'
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
  });

  $(document).on('click', '#translation-modal .save-translations', function() {
    const competencyId = $('#translation-modal').data('competency-id');
    const translations = {};
    
    $('#translation-modal input[data-lang]').each(function() {
      const lang = $(this).data('lang');
      const value = $(this).val().trim();
      if (value) {
        translations[lang] = value;
      }
    });
    
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('superadmin.competency.translations.save') }}',
      method: 'POST',
      data: {
        id: competencyId,
        translations: translations,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        swal_loader.close();
        $('#translation-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('superadmin/global-competencies.translations-saved') }}'
        }).then(() => {
          location.reload();
        });
      },
      error: function(xhr) {
        swal_loader.close();
        swal_error.fire({
          title: xhr.responseJSON?.error || '{{ __('global.error-occurred') }}'
        });
      }
    });
  });

  $(document).on('click', '#question-translation-modal .save-question-translations', function() {
    const questionId = $('#question-translation-modal').data('question-id');
    const translations = {};
    
    availableLanguages.forEach(lang => {
      const $fields = $(`.question-fields[data-lang="${lang}"]`);
      translations[lang] = {
        question: $fields.find('[data-field="question"]').val().trim(),
        question_self: $fields.find('[data-field="question_self"]').val().trim(),
        min_label: $fields.find('[data-field="min_label"]').val().trim(),
        max_label: $fields.find('[data-field="max_label"]').val().trim()
      };
    });
    
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('superadmin.competency.question.translations.save') }}',
      method: 'POST',
      data: {
        id: questionId,
        translations: translations,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        swal_loader.close();
        $('#question-translation-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('superadmin/global-competencies.translations-saved') }}'
        }).then(() => {
          location.reload();
        });
      },
      error: function(xhr) {
        swal_loader.close();
        swal_error.fire({
          title: xhr.responseJSON?.error || '{{ __('global.error-occurred') }}'
        });
      }
    });
  });
});
</script>