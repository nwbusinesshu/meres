<div class="modal fade" tabindex="-1" role="dialog" id="bonusmalus-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/employees.change-bonusmalus') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="bonusmalus-history">

        </div>
        <div class="bonusmalus-now">
          <p>{{ __('admin/employees.bonusmalus-now') }}</p>
          <select class="form-control bonusmalus-select">
            @foreach (__('global.bonus-malus') as $key => $item)
              <option value="{{ $key }}">{{ $item }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-primary bonusmalus-submit">{{ __('admin/employees.change-bonusmalus') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
  var bonusMalus = @json(__('global.bonus-malus'));
  function openBonusMalusModal(uid){
    swal_loader.fire();
    $('#bonusmalus-modal').attr('data-id', uid);
    $.ajax({
      url: "{{ route('admin.employee.bonusmalus.get') }}",
      data: { id: uid },
    })
    .done(function(response){
      $('.bonusmalus-history').html('');

      if(response.length > 1){
        for(let i = 1; i < response.length; i++){
          $('.bonusmalus-history').prepend('<p>'+response[i].month.substring(0, 7).replace('-','.')+'.: <span>'+bonusMalus[response[i].level]+'</span></p>');
        }
      }

      $('.bonusmalus-select').val(response[0].level);

      swal_loader.close();
      $('#bonusmalus-modal').modal();
    });
  }

  $(document).ready(function(){
    $('.bonusmalus-submit').click(function(){
      uid = $('#bonusmalus-modal').attr('data-id');
      swal_confirm.fire({
        title: '{{ __('admin/employees.change-bonusmalus-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();
          $.ajax({
            url: "{{ route('admin.employee.bonusmalus.set') }}",
            data: {
              id: uid,
              level:  $('.bonusmalus-select').val(),
            },
            successMessage: '{{ __('admin/employees.change-bonusmalus-success') }}',
          });
        }
      });
    });
  });
</script>