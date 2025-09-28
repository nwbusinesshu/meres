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

<style>
/* Department members modal styling - consistent with user-competency modal */
#dept-members-modal .modal-body {
  display: flex;
  flex-direction: column;
  gap: 1em;
}

#dept-members-modal .btn {
  width: 100%;
}

#dept-members-modal .dept-members-list {
  display: flex;
  flex-direction: column;
  gap: 1em;
  max-height: 300px;
  height: 300px;
  overflow-y: scroll;
  padding-right: 0.5em;
}

/* Member items styled like competency-item */
.dept-member-item {
  display: flex;
  gap: 1em;
  border-bottom: 3px solid var(--info);
  padding-bottom: 0.5em;
}

.dept-member-item i {
  display: flex;
  font-size: 1.2em;
  color: var(--danger);
  justify-content: center;
  align-items: center;
  cursor: pointer;
}

.dept-member-item .item-content {
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 0.5em;
}

.dept-member-item .item-content p {
  font-weight: bold;
  margin: 0;
}

.dept-member-item .item-content span {
  font-size: 0.9em;
  font-weight: normal;
  font-style: italic;
  color: var(--silver_chalice);
}

/* Empty state styling - simple like competency modal */
.dept-members-list:empty::after {
  content: "Nincs tag a részlegben. Használd az 'Új tag hozzáadása' gombot.";
  display: block;
  text-align: center;
  padding: 2rem;
  color: var(--silver_chalice);
  font-style: italic;
  font-weight: bold;
}

</style>

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
          // Refresh the page to show updates
          setTimeout(() => window.location.reload(), 1000);
        })
        .fail(function(xhr) {
          swal_loader.close();
          const errorMsg = xhr.responseJSON?.message || 'Nem sikerült menteni a változtatásokat.';
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
      title: 'Minden tag eltávolítása?',
      text: 'Ez a művelet eltávolítja az összes tagot a részlegből.',
      icon: 'warning'
    }).then((result) => {
      if (result.isConfirmed) {
        $('.dept-members-list').html('');
        Swal.fire({
          icon: 'success',
          title: 'Eltávolítva',
          text: 'Minden tag eltávolítva. Ne felejtsd el menteni!',
          timer: 2000,
          showConfirmButton: false
        });
      }
    });
  });

})();
</script>
@endif