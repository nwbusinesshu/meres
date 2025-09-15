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
        {{-- Members list --}}
        <div class="dept-members-list">
          {{-- Dynamic content will be populated here --}}
        </div>

        {{-- Action buttons --}}
        <div class="d-flex flex-column gap-2" style="margin-top: 1rem;">
          {{-- Add member button --}}
          <div class="tile tile-button trigger-add-member">
            <i class="fa fa-user-plus"></i> Új tag hozzáadása
          </div>
          
          {{-- Action buttons row --}}
          <div class="d-flex gap-2">
            <button class="btn btn-primary save-dept-members flex-fill">
              <i class="fa fa-save"></i> Mentés
            </button>
            <button class="btn btn-danger trigger-empty-department flex-fill">
              <i class="fa fa-users-slash"></i> Mindenki eltávolítása
            </button>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

<style>
/* Department members modal styling */
.dept-members-list {
    min-height: 120px;
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.75rem;
    margin-bottom: 1rem;
    background-color: #f8f9fa;
}

.dept-member-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    background-color: white;
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
}

.dept-member-item:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

.dept-member-item:last-child {
    margin-bottom: 0;
}

.dept-member-item i {
    color: #dc3545;
    cursor: pointer;
    padding: 0.375rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease;
}

.dept-member-item i:hover {
    background-color: #f8d7da;
    color: #721c24;
}

.dept-member-item div {
    flex: 1;
}

.dept-member-item p {
    margin: 0;
    font-weight: 500;
}

.dept-members-list:empty::after {
    content: "Nincs tag a részlegben. Használd az 'Új tag hozzáadása' gombot tagok hozzáadásához.";
    display: block;
    text-align: center;
    padding: 2rem;
    color: #6c757d;
    font-style: italic;
    background-color: white;
    border: 2px dashed #dee2e6;
    border-radius: 0.25rem;
}

/* Button styling improvements */
.tile.tile-button.trigger-add-member {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border: none;
    padding: 1rem;
    text-align: center;
    font-weight: 600;
    transition: all 0.3s ease;
}

.tile.tile-button.trigger-add-member:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.btn.save-dept-members {
    font-weight: 600;
    padding: 0.75rem 1.5rem;
}

.btn.trigger-empty-department {
    font-weight: 600;
    padding: 0.75rem 1.5rem;
}

/* Gap utility for older Bootstrap versions */
.gap-2 > * + * {
    margin-left: 0.5rem;
}

.flex-fill {
    flex: 1 1 auto;
}

/* Modal specific improvements */
#dept-members-modal .modal-body {
    padding: 1.5rem;
}

#dept-members-modal .modal-header {
    background: linear-gradient(135deg, #007bff, #6610f2);
    color: white;
    border-bottom: none;
}

#dept-members-modal .close {
    color: white;
    text-shadow: none;
    opacity: 0.8;
}

#dept-members-modal .close:hover {
    opacity: 1;
}
</style>

<script>
// Initialize department members modal functions
(function(){
  // Add member item to list
  function addMemberItem(uid, name, email){
    const mail = email ? ' <span class="text-muted small">(' + email + ')</span>' : '';
    $('.dept-members-list').append(
      '<div class="dept-member-item" data-id="'+uid+'">' +
        '<i class="fa fa-trash-alt" data-tippy-content="Eltávolítás"></i>' +
        '<div>' +
          '<p>'+name+mail+'</p>' +
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
      text: 'A változtatások mentésre kerülnek.',
      icon: 'question'
    }).then(function(r){
      if (!r.isConfirmed) return;
      
      swal_loader.fire();
      $.ajax({
        url: "{{ route('admin.employee.department.members.save') }}",
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ 
          department_id: deptId, 
          user_ids: ids 
        }),
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
          swal_loader.close();
          $('#dept-members-modal').modal('hide');
          
          Swal.fire({ 
            icon: 'success', 
            title: 'Mentve',
            text: 'Részleg tagjai sikeresen frissítve.',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            window.location.reload();
          });
        },
        error: function(xhr) {
          swal_loader.close();
          const errorMsg = xhr.responseJSON?.message || 'Nem sikerült menteni a változtatásokat.';
          Swal.fire({ 
            icon: 'error', 
            title: 'Hiba', 
            text: errorMsg
          });
        }
      });
    });
  });

  // FIXED: Empty department button
  $(document).on('click', '.trigger-empty-department', function(){
    const deptId = $('#dept-members-modal').attr('data-id');
    const memberCount = $('#dept-members-modal .dept-member-item').length;
    
    if (memberCount === 0) {
      Swal.fire({
        icon: 'info',
        title: 'Nincs mit eltávolítani',
        text: 'A részlegben jelenleg nincsenek tagok.'
      });
      return;
    }

    Swal.fire({
      icon: 'warning',
      title: 'Biztos vagy benne?',
      text: `Minden tag (${memberCount} fő) azonnal eltávolításra kerül a részlegből. A felhasználók megmaradnak a rendszerben, csak nem lesznek részleg tagjai.`,
      showCancelButton: true,
      confirmButtonText: 'Igen, mindenkit eltávolít',
      cancelButtonText: 'Mégse',
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        // Call server immediately with empty array
        $.ajax({
          url: "{{ route('admin.employee.department.members.save') }}",
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify({ 
            department_id: deptId, 
            user_ids: [] // Empty array to remove everyone
          }),
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          },
          success: function(response) {
            swal_loader.close();
            $('#dept-members-modal').modal('hide');
            
            // Set toast message for after page reload
            sessionStorage.setItem('dept_empty_success_toast', 'Minden tag eltávolításra került a részlegből.');
            
            // Reload page
            window.location.reload();
          },
          error: function(xhr) {
            swal_loader.close();
            const errorMsg = xhr.responseJSON?.message || 'Hiba történt az eltávolítás során.';
            Swal.fire({
              icon: 'error',
              title: 'Hiba',
              text: errorMsg
            });
          }
        });
      }
    });
  });

})();
</script>
@endif