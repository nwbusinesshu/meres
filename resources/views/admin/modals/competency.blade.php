{{-- Competency Modal --}}
<div class="modal fade" id="competency-modal" tabindex="-1">
  <div class="modal-dialog modal-drawer">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/competencies.create-competency') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        
        {{-- Single Language Mode (Default) --}}
        <div class="single-language-mode">
          <div class="form-group">
            <label>{{ __('admin/competencies.competency-name') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control competency-name" required 
                   placeholder="{{ __('admin/competencies.competency-name-placeholder') }}">
            <small class="form-text text-muted">
              {{ __('admin/competencies.current-language') }}: {{ $languageNames[$currentLocale] ?? strtoupper($currentLocale) }}
            </small>
          </div>
          
          {{-- Add Translations Button (appears when name is filled) --}}
          <div class="add-translations-section" style="display: none;">
            <button type="button" class="btn btn-outline-info btn-sm add-translations">
              <i class="fa fa-plus"></i> {{ __('admin/competencies.add-translations') }}
            </button>
          </div>
        </div>

        {{-- Multi-Language Mode (Hidden by default) --}}
        <div class="multi-language-mode" style="display: none;">
          <div class="form-group">
            <label>{{ __('admin/competencies.competency-name') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control competency-name-original" readonly>
            <small class="form-text text-muted">
              {{ __('admin/competencies.original-language') }}: <span class="original-language-name"></span>
            </small>
          </div>

          {{-- Translation fields --}}
          <div class="translation-fields">
            {{-- Fields will be populated via JavaScript --}}
          </div>

          {{-- AI Translate Section --}}
          <div class="ai-translate-section">
            <hr>
            <div class="text-center">
              <button type="button" class="btn btn-info ai-translate-all">
                <i class="fa fa-robot"></i> {{ __('admin/competencies.ai-translate-all') }}
              </button>
              <p class="small text-muted mt-2">{{ __('admin/competencies.ai-translate-help') }}</p>
            </div>
          </div>

          {{-- Back to simple mode --}}
          <div class="text-center mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary back-to-simple">
              <i class="fa fa-arrow-left"></i> {{ __('admin/competencies.back-to-simple') }}
            </button>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          {{ __('global.cancel') }}
        </button>
        <button type="button" class="btn btn-primary save-competency">
          {{ __('global.save') }}
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Competency Modal JavaScript
$(document).ready(function() {
  let competencyModalData = {
    id: null,
    isEditMode: false,
    isMultiLanguageMode: false,
    selectedLanguages: @json($selectedLanguages ?? [$currentLocale]),
    currentLocale: '{{ $currentLocale }}',
    translations: {}
  };

  // Initialize competency modal
  function openCompetencyModal(id = null, name = null) {
    // Reset modal state
    competencyModalData.id = id;
    competencyModalData.isEditMode = id !== null;
    competencyModalData.isMultiLanguageMode = false;
    competencyModalData.translations = {};
    
    // Set modal title
    const title = id ? '{{ __('admin/competencies.modify-competency') }}' : '{{ __('admin/competencies.create-competency') }}';
    $('#competency-modal .modal-title').text(title);
    
    // Reset modal display
    showSingleLanguageMode();
    
    if (id) {
      // Load existing competency
      loadCompetencyData(id);
    } else {
      // New competency
      $('.competency-name').val('');
      $('#competency-modal').modal('show');
    }
  }

  function loadCompetencyData(id) {
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.translations.get') }}',
      method: 'POST',
      data: {
        id: id,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        competencyModalData.translations = response.translations;
        
        // Check if we have multiple languages
        const hasMultipleTranslations = Object.keys(response.translations).filter(lang => 
          response.translations[lang].exists
        ).length > 1;
        
        if (hasMultipleTranslations) {
          showMultiLanguageMode(response);
        } else {
          // Show in simple mode with the translated name for current locale
          const currentTranslation = response.translations[competencyModalData.currentLocale];
          $('.competency-name').val(currentTranslation?.name || Object.values(response.translations)[0]?.name || '');
        }
        
        swal_loader.close();
        $('#competency-modal').modal('show');
      },
      error: function() {
        swal_loader.close();
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  function showSingleLanguageMode() {
    $('.single-language-mode').show();
    $('.multi-language-mode').hide();
    $('.add-translations-section').hide();
    competencyModalData.isMultiLanguageMode = false;
  }

  function showMultiLanguageMode(data = null) {
    $('.single-language-mode').hide();
    $('.multi-language-mode').show();
    competencyModalData.isMultiLanguageMode = true;
    
    if (data) {
      // Populate original language field
      const originalLang = data.original_language;
      const originalName = data.translations[originalLang]?.name || '';
      $('.competency-name-original').val(originalName);
      $('.original-language-name').text(window.languageNames[originalLang] || originalLang.toUpperCase());
      
      // Populate translation fields
      populateTranslationFields(data.translations, originalLang);
    } else {
      // Creating translations from simple mode
      const originalName = $('.competency-name').val();
      $('.competency-name-original').val(originalName);
      $('.original-language-name').text(window.languageNames[competencyModalData.currentLocale] || competencyModalData.currentLocale.toUpperCase());
      
      // Create empty translation fields
      const emptyTranslations = {};
      competencyModalData.selectedLanguages.forEach(lang => {
        emptyTranslations[lang] = {
          name: lang === competencyModalData.currentLocale ? originalName : '',
          exists: lang === competencyModalData.currentLocale
        };
      });
      
      populateTranslationFields(emptyTranslations, competencyModalData.currentLocale);
    }
  }

  function populateTranslationFields(translations, originalLang) {
    const $container = $('.translation-fields');
    $container.empty();
    
    competencyModalData.selectedLanguages.forEach(lang => {
      if (lang === originalLang) return; // Skip original language
      
      const langName = window.languageNames[lang] || lang.toUpperCase();
      const value = translations[lang]?.name || '';
      
      $container.append(`
        <div class="form-group">
          <label>${langName}</label>
          <input type="text" class="form-control translation-input" data-lang="${lang}" value="${value}">
        </div>
      `);
    });
  }

  // Event handlers
  $(document).on('input', '.competency-name', function() {
    const hasContent = $(this).val().trim().length > 0;
    $('.add-translations-section').toggle(hasContent && !competencyModalData.isEditMode);
  });

  $(document).on('click', '.add-translations', function() {
    showMultiLanguageMode();
  });

  $(document).on('click', '.back-to-simple', function() {
    showSingleLanguageMode();
  });

  $(document).on('click', '.ai-translate-all', function() {
    const originalName = $('.competency-name-original').val();
    const targetLanguages = competencyModalData.selectedLanguages.filter(lang => 
      lang !== competencyModalData.currentLocale
    );
    
    if (!originalName.trim()) {
      alert('{{ __('admin/competencies.please-enter-name-first') }}');
      return;
    }
    
    if (targetLanguages.length === 0) {
      alert('{{ __('admin/competencies.no-target-languages') }}');
      return;
    }
    
    // Create temporary competency for AI translation if in create mode
    if (!competencyModalData.isEditMode) {
      createTempCompetencyForTranslation(originalName, targetLanguages);
    } else {
      translateExistingCompetency(competencyModalData.id, targetLanguages);
    }
  });

  function createTempCompetencyForTranslation(name, targetLanguages) {
    swal_loader.fire();
    
    // First create the competency
    $.ajax({
      url: '{{ route('admin.competency.save') }}',
      method: 'POST',
      data: {
        name: name,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        competencyModalData.id = response.id;
        competencyModalData.isEditMode = true;
        
        // Now translate it
        translateExistingCompetency(response.id, targetLanguages);
      },
      error: function() {
        swal_loader.close();
        swal_error.fire({
          title: '{{ __('global.error-occurred') }}'
        });
      }
    });
  }

  function translateExistingCompetency(competencyId, targetLanguages) {
    $.ajax({
      url: '{{ route('admin.competency.translations.ai') }}',
      method: 'POST',
      data: {
        id: competencyId,
        languages: targetLanguages,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        swal_loader.close();
        
        if (response.translations) {
          // Update the translation input fields
          Object.keys(response.translations).forEach(lang => {
            const $input = $(`.translation-input[data-lang="${lang}"]`);
            $input.val(response.translations[lang]);
            $input.addClass('translation-success');
            setTimeout(() => $input.removeClass('translation-success'), 2000);
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

  $(document).on('click', '.save-competency', function() {
    if (competencyModalData.isMultiLanguageMode) {
      saveCompetencyWithTranslations();
    } else {
      saveSingleLanguageCompetency();
    }
  });

  function saveSingleLanguageCompetency() {
    const name = $('.competency-name').val().trim();
    
    if (!name) {
      alert('{{ __('admin/competencies.please-enter-name') }}');
      return;
    }
    
    swal_loader.fire();
    
    $.ajax({
      url: '{{ route('admin.competency.save') }}',
      method: 'POST',
      data: {
        id: competencyModalData.id,
        name: name,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        swal_loader.close();
        $('#competency-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('admin/competencies.competency-saved') }}'
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

  function saveCompetencyWithTranslations() {
    const translations = {};
    
    // Include original language
    translations[competencyModalData.currentLocale] = $('.competency-name-original').val().trim();
    
    // Include other translations
    $('.translation-input').each(function() {
      const lang = $(this).data('lang');
      const value = $(this).val().trim();
      if (value) {
        translations[lang] = value;
      }
    });
    
    if (!translations[competencyModalData.currentLocale]) {
      alert('{{ __('admin/competencies.please-enter-name') }}');
      return;
    }
    
    swal_loader.fire();
    
    
    if (!competencyModalData.isEditMode) {
      $.ajax({
        url: '{{ route('admin.competency.save') }}',
        method: 'POST',
        data: {
          name: translations[competencyModalData.currentLocale],
          _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
          competencyModalData.id = response.id;
          saveTranslations(translations);
        },
        error: function() {
          swal_loader.close();
          swal_error.fire({
            title: '{{ __('global.error-occurred') }}'
          });
        }
      });
    } else {
      saveTranslations(translations);
    }
  }

  function saveTranslations(translations) {
    $.ajax({
      url: '{{ route('admin.competency.translations.save') }}',
      method: 'POST',
      data: {
        id: competencyModalData.id,
        translations: translations,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function() {
        swal_loader.close();
        $('#competency-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('admin/competencies.competency-saved') }}'
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
  window.openCompetencyModal = openCompetencyModal;
});
</script>