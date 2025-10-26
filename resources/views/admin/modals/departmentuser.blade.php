{{-- resources/views/admin/modals/departmentuser.blade.php --}}
@if(!empty($enableMultiLevel) && $enableMultiLevel)
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="dept-members-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/employees.department-members-title') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('admin/employees.department-members-close') }}">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        {{-- Members list (using same pattern as competency modal) --}}
        <div class="dept-members-list">
          {{-- Dynamic content will be populated here --}}
        </div>

        {{-- Action buttons (consistent with user-competency modal pattern) --}}
        <div class="tile tile-button trigger-add-member">{{ __('admin/employees.department-add-member') }}</div>
        <button class="btn btn-primary save-dept-members">{{ __('admin/employees.department-save-members') }}</button>
        <button class="btn btn-danger trigger-empty-department">{{ __('admin/employees.department-remove-all-members') }}</button>

      </div>

    </div>
  </div>
</div>

<script>
// Initialize department members modal functions
(function(){
  // Add member item to list with consistent styling
  function addMemberItem(uid, name, email){
    const emailDisplay = email ? '<span>(' + email + ')</span>' : '';
    $('.dept-members-list').append(
      '<div class="dept-member-item" data-id="'+uid+'">' +
        '<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.department-member-remove-tooltip') }}"></i>' +
        '<div class="item-content">' +
          '<p>'+name+'</p>' +
          emailDisplay +
        '</div>' +
      '</div>'
    );
  }

  // Initialize department members modal
  function initDeptMembersModal(deptId){
    $('#dept-members-modal').attr('data-id', deptId);

    swal_loader.fire();
    $.ajax({
      url: "{{ route('admin.employee.department.members') }}",
      method: 'POST',
      data: { department_id: deptId },
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    })
    .done(function(resp){
      $('.dept-members-list').html('');
      (resp.members || []).forEach(function(m){
        addMemberItem(m.id, m.name, m.email);
      });
      if (window.tippy) tippy('.dept-members-list [data-tippy-content]');
      swal_loader.close();
      $('#dept-members-modal').modal();
    })
    .fail(function(xhr) {
      swal_loader.close();
      const errorMsg = xhr.responseJSON?.message || '{{ __("admin/employees.department-members-load-error") }}';
      Swal.fire({ 
        icon: 'error', 
        title: '{{ __("admin/employees.department-error-title") }}',
        text: errorMsg
      });
    });
  }

  // Make initDeptMembersModal available globally
  window.initDeptMembersModal = initDeptMembersModal;

  // Add member button click handler
  $(document).on('click', '.trigger-add-member', function(){
    const deptId = $('#dept-members-modal').attr('data-id');

    // Get currently selected members to exclude them
    var except = [];
    $('#dept-members-modal .dept-member-item').each(function(){
      except.push($(this).data('id')*1);
    });

    // Use select modal with multi-select capability
    openSelectModal({
      title: "{{ __('admin/employees.department-select-employee-title') }}",
      parentSelector: '#dept-members-modal',
      ajaxRoute: "{{ route('admin.employee.department.eligible') }}?department_id="+deptId,
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
        if ($('#dept-members-modal .dept-member-item[data-id="'+uid+'"]').length === 0) {
          addMemberItem(uid, name, email);
          if (window.tippy) tippy('.dept-members-list [data-tippy-content]');
        }
      },
      exceptArray: except,
      multiSelect: true, // Enable multi-select
      emptyMessage: '{{ __("admin/employees.department-no-selectable-employee") }}'
    });
  });

  // Remove individual member
  $(document).on('click', '#dept-members-modal .dept-member-item i', function(){
    $(this).closest('.dept-member-item').remove();
  });

  // Save department members
  $(document).on('click', '.save-dept-members', function(){
  const deptId = $('#dept-members-modal').attr('data-id');
  
  swal_confirm.fire({
    title: '{{ __("admin/employees.confirm-save-dept-members") }}',
    icon: 'question'
  }).then((result) => {
    if (result.isConfirmed) {
      swal_loader.fire();
      
      var ids = [];
      $('#dept-members-modal .dept-member-item').each(function(){
        ids.push($(this).attr('data-id') * 1);
      });
      
      // ✅ STANDARDIZED: Use successMessage and close modal in success callback
      $.ajax({
        url: "{{ route('admin.employee.department.members.save') }}",
        method: 'POST',
        data: { 
          department_id: deptId,
          user_ids: ids 
        },
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(resp){
          // Close modal only on success
          $('#dept-members-modal').modal('hide');
        },
        successMessage: '{{ __("admin/employees.department-members-save-success-text") }}',
        error: function(xhr){
          swal_loader.close();
          const errorMsg = xhr.responseJSON?.message || '{{ __("admin/employees.department-members-save-error") }}';
          Swal.fire({ 
            icon: 'error', 
            title: '{{ __("admin/employees.department-save-error-title") }}', 
            text: errorMsg 
          });
          // Modal stays open on error
        }
      });
    }
  });
});

  // Empty department (remove all members)
  $(document).on('click', '.trigger-empty-department', function(){
    swal_confirm.fire({
        title: '{{ __("admin/employees.department-empty-all-title") }}',
        text: '{{ __("admin/employees.department-empty-all-text") }}',
        icon: 'warning'
    }).then((result) => {
      if (result.isConfirmed) {
        $('.dept-members-list').html('');
        Swal.fire({
        icon: 'info',
        title: '{{ __("admin/employees.department-empty-success-title") }}',
        text: '{{ __("admin/employees.department-empty-success-text") }}',
        timer: 2000,
        showConfirmButton: false
      });
      }
    });
  });
  })();
</script>
@endif