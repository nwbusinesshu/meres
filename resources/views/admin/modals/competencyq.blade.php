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
        
        <!-- Language Carousel -->
        <div class="language-carousel-container">
          <div class="language-carousel">
            <div class="carousel-nav">
              <button class="carousel-prev" disabled>‹</button>
              <div class="language-tabs"></div>
              <button class="carousel-next">›</button>
            </div>
          </div>
        </div>

        <!-- Translation Content -->
        <div class="translation-content">
          <!-- Content will be dynamically generated for each language -->
        </div>

        <!-- Scale (always visible, not translated) -->
        <div class="form-row centered" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #eee;">
          <div class="form-group">
            <label>{{ __('admin/competencies.scale') }}</label>
            <input class="form-control scale" type="number" step="1" min="3" max="10"/>
          </div>
        </div>

        <button class="btn btn-primary save-competencyq">{{ __('admin/competencies.question-save') }}</button>
      </div>
    </div>
  </div>
</div>

<style>
.language-carousel-container {
  margin-bottom: 25px;
  border-bottom: 2px solid #f0f0f0;
  padding-bottom: 15px;
}

.language-carousel {
  width: 100%;
}

.carousel-nav {
  display: flex;
  align-items: center;
  gap: 10px;
}

.carousel-prev, .carousel-next {
  background: #6c757d;
  color: white;
  border: none;
  padding: 8px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 18px;
  font-weight: bold;
}

.carousel-prev:hover:not(:disabled), .carousel-next:hover:not(:disabled) {
  background: #5a6268;
}

.carousel-prev:disabled, .carousel-next:disabled {
  background: #dee2e6;
  color: #6c757d;
  cursor: not-allowed;
}

.language-tabs {
  flex: 1;
  overflow: hidden;
  position: relative;
}

.language-tabs-inner {
  display: flex;
  transition: transform 0.3s ease;
  gap: 10px;
}

.language-tab {
  flex-shrink: 0;
  padding: 10px 20px;
  background: #f8f9fa;
  border: 2px solid #dee2e6;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  min-width: 100px;
  text-align: center;
  position: relative;
}

.language-tab.active {
  background: #007bff;
  color: white;
  border-color: #0056b3;
}

.language-tab.original {
  background: #28a745;
  color: white;
  border-color: #1e7e34;
}

.language-tab.original.active {
  background: #1e7e34;
}

.language-tab.missing-translations {
  border-color: #ffc107;
  background: #fff3cd;
}

.language-tab.missing-translations::after {
  content: "⚠️";
  position: absolute;
  top: -5px;
  right: -5px;
  font-size: 12px;
}

.translation-content {
  min-height: 400px;
}

.language-content {
  display: none;
}

.language-content.active {
  display: block;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.translation-field {
  margin-bottom: 20px;
}

.translation-field.missing {
  background: #fff3cd;
  padding: 10px;
  border-radius: 5px;
  border-left: 4px solid #ffc107;
}

.translation-field label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 8px;
  display: block;
}

.translation-field.missing label {
  color: #856404;
}

.translation-field textarea,
.translation-field input {
  width: 100%;
  border-radius: 4px;
  border: 1px solid #ced4da;
  padding: 10px;
}

.translation-field.missing textarea,
.translation-field.missing input {
  border-color: #ffc107;
}

.form-row.flex {
  display: flex;
  gap: 15px;
}

.form-row.flex .form-group {
  flex: 1;
}

.form-row.centered {
  display: flex;
  justify-content: center;
}

.form-row.centered .form-group {
  min-width: 200px;
}
</style>

<script>
// Global variables for question translation management
let qCurrentLanguage = 'hu';
let qSelectedLanguages = ['hu'];
let qOriginalLanguage = 'hu';
let qTranslations = {
  question: {},
  question_self: {},
  min_label: {},
  max_label: {}
};
let qCarouselPosition = 0;

function openCompetencyQModal(id = null, compId = null) {
  swal_loader.fire();
  $('#competencyq-modal').attr('data-id', id ?? 0);
  $('#competencyq-modal').attr('data-compid', compId ?? 0);
  $('#competencyq-modal .modal-title').html(id == null ? '{{ __('admin/competencies.create-question') }}' : '{{ __('admin/competencies.question-modify') }}');
  
  // Reset state
  qCurrentLanguage = '{{ auth()->user()->locale ?? config('app.locale', 'hu') }}';
  qOriginalLanguage = qCurrentLanguage;
  qSelectedLanguages = [qCurrentLanguage];
  qTranslations = {
    question: {},
    question_self: {},
    min_label: {},
    max_label: {}
  };
  qCarouselPosition = 0;

  // Load organization languages first
  loadQuestionSelectedLanguages().then(() => {
    if (id) {
      loadQuestionTranslations(id);
    } else {
      setupQuestionModal();
      swal_loader.close();
      $('#competencyq-modal').modal();
    }
  });
}

