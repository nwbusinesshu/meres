{{-- resources/views/admin/modals/competency.blade.php --}}
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
        <!-- Original competency name -->
        <div class="form-row">
          <div class="form-group">
            <label>{{ __('global.name') }}</label>
            <input type="text" class="form-control competency-name">
          </div>
        </div>

        <!-- Translation section -->
        <div class="translation-section" style="display: none;">
          <hr style="margin: 1.5rem 0;">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">{{ __('admin/competencies.translations') }}</h6>
            <button type="button" class="btn btn-outline-secondary btn-sm hide-translations">
              <i class="fa fa-eye-slash"></i> {{ __('admin/competencies.hide-translations') }}
            </button>
          </div>
          
          <div class="translations-container">
            <!-- Translation inputs will be populated here -->
          </div>
        </div>

        <!-- Action buttons -->
        <div class="modal-actions">
          <button type="button" class="btn btn-outline-info create-translations" style="margin-right: 0.5rem;">
            <i class="fa fa-language"></i> {{ __('admin/competencies.create-translations') }}
          </button>
          <button class="btn btn-primary save-competency">{{ __('admin/competencies.save-competency') }}</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.translation-input-group {
  margin-bottom: 1rem;
  padding: 0.75rem;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  background-color: #f8f9fa;
}

.translation-input-group label {
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #495057;
}

.translation-input-group .language-flag {
  font-size: 0.875rem;
  color: #6c757d;
  text-transform: uppercase;
  margin-left: 0.5rem;
}

.translation-input-group.original-language {
  background-color: #fff3e0;
  border-color: #ff9800;
}

.translation-input-group.original-language label {
  color: #e65100;
}

.modal-actions {
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid #dee2e6;
}
</style>

<script>
let compSelectedLanguages = [];
let competencyTranslations = {};
let compOriginalLanguage = '{{ auth()->user()->locale ?? config('app.locale') }}';

/** Központi állapotfrissítő az AI fordító gombhoz */
function refreshAIButtonState() {
  const btn = $('#competency-modal .ai-translate-competency');
  const nameVal = ($('#competency-modal .competency-name').val() || '').trim();
  const langs = Array.isArray(compSelectedLanguages) ? compSelectedLanguages : [];
  const targets = langs.filter(l => l !== compOriginalLanguage);
  const isTranslationSectionVisible = $('.translation-section').is(':visible');

  // Button should only be enabled when translation section is visible, name is filled, and there are target languages
  const enable = isTranslationSectionVisible && nameVal.length > 0 && targets.length > 0;
  btn.toggleClass('disabled', !enable).prop('disabled', !enable);
}

/** Modal megnyitása */
function openCompetencyModal(id = null, name = null) {
  swal_loader.fire();

  $('#competency-modal').attr('data-id', id ?? 0);
  $('#competency-modal .modal-title').html(
    id == null ? '{{ __('admin/competencies.create-competency') }}' : '{{ __('admin/competencies.modify-competency') }}'
  );
  $('#competency-modal .save-competency').html('{{ __('admin/competencies.save-competency') }}');

  // programozott értékadás — lőjünk input eventet is, hogy a gombállapot frissüljön
  $('#competency-modal .competency-name').val(name ?? '').trigger('input');

  // Reset fordítások
  $('.translation-section').hide();
  $('.create-translations').show();
  competencyTranslations = {};
  
  // Remove AI button when modal opens (it will be added only when needed)
  removeCompetencyAIButton();

  if (id) {
    loadCompetencyTranslations(id);
  } else {
    swal_loader.close();
    $('#competency-modal').modal();
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
      if (response.name_json) {
        competencyTranslations = response.name_json;
        compOriginalLanguage = response.original_language || compOriginalLanguage;

        // ha vannak fordítások, töltsük a nyelveket és jelenítsük meg
        if (Object.keys(competencyTranslations).length > 1) {
          loadCompetencySelectedLanguages().then(() => {
            showCompetencyTranslationInputs();
          });
        }
      }
      swal_loader.close();
      $('#competency-modal').modal();
    },
    error: function() {
      swal_loader.close();
      $('#competency-modal').modal();
    }
  });
}

