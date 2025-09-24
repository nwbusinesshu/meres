<script>
// 🔥 SIMPLE: Just set the global mode flag
window.globalCompetencyMode = true;

$(document).ready(function(){
  const url = new URL(window.location.href);

  // UI handlers
  $('.create-competency').click(function(){
    openCompetencyModal();
  });

  $('.modify-competency').click(function(){
    const id = $(this).closest('.competency-item').attr('data-id')*1;
    const name = $(this).closest('.competency-item').attr('data-name');
    openCompetencyModal(id, name);
  });

  $('.create-question').click(function(){
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(null, compId);
  });

  $('.modify-question').click(function(){
    const id = $(this).closest('.question-item').attr('data-id');
    const compId = $(this).closest('.competency-item').attr('data-id');
    openCompetencyQModal(id, compId);
  });

  // Remove handlers with global routes
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
              text: xhr.responseJSON?.message || 'Error'
            });
          }
        });
      }
    });
  });

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
              text: xhr.responseJSON?.message || 'Error'
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

  $('.competency-clear-search').click(function(){
    $('.competency-search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
  });

  // Accordion functionality
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

  // Auto-search if URL parameter exists
  if(url.searchParams.has('search')){
    $('.competency-search-input').val(url.searchParams.get('search'))
      .trigger(jQuery.Event('keyup', { keyCode: 13 }));
  }

  // Initialize tooltips
  if (typeof tippy !== 'undefined') {
    tippy('[data-tippy-content]', {
      placement: 'top',
      theme: 'warning',
    });
  }

  console.log('🔥 Global competency mode initialized');
});
</script>