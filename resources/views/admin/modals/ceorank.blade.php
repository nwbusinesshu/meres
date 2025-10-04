<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="ceorank-modal">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/ceoranks.rank-settings') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        
        <!-- Language Carousel (only visible when multiple languages) -->
        <div class="language-carousel-container" style="display: none;">
          <div class="language-carousel">
            <div class="carousel-nav">
              <button class="carousel-prev">‹</button>
              <div class="language-tabs"></div>
              <button class="carousel-next">›</button>
            </div>
          </div>
        </div>

        <!-- Single Form Structure (reused for all languages) -->
        <div class="rank-form">
          <div class="form-row flex">
            <div class="form-group">
              <label>{{ __('admin/ceoranks.value') }}</label>
              <input type="number" class="form-control rank-value" step="1" min="0" max="100">
            </div>
            <div class="form-group">
              <label>
                {{ __('admin/ceoranks.name') }}
                <span class="language-indicator"></span>
              </label>
              <input type="text" class="form-control rank-name">
            </div>
          </div>
          <p>{{ __('admin/ceoranks.employee-number') }}</p>
          <div class="form-row flex">
            <div class="form-group">
              <div class="form-check">
                <input class="form-check-input has-rank-min" type="checkbox" value="" id="has-rank-min">
                <label class="form-check-label" for="has-rank-min">
                  {{ __('admin/ceoranks.min') }} (%)
                </label>
              </div>
              <input type="number" class="form-control rank-min" step="1" min="1" max="100" readonly>
            </div>
            <div class="form-group">
              <div class="form-check">
                <input class="form-check-input has-rank-max" type="checkbox" value="" id="has-rank-max">
                <label class="form-check-label" for="has-rank-max">
                  {{ __('admin/ceoranks.max') }} (%)
                </label>
              </div>
              <input type="number" class="form-control rank-max" step="1" min="1" max="100" readonly>
            </div>
          </div>
        </div>

        
      </div>

      <div class="modal-footer">
        <div class="modal-actions">
          <button class="btn btn-primary save-rank">{{ __('admin/ceoranks.save-rank') }}</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* Language Carousel Styling - Same as Competency Modal */
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

.form-row.flex {
  display: flex;
  gap: 15px;
}

.form-row.flex .form-group {
  flex: 1;
}

/* Read-only field styling */
.readonly-field {
  background-color: #f8f9fa !important;
  border-color: #e9ecef !important;
  color: #6c757d !important;
  cursor: not-allowed !important;
}

.readonly-checkbox {
  pointer-events: none;
  opacity: 0.6;
}

.language-indicator {
  color: #007bff;
  font-size: 0.875rem;
  font-style: italic;
  font-weight: normal;
}

/* Missing translation warning for name field */
.rank-name.missing-translation {
  border-color: #ffc107 !important;
  background-color: #fff3cd !important;
}

.rank-name.missing-translation::placeholder {
  color: #856404 !important;
}
</style>

<script>
// Translation variables
let rankSelectedLanguages = [];
let rankCurrentLanguage = '{{ auth()->user()->locale ?? config('app.locale', 'hu') }}';
let rankOriginalLanguage = rankCurrentLanguage;
let rankTranslations = { name: {} };
let rankBasicData = { value: '', min: '', max: '', hasMin: false, hasMax: false };
let isTranslationMode = false;

