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

function openCompetencyModal(id = null, name = null) {
  swal_loader.fire();
  $('#competency-modal').attr('data-id', id ?? 0);
  $('#competency-modal .modal-title').html(id == null ? '{{ __('admin/competencies.create-competency') }}' : '{{ __('admin/competencies.modify-competency') }}');
  $('#competency-modal .save-competency').html('{{ __('admin/competencies.save-competency') }}');
  $('#competency-modal .competency-name').val(name ?? '');
  
  // Reset translation state
  $('.translation-section').hide();
  $('.create-translations').show();
  competencyTranslations = {};
  
  // If editing existing competency, load its translations
  if (id) {
    loadCompetencyTranslations(id);
  } else {
    swal_loader.close();
    $('#competency-modal').modal();
  }
}

function loadCompetencyTranslations(competencyId) {
  $.ajax({
    url: "{{ route('admin.competency.translations.get') }}",
    method: 'POST',
    data: { 
      id: competencyId,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      if (response.name_json) {
        competencyTranslations = response.name_json;
        compOriginalLanguage = response.original_language || compOriginalLanguage;
        
        // If translations exist, show them
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

function loadCompetencySelectedLanguages() {
  return new Promise((resolve) => {
    $.ajax({
      url: "{{ route('admin.languages.selected') }}",
      method: 'GET',
      success: function(response) {
        compSelectedLanguages = response.selected_languages || [compOriginalLanguage];
        resolve();
      },
      error: function() {
        compSelectedLanguages = [compOriginalLanguage];
        resolve();
      }
    });
  });
}

function showCompetencyTranslationInputs() {
  const container = $('.translations-container');
  container.empty();
  
  // Get available language names
  const availableLocales = @json(config('app.available_locales'));
  
  // Filter out the original language since that's handled by the main input field
  const translationLanguages = compSelectedLanguages.filter(langCode => langCode !== compOriginalLanguage);
  
  if (translationLanguages.length === 0) {
    container.append(`
      <div class="alert alert-info">
        <i class="fa fa-info-circle"></i> 
        {{ __('admin/competencies.no-additional-languages') }}
        <br><small>{{ __('admin/competencies.original-language-note') }}</small>
      </div>
    `);
    $('.translation-section').show();
    $('.create-translations').hide();
    return;
  }
  
  // Show info about original language
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
}

$(document).ready(function() {
  // Create translations button
  $('.create-translations').click(function() {
    swal_loader.fire();
    
    loadCompetencySelectedLanguages().then(() => {
      // Initialize translations with current name for original language
      const currentName = $('#competency-modal .competency-name').val();
      if (currentName) {
        competencyTranslations[compOriginalLanguage] = currentName;
      }
      
      showCompetencyTranslationInputs();
      swal_loader.close();
    });
  });
  
  // Hide translations button
  $('.hide-translations').click(function() {
    $('.translation-section').hide();
    $('.create-translations').show();
  });
  
  // Save competency with translations
  $('#competency-modal .save-competency').click(function() {
    swal_confirm.fire({
      title: '{{ __('admin/competencies.save-competency-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        // Start with original language from main input
        const translations = {};
        const originalName = $('#competency-modal .competency-name').val().trim();
        
        if (originalName) {
          translations[compOriginalLanguage] = originalName;
        }
        
        // Add translations from translation inputs
        $('.translation-input').each(function() {
          const langCode = $(this).data('lang');
          const value = $(this).val().trim();
          if (value && langCode !== compOriginalLanguage) {
            translations[langCode] = value;
          }
        });
        
        $.ajax({
          url: "{{ route('admin.competency.save') }}",
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
            }).then(() => {
              location.reload(); // Refresh to show updated competency
            });
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
      }
    });
  });
});
</script>