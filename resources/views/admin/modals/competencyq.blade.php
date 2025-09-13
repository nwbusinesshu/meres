<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="competencyq-modal">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group full">
            <label>{{ __('admin/competencies.question') }}</label>
            <textarea class="form-control question" rows="4" maxlength="1024" style="resize: none;"></textarea>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group full">
            <label>{{ __('admin/competencies.question-self') }}</label>
            <textarea class="form-control question-self" rows="4" maxlength="1024" style="resize: none;"></textarea>
          </div>
        </div>
        <div class="form-row flex">
          <div class="form-group">
            <label>{{ __('admin/competencies.min-label') }}</label>
            <input type="text" class="form-control min-label"/>
          </div>
          <div class="form-group">
            <label>{{ __('admin/competencies.max-label') }}</label>
            <input type="text" class="form-control max-label"/>
          </div>
        </div>
        <div class="form-row centered">
          <div class="form-group">
            <label>{{ __('admin/competencies.scale') }}</label>
            <input class="form-control scale" type="number" step="1" min="3" max="10"/>
          </div>
        </div>
        <button class="btn btn-primary save-competencyq">{{ __('admin/competencies.question-save') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
function openCompetencyQModal(id = null, compId = null){
  swal_loader.fire();
  $('#competencyq-modal').attr('data-id', id ?? 0);
  $('#competencyq-modal').attr('data-compid', compId ?? 0);
  $('#competencyq-modal .modal-title').html(id == null ? '{{ __('admin/competencies.create-question') }}' : '{{ __('admin/competencies.question-modify') }}')
  if(id){
    swal_loader.fire();
    $.ajax({
      url: "{{ route('admin.competency.question.get') }}",
      data: { id: id },
    })
    .done(function(response){
      $('#competencyq-modal .question').val(response.question);
      $('#competencyq-modal .question-self').val(response.question_self);
      $('#competencyq-modal .min-label').val(response.min_label);
      $('#competencyq-modal .max-label').val(response.max_label);
      $('#competencyq-modal .scale').val(response.max_value);

      swal_loader.close();
      $('#competencyq-modal').modal();
    });
  }else{
    $('#competencyq-modal .question').val('');
    $('#competencyq-modal .question-self').val('');
    $('#competencyq-modal .min-label').val('');
    $('#competencyq-modal .max-label').val('');
    $('#competencyq-modal .scale').val(7);

    swal_loader.close();
    $('#competencyq-modal').modal();
  }
}
$(document).ready(function(){
  $('#competencyq-modal .save-competencyq').click(function(){
    swal_confirm.fire({
      title: '{{ __('admin/competencies.question-save-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.competency.question.save') }}",
          data: {
            id: $('#competencyq-modal').attr('data-id'),
            compId: $('#competencyq-modal').attr('data-compid'),
            question: $('#competencyq-modal .question').val(),
            questionSelf:  $('#competencyq-modal .question-self').val(),
            minLabel:  $('#competencyq-modal .min-label').val(),
            maxLabel:  $('#competencyq-modal .max-label').val(),
            scale:  $('#competencyq-modal .scale').val() 
          },
          successMessage: "{{ __('admin/competencies.question-save-success') }}",
        });
      }
    });
  });
});
</script>