{{-- resources/views/admin/modals/departmentuser.blade.php --}}
@if(!empty($enableMultiLevel) && $enableMultiLevel)
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="dept-members-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Részleg tagjai</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        {{-- Members list (using same pattern as competency modal) --}}
        <div class="dept-members-list">
          {{-- Dynamic content will be populated here --}}
        </div>

        {{-- Action buttons (consistent with user-competency modal pattern) --}}
        <div class="tile tile-button trigger-add-member">Új tag hozzáadása</div>
        <button class="btn btn-primary save-dept-members">Mentés</button>
        <button class="btn btn-danger trigger-empty-department">Összes eltávolítása</button>

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
        '<i class="fa fa-trash-alt" data-tippy-content="Eltávolítás"></i>' +
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
      const errorMsg = xhr.responseJSON?.message || 'Nem sikerült betölteni a részleg tagjait.';
      Swal.fire({ 
        icon: 'error', 
        title: 'Hiba', 
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
      title: "Dolgozó kiválasztása",
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
      emptyMessage: 'Nincs választható dolgozó'
    });
  });

  // Remove individual member
  $(document).on('click', '#dept-members-modal .dept-member-item i', function(){
    $(this).closest('.dept-member-item').remove();
  });

  // Save department members
  $(document).on('click', '.save-dept-members', function(){
    const deptId = $('#dept-members-modal').attr('data-id');
    var ids = [];
    $('#dept-members-modal .dept-member-item').each(function(){
      ids.push($(this).data('id')*1);
    });
    
    swal_confirm.fire({ 
      title: 'Részleg tagjainak mentése?',
      text: 'A változtatások mentésre kerülnek.'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.employee.department.members.save') }}",
          method: 'POST',
          data: { 
            department_id: deptId,
            user_ids: ids 
          },
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
        })
        .done(function(resp){
          swal_loader.close();
          $('#dept-members-modal').modal('hide');
          Swal.fire({
            icon: 'success',
            title: 'Mentve',
            text: resp.message || 'Részleg tagjai frissítve.',
            timer: 2000,
            showConfirmButton: false
          });
          location.reload();
        })
        .fail(function(xhr){
          swal_loader.close();
          const errorMsg = xhr.responseJSON?.message || 'Nem sikerült menteni a részleg tagjait.';
          Swal.fire({ 
            icon: 'error', 
            title: 'Hiba', 
            text: errorMsg 
          });
        });
      }
    });
  });

  // Empty department (remove all members)
  $(document).on('click', '.trigger-empty-department', function(){
    swal_confirm.fire({
      title: 'Összes tag eltávolítása?',
      text: 'Ez az összes tagot eltávolítja a részlegből.',
      icon: 'warning'
    }).then((result) => {
      if (result.isConfirmed) {
        $('.dept-members-list').html('');
        Swal.fire({
          icon: 'info',
          title: 'Tagok törölve',
          text: 'Ne felejts el menteni a változtatásokat!',
          timer: 2000,
          showConfirmButton: false
        });
      }
    });
  });

})();
</script>
@endif