{{-- Language Setup Modal --}}
<div class="modal fade" id="language-setup-modal" tabindex="-1">
  <div class="modal-dialog modal-drawer">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/competencies.manage-languages') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p>{{ __('admin/competencies.language-setup-help') }}</p>
        
        <div class="language-grid">
          @foreach($availableLanguages as $lang)
            <label class="language-option {{ $lang === $currentLocale ? 'required' : '' }}">
              <input type="checkbox" 
                     value="{{ $lang }}" 
                     {{ in_array($lang, $selectedLanguages) ? 'checked' : '' }}
                     {{ $lang === $currentLocale ? 'disabled' : '' }}>
              <span>{{ $languageNames[$lang] ?? strtoupper($lang) }}</span>
            </label>
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