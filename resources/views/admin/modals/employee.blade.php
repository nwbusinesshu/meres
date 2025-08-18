<div class="modal fade" tabindex="-1" role="dialog" id="employee-modal">
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
            <input type="text" class="form-control name">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>{{ __('global.email') }}</label>
            <input type="email" class="form-control email">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>{{ __('global.type') }}</label>
            <select class="form-control type">
              <option value="normal">{{ __('usertypes.normal') }}</option>
              <option value="ceo">{{ __('usertypes.ceo') }}</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-check">
            <input class="form-check-input auto-level-up" type="checkbox" id="auto-level-up">
            <label class="form-check-label" for="auto-level-up">
              {{ __('global.auto-level-up') }}
            </label>
          </div>
        </div>
        <button class="btn btn-primary trigger-submit"></button>
      </div>
    </div>
  </div>
</div>
<script>
  function openEmployeeModal(uid = null){
    swal_loader.fire();
    if(uid == null){
      $('#employee-modal').attr('data-id', 0);
      $('#employee-modal .modal-title').html('{{ __('admin/employees.new-employee') }}');
      $('#employee-modal .trigger-submit').html('{{ __('admin/employees.new-employee') }}');

      $('#employee-modal .name').val('');
      $('#employee-modal .email').val('');
      $('#employee-modal .email').prop('readonly', false);
      $('#employee-modal .type').val('normal');
      $('#employee-modal .auto-level-up').prop('checked', false);

      swal_loader.close();
      $('#employee-modal').modal();
    }else{
      $('#employee-modal').attr('data-id', uid);
      $('#employee-modal .modal-title').html('{{ __('admin/employees.modify-employee') }}');
      $('#employee-modal .trigger-submit').html('{{ __('admin/employees.modify') }}');

      $.ajax({
        url: "{{ route('admin.employee.get') }}",
        data: { id: uid },
      })
      .done(function(response){
        $('#employee-modal .name').val(response.name);
        $('#employee-modal .email').val(response.email);
        $('#employee-modal .email').prop('readonly', true);
        $('#employee-modal .type').val(response.type);
        $('#employee-modal .auto-level-up').prop('checked', response.has_auto_level_up == 1);

        swal_loader.close();
        $('#employee-modal').modal();
      });
    }
  }

  $(document).ready(function(){
    $('.trigger-submit').click(function(){
      uid = $('#employee-modal').attr('data-id');
      swal_confirm.fire({
        title: uid ? '{{ __('admin/employees.new-employee-confirm') }}' : '{{ __('admin/employees.modify-employee-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();
          $.ajax({
            url: "{{ route('admin.employee.save') }}",
            data: {
              id: uid,
              name:  $('.name').val(),
              email:  $('.email').val(),
              type:  $('.type').val(),
              autoLevelUp:  $('.auto-level-up').is(':checked') ? 1 : 0,
            },
            successMessage: uid ? "{{ __('admin/employees.new-employee-success') }}" : '{{ __('admin/employees.modify-employee-success') }}',
          });
        }
      });
    });
  });
</script>