// Original function - keeping it exactly the same for compatibility
function openCeoRankModal(rankId = null){
    swal_loader.fire();
    
    // Reset state
    rankCurrentLanguage = '{{ auth()->user()->locale ?? config('app.locale', 'hu') }}';
    rankOriginalLanguage = rankCurrentLanguage;
    rankSelectedLanguages = [rankCurrentLanguage];
    rankTranslations = { name: {} };
    rankBasicData = { value: '', min: '', max: '', hasMin: false, hasMax: false };
    isTranslationMode = false;

    if(rankId == null){
        $('#ceorank-modal').attr('data-id', 0);
        
        // Load selected languages first to determine mode
        loadRankSelectedLanguages().then(() => {
            // Clear form for new rank
            clearRankForm();
            setupRankModalMode();
            swal_loader.close();
            $('#ceorank-modal').modal();
        });
    }else{
        $('#ceorank-modal').attr('data-id', rankId);
        
        // Load selected languages first, then rank data
        loadRankSelectedLanguages().then(() => {
            // Original AJAX call - keeping it exactly the same
            $.ajax({
                url: "{{ route('admin.ceoranks.get') }}",
                data: { id: rankId },
            })
            .done(function(response){
                // Store basic data for reuse
                rankBasicData = {
                    value: response.value,
                    min: response.min || '',
                    max: response.max || '',
                    hasMin: response.min != null,
                    hasMax: response.max != null
                };

                // Original value setting - keeping it exactly the same
                $('#ceorank-modal .rank-value').val(response.value);
                $('#ceorank-modal .rank-name').val(response.name);
                $('#ceorank-modal .rank-max').val(response.max);
                $('#ceorank-modal .rank-min').val(response.min);

                if(response.max != null){
                    $('#ceorank-modal .has-rank-max').prop('checked', true).change();
                }

                if(response.min != null){
                    $('#ceorank-modal .has-rank-min').prop('checked', true).change();
                }

                // Load translation data if available
                if (isTranslationMode) {
                    loadRankTranslationData(rankId, response);
                } else {
                    setupRankModalMode();
                    swal_loader.close();
                    $('#ceorank-modal').modal();
                }
            });
        });
    }
}

function clearRankForm() {
    $('#ceorank-modal .rank-value').val('');
    $('#ceorank-modal .rank-name').val('');
    $('#ceorank-modal .rank-max').val('');
    $('#ceorank-modal .rank-min').val('');
    $('#ceorank-modal .has-rank-min').prop('checked', false).change();
    $('#ceorank-modal .has-rank-max').prop('checked', false).change();
}

function loadRankSelectedLanguages() {
    return new Promise((resolve) => {
        $.ajax({
            url: "{{ route('admin.ceoranks.languages.selected') }}",
            method: 'GET',
            success: function(response) {
                rankSelectedLanguages = response.selected_languages || [rankOriginalLanguage];
                isTranslationMode = rankSelectedLanguages.length > 1;
                console.log('Loaded languages:', rankSelectedLanguages, 'Translation mode:', isTranslationMode);
                resolve();
            },
            error: function() {
                rankSelectedLanguages = [rankOriginalLanguage];
                isTranslationMode = false;
                console.log('Failed to load languages, using default');
                resolve();
            }
        });
    });
}

function loadRankTranslationData(rankId, basicResponse) {
    $.ajax({
        url: "{{ route('admin.ceoranks.translations.get') }}",
        method: 'POST',
        data: {
            id: rankId,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            // Load translation data
            rankOriginalLanguage = response.original_language || rankOriginalLanguage;
            rankCurrentLanguage = rankOriginalLanguage;
            rankTranslations.name = response.name_json || {};

            // Set original values if not in translations
            if (basicResponse.name && !rankTranslations.name[rankOriginalLanguage]) {
                rankTranslations.name[rankOriginalLanguage] = basicResponse.name;
            }

            setupRankModalMode();
            swal_loader.close();
            $('#ceorank-modal').modal();
        },
        error: function() {
            // Fallback - just use the name from basic response
            rankTranslations.name[rankOriginalLanguage] = basicResponse.name;
            setupRankModalMode();
            swal_loader.close();
            $('#ceorank-modal').modal();
        }
    });
}

function setupRankModalMode() {
    if (!isTranslationMode) {
        // Single language mode - show original form only
        $('.language-carousel-container').hide();
        $('.language-indicator').text('');
        updateFieldStates(true); // All fields editable
    } else {
        // Translation mode - show carousel and manage field states
        $('.language-carousel-container').show();
        setupTranslationInterface();
    }
}

function setupTranslationInterface() {
    const availableLocales = @json(config('app.available_locales'));
    
    console.log('Setting up translation interface for languages:', rankSelectedLanguages);
    
    // Build language tabs
    const tabsContainer = $('.language-tabs');
    tabsContainer.empty();
    
    rankSelectedLanguages.forEach((lang, index) => {
        if (availableLocales[lang]) {
            const isActive = lang === rankCurrentLanguage;
            const hasTranslation = checkRankTranslation(lang);
            
            const tab = $(`
                <div class="language-tab ${isActive ? 'active' : ''} ${hasTranslation ? '' : 'missing'}" 
                     data-lang="${lang}">
                    ${availableLocales[lang]} 
                    ${lang === rankOriginalLanguage ? '({{ __('admin/competencies.original') }})' : ''}
                </div>
            `);
            
            tab.click(() => switchRankLanguage(lang));
            tabsContainer.append(tab);
        }
    });
    
    // Initialize carousel navigation
    initRankCarouselNavigation();
    
    // Set initial language state
    switchRankLanguage(rankCurrentLanguage);
    
    // Add AI translation button - ALWAYS ADD FOR TESTING
    console.log('About to add AI translation button');
    addRankTranslationButton();
}

