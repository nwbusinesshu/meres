<script>
$(document).ready(function(){
  const url = new URL(window.location.href);

  $('.create-competency').click(function(){
    openCompetencyModal();
  });

  $('.modify-competency').click(function(){
    const id = $(this).closest('.competency-item').attr('data-id')*1;
    const name = $(this).closest('.competency-item').attr('data-name');
    openCompetencyModal(id, name);
  });

  // FIXED: Arrow toggle functionality with event delegation and improved selector
  $(document).on('click', '.competency-item .bar > span', function(e){
    // Prevent bubbling to other elements
    e.stopPropagation();
    
    const $competencyItem = $(this).closest('.competency-item');
    const $questions = $competencyItem.find('.questions');
    const $icon = $(this).find('i');
    const show = $questions.hasClass('hidden');
    
    // Close all other questions first
    $('.competency-item .questions').addClass('hidden');
    $('.competency-item .bar span i').removeClass('fa-caret-up').addClass('fa-caret-down');
    
    if (show) {
      $questions.removeClass('hidden');
      $icon.removeClass('fa-caret-down').addClass('fa-caret-up');
      url.searchParams.set('open', $competencyItem.attr('data-id'));
      window.history.replaceState(null, null, url);
    } else {
      url.searchParams.delete('open');
      window.history.replaceState(null, null, url);
    }
  });

  // Auto-open from URL parameter
  if(url.searchParams.has('open')){
    const openId = url.searchParams.get('open');
    $('.competency-item[data-id="'+openId+'"] .bar > span').trigger('click');
  }

  $('.create-question').click(function(){
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(null, compId);
  });

  $('.modify-question').click(function(){
    const id = $(this).closest('.question-item').attr('data-id');
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(id, compId);
  });
  
  $('.competency-search-input').keyup(function(e){
    if(e.keyCode != 13){ return; }
    
    swal_loader.fire();
    const search = $(this).val().toLowerCase();

    $('.competency-item').addClass('hidden');
    $('.no-competency').addClass('hidden');

    $('.competency-item').each(function(){
      const name = $(this).attr('data-name');
      if(name && name.toLowerCase().includes(search)){
        $(this).removeClass('hidden');
      }
    });

    url.searchParams.delete('search');
    if(search.length != 0){
      url.searchParams.set('search', search);
    }
    window.history.replaceState(null, null, url);

    if($('.competency-item:not(.hidden)').length == 0){
      $('.no-competency').removeClass('hidden');
    }
    swal_loader.close();
  });

  if(url.searchParams.has('search')){
    $('.competency-search-input').val(url.searchParams.get('search'))
      .trigger(jQuery.Event('keyup', { keyCode: 13 }));
  }

  $('.competency-clear-search').click(function(){
    $('.competency-search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
  });

  $('.remove-question').click(function(){
    const id = $(this).closest('.question-item').attr('data-id');
    swal_confirm.fire({
      title: '{{ __('admin/competencies.question-remove-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.competency.question.remove') }}",
          data: { id: id },
          successMessage: "{{ __('admin/competencies.question-remove-success') }}",
        });
      }
    });
  });

  $('.remove-competency').click(function(){
    const id = $(this).closest('.competency-item').attr('data-id');
    swal_confirm.fire({
      title: '{{ __('admin/competencies.remove-competency-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.competency.remove') }}",
          data: { id: id },
          successMessage: "{{ __('admin/competencies.remove-competency-success') }}",
        });
      }
    });
  });

  // FIXED: Translation management button handler with event delegation
  $(document).on('click', '.manage-translations', function(e) {
    e.stopPropagation();
    const competencyId = $(this).data('competency-id');
    openCompetencyTranslationModal(competencyId);
  });

  // Initialize tooltips after page load
  setTimeout(function() {
    if (window.tippy) {
      tippy('.language-indicator[data-tippy-content]');
    }
  }, 100);
});

// FIXED: Translation management functions with proper loader handling
function openCompetencyTranslationModal(competencyId) {
  swal_loader.fire();
  
  $.ajax({
    url: "{{ route('admin.competency.translations.get') }}",
    method: 'POST',
    data: { 
      id: competencyId,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      buildTranslationManagementContent(response);
      $('#translation-management-modal').modal('show');
      swal_loader.close();
    },
    error: function(xhr) {
      swal_loader.close();
      console.error('Translation load failed:', xhr);
      alert(xhr.responseJSON?.error || '{{ __('global.error-occurred') }}');
    }
  });
}

