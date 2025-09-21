<script>
// Admin Competencies JavaScript
// This file handles all translation functionality for admin competencies

$(document).ready(function(){
  const url = new URL(window.location.href);

  // Basic competency management
  $('.create-competency').click(function(){
    openCompetencyModal();
  });

  $('.modify-competency').click(function(){
    const id = $(this).closest('.competency-item').attr('data-id')*1;
    const name = $(this).closest('.competency-item').attr('data-name');
    openCompetencyModal(id, name);
  });

  // Arrow toggle functionality with event delegation
  $(document).on('click', '.competency-item .bar > span', function(e){
    e.stopPropagation();
    
    const $competencyItem = $(this).closest('.competency-item');
    const $questions = $competencyItem.find('.questions');
    const $icon = $(this).find('i');
    const show = $questions.hasClass('hidden');
    
    // Close all other questions first
    $('.competency-item .questions').addClass('hidden');
    $('.competency-item .bar span i').removeClass('fa-caret-up').addClass('fa-caret-down');
    
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

  // Question management (only for organization competencies)
  $(document).on('click', '.competency-list--org-crud .create-question', function(){
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(null, compId);
  });

  $(document).on('click', '.competency-list--org-crud .modify-question', function(){
    const id = $(this).closest('.question-item').attr('data-id');
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(id, compId);
  });

  // Search functionality
  $('.competency-search-input').keyup(function(e){
    if(e.keyCode != 13) return;

    swal_loader.fire();
    const search = $(this).val().toLowerCase();

    $('.competency-item').addClass('hidden');
    $('.no-competency').addClass('hidden');

    $('.competency-item').each(function(){
      const name = $(this).attr('data-name');
      if(name && name.toLowerCase().includes(search)){
        $(this).removeClass('hidden');
      }
    });

    url.searchParams.delete('search');
    if(search.length !== 0){
      url.searchParams.set('search', search);
    }
    window.history.replaceState(null, null, url);

    if($('.competency-item:not(.hidden)').length === 0){
      $('.no-competency').removeClass('hidden');
    }
    swal_loader.close();
  });

  if(url.searchParams.has('search')){
    $('.competency-search-input').val(url.searchParams.get('search'))
      .trigger(jQuery.Event('keyup', { keyCode: 13 }));
  }

  $('.competency-clear-search').click(function(){
    $('.competency-search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
  });

  // Remove handlers (only for organization competencies)
  $(document).on('click', '.competency-list--org-crud .remove-question', function(){
    const id = $(this).closest('.question-item').attr('data-id');
    swal_confirm.fire({
      title: 'Are you sure you want to remove this question?'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: window.routes.admin_competency_question_remove,
          data: { id: id },
          successMessage: "Question removed successfully",
        });
      }
    });
  });

  $(document).on('click', '.competency-list--org-crud .remove-competency', function(){
    const id = $(this).closest('.competency-item').attr('data-id');
    swal_confirm.fire({
      title: 'Are you sure you want to remove this competency?'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: window.routes.admin_competency_remove,
          data: { id: id },
          successMessage: "Competency removed successfully",
        });
      }
    });
  });

  // Translation management button handler (only for organization competencies)
  $(document).on('click', '.competency-list--org-crud .manage-translations', function(e) {
    e.stopPropagation();
    const competencyId = $(this).data('competency-id');
    openCompetencyTranslationModal(competencyId);
  });

  // Question translation management (only for organization competencies)
  $(document).on('click', '.competency-list--org-crud .manage-question-translations', function(e) {
    e.stopPropagation();
    const questionId = $(this).data('question-id');
    openQuestionTranslationModal(questionId);
  });

  // Initialize tooltips after page load
  setTimeout(function() {
    if (window.tippy) {
      tippy('.language-indicator[data-tippy-content]');
    }
  }, 100);
});

// Translation management functions for admin
function openCompetencyTranslationModal(competencyId) {
  swal_loader.fire();
  
  $.ajax({
    url: window.routes.admin_competency_translations_get,
    method: 'POST',
    data: { 
      id: competencyId,
      _token: $('meta[name="csrf-token"]').attr('content')
    },
    success: function(response) {
      buildTranslationManagementContent(response);
      $('#translation-management-modal').modal('show');
      swal_loader.close();
    },
    error: function(xhr) {
      swal_loader.close();
      console.error('Translation load failed:', xhr);
      alert(xhr.responseJSON?.error || 'An error occurred');
    }
  });
}