function initRankCarouselNavigation() {
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

function switchRankLanguage(lang) {
    if (!isTranslationMode) return;
    
    // Save current language input
    saveCurrentLanguageData();
    
    // Switch to new language
    rankCurrentLanguage = lang;
    
    // Update active states
    $('.language-tab').removeClass('active');
    $(`.language-tab[data-lang="${lang}"]`).addClass('active');
    
    // Update field states and content
    const isOriginalLanguage = lang === rankOriginalLanguage;
    updateFieldStates(isOriginalLanguage);
    updateLanguageIndicator(lang);
    populateCurrentLanguageData();
    updateTabStates();
    
    // Update AI button state
    checkRankTranslationButtonState();
}

function updateFieldStates(isOriginalLanguage) {
    // Name field is always editable
    $('.rank-name').prop('readonly', false).removeClass('readonly-field');
    
    if (isOriginalLanguage) {
        // Original language - all fields editable
        $('.rank-value').prop('readonly', false).removeClass('readonly-field');
        $('.rank-min').prop('readonly', $('.has-rank-min').is(':checked') ? false : true).removeClass('readonly-field');
        $('.rank-max').prop('readonly', $('.has-rank-max').is(':checked') ? false : true).removeClass('readonly-field');
        $('.has-rank-min, .has-rank-max').removeClass('readonly-checkbox');
    } else {
        // Translation language - only name field editable, others readonly
        $('.rank-value').prop('readonly', true).addClass('readonly-field');
        $('.rank-min').prop('readonly', true).addClass('readonly-field');
        $('.rank-max').prop('readonly', true).addClass('readonly-field');
        $('.has-rank-min, .has-rank-max').addClass('readonly-checkbox');
        
        // Show original values in readonly fields
        $('.rank-value').val(rankBasicData.value);
        $('.rank-min').val(rankBasicData.min);
        $('.rank-max').val(rankBasicData.max);
        $('.has-rank-min').prop('checked', rankBasicData.hasMin);
        $('.has-rank-max').prop('checked', rankBasicData.hasMax);
    }
}

function updateLanguageIndicator(lang) {
    const availableLocales = @json(config('app.available_locales'));
    if (isTranslationMode) {
        const isOriginal = lang === rankOriginalLanguage;
        $('.language-indicator').text(
            isOriginal ? 
            `(${availableLocales[lang]} - {{ __('admin/competencies.original') }})` : 
            `(${availableLocales[lang]})`
        );
    }
}

function saveCurrentLanguageData() {
    // Always save name translation
    rankTranslations.name[rankCurrentLanguage] = $('.rank-name').val();
    
    // Save basic data only from original language
    if (rankCurrentLanguage === rankOriginalLanguage) {
        rankBasicData.value = $('.rank-value').val();
        rankBasicData.min = $('.rank-min').val();
        rankBasicData.max = $('.rank-max').val();
        rankBasicData.hasMin = $('.has-rank-min').is(':checked');
        rankBasicData.hasMax = $('.has-rank-max').is(':checked');
    }
}

function populateCurrentLanguageData() {
    // Populate name for current language
    $('.rank-name').val(rankTranslations.name[rankCurrentLanguage] || '');
    
    // Update missing translation styling
    const hasNameTranslation = rankTranslations.name[rankCurrentLanguage] && rankTranslations.name[rankCurrentLanguage].trim() !== '';
    const isOriginalLanguage = rankCurrentLanguage === rankOriginalLanguage;
    
    if (!isOriginalLanguage && !hasNameTranslation) {
        $('.rank-name').addClass('missing-translation').attr('placeholder', 'Enter translation for this language...');
    } else {
        $('.rank-name').removeClass('missing-translation').attr('placeholder', '');
    }
}

function checkRankTranslation(lang) {
    return rankTranslations.name[lang] && rankTranslations.name[lang].trim() !== '';
}

function updateTabStates() {
    if (!isTranslationMode) return;
    
    rankSelectedLanguages.forEach(lang => {
        const tab = $(`.language-tab[data-lang="${lang}"]`);
        const hasTranslation = checkRankTranslation(lang);
        
        if (hasTranslation || lang === rankOriginalLanguage) {
            tab.removeClass('missing');
        } else {
            tab.addClass('missing');
        }
    });
}

function collectTranslations() {
    // Save current input
    if (isTranslationMode) {
        saveCurrentLanguageData();
    }
    
    // Collect all translations
    const translations = {};
    rankSelectedLanguages.forEach(lang => {
        const value = rankTranslations.name[lang];
        if (value && value.trim() !== '') {
            translations[lang] = value.trim();
        }
    });
    
    return translations;
}

// AI Translation Functions
function addRankTranslationButton() {
    console.log('addRankTranslationButton called');
    
    // Remove existing button first
    $('#ceorank-modal .ai-translate-rank').remove();
    
    const aiButton = $(`
        <button type="button" class="btn btn-outline-primary ai-translate-rank disabled" style="margin-left: 0.5rem;" disabled>
            <i class="fa fa-robot"></i> {{ __('admin/ceoranks.ai-translate') }}
        </button>
    `);
    
    console.log('Created AI button:', aiButton);
    
    // Try multiple insertion methods
    const saveButton = $('#ceorank-modal .save-rank');
    console.log('Save button found:', saveButton.length);
    
    if (saveButton.length > 0) {
        aiButton.insertAfter(saveButton);
        console.log('AI button inserted before save button');
    } else {
        // Fallback: append to modal actions
        $('.modal-actions').prepend(aiButton);
        console.log('AI button prepended to modal actions');
    }
    
    console.log('AI translate button added. Count:', $('#ceorank-modal .ai-translate-rank').length);
    
    initRankTranslationButton();
}

function initRankTranslationButton() {
    // Remove existing event handlers
    $(document).off('input.airank keyup.airank change.airank', '#ceorank-modal .rank-name');
    $(document).off('click.airank', '.language-tab');
    
    // Listen to name field changes
    $(document).on('input.airank keyup.airank change.airank', '#ceorank-modal .rank-name', function() {
        saveCurrentLanguageData();
        setTimeout(checkRankTranslationButtonState, 50);
    });
    
    // Listen to language tab switches
    $(document).on('click.airank', '.language-tab', function() {
        setTimeout(checkRankTranslationButtonState, 100);
    });
    
    // Initial check
    setTimeout(checkRankTranslationButtonState, 100);
    
    // Handle translation button click
    $(document).off('click.aitranslaterank', '#ceorank-modal .ai-translate-rank').on('click.aitranslaterank', '#ceorank-modal .ai-translate-rank', function() {
        if ($(this).hasClass('disabled')) return;
        
        const nameToTranslate = rankTranslations.name[rankOriginalLanguage] || '';
        const targetLanguages = rankSelectedLanguages.filter(lang => lang !== rankOriginalLanguage);
        
        if (!nameToTranslate) {
            swal.fire({
                icon: 'warning',
                title: '{{ __('admin/ceoranks.name-required') }}',
                text: '{{ __('admin/ceoranks.enter-name-first') }}'
            });
            return;
        }
        
        translateRankName(nameToTranslate, rankOriginalLanguage, targetLanguages);
    });
}

function checkRankTranslationButtonState() {
    const translateButton = $('#ceorank-modal .ai-translate-rank');
    if (translateButton.length === 0) return;
    
    const nameInOriginal = rankTranslations.name[rankOriginalLanguage] && rankTranslations.name[rankOriginalLanguage].trim() !== '';
    const hasMultipleLanguages = rankSelectedLanguages.length > 1;
    const isOriginalLanguage = rankCurrentLanguage === rankOriginalLanguage;
    
    if (nameInOriginal && hasMultipleLanguages && isOriginalLanguage) {
        translateButton.removeClass('disabled').prop('disabled', false);
    } else {
        translateButton.addClass('disabled').prop('disabled', true);
    }
}

function translateRankName(nameToTranslate, sourceLanguage, targetLanguages) {
    const translateButton = $('#ceorank-modal .ai-translate-rank');
    const originalText = translateButton.html();
    translateButton.html('<i class="fa fa-spinner fa-spin"></i> {{ __('admin/ceoranks.translating') }}...').prop('disabled', true);
    
    $.ajax({
        url: "{{ route('admin.ceoranks.translate-name') }}",
        method: 'POST',
        data: {
            rank_name: nameToTranslate,
            source_language: sourceLanguage,
            target_languages: targetLanguages,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success && response.translations) {
                // Update translations with new AI translations
                Object.entries(response.translations).forEach(([lang, translations]) => {
                    if (translations.name) {
                        rankTranslations.name[lang] = translations.name;
                    }
                });
                
                updateAllRankLanguageContent();
                updateTabStates();
                
                swal.fire({
                    icon: 'success',
                    title: '{{ __('admin/ceoranks.translation-success') }}',
                    text: '{{ __('admin/ceoranks.ai-translations-generated') }}',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        },
        error: function(xhr) {
            swal.fire({
                icon: 'error',
                title: '{{ __('admin/ceoranks.translation-failed') }}',
                text: xhr.responseJSON?.message || '{{ __('admin/ceoranks.translation-error') }}'
            });
        },
        complete: function() {
            translateButton.html(originalText).prop('disabled', false);
            checkRankTranslationButtonState();
        }
    });
}

function updateAllRankLanguageContent() {
    // Update the name field for current language
    $('.rank-name').val(rankTranslations.name[rankCurrentLanguage] || '');
    
    // Update field status
    populateCurrentLanguageData();
}

// Original document ready - keeping it exactly the same for compatibility
$(document).ready(function(){
    // Button click handlers
    $('.add-rank').click(function(){
        openCeoRankModal();
    });

    $('.modify-rank').click(function(){
        openCeoRankModal($(this).parents('.rank').attr('data-id'));
    });

    $('.remove-rank').click(function(){
        swal_confirm.fire({
            title: '{{ __('admin/ceoranks.remove-rank-confirm') }}'
        }).then((result) => {
            if (result.isConfirmed) {
                swal_loader.fire();
                $.ajax({
                    url: "{{ route('admin.ceoranks.remove') }}",
                    data: {
                        id: $(this).parents('.rank').attr('data-id')
                    },
                    successMessage: "{{ __('admin/ceoranks.remove-rank-success') }}",
                });
            }
        });
    });

    $('.has-rank-max, .has-rank-min').change(function(){
        // Only allow changes in original language or single language mode
        if (isTranslationMode && rankCurrentLanguage !== rankOriginalLanguage) {
            return false;
        }
        
        var input = $(this).parents('.form-group').find('input[type="number"]');
        var checked = $(this).is(':checked');
        input.prop('readonly', !checked);
        if(!checked){ input.val(''); }
    });

    $('.save-rank').click(function(){
        rankId = $('#ceorank-modal').attr('data-id');
        swal_confirm.fire({
            title: '{{ __('admin/ceoranks.save-confirm') }}'
        }).then((result) => {
            if (result.isConfirmed) {
                swal_loader.fire();
                
                let requestData;
                
                if (isTranslationMode) {
                    // Translation mode - include translations
                    requestData = {
                        id: rankId,
                        name: rankTranslations.name[rankOriginalLanguage] || '', // Use original language name
                        value: rankBasicData.value,
                        min: rankBasicData.hasMin ? rankBasicData.min : 0,
                        max: rankBasicData.hasMax ? rankBasicData.max : 0,
                        translations: collectTranslations()
                    };
                } else {
                    // Simple mode - original request format
                    requestData = {
                        id: rankId,
                        name: $('.rank-name').val(),
                        value: $('.rank-value').val(),
                        min: $('.has-rank-min').is(':checked') ? $('.rank-min').val() : 0,
                        max: $('.has-rank-max').is(':checked') ? $('.rank-max').val() : 0
                    };
                }
                
                $.ajax({
                    url: "{{ route('admin.ceoranks.save') }}",
                    data: requestData,
                    successMessage: '{{ __('admin/ceoranks.save-success') }}',
                });
            }
        });
    });
});
</script>