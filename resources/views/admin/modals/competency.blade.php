{{-- resources/views/admin/modals/competency.blade.php --}}
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="competency-modal">
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

        
      </div>
      <div class="modal-footer">
        <!-- Action buttons -->
          <button class="btn btn-primary save-competency">{{ __('admin/competencies.save-competency') }}</button>
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
  overflow-x: scroll;
  gap: 5px;
  padding: 0 10px;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  -ms-overflow-style: none;
  scroll-behavior: smooth;
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
  top: -2px;
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
  min-height: 300px;
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
  border-radius: 4px;
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

.competency-description {
  resize: vertical;
  min-height: 75px;
}

.modal-actions {
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid #dee2e6;
}
</style>

<script>
// Global variables for competency translation management
let compCurrentLanguage = 'hu';
let compSelectedLanguages = ['hu'];
let compOriginalLanguage = 'hu';
let compTranslations = {
  name: {},
  description: {}
};

/** Modal megnyitása */
function openCompetencyModal(id = null, name = null, description = null) {
  swal_loader.fire();

  $('#competency-modal').attr('data-id', id ?? 0);
  $('#competency-modal .modal-title').html(
    id == null ? '{{ __('admin/competencies.create-competency') }}' : '{{ __('admin/competencies.modify-competency') }}'
  );
  $('#competency-modal .save-competency').html('{{ __('admin/competencies.save-competency') }}');

  // Reset translations
  compTranslations = { name: {}, description: {} };
  
  // Load selected languages first
  loadCompetencyLanguages().then(() => {
    if (id) {
      loadCompetencyTranslations(id);
    } else {
      // Set default values for new competency
      if (name) compTranslations.name[compOriginalLanguage] = name;
      if (description) compTranslations.description[compOriginalLanguage] = description;
      
      setupCompetencyModal();
      swal_loader.close();
      $('#competency-modal').modal();
    }
  });
}

/** Load languages based on mode */
function loadCompetencyLanguages() {
  const isGlobalMode = window.globalCompetencyMode || false;
  
  if (isGlobalMode) {
    // GLOBAL MODE: Load all available languages automatically
    return $.ajax({
      url: "{{ route('superadmin.competency.languages.available') }}",
      method: 'GET',
      success: function(response) {
        const availableLocales = response.available_locales || {};
        compSelectedLanguages = Object.keys(availableLocales);
        compOriginalLanguage = response.user_default_language || 'hu';
        compCurrentLanguage = compOriginalLanguage;
      },
      error: function() {
        // Fallback for global mode
        compSelectedLanguages = ['hu', 'en', 'de', 'fr', 'es'];
        compOriginalLanguage = 'hu';
        compCurrentLanguage = 'hu';
      }
    });
  } else {
    // ADMIN MODE: Use organization's selected languages
    return $.ajax({
      url: "{{ route('admin.languages.selected') }}",
      method: 'GET',
      success: function(response) {
        compSelectedLanguages = response.selected_languages || ['hu'];
        compOriginalLanguage = '{{ auth()->user()->locale ?? config('app.locale', 'hu') }}';
        compCurrentLanguage = compOriginalLanguage;
      },
      error: function() {
        // Fallback for admin mode
        compSelectedLanguages = ['hu'];
        compOriginalLanguage = 'hu';
        compCurrentLanguage = 'hu';
      }
    });
  }
}

/** Fordítások betöltése (szerkesztéskor) */
function loadCompetencyTranslations(competencyId) {
  const isGlobalMode = window.globalCompetencyMode || false;
  const translationsUrl = isGlobalMode
    ? "{{ route('superadmin.competency.translations.get') }}"
    : "{{ route('admin.competency.translations.get') }}";

  $.ajax({
    url: translationsUrl,
    method: 'POST',
    data: {
      id: competencyId,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      // Load translations
      compOriginalLanguage = response.original_language || compOriginalLanguage;
      compCurrentLanguage = compOriginalLanguage;
      compTranslations.name = response.name_json || {};
      compTranslations.description = response.description_json || {};

      // Set original values if not in translations
      if (response.name && !compTranslations.name[compOriginalLanguage]) {
        compTranslations.name[compOriginalLanguage] = response.name;
      }
      if (response.description && !compTranslations.description[compOriginalLanguage]) {
        compTranslations.description[compOriginalLanguage] = response.description;
      }

      setupCompetencyModal();
      swal_loader.close();
      $('#competency-modal').modal();
    },
    error: function(xhr) {
      setupCompetencyModal();
      swal_loader.close();
      $('#competency-modal').modal();
    }
  });
}

