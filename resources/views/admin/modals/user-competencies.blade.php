<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="user-competencies-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/employees.competencies') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="competency-list">

        </div>
        <div class="tile tile-button trigger-new-competency">{{ __('admin/employees.add-new-competencies') }}</div>
        <button class="btn btn-primary save-competency">{{ __('admin/employees.save-competencies') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
function addNewCompetencyItem(uid, name){
  $('.competency-list').append(''
    +'<div class="competency-item" data-id="'+uid+'">'
    +'<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.remove-competency') }}"></i>'
    +'<div>'
    +'<p>'+name+'</p>'
    +'</select>'
    +'</div>'
    +'</div>');
}
function initCompetenciesModal(uid){
  $('#user-competencies-modal').attr('data-id', uid);
  swal_loader.fire();
  $.ajax({
    url: "{{ route('admin.employee.competencies') }}",
    data: { id: uid },
  })
  .done(function(response){
    $('.competency-list').html('');

    response.forEach(item => {
      addNewCompetencyItem(item.id, item.name);
    });


    tippy('.competency-list [data-tippy-content]');

    swal_loader.close();

    $('#user-competencies-modal').modal();
  });

  $(document).ready(function(){
    $('.trigger-new-competency').click(function(){
      var exceptArray = [];
      $('#user-competencies-modal .competency-item').each(function(){
        exceptArray.push($(this).attr('data-id')*1);
      });
      openSelectModal({
        title: "{{ __('admin/employees.select-competency') }}",
        parentSelector: '#user-competencies-modal',
        ajaxRoute: "{{ route('admin.competency.all') }}",
        itemData: function(item){ return {
          id: item.id,
          name: item.name,
        }; },
        selectFunction: function(){
          addNewCompetencyItem($(this).attr('data-id'), $(this).attr('data-name'));
          tippy('.competency-list [data-tippy-content]');
          $('#select-modal').modal('hide');
        },
        exceptArray: exceptArray,
        emptyMessage: '{{ __('admin/competencies.no-competency') }}'
      });
    });

    $(document).delegate('.competency-item:not(.cant-remove) i', 'click', function(){
      $(this).parents('.competency-item').remove();
    });

    $('.save-competency').click(function(){
      swal_confirm.fire({
        title: '{{ __('admin/employees.save-competencies-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();

          var comps = [];

          $('.competency-item').each(function(){
            comps.push($(this).attr('data-id')*1);
          });

          $.ajax({
            url: "{{ route('admin.employee.competencies.save') }}",
            data: {
              id: $('#user-competencies-modal').attr('data-id'),
              competencies: comps
            },
            successMessage: "{{ __('admin/employees.save-competencies-success') }}",
          });
        }
      });
    });
  });
}
</script>