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
        <div class="group-info" style="background-color: #f8f9fa; padding: 0.75rem; margin-bottom: 1rem; border-radius: 0.375rem; border-left: 4px solid #007bff;">
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
        <button class="btn btn-primary save-group-users">{{ __('admin/competencies.save-user-assignments') }}</button>
        <button class="btn btn-danger trigger-empty-group">{{ __('global.remove-all') }}</button>

      </div>

    </div>
  </div>
</div>

<style>
/* Group users modal styling - consistent with departmentuser modal */
#group-users-modal .modal-body {
  display: flex;
  flex-direction: column;
  gap: 1em;
}

#group-users-modal .btn {
  width: 100%;
}

#group-users-modal .group-users-list {
  display: flex;
  flex-direction: column;
  gap: 1em;
  max-height: 300px;
  height: 300px;
  overflow-y: scroll;
  padding-right: 0.5em;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  padding: 1rem;
}

/* User items styled like dept-member-item */
.group-user-item {
  display: flex;
  gap: 1em;
  border-bottom: 3px solid var(--info);
  padding-bottom: 0.5em;
}

.group-user-item i {
  display: flex;
  font-size: 1.2em;
  color: var(--danger);
  justify-content: center;
  align-items: center;
  cursor: pointer;
}

.group-user-item .item-content {
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 0.5em;
}

.group-user-item .item-content p {
  font-weight: bold;
  margin: 0;
}

.group-user-item .item-content span {
  font-size: 0.9em;
  font-weight: normal;
  font-style: italic;
  color: var(--silver_chalice);
}

/* Empty state styling - simple like departmentuser modal */
.group-users-list:empty::after {
  content: "{{ __('admin/competencies.no-users-assigned') }}";
  display: block;
  text-align: center;
  padding: 2rem;
  color: var(--silver_chalice);
  font-style: italic;
  font-weight: bold;
}

</style>

<script>
// Initialize group users modal functions
(function(){
  // Add user item to list with consistent styling
  function addGroupUserItem(uid, name, email){
    const emailDisplay = email ? 
      `<span>${email}</span>` : 
      '<span style="color: #6c757d; font-style: italic;">{{ __("global.no-email") }}</span>';
    
    $('.group-users-list').append(`
      <div class="group-user-item" data-id="${uid}">
        <i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/competencies.remove-user') }}"></i>
        <div class="item-content">
          <p>${name}</p>
          ${emailDisplay}
        </div>
      </div>
    `);
  }

  // Make addGroupUserItem available globally
  window.addGroupUserItem = addGroupUserItem;

  // Add user button click handler
  $(document).on('click', '.trigger-add-user', function(){
    const groupId = $('#group-users-modal').data('group-id');

    // Get currently selected users to exclude them
    var except = [];
    $('#group-users-modal .group-user-item').each(function(){
      except.push($(this).data('id')*1);
    });

    // Use select modal with multi-select capability (same as departmentuser)
    openSelectModal({
      title: "{{ __('admin/competencies.select-users') }}",
      parentSelector: '#group-users-modal',
      ajaxRoute: "{{ route('admin.competency-group.users.eligible') }}?group_id="+groupId,
      itemData: function(item){ 
        return {
          id: item.id,
          name: item.name,
          top: null,
          bottom: item.email || null
        };
      },
      selectFunction: function(){
        const uid = $(this).attr('data-id');
        const name = $(this).attr('data-name');
        const email = $(this).find('.item-content span').text() || '';
        
        // Check if not already added
        if ($('#group-users-modal .group-user-item[data-id="'+uid+'"]').length === 0) {
          addGroupUserItem(uid, name, email);
          if (window.tippy) tippy('.group-users-list [data-tippy-content]');
        }
      },
      exceptArray: except,
      multiSelect: true, // Enable multi-select
      emptyMessage: '{{ __("global.no-employee") }}'
    });
  });

  // Remove individual user
  $(document).on('click', '#group-users-modal .group-user-item i', function(){
    $(this).closest('.group-user-item').remove();
  });

  // Empty all users
  $(document).on('click', '.trigger-empty-group', function(){
    swal_confirm.fire({ 
      title: '{{ __("global.remove-all") }}?',
      text: '{{ __("admin/competencies.remove-all-users-confirm") }}'
    }).then((result) => {
      if (result.isConfirmed) {
        $('.group-users-list').empty();
      }
    });
  });

  // Save group users
  $(document).on('click', '.save-group-users', function(){
    const groupId = $('#group-users-modal').attr('data-id');
    var ids = [];
    $('#group-users-modal .group-user-item').each(function(){
      ids.push($(this).data('id')*1);
    });
    
    swal_confirm.fire({ 
      title: '{{ __("admin/competencies.save-user-assignments") }}?',
      text: '{{ __("global.changes-will-be-saved") }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.competency-group.users.save') }}",
          method: 'POST',
          data: { 
            group_id: groupId,
            user_ids: ids 
          },
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
        })
        .done(function(resp){
          swal_loader.close();
          $('#group-users-modal').modal('hide');
          Swal.fire({
            icon: 'success',
            title: '{{ __("global.success") }}',
            text: resp.message || '{{ __("admin/competencies.user-assignments-saved") }}',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            // Refresh the page to show updated user counts
            window.location.reload();
          });
        })
        .fail(function(xhr){
          swal_loader.close();
          const errorMsg = xhr.responseJSON?.message || '{{ __("global.error") }}';
          Swal.fire({ 
            icon: 'error', 
            title: '{{ __("global.error") }}', 
            text: errorMsg
          });
        });
      }
    });
  });

})();

// Initialize group users modal
function initGroupUsersModal(groupId, groupName) {
  swal_loader.fire();
  
  $('#group-users-modal').attr('data-id', groupId);
  $('.group-name-display').text(groupName);
  $('.group-users-list').empty();

  // Load existing assigned users
  $.ajax({
    url: "{{ route('admin.competency-group.users.get') }}",
    method: 'POST',
    data: { group_id: groupId },
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  })
  .done(function(users) {
    users.forEach(user => {
      addGroupUserItem(user.id, user.name, user.email || null);
    });
    
    // Store group ID for use in user selection
    $('#group-users-modal').data('group-id', groupId);
    
    if (window.tippy) tippy('.group-users-list [data-tippy-content]');
    
    swal_loader.close();
    $('#group-users-modal').modal('show');
  })
  .fail(function(xhr) {
    swal_loader.close();
    const errorMsg = xhr.responseJSON?.message || '{{ __("global.error") }}';
    Swal.fire({ 
      icon: 'error', 
      title: '{{ __("global.error") }}', 
      text: errorMsg
    });
  });
}

// Make function globally available
window.initGroupUsersModal = initGroupUsersModal;
</script>