{{-- resources/views/admin/modals/competencyq.blade.php --}}
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
              <button class="carousel-prev">‹</button>
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

        <button class="btn btn-primary save-question">{{ __('admin/competencies.question-save') }}</button>
      </div>
    </div>
  </div>
</div>

<style>
.language-carousel-container {
  margin-bottom: 20px;
}

.language-carousel {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 0px;
  border: 1px solid #dee2e6;
}

.carousel-nav {
  display: flex;
  align-items: center;
  gap: 10px;
}

.carousel-prev, 
.carousel-next {
  background: #6c757d;
  color: white;
  border: none;
  border-radius: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 32px;
  font-weight: bold;
  padding-bottom: 10px;
}

.carousel-prev:disabled, 
.carousel-next:disabled {
  background: #e9ecef;
  color: #6c757d;
  cursor: not-allowed;
}

.language-tabs {
  display: flex;
  flex: 1;
  overflow-x: scroll; /* Változtatás: 'scroll' vagy 'auto' */
  gap: 5px;
  padding: 0 10px;
  -webkit-overflow-scrolling: touch; /* iOS-specifikus gördítési inercia */
  scrollbar-width: none;
  -ms-overflow-style: none;
  scroll-behavior: smooth; /* Zökkenőmentes lapozás */
}

.language-tabs::-webkit-scrollbar {
  display: none;
}

.language-tab {
  background: white;
  border: 2px solid #dee2e6;
  padding: 8px 16px;
  border-radius: 20px;
  cursor: pointer;
  white-space: nowrap;
  min-width: fit-content;
  font-weight: 500;
  position: relative;
  transition: all 0.2s ease;
}

.language-tab:hover {
  border-color: #0d6efd;
  background: #f8f9ff;
}

.language-tab.active {
  background: #0d6efd;
  border-color: #0d6efd;
  color: white;
}

