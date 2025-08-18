<div class="modal fade" tabindex="-1" role="dialog" id="assessment-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/home.assessment') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>{{ __('admin/home.due') }}</label>
            <input type="date" class="form-control assessment-due" min="{{ date('Y-m-d',strtotime('+1 Day')) }}">
          </div>
        </div>
        <div class="tile tile-warning">
          <p>{!! __('admin/home.assessment-warning') !!}</p>
        </div>
        <button class="btn btn-primary save-assessment">{{ __('admin/home.save-assessment') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
function openAssessmentModal(id = 0){
  swal_loader.fire();
  $('#assessment-modal').attr('data-id', id ?? 0);
  $('#assessment-modal .assessment-due').val("{{ date('Y-m-d', strtotime('+7 Days')) }}");
  $('#assessment-modal .tile-warning').addClass("hidden");
  if(id==0){
    swal_loader.close();
    $('#assessment-modal .tile-warning').removeClass("hidden");
    $('#assessment-modal').modal();
  }else{
    swal_loader.fire();
    $.ajax({
      url: "{{ route('admin.assessment.get') }}",
      data: { id: id },
    })
    .done(function(response){
      $('#assessment-modal .assessment-due').val(response.due_at.split(' ')[0]);
      swal_loader.close();
      $('#assessment-modal').modal();
    });
  }
}
$(document).ready(function(){
  $('#assessment-modal .save-assessment').click(function(){
    swal_confirm.fire({
      title: '{{ __('admin/home.save-assessment-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.assessment.save') }}",
          data: {
            id: $('#assessment-modal').attr('data-id'),
            due: $('#assessment-modal .assessment-due').val()
          },
          successMessage: "{{ __('admin/home.save-assessment-success') }}",
        });
      }
    });
  });
});
</script>