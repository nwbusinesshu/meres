<script>
$(document).ready(function(){
  const url = new URL(window.location.href);

  // Create competency button
  $('.create-competency').click(function(){
    openCompetencyModal();
  });

  // Modify competency button
  $('.modify-competency').click(function(){
    const id = $(this).closest('.competency-item').attr('data-id')*1;
    const name = $(this).closest('.competency-item').attr('data-name');
    openCompetencyModal(id, name);
  });

  

  // Remove competency with global route
  $(document).on('click', '.remove-competency', function(){
    const id = $(this).closest('.competency-item').attr('data-id')*1;
    
    swal_confirm.fire({
      title: '{{ __('admin/competencies.remove-competency-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        $.ajax({
          url: "{{ route('superadmin.competency.remove') }}",
          method: 'POST',
          data: {
            id: id,
            _token: '{{ csrf_token() }}'
          },
          success: function(response) {
            swal_loader.close();
            
            swal.fire({
              icon: 'success',
              title: '{{ __('global.success') }}',
              text: '{{ __('admin/competencies.remove-competency-success') }}',
              timer: 2000,
              showConfirmButton: false
            }).then(() => {
              location.reload();
            });
          },
          error: function(xhr) {
            swal_loader.close();
            swal.fire({
              icon: 'error',
              title: '{{ __('global.error') }}',
              text: xhr.responseJSON?.message || '{{ __('admin/competencies.remove-competency-error') }}'
            });
          }
        });
      }
    });
  });

  // Competency accordion toggle
  $('.competency-item .bar span').click(function(e){
    const $item = $(this).closest('.competency-item');
    const show = $item.find('.questions').hasClass('hidden');

    $('.competency-item .questions').addClass('hidden');
    $('.competency-item .bar span i').removeClass('fa-caret-up').addClass('fa-caret-down');

    if (show) {
      $item.find('.questions').removeClass('hidden');
      $(this).find('i').removeClass('fa-caret-down').addClass('fa-caret-up');
      url.searchParams.set('open', $item.attr('data-id'));
      window.history.replaceState(null, null, url);
    }
  });

  // Auto-open if URL parameter exists
  if(url.searchParams.has('open')){
    $('.competency-item[data-id="'+url.searchParams.get('open')+'"] .bar span').click();
  }

  // Create question button
  $('.create-question').click(function(){
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(null, compId);
  });

  // Modify question button
  $('.modify-question').click(function(){
    const id = $(this).closest('.question-item').attr('data-id');
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(id, compId);
  });

  // Remove question with global route
  $(document).on('click', '.remove-question', function(){
    const id = $(this).closest('.question-item').attr('data-id')*1;
    
    swal_confirm.fire({
      title: '{{ __('admin/competencies.remove-question-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        $.ajax({
          url: "{{ route('superadmin.competency.q.remove') }}",
          method: 'POST',
          data: {
            id: id,
            _token: '{{ csrf_token() }}'
          },
          success: function(response) {
            swal_loader.close();
            
            swal.fire({
              icon: 'success',
              title: '{{ __('global.success') }}',
              text: '{{ __('admin/competencies.remove-question-success') }}',
              timer: 2000,
              showConfirmButton: false
            }).then(() => {
              location.reload();
            });
          },
          error: function(xhr) {
            swal_loader.close();
            swal.fire({
              icon: 'error',
              title: '{{ __('global.error') }}',
              text: xhr.responseJSON?.message || '{{ __('admin/competencies.remove-question-error') }}'
            });
          }
        });
      }
    });
  });

  // Search functionality
  $('.competency-search-input').keyup(function(e){
    if(e.keyCode != 13) return;

    swal_loader.fire();
    const search = $(this).val().toLowerCase();

    $('.competency-item').addClass('hidden');
    $('.no-competency').addClass('hidden');

    $('.competency-item').each(function(){
      const name = $(this).attr('data-name')?.toLowerCase();
      if(name.includes(search)){
        $(this).removeClass('hidden');
      }
    });

    url.searchParams.delete('search');
    if(search.length !== 0){
      url.searchParams.set('search', search);
    }
    window.history.replaceState(null, null, url);

    if($('.competency-item:not(.hidden)').length === 0){
      $('.no-competency').removeClass('hidden');
    }
    swal_loader.close();
  });

  // Clear search
  $('.competency-clear-search').click(function(){
    $('.competency-search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
  });

  // Auto-search if URL parameter exists
  if(url.searchParams.has('search')){
    $('.competency-search-input').val(url.searchParams.get('search'))
      .trigger(jQuery.Event('keyup', { keyCode: 13 }));
  }

  // Override modal functions for global competency routes
  const originalLoadCompetencyTranslations = window.loadCompetencyTranslations;
  if (originalLoadCompetencyTranslations) {
    window.loadCompetencyTranslations = function(competencyId) {
      $.ajax({
        url: "{{ route('superadmin.competency.translations.get') }}",
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
    };
  }

  // Override selected languages loading to return all languages for global competencies
  const originalLoadCompetencySelectedLanguages = window.loadCompetencySelectedLanguages;
  if (originalLoadCompetencySelectedLanguages) {
    window.loadCompetencySelectedLanguages = function() {
      return new Promise((resolve) => {
        $.ajax({
          url: "{{ route('superadmin.competency.languages.selected') }}",
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
    };
  }

  // Override question selected languages loading
  const originalLoadQuestionSelectedLanguages = window.loadQuestionSelectedLanguages;
  if (originalLoadQuestionSelectedLanguages) {
    window.loadQuestionSelectedLanguages = function() {
      return new Promise((resolve) => {
        $.ajax({
          url: "{{ route('superadmin.competency.languages.selected') }}",
          method: 'GET',
          success: function(response) {
            qSelectedLanguages = response.selected_languages || [qOriginalLanguage];
            resolve();
          },
          error: function() {
            qSelectedLanguages = [qOriginalLanguage];
            resolve();
          }
        });
      });
    };
  }

  // Override question translations loading
  const originalLoadQuestionTranslations = window.loadQuestionTranslations;
  if (originalLoadQuestionTranslations) {
    window.loadQuestionTranslations = function(questionId) {
      $.ajax({
        url: "{{ route('superadmin.competency.question.translations.get') }}",
        method: 'POST',
        data: { 
          id: questionId,
          _token: '{{ csrf_token() }}'
        },
        success: function(response) {
          // Set basic values
          $('#competencyq-modal .scale').val(response.max_value);
          qOriginalLanguage = response.original_language || qOriginalLanguage;
          
          // Load translations
          qTranslations.question = response.question_json || {};
          qTranslations.question_self = response.question_self_json || {};
          qTranslations.min_label = response.min_label_json || {};
          qTranslations.max_label = response.max_label_json || {};
          
          // Set current form values from original or current language
          const currentLang = qCurrentLanguage;
          $('#competencyq-modal .question').val(qTranslations.question[currentLang] || response.question || '');
          $('#competencyq-modal .question-self').val(qTranslations.question_self[currentLang] || response.question_self || '');
          $('#competencyq-modal .min-label').val(qTranslations.min_label[currentLang] || response.min_label || '');
          $('#competencyq-modal .max-label').val(qTranslations.max_label[currentLang] || response.max_label || '');
          
          setupQuestionModal();
          swal_loader.close();
          $('#competencyq-modal').modal();
        },
        error: function() {
          setupQuestionModal();
          swal_loader.close();
          $('#competencyq-modal').modal();
        }
      });
    };
  }
});
</script>