.language-tab.missing::after {
  content: '!';
  position: absolute;
  top: -5px;
  right: -5px;
  background: #ffc107;
  color: #000;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: bold;
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
  padding: 0px;
  border-radius: 0px;
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
    // 🔥 CONTEXT-AWARE ROUTE SELECTION
    const isGlobalMode = window.globalCompetencyMode || false;
    const languagesUrl = isGlobalMode ? 
      "{{ route('superadmin.competency.languages.selected') }}" : 
      "{{ route('admin.languages.selected') }}";
    
    console.log('🔥 Loading languages from:', languagesUrl);
    
    $.ajax({
      url: languagesUrl,
      method: 'GET',
      success: function(response) {
        qSelectedLanguages = response.selected_languages || [qOriginalLanguage];
        console.log('🔥 Selected languages:', qSelectedLanguages);
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
  // 🔥 CONTEXT-AWARE ROUTE SELECTION
  const isGlobalMode = window.globalCompetencyMode || false;
  const translationsUrl = isGlobalMode ? 
    "{{ route('superadmin.competency.question.translations.get') }}" : 
    "{{ route('admin.competency.question.translations.get') }}";
  
  console.log('🔥 Loading translations from:', translationsUrl);
  
  $.ajax({
    url: translationsUrl,
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
      
      // Set current form values from original or current language
      const currentLang = qCurrentLanguage;
      $('#competencyq-modal .question').val(qTranslations.question[currentLang] || response.question || '');
      $('#competencyq-modal .question-self').val(qTranslations.question_self[currentLang] || response.question_self || '');
      $('#competencyq-modal .min-label').val(qTranslations.min_label[currentLang] || response.min_label || '');
      $('#competencyq-modal .max-label').val(qTranslations.max_label[currentLang] || response.max_label || '');
      
      console.log('🔥 Loaded translations:', qTranslations);
      
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
  const availableLocales = @json(config('app.available_locales'));
  
  // Build language tabs
  const tabsContainer = $('.language-tabs');
  tabsContainer.empty();
  
  qSelectedLanguages.forEach((lang, index) => {
    if (availableLocales[lang]) {
      const isActive = lang === qCurrentLanguage;
      const hasAllTranslations = checkAllTranslations(lang);
      
      const tab = $(`
        <div class="language-tab ${isActive ? 'active' : ''} ${hasAllTranslations ? '' : 'missing'}" 
             data-lang="${lang}">
          ${availableLocales[lang]} 
          ${lang === qOriginalLanguage ? '({{ __('admin/competencies.original') }})' : ''}
        </div>
      `);
      
      tab.click(() => switchQuestionLanguage(lang));
      tabsContainer.append(tab);
    }
  });
  
  // Build content for each language
  const contentContainer = $('.translation-content');
  contentContainer.empty();
  
  qSelectedLanguages.forEach(lang => {
    const isActive = lang === qCurrentLanguage;
    const content = $(`
      <div class="language-content ${isActive ? 'active' : ''}" data-lang="${lang}">
        <h5>
          ${availableLocales[lang]} 
          ${lang === qOriginalLanguage ? '({{ __('admin/competencies.original') }})' : ''}
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
    const hasAllTranslations = checkAllTranslations(lang);
    
    if (hasAllTranslations) {
      tab.removeClass('missing');
    } else {
      tab.addClass('missing');
    }
  });
}

function checkAllTranslations(lang) {
  if (lang === qOriginalLanguage) return true;
  
  const fields = ['question', 'question_self', 'min_label', 'max_label'];
  return fields.every(field => 
    qTranslations[field] && qTranslations[field][lang] && qTranslations[field][lang].trim()
  );
}

// 🔥 CONTEXT-AWARE SAVE HANDLER
$(document).ready(function(){
  $('.save-question').click(function(){
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
        
        // 🔥 CONTEXT-AWARE ROUTE SELECTION - FIXED: Use q.save alias for global
        const isGlobalMode = window.globalCompetencyMode || false;
        const saveUrl = isGlobalMode ? 
          "{{ route('superadmin.competency.q.save') }}" : 
          "{{ route('admin.competency.question.save') }}";
        
        console.log('🔥 Saving to URL:', saveUrl);
        console.log('🔥 Data:', data);
        
        $.ajax({
          url: saveUrl,
          method: 'POST',
          data: data,
          success: function(response) {
            console.log('🔥 SUCCESS:', response);
            swal_loader.close();
            $('#competencyq-modal').modal('hide');
            
            swal.fire({
              icon: 'success',
              title: '{{ __('global.success') }}',
              text: '{{ __('admin/competencies.question-save-success') }}',
              timer: 2000,
              showConfirmButton: false
            }).then(() => {
              location.reload();
            });
          },
          error: function(xhr) {
            console.error('🔥 ERROR:', xhr);
            swal_loader.close();
            swal.fire({
              icon: 'error',
              title: '{{ __('global.error') }}',
              text: xhr.responseJSON?.message || '{{ __('admin/competencies.question-translation-error') }}'
            });
          }
        });
      }
    });
  });
});
$(document).ready(function() {
  const tabsContainer = $('.language-tabs');

// Balra lapozás (vissza a végére, ha az elején van)
$('.carousel-prev').on('click', function() {
  const currentScroll = tabsContainer.scrollLeft();
  const firstTab = tabsContainer.find('.language-tab').first();
  const firstTabWidth = firstTab.outerWidth(true);

  if (currentScroll === 0) {
    // Ha az elején van, ugorjon a végére
    tabsContainer.animate({
      scrollLeft: tabsContainer.prop('scrollWidth') - tabsContainer.prop('clientWidth')
    }, 200);
  } else {
    // Vissza a balra lapozás
    tabsContainer.animate({
      scrollLeft: currentScroll - firstTabWidth - 5 // a gap értékét is figyelembe véve
    }, 200);
  }
});

// Jobbra lapozás (vissza az elejére, ha a végén van)
$('.carousel-next').on('click', function() {
  const currentScroll = tabsContainer.scrollLeft();
  const scrollEnd = tabsContainer.prop('scrollWidth') - tabsContainer.prop('clientWidth');
  const firstTab = tabsContainer.find('.language-tab').first();
  const firstTabWidth = firstTab.outerWidth(true);

  if (currentScroll >= scrollEnd - 5) { // kis hibahatárral
    // Ha a végén van, ugorjon az elejére
    tabsContainer.animate({
      scrollLeft: 0
    }, 200);
  } else {
    // Tovább a jobbra lapozás
    tabsContainer.animate({
      scrollLeft: currentScroll + firstTabWidth + 5
    }, 200);
  }
});
});
</script>