function loadQuestionSelectedLanguages() {
  return new Promise((resolve) => {
    $.ajax({
      url: "{{ route('admin.languages.selected') }}",
      method: 'GET',
      success: function(response) {
        qSelectedLanguages = response.selected_languages || [qOriginalLanguage];
        resolve();
      },
      error: function() {
        qSelectedLanguages = [qOriginalLanguage];
        resolve();
      }
    });
  });
}

function loadQuestionTranslations(questionId) {
  $.ajax({
    url: "{{ route('admin.competency.question.translations.get') }}",
    method: 'POST',
    data: { 
      id: questionId,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      // Set basic values
      $('#competencyq-modal .scale').val(response.max_value);
      qOriginalLanguage = response.original_language || qOriginalLanguage;
      
      // Load translations
      qTranslations.question = response.question_json || {};
      qTranslations.question_self = response.question_self_json || {};
      qTranslations.min_label = response.min_label_json || {};
      qTranslations.max_label = response.max_label_json || {};

      // Ensure original language data exists
      if (!qTranslations.question[qOriginalLanguage]) {
        qTranslations.question[qOriginalLanguage] = response.question;
      }
      if (!qTranslations.question_self[qOriginalLanguage]) {
        qTranslations.question_self[qOriginalLanguage] = response.question_self;
      }
      if (!qTranslations.min_label[qOriginalLanguage]) {
        qTranslations.min_label[qOriginalLanguage] = response.min_label;
      }
      if (!qTranslations.max_label[qOriginalLanguage]) {
        qTranslations.max_label[qOriginalLanguage] = response.max_label;
      }

      setupQuestionModal();
      swal_loader.close();
      $('#competencyq-modal').modal();
    },
    error: function() {
      setupQuestionModal();
      swal_loader.close();
      $('#competencyq-modal').modal();
    }
  });
}

function setupQuestionModal() {
  setupQuestionCarousel();
  generateQuestionContent();
  switchQuestionLanguage(qOriginalLanguage);
}

function setupQuestionCarousel() {
  const tabsContainer = $('.language-tabs');
  tabsContainer.empty();
  
  const tabsInner = $('<div class="language-tabs-inner"></div>');
  
  qSelectedLanguages.forEach(lang => {
    const isOriginal = lang === qOriginalLanguage;
    const hasMissingTranslations = checkQuestionMissingTranslations(lang);
    
    let tabClass = 'language-tab';
    if (isOriginal) tabClass += ' original';
    if (hasMissingTranslations && !isOriginal) tabClass += ' missing-translations';
    
    const tab = $(`<div class="${tabClass}" data-lang="${lang}">
      ${getLanguageName(lang)}
    </div>`);
    
    tab.click(function() {
      switchQuestionLanguage(lang);
    });
    
    tabsInner.append(tab);
  });
  
  tabsContainer.append(tabsInner);
  updateQuestionCarouselNavigation();
}

function checkQuestionMissingTranslations(lang) {
  if (lang === qOriginalLanguage) return false;
  
  return !qTranslations.question[lang] || 
         !qTranslations.question_self[lang] || 
         !qTranslations.min_label[lang] || 
         !qTranslations.max_label[lang];
}

function generateQuestionContent() {
  const contentContainer = $('.translation-content');
  contentContainer.empty();
  
  qSelectedLanguages.forEach(lang => {
    const isOriginal = lang === qOriginalLanguage;
    const languageName = getLanguageName(lang);
    
    const content = $(`
      <div class="language-content" data-lang="${lang}">
        <h5 style="margin-bottom: 20px; color: #495057; border-bottom: 2px solid #eee; padding-bottom: 10px;">
          ${languageName}${isOriginal ? ' ({{ __('admin/competencies.original') }})' : ''}
        </h5>
        
        <div class="translation-field question-field" data-field="question">
          <label>{{ __('admin/competencies.question') }}</label>
          <textarea class="form-control question-input" rows="4" maxlength="1024" style="resize: none;" data-lang="${lang}"></textarea>
        </div>
        
        <div class="translation-field question-self-field" data-field="question_self">
          <label>{{ __('admin/competencies.question-self') }}</label>
          <textarea class="form-control question-self-input" rows="4" maxlength="1024" style="resize: none;" data-lang="${lang}"></textarea>
        </div>
        
        <div class="form-row flex">
          <div class="translation-field min-label-field" data-field="min_label">
            <label>{{ __('admin/competencies.min-label') }}</label>
            <input type="text" class="form-control min-label-input" data-lang="${lang}"/>
          </div>
          <div class="translation-field max-label-field" data-field="max_label">
            <label>{{ __('admin/competencies.max-label') }}</label>
            <input type="text" class="form-control max-label-input" data-lang="${lang}"/>
          </div>
        </div>
      </div>
    `);
    
    contentContainer.append(content);
  });
  
  // Populate fields with existing translations
  populateQuestionFields();
  
  // Add change handlers
  $('.language-content input, .language-content textarea').on('input', function() {
    const field = $(this).closest('.translation-field').data('field');
    const lang = $(this).data('lang');
    const value = $(this).val();
    
    if (!qTranslations[field]) qTranslations[field] = {};
    qTranslations[field][lang] = value;
    
    updateQuestionFieldStatus();
    updateQuestionTabStatus();
  });
}

