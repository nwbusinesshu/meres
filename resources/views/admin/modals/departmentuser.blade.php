@if(!empty($enableMultiLevel) && $enableMultiLevel)
<div class="modal fade" tabindex="-1" role="dialog" id="dept-members-modal">
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

        <div class="tile tile-button trigger-add-member" style="margin-top:.5rem;">
          Új tag hozzáadása
        </div>

        <button class="btn btn-primary save-dept-members" style="margin-top:.5rem;">
          Mentés
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
    const deptId = $(this).closest('tr').data('id');
    if (!deptId) return;
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
</script>

@endif
