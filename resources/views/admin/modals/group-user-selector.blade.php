{{-- resources/views/admin/modals/group-user-selector.blade.php --}}
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="group-users-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/competencies.assign-users-to-group') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        {{-- Group info display --}}
        <div class="group-info" style="background-color: #f8f9fa; padding: 0.75rem; margin-bottom: 1rem; border-radius: 0.375rem; border-left: 4px solid #007bff; flex-shrink: 0;">
          <strong>{{ __('admin/competencies.group-name') }}:</strong> <span class="group-name-display"></span>
          <br>
          <small class="text-muted" style="font-style: italic;">
            {{ __('admin/competencies.one-user-per-group-note') }}
          </small>
        </div>

        {{-- Assigned users list (using same pattern as department modal) --}}
        <div class="group-users-list">
          {{-- Dynamic content will be populated here --}}
        </div>

        {{-- Action buttons (consistent with departmentuser modal pattern) --}}
        <div class="tile tile-button trigger-add-user">{{ __('admin/competencies.select-users') }}</div>
        

      </div>

      <div class="modal-footer">
<button class="btn btn-primary save-group-users">{{ __('admin/competencies.save-user-assignments') }}</button>
        <button class="btn btn- secondary btn-danger trigger-empty-group">{{ __('global.remove-all') }}</button>
      </div>

    </div>
  </div>
</div>

<script>
// Initialize group users modal functions  
(function(){
  
  // Add user to the list
  function addGroupUserItem(userId, userName, userEmail = null){
    const emailDisplay = userEmail ? `<span>(${userEmail})</span>` : '';
    
    $('.group-users-list').append(`
      <div class="group-user-item" data-id="${userId}">
        <i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/competencies.remove-user') }}"></i>
        <div class="item-content">
          <p>${userName}</p>
          ${emailDisplay}
        </div>
      </div>
    `);
  }

  // Initialize group users modal
  window.initGroupUsersModal = function(groupId, groupName) {
    $('#group-users-modal').attr('data-id', groupId);
    $('.group-name-display').text(groupName);
    
    swal_loader.fire();
    
    $.ajax({
      url: "{{ route('admin.competency-group.users.get') }}",
      method: 'POST',
      data: { group_id: groupId },
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    })
    .done(function(response){
      $('.group-users-list').html('');
      
      if (response.users && response.users.length > 0) {
        response.users.forEach(function(user){
          addGroupUserItem(user.id, user.name, user.email);
        });
      }
      
      if (window.tippy) tippy('.group-users-list [data-tippy-content]');
      
      swal_loader.close();
      $('#group-users-modal').modal('show');
    })
    .fail(function(xhr){
      swal_loader.close();
      const errorMsg = xhr.responseJSON?.message || '{{ __('global.error-occurred') }}';
      swal.fire({
        icon: 'error',
        title: '{{ __('global.error') }}',
        text: errorMsg
      });
    });
  };

  // Add user button
  $(document).on('click', '.trigger-add-user', function(){
    const groupId = $('#group-users-modal').attr('data-id');
    
    var exceptArray = [];
    $('#group-users-modal .group-user-item').each(function(){
      exceptArray.push($(this).data('id') * 1);
    });
    
    openSelectModal({
      title: "{{ __('admin/competencies.select-users') }}",
      parentSelector: '#group-users-modal',
      ajaxRoute: "{{ route('admin.employee.all') }}",
      itemData: function(item){
        return {
          id: item.id,
          name: item.name,
          top: null,
          bottom: item.email || null
        };
      },
      selectFunction: function(){
        const userId = $(this).attr('data-id');
        const userName = $(this).attr('data-name');
        const userEmail = $(this).find('.item-content span').text() || null;
        
        if ($('#group-users-modal .group-user-item[data-id="'+userId+'"]').length === 0) {
          addGroupUserItem(userId, userName, userEmail);
          if (window.tippy) tippy('.group-users-list [data-tippy-content]');
        }
      },
      exceptArray: exceptArray,
      multiSelect: true,
      emptyMessage: '{{ __('admin/competencies.no-users') }}'
    });
  });

  // Remove user
  $(document).on('click', '.group-user-item i', function(){
    $(this).closest('.group-user-item').remove();
  });

  // Save group users
  $(document).on('click', '.save-group-users', function(){
    const groupId = $('#group-users-modal').attr('data-id');
    
    var userIds = [];
    $('#group-users-modal .group-user-item').each(function(){
      userIds.push($(this).data('id') * 1);
    });
    
    swal_confirm.fire({
      title: '{{ __('admin/competencies.save-user-assignments-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        $.ajax({
          url: "{{ route('admin.competency-group.users.save') }}",
          method: 'POST',
          data: {
            group_id: groupId,
            user_ids: userIds
          },
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
        })
        .done(function(response){
          swal_loader.close();
          $('#group-users-modal').modal('hide');
          swal.fire({
            icon: 'success',
            title: '{{ __('global.success') }}',
            text: response.message || '{{ __('admin/competencies.user-assignments-saved') }}',
            timer: 2000,
            showConfirmButton: false
          });
          location.reload();
        })
        .fail(function(xhr){
          swal_loader.close();
          const errorMsg = xhr.responseJSON?.message || '{{ __('global.error-occurred') }}';
          swal.fire({
            icon: 'error',
            title: '{{ __('global.error') }}',
            text: errorMsg
          });
        });
      }
    });
  });

  // Empty group (remove all users)
  $(document).on('click', '.trigger-empty-group', function(){
    swal_confirm.fire({
      title: '{{ __('admin/competencies.remove-all-users-confirm') }}',
      text: '{{ __('admin/competencies.remove-all-users-warning') }}',
      icon: 'warning'
    }).then((result) => {
      if (result.isConfirmed) {
        $('.group-users-list').html('');
        swal.fire({
          icon: 'info',
          title: '{{ __('global.removed') }}',
          text: '{{ __('admin/competencies.remember-to-save') }}',
          timer: 2000,
          showConfirmButton: false
        });
      }
    });
  });

})();
</script>