function populateQuestionFields() {
  qSelectedLanguages.forEach(lang => {
    $(`.question-input[data-lang="${lang}"]`).val(qTranslations.question[lang] || '');
    $(`.question-self-input[data-lang="${lang}"]`).val(qTranslations.question_self[lang] || '');
    $(`.min-label-input[data-lang="${lang}"]`).val(qTranslations.min_label[lang] || '');
    $(`.max-label-input[data-lang="${lang}"]`).val(qTranslations.max_label[lang] || '');
  });
}

function switchQuestionLanguage(lang) {
  qCurrentLanguage = lang;
  
  // Update tabs
  $('.language-tab').removeClass('active');
  $(`.language-tab[data-lang="${lang}"]`).addClass('active');
  
  // Update content
  $('.language-content').removeClass('active');
  $(`.language-content[data-lang="${lang}"]`).addClass('active');
  
  updateQuestionFieldStatus();
}

function updateQuestionFieldStatus() {
  const currentContent = $(`.language-content[data-lang="${qCurrentLanguage}"]`);
  
  if (qCurrentLanguage !== qOriginalLanguage) {
    // Check for missing translations
    const fields = ['question', 'question_self', 'min_label', 'max_label'];
    
    fields.forEach(field => {
      const fieldElement = currentContent.find(`.translation-field[data-field="${field}"]`);
      const hasTranslation = qTranslations[field] && qTranslations[field][qCurrentLanguage];
      
      if (!hasTranslation) {
        fieldElement.addClass('missing');
      } else {
        fieldElement.removeClass('missing');
      }
    });
  }
}

function updateQuestionTabStatus() {
  qSelectedLanguages.forEach(lang => {
    const tab = $(`.language-tab[data-lang="${lang}"]`);
    const hasMissingTranslations = checkQuestionMissingTranslations(lang);
    
    if (hasMissingTranslations && lang !== qOriginalLanguage) {
      tab.addClass('missing-translations');
    } else {
      tab.removeClass('missing-translations');
    }
  });
}

function updateQuestionCarouselNavigation() {
  const tabs = $('.language-tabs-inner .language-tab');
  const visibleCount = 3; // Number of tabs visible at once
  
  $('.carousel-prev').prop('disabled', qCarouselPosition <= 0);
  $('.carousel-next').prop('disabled', qCarouselPosition >= tabs.length - visibleCount);
  
  const translateX = -(qCarouselPosition * (100 + 10)); // 100px width + 10px gap
  $('.language-tabs-inner').css('transform', `translateX(${translateX}px)`);
}

function getLanguageName(code) {
  const names = {
    'hu': 'Magyar',
    'en': 'English', 
    'de': 'Deutsch',
    'ro': 'Română'
  };
  return names[code] || code.toUpperCase();
}

// Carousel navigation
$(document).on('click', '.carousel-prev', function() {
  if (qCarouselPosition > 0) {
    qCarouselPosition--;
    updateQuestionCarouselNavigation();
  }
});

$(document).on('click', '.carousel-next', function() {
  const tabs = $('.language-tabs-inner .language-tab');
  const visibleCount = 3;
  
  if (qCarouselPosition < tabs.length - visibleCount) {
    qCarouselPosition++;
    updateQuestionCarouselNavigation();
  }
});

// Save functionality
$(document).ready(function(){
  $('#competencyq-modal .save-competencyq').click(function(){
    swal_confirm.fire({
      title: '{{ __('admin/competencies.question-save-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        // Prepare data
        const questionId = $('#competencyq-modal').attr('data-id');
        const compId = $('#competencyq-modal').attr('data-compid');
        
        // Get values from original language
        const originalQuestion = qTranslations.question[qOriginalLanguage] || '';
        const originalQuestionSelf = qTranslations.question_self[qOriginalLanguage] || '';
        const originalMinLabel = qTranslations.min_label[qOriginalLanguage] || '';
        const originalMaxLabel = qTranslations.max_label[qOriginalLanguage] || '';
        const scale = $('#competencyq-modal .scale').val();
        
        const data = {
          id: questionId,
          compId: compId,
          question: originalQuestion,
          questionSelf: originalQuestionSelf,
          minLabel: originalMinLabel,
          maxLabel: originalMaxLabel,
          scale: scale,
          question_translations: qTranslations.question,
          question_self_translations: qTranslations.question_self,
          min_label_translations: qTranslations.min_label,
          max_label_translations: qTranslations.max_label,
          _token: '{{ csrf_token() }}'
        };
        
        $.ajax({
          url: "{{ route('admin.competency.question.save') }}",
          method: 'POST',
          data: data,
          successMessage: "{{ __('admin/competencies.question-save-success') }}",
        });
      }
    });
  });
});
</script>