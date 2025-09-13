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

        <div class="dept-members-list"><!-- ide töltjük a tagokat --></div>

        <div class="d-flex" style="gap: 0.5rem; margin-top: 0.5rem;">
          <div class="tile tile-button trigger-add-member" style="flex: 1;">
            Új tag hozzáadása
          </div>
        </div>

        <button class="btn btn-primary save-dept-members" style="margin-top:.5rem;">
          Mentés
        </button>
        <button class="btn btn-danger trigger-empty-department" style="flex: 1;">
            <i class="fa fa-users-slash"></i> Mindenki eltávolítása
          </button>

      </div>

    </div>
  </div>
</div>
<script>
(function(){
  // listába 1 elem hozzáadása
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

  // modal init
  function initDeptMembersModal(deptId){
    $('#dept-members-modal').attr('data-id', deptId);

    swal_loader.fire();
    $.ajax({
      url: "{{ route('admin.employee.department.members') }}",
      method: 'POST',
      data: { department_id: deptId },
    })
    .done(function(resp){
      $('.dept-members-list').html('');
      (resp.members || []).forEach(function(m){
        addMemberItem(m.id, m.name, m.email);
      });
      tippy('.dept-members-list [data-tippy-content]');
      swal_loader.close();
      $('#dept-members-modal').modal();
    });
  }

  // gomb a táblában
  $(document).on('click', '.dept-members', function(){
    const deptId = getDeptIdFromAny(this);
    if (!deptId) return console.warn('Nincs department ID.');
    initDeptMembersModal(deptId);
  });

  // új tag hozzáadása – select modal újrahasznosítása
  $(document).on('click', '.trigger-add-member', function(){
    const deptId = $('#dept-members-modal').attr('data-id');

    // except: akik már a listában vannak
    var except = [];
    $('#dept-members-modal .dept-member-item').each(function(){
      except.push($(this).data('id')*1);
    });

    openSelectModal({
      title: "Dolgozó kiválasztása",
      parentSelector: '#dept-members-modal',
      ajaxRoute: "{{ route('admin.employee.department.eligible') }}?department_id="+deptId,
      itemData: function(item){ return {
        id: item.id,
        name: item.name,
        top: null,
        bottom: item.email || null
      };},
      selectFunction: function(){
        const uid = $(this).attr('data-id');
        const name = $(this).attr('data-name');
        const email = $(this).find('span').last().text().replace(/[()]/g,'') || '';
        // ha már szerepel, ne duplikáljuk
        if ($('#dept-members-modal .dept-member-item[data-id="'+uid+'"]').length === 0) {
          addMemberItem(uid, name, email);
          tippy('.dept-members-list [data-tippy-content]');
        }
        $('#select-modal').modal('hide');
      },
      exceptArray: except,
      emptyMessage: 'Nincs választható dolgozó'
    });
  });

  // NEW: Remove all members button
  $(document).on('click', '.trigger-remove-all-members', function(){
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
      text: `Minden tag (${memberCount} fő) eltávolításra kerül a részlegből. A felhasználók megmaradnak, csak nem lesznek részleg tagjai.`,
      showCancelButton: true,
      confirmButtonText: 'Igen, mindenkit eltávolít',
      cancelButtonText: 'Mégse',
      confirmButtonColor: '#d33'
    }).then((result) => {
      if (result.isConfirmed) {
        $('.dept-members-list').html('');
        Swal.fire({
          icon: 'success',
          title: 'Tagok eltávolítva',
          text: 'Minden tag eltávolításra került. Ne felejts el menteni!',
          timer: 2000,
          showConfirmButton: false
        });
      }
    });
  });

  // eltávolítás a listából (csak UI, mentéskor érvényesül)
  $(document).on('click', '#dept-members-modal .dept-member-item i', function(){
    $(this).closest('.dept-member-item').remove();
  });

  // mentés (készlet-alapú)
  $(document).on('click', '.save-dept-members', function(){
    const deptId = $('#dept-members-modal').attr('data-id');
    var ids = [];
    $('#dept-members-modal .dept-member-item').each(function(){
      ids.push($(this).data('id')*1);
    });

    swal_confirm.fire({ title: 'Részleg tagjainak mentése?' })
      .then(function(r){
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
          successMessage: 'Mentve',
        });
      });
  });

})();

// NEW: Department Delete Functionality
$(document).ready(function(){
  $(document).on('click', '.dept-remove', function(){
    const deptId = getDeptIdFromAny(this);
    if (!deptId) return console.warn('Nincs department ID.');

    const $deptBlock = $(this).closest('.dept-block');
    const deptName = $deptBlock.find('.dept-title').text();
    const memberCount = $deptBlock.find('.badge.count').text() || '0';
    const hasManager = $deptBlock.find('.user-row--manager').length > 0;

    // Check if department has members or manager
    const hasUsers = parseInt(memberCount) > 0 || hasManager;

    let confirmText = `Biztosan törlöd a "${deptName}" részleget?`;
    if (hasUsers) {
      confirmText += `\n\nA részleg nem üres (${memberCount} tag${hasManager ? ' + vezető' : ''}). ` +
                     'Törlés előtt minden felhasználó eltávolításra kerül a részlegből, de megmaradnak a rendszerben.';
    }

    Swal.fire({
      icon: 'warning',
      title: 'Részleg törlése',
      text: confirmText,
      showCancelButton: true,
      confirmButtonText: 'Igen, törölje',
      cancelButtonText: 'Mégse',
      confirmButtonColor: '#d33'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        fetch("{{ route('admin.employee.department.delete') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          },
          body: JSON.stringify({ id: deptId })
        })
        .then(async r => {
          if (!r.ok) {
            const j = await r.json().catch(()=>({}));
            throw new Error(j?.message || ('HTTP ' + r.status));
          }
          return r.json();
        })
        .then(() => {
          Swal.fire({ 
            icon:'success', 
            title:'Sikeres törlés', 
            text: `A "${deptName}" részleg törölve lett.` 
          }).then(() => window.location.reload());
        })
        .catch(err => {
          swal_loader.close();
          Swal.fire({
            icon: 'error',
            title: 'Hiba történt',
            text: String(err.message || err)
          });
        });
      }
    });
  });
});
</script>

@endif