function buildTranslationManagementContent(data) {
  const availableLanguages = {!! json_encode($availableLanguages ?? ['hu', 'en']) !!};
  const languageNames = {!! json_encode($languageNames ?? ['hu' => 'Magyar', 'en' => 'English']) !!};
  
  let content = `
    <div class="competency-translation-manager">
      <h6>{{ __('admin/competencies.competency-translations') }}</h6>
      <div class="translation-grid">
  `;
  
  availableLanguages.forEach(lang => {
    const translation = data.translations[lang] || {};
    const isOriginal = lang === data.original_language;
    const langName = languageNames[lang] || lang.toUpperCase();
    
    content += `
      <div class="form-group">
        <label>
          ${langName}
          ${isOriginal ? '<span class="badge badge-primary ml-1">{{ __('translations.original') }}</span>' : ''}
        </label>
        <input type="text" 
               class="form-control translation-input" 
               data-language="${lang}"
               value="${translation.name || ''}"
               ${isOriginal ? 'required' : ''}>
        ${!isOriginal ? '<small class="form-text text-muted">{{ __('translations.leave-empty-to-remove') }}</small>' : ''}
      </div>
    `;
  });
  
  content += `
      </div>
      <div class="mt-3">
        <button class="btn btn-primary btn-sm" onclick="saveCompetencyTranslations(${data.competency_id || 'null'})">
          {{ __('translations.save') }}
        </button>
        <button class="btn btn-info btn-sm ml-2" onclick="translateCompetencyWithAI(${data.competency_id || 'null'})">
          <i class="fa fa-robot"></i> {{ __('translations.translate-with-ai') }}
        </button>
      </div>
    </div>
  `;
  
  $('#translation-content').html(content);
}

function saveCompetencyTranslations(competencyId) {
  const translations = {};
  
  $('.translation-input').each(function() {
    const lang = $(this).data('language');
    const value = $(this).val().trim();
    if (value) {
      translations[lang] = value;
    }
  });
  
  swal_loader.fire();
  
  $.ajax({
    url: "{{ route('admin.competency.translations.save') }}",
    method: 'POST',
    data: {
      id: competencyId,
      translations: translations,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      if (response.success) {
        $('#translation-management-modal').modal('hide');
        swal_success.fire({
          title: '{{ __('translations.translations-saved') }}'
        }).then(() => {
          location.reload();
        });
      } else {
        swal_loader.close();
        alert(response.error || '{{ __('global.error-occurred') }}');
      }
    },
    error: function(xhr) {
      swal_loader.close();
      console.error('Translation save failed:', xhr);
      alert(xhr.responseJSON?.error || '{{ __('global.error-occurred') }}');
    }
  });
}

// FIXED: AI translation with proper loader handling
function translateCompetencyWithAI(competencyId) {
  const missingLanguages = [];
  $('.translation-input').each(function() {
    const lang = $(this).data('language');
    const value = $(this).val().trim();
    const isOriginal = $(this).prop('required');
    if (!value && !isOriginal) {
      missingLanguages.push(lang);
    }
  });
  
  if (missingLanguages.length === 0) {
    alert('{{ __('translations.no-missing-translations') }}');
    return;
  }
  
  swal_loader.fire();
  
  $.ajax({
    url: "{{ route('admin.competency.translations.ai') }}",
    method: 'POST',
    data: {
      id: competencyId,
      languages: missingLanguages,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      // Always close loader first
      swal_loader.close();
      
      if (response.success && response.translations) {
        // Apply AI translations to inputs
        Object.keys(response.translations).forEach(lang => {
          $(`.translation-input[data-language="${lang}"]`).val(response.translations[lang]);
        });
        
        swal_success.fire({
          title: '{{ __('translations.ai-translation-complete') }}'
        });
      } else {
        alert(response.error || '{{ __('translations.ai-translation-failed') }}');
      }
    },
    error: function(xhr) {
      // Always close loader on error
      swal_loader.close();
      console.error('AI translation failed:', xhr);
      alert(xhr.responseJSON?.error || '{{ __('translations.ai-translation-failed') }}');
    }
  });
}
</script>