/** Választott nyelvek betöltése (globális/organizációs mód szerint) */
function loadCompetencySelectedLanguages() {
  return new Promise((resolve) => {
    const isGlobalMode = window.globalCompetencyMode || false;

    if (isGlobalMode) {
      const availableLanguages = @json(config('app.available_locales'));
      compSelectedLanguages = Object.keys(availableLanguages);
      refreshAIButtonState(); // <- fontos
      resolve();
    } else {
      $.ajax({
        url: "{{ route('admin.languages.selected') }}",
        method: 'GET',
        success: function(response) {
          compSelectedLanguages = response.selected_languages || [compOriginalLanguage];
          refreshAIButtonState(); // <- fontos
          resolve();
        },
        error: function() {
          compSelectedLanguages = [compOriginalLanguage];
          refreshAIButtonState(); // <- fontos
          resolve();
        }
      });
    }
  });
}

/** Fordítási inputok renderelése */
function showCompetencyTranslationInputs() {
  const container = $('.translations-container');
  container.empty();

  const availableLocales = @json(config('app.available_locales'));
  const translationLanguages = compSelectedLanguages.filter(langCode => langCode !== compOriginalLanguage);

  if (translationLanguages.length === 0) {
    container.append(`
      <div class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        {{ __('admin/competencies.no-additional-languages') }}
        <br><small>{{ __('admin/competencies.original-language-explanation') }}</small>
      </div>
    `);
    $('.translation-section').show();
    $('.create-translations').hide();
    
    // ADD AI button when translation section becomes visible
    addCompetencyAIButton();
    refreshAIButtonState();
    return;
  }

  container.append(`
    <div class="alert alert-warning">
      <i class="fa fa-info-circle"></i>
      <strong>{{ __('admin/competencies.original-language-info') }}:</strong>
      ${availableLocales[compOriginalLanguage]} (${compOriginalLanguage.toUpperCase()})
      <br><small>{{ __('admin/competencies.original-language-explanation') }}</small>
    </div>
  `);

  translationLanguages.forEach(langCode => {
    if (availableLocales[langCode]) {
      const currentValue = competencyTranslations[langCode] || '';
      const inputGroup = $(`
        <div class="translation-input-group" data-lang="${langCode}">
          <label>
            ${availableLocales[langCode]}
            <span class="language-flag">(${langCode})</span>
          </label>
          <input type="text" class="form-control translation-input"
                 data-lang="${langCode}"
                 value="${currentValue}"
                 placeholder="{{ __('admin/competencies.enter-translation-for') }} ${availableLocales[langCode]}">
        </div>
      `);
      container.append(inputGroup);
    }
  });

  $('.translation-section').show();
  $('.create-translations').hide();
  
  // ADD AI button when translation section becomes visible
  addCompetencyAIButton();
  refreshAIButtonState();
}

/** AI gomb hozzáadása CSAK amikor a fordítási szekció megjelenik */
function addCompetencyAIButton() {
  if ($('.translation-section').is(':visible') && $('#competency-modal .ai-translate-competency').length === 0) {
    const aiButton = $(`
      <button type="button" class="btn btn-outline-primary ai-translate-competency disabled" style="margin-right: 0.5rem;" disabled>
        <i class="fa fa-robot"></i> {{ __('admin/competencies.ai-translate') }}
      </button>
    `);
    aiButton.insertBefore('#competency-modal .save-competency');
    initCompetencyTranslationButton();
  }
}

/** AI gomb eltávolítása amikor a fordítási szekció el van rejtve */
function removeCompetencyAIButton() {
  $('#competency-modal .ai-translate-competency').remove();
}

/** AI fordító gomb inicializálása + események */
function initCompetencyTranslationButton() {
  const competencyNameInput = $('#competency-modal .competency-name');
  const translateButton = $('#competency-modal .ai-translate-competency');

  // ne duplikáljunk bindingot
  competencyNameInput.off('input.ai keyup.ai').on('input.ai keyup.ai', refreshAIButtonState);

  // induláskor is frissítünk
  refreshAIButtonState();

  translateButton.off('click.ai').on('click.ai', function() {
    if ($(this).hasClass('disabled')) return;

    const competencyName = competencyNameInput.val().trim();
    const sourceLanguage = compOriginalLanguage;
    const targetLanguages = (compSelectedLanguages || []).filter(l => l !== sourceLanguage);

    if (!competencyName) {
      swal.fire({
        icon: 'warning',
        title: '{{ __('global.warning') }}',
        text: '{{ __('admin/competencies.fill-competency-name-first') }}'
      });
      return;
    }

    if (targetLanguages.length === 0) {
      swal.fire({
        icon: 'info',
        title: '{{ __('global.info') }}',
        text: '{{ __('admin/competencies.no-target-languages') }}'
      });
      return;
    }

    translateCompetencyName(competencyName, sourceLanguage, targetLanguages);
  });
}

