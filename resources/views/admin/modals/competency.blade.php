<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="competency-modal">
  <div class="modal-dialog modal-lg" role="document">
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
          <nav class="nav nav-pills nav-fill mb-3" id="language-nav">
            <!-- Language tabs will be populated dynamically -->
          </nav>
        </div>

        <!-- Single Language Mode (default) -->
        <div class="single-language-mode">
          <div class="form-row">
            <div class="form-group">
              <label>{{ __('global.name') }}</label>
              <input type="text" class="form-control competency-name" >
            </div>
          </div>
        </div>

        <!-- Multi Language Mode -->
        <div class="multi-language-mode" style="display: none;">
          <div class="tab-content" id="language-content">
            <!-- Language content will be populated dynamically -->
          </div>
        </div>

        <!-- Translation Controls -->
        <div class="translation-controls" style="display: none;">
          <div class="form-row">
            <div class="col-md-6">
              <button class="btn btn-outline-primary btn-sm" id="create-translations-btn">
                <i class="fa fa-language"></i> {{ __('translations.create-translations') }}
              </button>
            </div>
            <div class="col-md-6 text-right">
              <button class="btn btn-outline-info btn-sm" id="translate-ai-btn" style="display: none;">
                <i class="fa fa-robot"></i> {{ __('translations.translate-with-ai') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Language Selection Modal for New Translations -->
        <div class="language-selection" style="display: none;">
          <h6>{{ __('translations.select-languages') }}</h6>
          <div class="form-check-list" id="language-checkboxes">
            <!-- Checkboxes will be populated dynamically -->
          </div>
          <div class="form-row mt-2">
            <div class="col-md-6">
              <button class="btn btn-secondary btn-sm" id="cancel-language-selection">
                {{ __('global.cancel') }}
              </button>
            </div>
            <div class="col-md-6 text-right">
              <button class="btn btn-primary btn-sm" id="confirm-language-selection">
                {{ __('global.confirm') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
          <button class="btn btn-primary save-competency"></button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Global variables for competency modal
let competencyModalData = {
  id: null,
  isEditMode: false,
  currentLanguage: 'hu',
  availableLanguages: ['hu', 'en'],
  languageNames: { 'hu': 'Magyar', 'en': 'English' },
  translations: {},
  originalLanguage: 'hu',
  isTranslationMode: false
};

function openCompetencyModal(id = null, name = null) {
  swal_loader.fire();
  
  // Reset modal state
  competencyModalData.id = id;
  competencyModalData.isEditMode = id !== null;
  competencyModalData.isTranslationMode = false;
  competencyModalData.translations = {};
  
  // Set modal title and button text
  $('#competency-modal .modal-title').html(
    id == null ? '{{ __('admin/competencies.create-competency') }}' : '{{ __('admin/competencies.modify-competency') }}'
  );
  $('#competency-modal .save-competency').html('{{ __('admin/competencies.save-competency') }}');
  
  if (competencyModalData.isEditMode) {
    // Load existing competency with translations
    loadCompetencyTranslations(id, name);
  } else {
    // New competency - show simple form
    showSingleLanguageMode();
    $('#competency-modal .competency-name').val('');
    competencyModalData.currentLanguage = getCurrentAppLocale();
    swal_loader.close();
    $('#competency-modal').modal();
  }
}

function loadCompetencyTranslations(competencyId, fallbackName) {
  const routeName = isInSuperAdminContext() ? 
    '{{ route('superadmin.competency.translations.get') }}' : 
    '{{ route('admin.competency.translations.get') }}';
    
  $.ajax({
    url: routeName,
    method: 'POST',
    data: { id: competencyId },
    success: function(response) {
      competencyModalData.translations = response.translations;
      competencyModalData.originalLanguage = response.original_language;
      competencyModalData.availableLanguages = getSystemLanguages();
      
      // Check if we have multiple languages to show
      const hasMultipleLanguages = Object.keys(response.translations).filter(lang => 
        response.translations[lang].exists
      ).length > 1;
      
      if (hasMultipleLanguages) {
        showMultiLanguageMode();
      } else {
        showSingleLanguageMode();
        // Set the name in current language or fallback
        const currentLang = getCurrentAppLocale();
        const nameToShow = response.translations[currentLang]?.name || 
                          response.translations[competencyModalData.originalLanguage]?.name || 
                          fallbackName;
        $('#competency-modal .competency-name').val(nameToShow);
      }
      
      swal_loader.close();
      $('#competency-modal').modal();
    },
    error: function() {
      // Fallback to simple mode
      showSingleLanguageMode();
      $('#competency-modal .competency-name').val(fallbackName || '');
      swal_loader.close();
      $('#competency-modal').modal();
    }
  });
}

function showSingleLanguageMode() {
  $('.single-language-mode').show();
  $('.multi-language-mode').hide();
  $('.language-tabs').hide();
  $('.translation-controls').show();
  competencyModalData.isTranslationMode = false;
}

function showMultiLanguageMode() {
  $('.single-language-mode').hide();
  $('.multi-language-mode').show();
  $('.language-tabs').show();
  $('.translation-controls').show();
  competencyModalData.isTranslationMode = true;
  
  buildLanguageTabs();
  buildLanguageContent();
}

function buildLanguageTabs() {
  const navContainer = $('#language-nav');
  navContainer.empty();
  
  competencyModalData.availableLanguages.forEach((lang, index) => {
    const isActive = index === 0;
    const isOriginal = lang === competencyModalData.originalLanguage;
    const hasTranslation = competencyModalData.translations[lang]?.exists || false;
    const langName = competencyModalData.languageNames[lang] || lang.toUpperCase();
    
    let badgeClass = 'badge-secondary';
    if (isOriginal) badgeClass = 'badge-primary';
    else if (hasTranslation) badgeClass = 'badge-success';
    
    const tab = $(`
      <a class="nav-link ${isActive ? 'active' : ''}" 
         id="lang-${lang}-tab" 
         data-toggle="pill" 
         href="#lang-${lang}" 
         role="tab" 
         data-language="${lang}">
        ${langName}
        <span class="badge ${badgeClass} ml-1">
          ${isOriginal ? 'ORIG' : (hasTranslation ? 'OK' : 'MISS')}
        </span>
      </a>
    `);
    
    navContainer.append(tab);
  });
}

function buildLanguageContent() {
  const contentContainer = $('#language-content');
  contentContainer.empty();
  
  competencyModalData.availableLanguages.forEach((lang, index) => {
    const isActive = index === 0;
    const translation = competencyModalData.translations[lang] || {};
    const isOriginal = lang === competencyModalData.originalLanguage;
    
    const content = $(`
      <div class="tab-pane fade ${isActive ? 'show active' : ''}" 
           id="lang-${lang}" 
           role="tabpanel">
        <div class="form-group">
          <label>
            {{ __('global.name') }}
            ${isOriginal ? '<span class="badge badge-primary ml-1">{{ __('translations.original') }}</span>' : ''}
          </label>
          <input type="text" 
                 class="form-control competency-name-translated" 
                 data-language="${lang}"
                 value="${translation.name || ''}"
                 ${isOriginal ? 'required' : ''}>
          ${!isOriginal ? '<small class="form-text text-muted">{{ __('translations.leave-empty-to-remove') }}</small>' : ''}
        </div>
      </div>
    `);
    
    contentContainer.append(content);
  });
}

// Event handlers
$(document).ready(function() {
  
  // Save competency (updated to handle translations)
  $('#competency-modal .save-competency').click(function() {
    if (competencyModalData.isTranslationMode) {
      saveCompetencyWithTranslations();
    } else {
      saveCompetencySimple();
    }
  });
  
  // Create translations button
  $('#create-translations-btn').click(function() {
    if (competencyModalData.isEditMode) {
      showLanguageSelection();
    } else {
      // For new competencies, switch to translation mode immediately
      competencyModalData.isTranslationMode = true;
      showMultiLanguageMode();
      initializeEmptyTranslations();
    }
  });
  
  // AI translation button
  $('#translate-ai-btn').click(function() {
    showLanguageSelectionForAI();
  });
  
  // Language selection handlers
  $('#confirm-language-selection').click(function() {
    const selectedLanguages = getSelectedLanguages();
    if (selectedLanguages.length > 0) {
      createTranslationsForLanguages(selectedLanguages);
    }
  });
  
  $('#cancel-language-selection').click(function() {
    $('.language-selection').hide();
    $('.action-buttons').show();
  });
  
});

function saveCompetencySimple() {
  const name = $('#competency-modal .competency-name').val().trim();
  if (!name) {
    alert('{{ __('validation.required', ['attribute' => __('global.name')]) }}');
    return;
  }
  
  swal_confirm.fire({
    title: '{{ __('admin/competencies.save-competency-confirm') }}'
  }).then((result) => {
    if (result.isConfirmed) {
      swal_loader.fire();
      const routeName = isInSuperAdminContext() ? 
        '{{ route('superadmin.competency.save') }}' : 
        '{{ route('admin.competency.save') }}';
        
      $.ajax({
        url: routeName,
        method: 'POST',
        data: {
          id: competencyModalData.id,
          name: name
        },
        successMessage: "{{ __('admin/competencies.save-competency-success') }}",
      });
    }
  });
}

function saveCompetencyWithTranslations() {
  // Collect all translations
  const translations = {};
  $('.competency-name-translated').each(function() {
    const lang = $(this).data('language');
    const value = $(this).val().trim();
    if (value) {
      translations[lang] = value;
    }
  });
  
  // Validate original language
  if (!translations[competencyModalData.originalLanguage]) {
    alert('{{ __('translations.original-language-required') }}');
    return;
  }
  
  swal_confirm.fire({
    title: '{{ __('admin/competencies.save-competency-confirm') }}'
  }).then((result) => {
    if (result.isConfirmed) {
      swal_loader.fire();
      
      if (competencyModalData.isEditMode) {
        // Save translations for existing competency
        const routeName = isInSuperAdminContext() ? 
          '{{ route('superadmin.competency.translations.save') }}' : 
          '{{ route('admin.competency.translations.save') }}';
          
        $.ajax({
          url: routeName,
          method: 'POST',
          data: {
            id: competencyModalData.id,
            translations: translations
          },
          successMessage: "{{ __('admin/competencies.save-competency-success') }}",
        });
      } else {
        // Create new competency with translation
        const routeName = isInSuperAdminContext() ? 
          '{{ route('superadmin.competency.save') }}' : 
          '{{ route('admin.competency.save') }}';
          
        $.ajax({
          url: routeName,
          method: 'POST',
          data: {
            name: translations[competencyModalData.originalLanguage]
          },
          success: function(response) {
            if (response.success || response.ok) {
              // Now save additional translations if any
              const additionalTranslations = {...translations};
              delete additionalTranslations[competencyModalData.originalLanguage];
              
              if (Object.keys(additionalTranslations).length > 0) {
                const translationRoute = isInSuperAdminContext() ? 
                  '{{ route('superadmin.competency.translations.save') }}' : 
                  '{{ route('admin.competency.translations.save') }}';
                  
                $.ajax({
                  url: translationRoute,
                  method: 'POST',
                  data: {
                    id: response.id,
                    translations: additionalTranslations
                  },
                  successMessage: "{{ __('admin/competencies.save-competency-success') }}",
                });
              } else {
                // Just show success and reload
                swal_success.fire({
                  title: "{{ __('admin/competencies.save-competency-success') }}"
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

// Utility functions
function getCurrentAppLocale() {
  return document.documentElement.lang || 'hu';
}

function getSystemLanguages() {
  return ['hu', 'en']; // This should be dynamic based on LanguageService
}

function isInSuperAdminContext() {
  return window.location.href.includes('/superadmin/');
}

function showLanguageSelection() {
  $('.action-buttons').hide();
  $('.language-selection').show();
  
  // Build language checkboxes
  const container = $('#language-checkboxes');
  container.empty();
  
  const availableLanguages = getSystemLanguages();
  const existingLanguages = Object.keys(competencyModalData.translations).filter(lang => 
    competencyModalData.translations[lang].exists
  );
  
  availableLanguages.forEach(lang => {
    if (!existingLanguages.includes(lang)) {
      const langName = competencyModalData.languageNames[lang] || lang.toUpperCase();
      const checkbox = $(`
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="${lang}" id="lang-check-${lang}">
          <label class="form-check-label" for="lang-check-${lang}">
            ${langName}
          </label>
        </div>
      `);
      container.append(checkbox);
    }
  });
}

function getSelectedLanguages() {
  const selected = [];
  $('#language-checkboxes input:checked').each(function() {
    selected.push($(this).val());
  });
  return selected;
}

function createTranslationsForLanguages(languages) {
  // Add empty translations for selected languages
  languages.forEach(lang => {
    if (!competencyModalData.translations[lang]) {
      competencyModalData.translations[lang] = {
        name: '',
        exists: false,
        is_original: false
      };
    }
  });
  
  // Switch to multi-language mode
  competencyModalData.isTranslationMode = true;
  showMultiLanguageMode();
  
  // Hide language selection and show AI button
  $('.language-selection').hide();
  $('.action-buttons').show();
  $('#translate-ai-btn').show();
}

function initializeEmptyTranslations() {
  const currentLang = getCurrentAppLocale();
  const currentName = $('#competency-modal .competency-name').val();
  
  competencyModalData.originalLanguage = currentLang;
  competencyModalData.translations = {};
  
  getSystemLanguages().forEach(lang => {
    competencyModalData.translations[lang] = {
      name: lang === currentLang ? currentName : '',
      exists: lang === currentLang,
      is_original: lang === currentLang
    };
  });
}

// Show language selection for AI translation
function showLanguageSelectionForAI() {
  // Similar to showLanguageSelection but for AI translation
  // This would trigger the AI translation process
  // Implementation would be similar but call the AI endpoint
}
</script>