/** Setup modal with language tabs and content */
function setupCompetencyModal() {
  const availableLocales = @json(config('app.available_locales'));
  
  // Build language tabs
  const tabsContainer = $('.language-tabs');
  tabsContainer.empty();
  
  compSelectedLanguages.forEach((lang, index) => {
    if (availableLocales[lang]) {
      const isActive = lang === compCurrentLanguage;
      const hasAllTranslations = checkAllCompetencyTranslations(lang);
      
      const tab = $(`
        <div class="language-tab ${isActive ? 'active' : ''} ${hasAllTranslations ? '' : 'missing'}" 
             data-lang="${lang}">
          ${availableLocales[lang]} 
          ${lang === compOriginalLanguage ? '({{ __('admin/competencies.original') }})' : ''}
        </div>
      `);
      
      tab.click(() => switchCompetencyLanguage(lang));
      tabsContainer.append(tab);
    }
  });
  
  // Build content for each language
  const contentContainer = $('.translation-content');
  contentContainer.empty();
  
  compSelectedLanguages.forEach(lang => {
    const isActive = lang === compCurrentLanguage;
    const content = $(`
      <div class="language-content ${isActive ? 'active' : ''}" data-lang="${lang}">
        <h5>
          ${availableLocales[lang]} 
          ${lang === compOriginalLanguage ? '({{ __('admin/competencies.original') }})' : ''}
        </h5>
        
        <div class="translation-field" data-field="name">
          <label>{{ __('global.name') }}</label>
          <input class="form-control competency-name" type="text"
                 value="${compTranslations.name[lang] || ''}">
        </div>
        
        <div class="translation-field" data-field="description">
          <label>{{ __('global.description') }}</label>
          <textarea class="form-control competency-description" rows="3"
                    placeholder="{{ __('admin/competencies.enter-description') }}">${compTranslations.description[lang] || ''}</textarea>
        </div>
      </div>
    `);
    
    contentContainer.append(content);
  });
  
  updateCompetencyLanguageTabs();
  addCompetencyTranslationButton();
  initCarouselNavigation();
}

/** Check if language has all required translations */
function checkAllCompetencyTranslations(lang) {
  if (lang === compOriginalLanguage) return true;
  
  return compTranslations.name[lang] && compTranslations.name[lang].trim() !== '';
}

/** Switch to a different language */
function switchCompetencyLanguage(lang) {
  // Save current language data before switching
  saveCurrentCompetencyLanguageData();
  
  compCurrentLanguage = lang;
  
  // Update tabs
  $('.language-tab').removeClass('active');
  $(`.language-tab[data-lang="${lang}"]`).addClass('active');
  
  // Update content
  $('.language-content').removeClass('active');
  $(`.language-content[data-lang="${lang}"]`).addClass('active');
  
  updateCompetencyFieldStatus();
  checkCompetencyTranslationButtonState();
}

/** Save current language form data */
function saveCurrentCompetencyLanguageData() {
  const currentContent = $(`.language-content[data-lang="${compCurrentLanguage}"]`);
  if (currentContent.length > 0) {
    compTranslations.name[compCurrentLanguage] = currentContent.find('.competency-name').val() || '';
    compTranslations.description[compCurrentLanguage] = currentContent.find('.competency-description').val() || '';
  }
}

/** Update field status for current language */
function updateCompetencyFieldStatus() {
  const currentContent = $(`.language-content[data-lang="${compCurrentLanguage}"]`);
  
  if (compCurrentLanguage !== compOriginalLanguage) {
    // Check for missing translations
    const fields = ['name', 'description'];
    
    fields.forEach(field => {
      const fieldElement = currentContent.find(`.translation-field[data-field="${field}"]`);
      const hasTranslation = compTranslations[field] && compTranslations[field][compCurrentLanguage] && compTranslations[field][compCurrentLanguage].trim() !== '';
      
      if (field === 'name' && !hasTranslation) {
        fieldElement.addClass('missing');
      } else if (field === 'description') {
        // Description is optional, so don't mark as missing
        fieldElement.removeClass('missing');
      } else {
        fieldElement.removeClass('missing');
      }
    });
  }
}

