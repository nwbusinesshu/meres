<script>
$(document).ready(function(){
  const url = new URL(window.location.href);

  $('.create-competency').click(function(){
    openCompetencyModal();
  });

  $('.modify-competency').click(function(){
    id = $(this).parents('.competency-item').attr('data-id')*1;
    name = $(this).parents('.competency-item').attr('data-name');
    openCompetencyModal(id, name);
  });

  // ORIGINAL: Handle ALL competency-item clicks (including groups)
  $('.competency-item .bar span').click(function(e){
    var show = $(this).parents('.competency-item').find('.questions').hasClass('hidden');
    $('.competency-item .questions').addClass('hidden');
    $('.competency-item .bar span i').removeClass('fa-caret-up');
    $('.competency-item .bar span i').addClass('fa-caret-down');

    if(show){
      $(this).parents('.competency-item').find('.questions').removeClass('hidden');
      $(this).parents('.bar').find('span i').addClass('fa-caret-up');
      url.searchParams.set('open', $(this).parents('.competency-item').attr('data-id'));
      window.history.replaceState(null, null, url);
    }
  });

  if(url.searchParams.has('open')){
    $('.competency-item[data-id="'+url.searchParams.get('open')+'"] .bar span').click();
  }

  $('.create-question').click(function(){
    openCompetencyQModal(null, $(this).parents('.competency-item').attr('data-id'));
  });

  $('.modify-question').click(function(){
    openCompetencyQModal($(this).parents('.question-item').attr('data-id'), $(this).parents('.competency-item').attr('data-id'));
  });
  
  $('.competency-search-input').keyup(function(e){
    if(e.keyCode != 13){ return; }
    swal_loader.fire();
    search = $(this).val().toLowerCase();

    $('.competency-item').addClass('hidden');
    $('.no-competency').addClass('hidden');

    $('.competency-item').each(function(){
      if($(this).attr('data-name').toLowerCase().includes(search)){
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
    swal_confirm.fire({
      title: '{{ __('admin/competencies.question-remove-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.competency.question.remove') }}",
          data: { id: $(this).parents('.question-item').attr('data-id') },
          successMessage: "{{ __('admin/competencies.question-remove-success') }}",
        });
      }
    });
  });

  $('.remove-competency').click(function(){
    swal_confirm.fire({
      title: '{{ __('admin/competencies.remove-competency-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.competency.remove') }}",
          data: { id: $(this).parents('.competency-item').attr('data-id') },
          successMessage: "{{ __('admin/competency.remove-competency-success') }}",
        });
      }
    });
  });

  // NEW: Handle competency group specific actions
  $('.remove-competency-group').click(function(e){
    e.stopPropagation();
    
    const groupId = $(this).closest('.competency-group-item').attr('data-id');
    const groupName = $(this).closest('.competency-group-item').attr('data-name');
    
    swal_confirm.fire({
      title: '{{ __('admin/competencies.remove-group-confirm') }}',
      text: `{{ __('admin/competencies.remove-group-text') }} "${groupName}"?`,
      icon: 'warning'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        $.ajax({
          url: "{{ route('admin.competency-group.remove') }}",
          method: 'POST',
          data: {
            id: groupId,
            _token: $('meta[name="csrf-token"]').attr('content')
          },
          success: function(response) {
            if (response.ok) {
              swal.fire({
                icon: 'success',
                title: '{{ __('admin/competencies.group-removed-success') }}',
                timer: 2000,
                showConfirmButton: false
              }).then(() => {
                location.reload();
              });
            }
          },
          error: function(xhr) {
            swal_loader.close();
            swal.fire({
              icon: 'error',
              title: '{{ __('global.error') }}',
              text: xhr.responseJSON?.message || '{{ __('global.error-occurred') }}'
            });
          }
        });
      }
    });
  });

  // FIXED: Improved modify-competency-group click handler with better error handling
  $('.modify-competency-group').click(function(e){
    e.stopPropagation();
    
    const groupId = $(this).closest('.competency-group-item').attr('data-id');
    const groupName = $(this).closest('.competency-group-item').attr('data-name');
    
    // ADDED: Validation before making the request
    if (!groupId) {
      swal.fire({
        icon: 'error',
        title: '{{ __('global.error') }}',
        text: '{{ __("admin/competencies.error-group-id-not-found") }}'
      });
      return;
    }
    
    swal_loader.fire();
    
    $.ajax({
      url: "{{ route('admin.competency-group.get') }}",
      method: 'POST',
      data: {
        id: groupId,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        swal_loader.close();
        
        // FIXED: Added validation for response structure
        if (response && response.competencies) {
          // FIXED: Call the global function directly
          window.initEditCompetencyGroupModal(groupId, groupName, response.competencies);
        } else {
          swal.fire({
            icon: 'error',
            title: '{{ __('global.error') }}',
            text: '{{ __("admin/competencies.error-invalid-response") }}'
          });
        }
      },
      error: function(xhr) {
        swal_loader.close();
        
        // IMPROVED: Better error handling with more specific messages
        let errorMessage = '{{ __('global.error-occurred') }}';
        
        if (xhr.status === 404) {
          errorMessage = '{{ __('admin/competencies.group-not-found') }}';
        } else if (xhr.status === 403) {
          errorMessage = '{{ __("admin/competencies.error-no-permission-edit-group") }}';
        } else if (xhr.status === 422) {
          errorMessage = '{{ __("admin/competencies.error-invalid-group-id") }}';
        } else if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = xhr.responseJSON.message;
        }
        
        swal.fire({
          icon: 'error',
          title: '{{ __('global.error') }}',
          text: errorMessage
        });
        
        // ADDED: Console logging for debugging
        console.error('Group edit error:', xhr);
      }
    });
  });
  
// NEW: Handle group user assignment button
  $('.assign-group-users').click(function(e){
    e.stopPropagation();
    
    const groupId = $(this).data('group-id');
    const groupName = $(this).data('group-name');
    
    // Open the group users modal
    initGroupUsersModal(groupId, groupName);
  });

}); // FIXED: Only one closing bracket for $(document).ready
</script>