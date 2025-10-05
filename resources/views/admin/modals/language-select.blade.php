<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="language-select-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/competencies.select-languages') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="language-selection-container">
          <!-- Selected Languages -->
          <div class="language-section">
            <h6>{{ __('admin/competencies.selected-languages') }}</h6>
            <div class="selected-languages-list">
              <!-- Selected languages will be populated here via JavaScript -->
            </div>
          </div>

          <!-- Available Languages -->
          <div class="language-section">
            <h6>{{ __('admin/competencies.available-languages') }}</h6>
            <div class="available-languages-list">
              <!-- Available languages will be populated here via JavaScript -->
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary save-languages">{{ __('admin/competencies.save-languages') }}</button>

      </div>
    </div>
  </div>
</div>

<style>
.language-selection-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  margin-bottom: 1rem;
}

.language-section {
  border: 1px solid #dee2e6;
  padding: 1rem;
}

.language-section h6 {
  margin-bottom: 0.75rem;
  font-weight: 600;
  color: #495057;
}

.selected-languages-list,
.available-languages-list {
  min-height: 100px;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 200px;
  overflow-y: auto;
  padding-right: 0.5rem;
}

.language-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem;
  border: 1px solid #dee2e6;
  background-color: #fff;
  cursor: pointer;
  transition: all 0.2s;
}

.language-item:hover {
  background-color: #f8f9fa;
  border-color: #adb5bd;
}

.language-item.selected-language {
  background-color: #e3f2fd;
  border-color: #2196f3;
  cursor: default;
}

.language-item.selected-language:hover {
  background-color: #e3f2fd;
}

.language-item.available-language {
  background-color: #fff;
}

.language-item.available-language:hover {
  background-color: #f8f9fa;
}

.language-item.user-default {
  background-color: #fff3e0;
  border-color: #ff9800;
  font-weight: 600;
}

.language-item.user-default .language-badge {
  background-color: #ff9800;
  color: white;
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

.language-name {
  font-weight: 500;
  color: #495057;
}

.language-code {
  font-size: 0.875rem;
  color: #6c757d;
  text-transform: uppercase;
}

.language-actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.language-remove {
  background-color: #dc3545;
  color: white;
  border: none;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  cursor: pointer;
  transition: background-color 0.2s;
}

.language-remove:hover {
  background-color: #c82333;
}

.language-add {
  background-color: #28a745;
  color: white;
  border: none;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  cursor: pointer;
  transition: background-color 0.2s;
}

.language-add:hover {
  background-color: #218838;
}

.no-languages {
  padding: 1rem;
  text-align: center;
  color: #6c757d;
  font-style: italic;
}
</style>

<script>
let selectedLanguages = [];
let availableLanguages = [];
let userDefaultLanguage = '';

function initLanguageModal() {
  // ✅ FIXED: Show loader when opening modal
  swal_loader.fire();
  
  // Get available languages from config
  $.ajax({
    url: "{{ route('admin.languages.available') }}",
    method: 'GET',
    success: function(response) {
      availableLanguages = response.available_locales;
      userDefaultLanguage = response.user_default_language;
      
      // Get currently selected languages for organization
      getSelectedLanguages();
    },
    error: function() {
      swal_loader.close();
      swal.fire({
        icon: 'error',
        title: '{{ __('global.error') }}',
        text: '{{ __('admin/competencies.error-loading-languages') }}'
      });
    }
  });
}

function getSelectedLanguages() {
  $.ajax({
    url: "{{ route('admin.languages.selected') }}",
    method: 'GET',
    success: function(response) {
      selectedLanguages = response.selected_languages || [userDefaultLanguage];
      
      // Ensure user default is always included
      if (!selectedLanguages.includes(userDefaultLanguage)) {
        selectedLanguages.push(userDefaultLanguage);
      }
      
      renderLanguageLists();
      swal_loader.close();
      $('#language-select-modal').modal('show');
    },
    error: function() {
      // Default to user's language if error
      selectedLanguages = [userDefaultLanguage];
      renderLanguageLists();
      swal_loader.close();
      $('#language-select-modal').modal('show');
    }
  });
}

function renderLanguageLists() {
  renderSelectedLanguages();
  renderAvailableLanguages();
}

function renderSelectedLanguages() {
  const container = $('.selected-languages-list');
  container.empty();
  
  if (selectedLanguages.length === 0) {
    container.append('<div class="no-languages">{{ __('admin/competencies.no-languages-selected') }}</div>');
    return;
  }
  
  selectedLanguages.forEach(langCode => {
    if (availableLanguages[langCode]) {
      const isDefault = langCode === userDefaultLanguage;
      const item = $(`
        <div class="language-item selected-language ${isDefault ? 'user-default' : ''}" data-lang-code="${langCode}">
          <div>
            <span class="language-name">${availableLanguages[langCode]}</span>
            <span class="language-code">(${langCode})</span>
          </div>
          <div class="language-actions">
            ${isDefault ? '<span class="language-badge">{{ __('admin/competencies.default') }}</span>' : '<button class="language-remove" onclick="removeLanguage(\''+langCode+'\')">{{ __('admin/competencies.remove') }}</button>'}
          </div>
        </div>
      `);
      container.append(item);
    }
  });
}

function renderAvailableLanguages() {
  const container = $('.available-languages-list');
  container.empty();
  
  const availableToAdd = Object.keys(availableLanguages).filter(langCode => 
    !selectedLanguages.includes(langCode)
  );
  
  if (availableToAdd.length === 0) {
    container.append('<div class="no-languages">{{ __('admin/competencies.all-languages-selected') }}</div>');
    return;
  }
  
  availableToAdd.forEach(langCode => {
    const item = $(`
      <div class="language-item available-language" data-lang-code="${langCode}">
        <div>
          <span class="language-name">${availableLanguages[langCode]}</span>
          <span class="language-code">(${langCode})</span>
        </div>
        <div class="language-actions">
          <button class="language-add" onclick="addLanguage('${langCode}')">{{ __('admin/competencies.add') }}</button>
        </div>
      </div>
    `);
    container.append(item);
  });
}

function addLanguage(langCode) {
  if (!selectedLanguages.includes(langCode)) {
    selectedLanguages.push(langCode);
    renderLanguageLists();
  }
}

function removeLanguage(langCode) {
  // Cannot remove user's default language
  if (langCode === userDefaultLanguage) {
    return;
  }
  
  const index = selectedLanguages.indexOf(langCode);
  if (index > -1) {
    selectedLanguages.splice(index, 1);
    renderLanguageLists();
  }
}

// Save selected languages
$(document).ready(function() {
  $('.save-languages').click(function() {
    swal_loader.fire();
    
    // ✅ FIXED: Close modal BEFORE AJAX
    $('#language-select-modal').modal('hide');
    
    // ✅ FIXED: Use successMessage property
    $.ajax({
      url: "{{ route('admin.languages.save') }}",
      method: 'POST',
      data: {
        languages: selectedLanguages,
        _token: '{{ csrf_token() }}'
      },
      successMessage: '{{ __('admin/competencies.languages-saved-successfully') }}',
      error: function() {
        swal_loader.close();
        swal.fire({
          icon: 'error',
          title: '{{ __('global.error') }}',
          text: '{{ __('admin/competencies.error-saving-languages') }}'
        });
      }
    });
  });
});
</script>