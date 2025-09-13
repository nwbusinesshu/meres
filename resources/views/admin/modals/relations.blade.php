<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="relations-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/employees.relations') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="relation-list">

        </div>
        <div class="tile tile-button trigger-new-relation">{{ __('admin/employees.add-new-relation') }}</div>
        <button class="btn btn-primary save-relation">{{ __('admin/employees.save-relation') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
function addNewRelationItem(uid, name, type){
  $('.relation-list').append(''
    +'<div class="relation-item" data-id="'+uid+'">'
    +'<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.remove-relation') }}"></i>'
    +'<div>'
    +'<p>'+name+'</p>'
    +'<select class="form-control relation-type">'
    +'<option value="colleague">{{ __('userrelationtypes.colleague') }}</option>'
    +'<option value="subordinate">{{ __('userrelationtypes.subordinate') }}</option>'
    +'</select>'
    +'</div>'
    +'</div>');
  $('.relation-type').last().val(type);
}
function initRelationsModal(uid){
   $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  $('#relations-modal').attr('data-id', uid);
  swal_loader.fire();
  $.ajax({
    url: "{{ route('admin.employee.relations') }}",
    data: { id: uid },
  })
  .done(function(response){
    $('.relation-list').html('');
    user = response.filter(item => item.target_id == uid)[0].target;
    response = response.filter(item => item.target_id != uid);

    $('.relation-list').append(''
      +'<div class="relation-item cant-remove" data-id="'+user.id+'">'
      +'<i class="fa fa-trash-alt"></i>'
      +'<div>'
      +'<p>'+user.name+'</p>'
      +'<select class="form-control relation-type" disabled readonly>'
      +'<option value="self">{{ __('userrelationtypes.self') }}</option>'
      +'</select>'
      +'</div>'
      +'</div>');

    response.forEach(item => {
      addNewRelationItem(item.target.id, item.target.name, item.type);
    });

    tippy('.relation-list [data-tippy-content]');

    swal_loader.close();

    $('#relations-modal').modal();
  });

  $(document).ready(function(){
    $('.trigger-new-relation').click(function(){
      var exceptArray = [];
      $('#relations-modal .relation-item').each(function(){
        exceptArray.push($(this).attr('data-id')*1);
      });
      openSelectModal({
        title: "{{ __('select.title') }}",
        parentSelector: '#relations-modal',
        ajaxRoute: "{{ route('admin.employee.all') }}",
        itemData: function(item){ return {
          id: item.id,
          name: item.name,
        }; },
        selectFunction: function(){
          addNewRelationItem($(this).attr('data-id'), $(this).attr('data-name'), 'colleague');
          tippy('.relation-list [data-tippy-content]');
          $('#select-modal').modal('hide');
        },
        exceptArray: exceptArray,
        emptyMessage: '{{ __('select.no-user') }}'
      });
    });

    $(document).delegate('.relation-item:not(.cant-remove) i', 'click', function(){
      $(this).parents('.relation-item').remove();
    });

    $('.save-relation').click(function(){
      swal_confirm.fire({
        title: '{{ __('admin/employees.save-relation-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();

          var relations = [];

          $('.relation-item').each(function(){
            relations.push({
              target: $(this).attr('data-id'),
              type: $(this).find('select').val()
            });
          });

          $.ajax({
          url: "{{ route('admin.employee.relations.save') }}",
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify({
            id: $('#relations-modal').attr('data-id'),
            relations: relations
          }),
          successMessage: "{{ __('admin/employees.save-relation-success') }}"
        });
        }
      });
    });
  });
}
</script>