/** Update language tab status */
function updateCompetencyLanguageTabs() {
  compSelectedLanguages.forEach(lang => {
    const tab = $(`.language-tab[data-lang="${lang}"]`);
    const hasAllTranslations = checkAllCompetencyTranslations(lang);
    
    if (hasAllTranslations) {
      tab.removeClass('missing');
    } else {
      tab.addClass('missing');
    }
  });
}

/** Initialize carousel navigation */
function initCarouselNavigation() {
  const tabsContainer = $('.language-tabs');

  // Previous button
  $('.carousel-prev').off('click').on('click', function() {
    const currentScroll = tabsContainer.scrollLeft();
    const firstTab = tabsContainer.find('.language-tab').first();
    const firstTabWidth = firstTab.outerWidth(true);

    if (currentScroll === 0) {
      tabsContainer.animate({
        scrollLeft: tabsContainer.prop('scrollWidth') - tabsContainer.prop('clientWidth')
      }, 200);
    } else {
      tabsContainer.animate({
        scrollLeft: currentScroll - firstTabWidth - 5
      }, 200);
    }
  });

  // Next button
  $('.carousel-next').off('click').on('click', function() {
    const currentScroll = tabsContainer.scrollLeft();
    const scrollEnd = tabsContainer.prop('scrollWidth') - tabsContainer.prop('clientWidth');
    const firstTab = tabsContainer.find('.language-tab').first();
    const firstTabWidth = firstTab.outerWidth(true);

    if (currentScroll >= scrollEnd - 5) {
      tabsContainer.animate({
        scrollLeft: 0
      }, 200);
    } else {
      tabsContainer.animate({
        scrollLeft: currentScroll + firstTabWidth + 5
      }, 200);
    }
  });
}

/** Add AI translation button */
function addCompetencyTranslationButton() {
  if ($('#competency-modal .ai-translate-competency').length === 0) {
    const aiButton = $(`
      <button type="button" class="btn btn-outline-primary ai-translate-competency disabled" style="margin-right: 0.5rem;" disabled>
        <i class="fa fa-robot"></i> {{ __('admin/competencies.ai-translate') }}
      </button>
    `);
    
    aiButton.insertBefore('#competency-modal .save-competency');
  }
  
  initCompetencyTranslationButton();
}

/** Initialize AI translation button */
function initCompetencyTranslationButton() {
  // Remove existing event handlers
  $(document).off('input.aicomp keyup.aicomp change.aicomp', '#competency-modal .language-content input, #competency-modal .language-content textarea');
  $(document).off('click.aicomp', '.language-tab');
  
  // Listen to input changes
  $(document).on('input.aicomp keyup.aicomp change.aicomp', '#competency-modal .language-content input, #competency-modal .language-content textarea', function() {
    saveCurrentCompetencyLanguageData();
    setTimeout(checkCompetencyTranslationButtonState, 50);
  });
  
  // Listen to language tab switches
  $(document).on('click.aicomp', '.language-tab', function() {
    setTimeout(checkCompetencyTranslationButtonState, 100);
  });
  
  // Initial check
  setTimeout(checkCompetencyTranslationButtonState, 100);
  
  // Handle translation button click
  $(document).off('click.aitranslatecomp', '#competency-modal .ai-translate-competency').on('click.aitranslatecomp', '#competency-modal .ai-translate-competency', function() {
    if ($(this).hasClass('disabled')) return;
    
    const nameToTranslate = compTranslations.name[compOriginalLanguage] || '';
    const descriptionToTranslate = compTranslations.description[compOriginalLanguage] || '';
    const targetLanguages = compSelectedLanguages.filter(lang => lang !== compOriginalLanguage);
    
    if (!nameToTranslate) {
      swal.fire({
        icon: 'warning',
        title: '{{ __('admin/competencies.name-required') }}',
        text: '{{ __('admin/competencies.enter-name-first') }}'
      });
      return;
    }
    
    translateCompetencyName(nameToTranslate, descriptionToTranslate, compOriginalLanguage, targetLanguages);
  });
}

/** Check AI translation button state */
function checkCompetencyTranslationButtonState() {
  const translateButton = $('#competency-modal .ai-translate-competency');
  if (translateButton.length === 0) return;
  
  const nameInOriginal = compTranslations.name[compOriginalLanguage] && compTranslations.name[compOriginalLanguage].trim() !== '';
  const hasMultipleLanguages = compSelectedLanguages.length > 1;
  const isOriginalLanguage = compCurrentLanguage === compOriginalLanguage;
  
  if (nameInOriginal && hasMultipleLanguages && isOriginalLanguage) {
    translateButton.removeClass('disabled').prop('disabled', false);
  } else {
    translateButton.addClass('disabled').prop('disabled', true);
  }
}