function buildTranslationManagementContent(data) {
  // Get language data from window object or use defaults
  const availableLanguages = window.availableLanguages || ['hu', 'en'];
  const languageNames = window.languageNames || {'hu': 'Magyar', 'en': 'English'};
  
  let content = `
    <div class="competency-translation-manager">
      <h6>Competency Translations</h6>
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
          ${isOriginal ? '<span class="badge badge-primary ml-1">Original</span>' : ''}
        </label>
        <input type="text" 
               class="form-control translation-input" 
               data-language="${lang}"
               value="${translation.name || ''}"
               ${isOriginal ? 'required' : ''}>
        ${!isOriginal ? `
          <button class="btn btn-sm btn-info ai-translate-button" 
                  data-target-language="${lang}">
            <i class="fa fa-robot"></i> AI Translate
          </button>
        ` : ''}
      </div>
    `;
  });
  
  content += `
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-info" id="bulk-ai-translate">
          <i class="fa fa-robot"></i> Translate All Missing
        </button>
        <button class="btn btn-primary" id="save-translations">Save</button>
      </div>
    </div>
  `;
  
  $('#translation-content').html(content);
  
  // Bind events for this modal
  bindTranslationEvents(data);
}

function bindTranslationEvents(data) {
  // Save translations
  $('#save-translations').click(function() {
    const translations = {};
    $('.translation-input').each(function() {
      const lang = $(this).data('language');
      const value = $(this).val().trim();
      if (value) {
        translations[lang] = value;
      }
    });
    
    saveCompetencyTranslations(data.competency_id || $('.modal').data('competency-id'), translations);
  });
  
  // Individual AI translation
  $('.ai-translate-button').click(function() {
    const targetLang = $(this).data('target-language');
    const competencyId = data.competency_id || $('.modal').data('competency-id');
    translateCompetencyWithAI(competencyId, [targetLang]);
  });
  
  // Bulk AI translation
  $('#bulk-ai-translate').click(function() {
    const missingLanguages = [];
    $('.translation-input').each(function() {
      const lang = $(this).data('language');
      const value = $(this).val().trim();
      if (!value && lang !== data.original_language) {
        missingLanguages.push(lang);
      }
    });
    
    if (missingLanguages.length > 0) {
      const competencyId = data.competency_id || $('.modal').data('competency-id');
      translateCompetencyWithAI(competencyId, missingLanguages);
    } else {
      alert('No missing translations found');
    }
  });
}

function saveCompetencyTranslations(competencyId, translations) {
  swal_loader.fire();
  
  $.ajax({
    url: window.routes.admin_competency_translations_save,
    method: 'POST',
    data: {
      id: competencyId,
      translations: translations,
      _token: $('meta[name="csrf-token"]').attr('content')
    },
    success: function() {
      swal_loader.close();
      $('#translation-management-modal').modal('hide');
      swal_success.fire({
        title: 'Translations saved successfully'
      }).then(() => {
        location.reload();
      });
    },
    error: function(xhr) {
      swal_loader.close();
      alert(xhr.responseJSON?.error || 'Failed to save translations');
    }
  });
}

function translateCompetencyWithAI(competencyId, targetLanguages) {
  swal_loader.fire();
  
  $.ajax({
    url: window.routes.admin_competency_translations_ai,
    method: 'POST',
    data: {
      id: competencyId,
      languages: targetLanguages,
      _token: $('meta[name="csrf-token"]').attr('content')
    },
    success: function(response) {
      swal_loader.close();
      
      if (response.success && response.translations) {
        // Update the input fields with AI translations
        Object.keys(response.translations).forEach(lang => {
          const input = $(`.translation-input[data-language="${lang}"]`);
          if (input.length && response.translations[lang]) {
            input.val(response.translations[lang]);
            input.addClass('translation-success');
            setTimeout(() => input.removeClass('translation-success'), 2000);
          }
        });
        
        swal_success.fire({
          title: 'AI translation completed'
        });
      } else {
        alert('AI translation failed');
      }
    },
    error: function(xhr) {
      swal_loader.close();
      alert(xhr.responseJSON?.error || 'AI translation failed');
    }
  });
}

// Question translation functions
function openQuestionTranslationModal(questionId) {
  swal_loader.fire();
  
  $.ajax({
    url: window.routes.admin_competency_question_translations_get,
    method: 'POST',
    data: { 
      id: questionId,
      _token: $('meta[name="csrf-token"]').attr('content')
    },
    success: function(response) {
      buildQuestionTranslationContent(response);
      $('#question-translation-modal').modal('show');
      swal_loader.close();
    },
    error: function(xhr) {
      swal_loader.close();
      console.error('Question translation load failed:', xhr);
      alert(xhr.responseJSON?.error || 'An error occurred');
    }
  });
}

