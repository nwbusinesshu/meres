<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="ceorank-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/ceoranks.rank-settings') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-row flex">
          <div class="form-group">
            <label>{{ __('admin/ceoranks.value') }}</label>
            <input type="number" class="form-control rank-value" step="1" min="0" max="100">
          </div>
          <div class="form-group">
            <label>{{ __('admin/ceoranks.name') }}</label>
            <input type="text" class="form-control rank-name">
          </div>
        </div>
        <p>{{ __('admin/ceoranks.employee-number') }}</p>
        <div class="form-row flex">
          <div class="form-group">
            <div class="form-check">
              <input class="form-check-input has-rank-min" type="checkbox" value="" id="has-rank-min">
              <label class="form-check-label" for="has-rank-min">
                {{ __('admin/ceoranks.min') }} (%)
              </label>
            </div>
            <input type="number" class="form-control rank-min" step="1" min="1" max="100" readonly>
          </div>
          <div class="form-group">
            <div class="form-check">
              <input class="form-check-input has-rank-max" type="checkbox" value="" id="has-rank-max">
              <label class="form-check-label" for="has-rank-max">
                {{ __('admin/ceoranks.max') }} (%)
              </label>
            </div>
            <input type="number" class="form-control rank-max" step="1" min="1" max="100" readonly>
          </div>
        </div>
        <button class="btn btn-primary save-rank">{{ __('admin/ceoranks.save-rank') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
  function openCeoRankModal(rankId = null){
    swal_loader.fire();
    if(rankId == null){
      $('#ceorank-modal').attr('data-id', 0);
      $('#ceorank-modal .rank-value').val('');
      $('#ceorank-modal .rank-name').val('');
      $('#ceorank-modal .rank-max').val('');
      $('#ceorank-modal .rank-min').val('');
      $('#ceorank-modal .has-rank-min').prop('checked', false).change();
      $('#ceorank-modal .has-rank-max').prop('checked', false).change();

      swal_loader.close();
      $('#ceorank-modal').modal();
    }else{
      $('#ceorank-modal').attr('data-id', rankId);
      
      $.ajax({
        url: "{{ route('admin.ceoranks.get') }}",
        data: { id: rankId },
      })
      .done(function(response){
        $('#ceorank-modal .rank-value').val(response.value);
        $('#ceorank-modal .rank-name').val(response.name);
        $('#ceorank-modal .rank-max').val(response.max);
        $('#ceorank-modal .rank-min').val(response.min);

        if(response.max != null){
          $('#ceorank-modal .has-rank-max').prop('checked', true).change();
        }

        if(response.min != null){
          $('#ceorank-modal .has-rank-min').prop('checked', true).change();
        }

        swal_loader.close();
        $('#ceorank-modal').modal();
      });
    }
  }

  $(document).ready(function(){
    $('.has-rank-max, .has-rank-min').change(function(){
      var input = $(this).parents('.form-group').find('input[type="number"]');
      var checked = $(this).is(':checked');
      input.prop('readonly',  !checked);
      if(!checked){ input.val(''); }
    });

    $('.save-rank').click(function(){
      rankId = $('#ceorank-modal').attr('data-id');
      swal_confirm.fire({
        title: '{{ __('admin/ceoranks.save-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();
          $.ajax({
            url: "{{ route('admin.ceoranks.save') }}",
            data: {
              id: rankId,
              name:  $('.rank-name').val(),
              value:  $('.rank-value').val(),
              min:  $('.has-rank-min').is(':checked') ? $('.rank-min').val() : 0,
              max:  $('.has-rank-max').is(':checked') ? $('.rank-max').val() : 0
            },
            successMessage: '{{ __('admin/ceoranks.save-success') }}',
          });
        }
      });
    });
  });
</script>