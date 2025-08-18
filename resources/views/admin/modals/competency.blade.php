<div class="modal fade" tabindex="-1" role="dialog" id="competency-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>{{ __('global.name') }}</label>
            <input type="text" class="form-control competency-name" >
          </div>
        </div>
        <button class="btn btn-primary save-competency"></button>
      </div>
    </div>
  </div>
</div>
<script>
function openCompetencyModal(id = null, name = null){
  swal_loader.fire();
  $('#competency-modal').attr('data-id', id ?? 0);
  $('#competency-modal .modal-title').html(id == null ? '{{ __('admin/competencies.create-competency') }}' : '{{ __('admin/competencies.modify-competency') }}')
  $('#competency-modal .save-competency').html('{{ __('admin/competencies.save-competency') }}');
  $('#competency-modal .competency-name').val(name ?? '');
  swal_loader.close();
  $('#competency-modal').modal();
}
$(document).ready(function(){
  $('#competency-modal .save-competency').click(function(){
    swal_confirm.fire({
      title: '{{ __('admin/competencies.save-competency-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.competency.save') }}",
          data: {
            id: $('#competency-modal').attr('data-id'),
            name: $('#competency-modal .competency-name').val()
          },
          successMessage: "{{ __('admin/competencies.save-competency-success') }}",
        });
      }
    });
  });
});
</script>