function buildQuestionTranslationContent(data) {
  const availableLanguages = window.availableLanguages || ['hu', 'en'];
  const languageNames = window.languageNames || {'hu': 'Magyar', 'en': 'English'};
  
  let content = `
    <div class="question-translation-manager">
      <h6>Question Translations</h6>
      <div class="translation-tabs">
  `;
  
  // Build language tabs
  availableLanguages.forEach((lang, index) => {
    const isActive = index === 0;
    const isOriginal = lang === data.original_language;
    const translation = data.translations[lang] || {};
    const isComplete = translation.is_complete || false;
    const langName = languageNames[lang] || lang.toUpperCase();
    
    let badgeClass = 'badge-secondary';
    if (isOriginal) badgeClass = 'badge-primary';
    else if (isComplete) badgeClass = 'badge-success';
    else if (translation.is_partial) badgeClass = 'badge-warning';
    
    content += `
      <a class="nav-link ${isActive ? 'active' : ''}" 
         data-toggle="tab" 
         href="#question-lang-${lang}">
        ${langName}
        <span class="badge ${badgeClass} ml-1">
          ${isOriginal ? 'ORIG' : (isComplete ? 'OK' : (translation.is_partial ? 'PART' : 'MISS'))}
        </span>
      </a>
    `;
  });
  
  content += `
      </div>
      <div class="tab-content">
  `;
  
  // Build language content
  availableLanguages.forEach((lang, index) => {
    const isActive = index === 0;
    const translation = data.translations[lang] || {};
    
    content += `
      <div class="tab-pane ${isActive ? 'active' : ''}" id="question-lang-${lang}">
        <div class="form-group">
          <label>Question (for rating others)</label>
          <textarea class="form-control question-translated" 
                    data-field="question" 
                    rows="2">${translation.question || ''}</textarea>
        </div>
        <div class="form-group">
          <label>Question (for self-rating)</label>
          <textarea class="form-control question-self-translated" 
                    data-field="question_self" 
                    rows="2">${translation.question_self || ''}</textarea>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label>Minimum Label</label>
              <input type="text" 
                     class="form-control min-label-translated" 
                     data-field="min_label"
                     value="${translation.min_label || ''}">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label>Maximum Label</label>
              <input type="text" 
                     class="form-control max-label-translated" 
                     data-field="max_label"
                     value="${translation.max_label || ''}">
            </div>
          </div>
        </div>
        ${lang !== data.original_language ? `
          <button class="btn btn-info btn-sm ai-translate-question" 
                  data-target-language="${lang}">
            <i class="fa fa-robot"></i> AI Translate This Language
          </button>
        ` : ''}
      </div>
    `;
  });
  
  content += `
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="save-question-translations">Save</button>
      </div>
    </div>
  `;
  
  $('#question-translation-content').html(content);
  bindQuestionTranslationEvents(data);
}

function bindQuestionTranslationEvents(data) {
  // Save question translations
  $('#save-question-translations').click(function() {
    const translations = {};
    
    $('.tab-pane').each(function() {
      const lang = $(this).attr('id').replace('question-lang-', '');
      const fields = {};
      
      $(this).find('[data-field]').each(function() {
        const field = $(this).data('field');
        const value = $(this).val().trim();
        if (value) {
          fields[field] = value;
        }
      });
      
      if (Object.keys(fields).length > 0) {
        translations[lang] = fields;
      }
    });
    
    saveQuestionTranslations(data.question_id, translations);
  });
  
  // AI translation for individual languages
  $('.ai-translate-question').click(function() {
    const targetLang = $(this).data('target-language');
    const questionId = data.question_id;
    translateQuestionWithAI(questionId, [targetLang]);
  });
}

function saveQuestionTranslations(questionId, translations) {
  swal_loader.fire();
  
  $.ajax({
    url: window.routes.admin_competency_question_translations_save,
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
        title: 'Question translations saved successfully'
      }).then(() => {
        location.reload();
      });
    },
    error: function(xhr) {
      swal_loader.close();
      alert(xhr.responseJSON?.error || 'Failed to save question translations');
    }
  });
}

function translateQuestionWithAI(questionId, targetLanguages) {
  swal_loader.fire();
  
  $.ajax({
    url: window.routes.admin_competency_question_translations_ai,
    method: 'POST',
    data: {
      id: questionId,
      languages: targetLanguages,
      _token: $('meta[name="csrf-token"]').attr('content')
    },
    success: function(response) {
      swal_loader.close();
      
      if (response.success && response.translations) {
        // Update the input fields with AI translations
        Object.keys(response.translations).forEach(lang => {
          const tabPane = $(`#question-lang-${lang}`);
          const langData = response.translations[lang];
          
          if (tabPane.length && langData) {
            Object.keys(langData).forEach(field => {
              const input = tabPane.find(`[data-field="${field}"]`);
              if (input.length && langData[field]) {
                input.val(langData[field]);
                input.addClass('translation-success');
                setTimeout(() => input.removeClass('translation-success'), 2000);
              }
            });
          }
        });
        
        swal_success.fire({
          title: 'AI question translation completed'
        });
      } else {
        alert('AI question translation failed');
      }
    },
    error: function(xhr) {
      swal_loader.close();
      alert(xhr.responseJSON?.error || 'AI question translation failed');
    }
  });
}
</script>