/** Fordítás meghívása backendről */
function translateCompetencyName(competencyName, sourceLanguage, targetLanguages) {
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
      competency_name: competencyName,
      source_language: sourceLanguage,
      target_languages: targetLanguages,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      if (response.success && response.translations) {
        competencyTranslations = { ...competencyTranslations, ...response.translations };
        competencyTranslations[sourceLanguage] = competencyName;
        showCompetencyTranslationInputs();

        swal.fire({
          icon: 'success',
          title: '{{ __('admin/competencies.translation-success') }}',
          text: '{{ __('admin/competencies.ai-translations-generated') }}',
          timer: 3000,
          showConfirmButton: false
        });
      } else {
        swal.fire({
          icon: 'error',
          title: '{{ __('global.error') }}',
          text: response.message || '{{ __('admin/competencies.translation-failed') }}'
        });
      }
    },
    error: function(xhr) {
      let errorMessage = '{{ __('admin/competencies.translation-failed') }}';
      if (xhr.responseJSON && xhr.responseJSON.message) {
        errorMessage = xhr.responseJSON.message;
      }
      swal.fire({ icon: 'error', title: '{{ __('global.error') }}', text: errorMessage });
    },
    complete: function() {
      translateButton.html(originalText).prop('disabled', false);
      initCompetencyTranslationButton(); // állapotfigyelés újra
    }
  });
}

$(document).ready(function() {
  // "Fordítások létrehozása" gomb
  $('.create-translations').off('click').on('click', function() {
    swal_loader.fire();
    loadCompetencySelectedLanguages().then(() => {
      const currentName = $('#competency-modal .competency-name').val();
      if (currentName) {
        competencyTranslations[compOriginalLanguage] = currentName;
      }
      showCompetencyTranslationInputs(); // This will now add the AI button
      swal_loader.close();
    });
  });

  // "Elrejtés" gomb - UPDATED
  $('.hide-translations').off('click').on('click', function() {
    $('.translation-section').hide();
    $('.create-translations').show();
    removeCompetencyAIButton(); // Remove AI button when hiding translations
  });

  // Add name input listener
  $(document).on('input keyup', '#competency-modal .competency-name', function() {
    refreshAIButtonState();
  });

  // Mentés
  $('.save-competency').off('click').on('click', function() {
    swal_confirm.fire({
      title: '{{ __('admin/competencies.save-competency-confirm') }}'
    }).then((result) => {
      if (!result.isConfirmed) return;

      swal_loader.fire();

      const translations = {};
      const originalName = $('#competency-modal .competency-name').val().trim();
      if (originalName) {
        translations[compOriginalLanguage] = originalName;
      }

      $('.translation-input').each(function() {
        const langCode = $(this).data('lang');
        const value = $(this).val().trim();
        if (value && langCode !== compOriginalLanguage) {
          translations[langCode] = value;
        }
      });

      const isGlobalMode = window.globalCompetencyMode || false;
      const saveUrl = isGlobalMode
        ? "{{ route('superadmin.competency.save') }}"
        : "{{ route('admin.competency.save') }}";

      $.ajax({
        url: saveUrl,
        method: 'POST',
        data: {
          id: $('#competency-modal').attr('data-id'),
          name: originalName,
          translations: translations,
          original_language: compOriginalLanguage,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
          swal_loader.close();
          $('#competency-modal').modal('hide');
          swal.fire({
            icon: 'success',
            title: '{{ __('global.success') }}',
            text: '{{ __('admin/competencies.save-competency-success') }}',
            timer: 2000,
            showConfirmButton: false
          }).then(() => location.reload());
        },
        error: function(xhr) {
          swal_loader.close();
          swal.fire({
            icon: 'error',
            title: '{{ __('global.error') }}',
            text: xhr.responseJSON?.message || '{{ __('admin/competencies.translation-error') }}'
          });
        }
      });
    });
  });
});

// tedd globálissá, ha kívülről hívod (pl. kattintás handler másik fájlban)
window.openCompetencyModal = openCompetencyModal;
</script>