/** Translate competency using AI */
function translateCompetencyName(nameToTranslate, descriptionToTranslate, sourceLanguage, targetLanguages) {
  const translateButton = $('#competency-modal .ai-translate-competency');
  const originalText = translateButton.html();
  translateButton.html('<i class="fa fa-spinner fa-spin"></i> {{ __('admin/competencies.translating') }}...').prop('disabled', true);
  
  const isGlobalMode = window.globalCompetencyMode || false;
  const translateUrl = isGlobalMode
    ? "{{ route('superadmin.competency.translate-name') }}"
    : "{{ route('admin.competency.translate-name') }}";

  $.ajax({
    url: translateUrl,
    method: 'POST',
    data: {
      competency_name: nameToTranslate,
      competency_description: descriptionToTranslate,
      source_language: sourceLanguage,
      target_languages: targetLanguages,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      if (response.success && response.translations) {
        // Update translations with new AI translations
        Object.entries(response.translations).forEach(([lang, translations]) => {
          if (translations.name) {
            compTranslations.name[lang] = translations.name;
          }
          if (translations.description && descriptionToTranslate) {
            compTranslations.description[lang] = translations.description;
          }
        });
        
        updateAllCompetencyLanguageContent();
        updateCompetencyLanguageTabs();
        
        // ✅ FIXED: Use toast instead of swal.fire
        window.toast('success', '{{ __('admin/competencies.translation-success') }}');
      }
    },
    error: function(xhr) {
      swal.fire({
        icon: 'error',
        title: '{{ __('admin/competencies.translation-failed') }}',
        text: xhr.responseJSON?.message || '{{ __('admin/competencies.translation-error') }}'
      });
    },
    complete: function() {
      translateButton.html(originalText).prop('disabled', false);
      checkCompetencyTranslationButtonState();
    }
  });
}

/** Update all language content with new translations */
function updateAllCompetencyLanguageContent() {
  compSelectedLanguages.forEach(lang => {
    const langContent = $(`.language-content[data-lang="${lang}"]`);
    
    if (langContent.length > 0) {
      langContent.find('.competency-name').val(compTranslations.name[lang] || '');
      langContent.find('.competency-description').val(compTranslations.description[lang] || '');
    }
  });
}

/** Event Listeners */
$(document).ready(function() {
  
  // Save competency
  $('.save-competency').off('click').on('click', function() {
    // Save current language data before saving
    saveCurrentCompetencyLanguageData();
    
    swal_confirm.fire({
      title: '{{ __('admin/competencies.save-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        const originalName = compTranslations.name[compOriginalLanguage] || '';
        const originalDescription = compTranslations.description[compOriginalLanguage] || '';
        
        // Filter out empty translations
        const nameTranslations = Object.fromEntries(
          Object.entries(compTranslations.name).filter(([key, value]) => value && value.trim())
        );
        
        const descriptionTranslations = Object.fromEntries(
          Object.entries(compTranslations.description).filter(([key, value]) => value && value.trim())
        );

        const isGlobalMode = window.globalCompetencyMode || false;
        const saveUrl = isGlobalMode 
          ? "{{ route('superadmin.competency.save') }}"
          : "{{ route('admin.competency.save') }}";

        // ✅ FIXED: Close modal BEFORE AJAX
        $('#competency-modal').modal('hide');

        // ✅ FIXED: Use successMessage property
        $.ajax({
          url: saveUrl,
          method: 'POST',
          data: {
            id: $('#competency-modal').attr('data-id'),
            name: originalName,
            description: originalDescription,
            translations: nameTranslations,
            description_translations: descriptionTranslations,
            original_language: compOriginalLanguage,
            _token: '{{ csrf_token() }}'
          },
          successMessage: '{{ __('admin/competencies.save-competency-success') }}',
          error: function(xhr) {
            swal_loader.close();
            swal.fire({
              icon: 'error',
              title: '{{ __('global.error') }}',
              text: xhr.responseJSON?.message || '{{ __('admin/competencies.translation-error') }}'
            });
          }
        });
      }
    });
  });
});

window.openCompetencyModal = openCompetencyModal;
</script>