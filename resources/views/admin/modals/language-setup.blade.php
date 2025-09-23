{{-- Language Setup Modal - FIXED VERSION --}}
<div class="modal fade" id="language-setup-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/competencies.setup-languages') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p>{{ __('admin/competencies.select-languages-help') }}</p>
        
        <div class="language-selection">
          {{-- FIXED: Show current locale as always selected --}}
          <label class="language-option required">
            <input type="checkbox" 
                   value="{{ $currentLocale }}" 
                   checked 
                   disabled>
            <span>{{ $languageNames[$currentLocale] ?? strtoupper($currentLocale) }} ({{ __('admin/competencies.current-language') }})</span>
          </label>
          
          {{-- FIXED: Show other available languages --}}
          @foreach($availableLanguages as $lang)
            @if($lang !== $currentLocale)
              <label class="language-option">
                <input type="checkbox" 
                       value="{{ $lang }}" 
                       {{ in_array($lang, $selectedLanguages) ? 'checked' : '' }}>
                <span>{{ $languageNames[$lang] ?? strtoupper($lang) }}</span>
              </label>
            @endif
          @endforeach
        </div>
        
        <div class="alert alert-info mt-3">
          <i class="fa fa-info-circle"></i>
          {{ __('admin/competencies.current-language-required') }}
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          {{ __('global.cancel') }}
        </button>
        <button type="button" class="btn btn-primary save-language-selection">
          {{ __('global.save') }}
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Translation Management Modal --}}
<div class="modal fade" id="translation-modal" tabindex="-1">
  <div class="modal-dialog modal-drawer">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/competencies.manage-translations') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="translation-form">
          {{-- Translation fields will be populated via JavaScript --}}
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          {{ __('global.cancel') }}
        </button>
        <button type="button" class="btn btn-primary save-translations">
          {{ __('global.save') }}
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Question Translation Modal --}}
<div class="modal fade" id="question-translation-modal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-drawer">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/competencies.manage-question-translations') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        {{-- Language tabs for switching between languages --}}
        <div class="language-tabs">
          {{-- Tabs will be populated via JavaScript --}}
        </div>
        
        {{-- Translation content area --}}
        <div class="question-translation-content">
          {{-- Content will be populated via JavaScript --}}
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          {{ __('global.cancel') }}
        </button>
        <button type="button" class="btn btn-primary save-question-translations">
          {{ __('global.save') }}
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.language-option {
  display: block;
  padding: 8px 0;
  cursor: pointer;
}

.language-option.required {
  font-weight: 600;
  color: #28a745;
}

.language-option input[disabled] {
  cursor: not-allowed;
}

.translation-field {
  margin-bottom: 15px;
}

.translation-field label {
  font-weight: 600;
  margin-bottom: 5px;
  display: block;
}

.translation-field label.original-language {
  color: #28a745;
}

.ai-translate-section {
  text-align: center;
  margin-top: 20px;
  padding-top: 15px;
  border-top: 1px solid #dee2e6;
}

.language-tabs {
  margin-bottom: 20px;
  border-bottom: 1px solid #dee2e6;
}

.language-tab {
  margin-right: 10px;
  margin-bottom: 10px;
}

.question-fields {
  padding: 20px 0;
}

.question-fields.hidden {
  display: none;
}

.field-group {
  margin-bottom: 15px;
}

.field-label {
  font-weight: 600;
  margin-bottom: 5px;
  display: block;
}

.modal-drawer {
  max-width: 800px;
}
</style>