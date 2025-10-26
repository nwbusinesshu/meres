<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="bonusmalus-modal">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">{{ __('admin/employees.change-bonusmalus') }}</h5>
          <small class="modal-subtitle text-muted" style="display: none;"></small>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
            <h6 class="text-muted mb-3">{{ __('admin/employees.bonusmalus-select-level') }}</h6>
            <div class="bonusmalus-levels">
              @php
                $bonusMalusLevels = __('global.bonus-malus');
                krsort($bonusMalusLevels); // Sort descending (highest first)
              @endphp
              
              @foreach ($bonusMalusLevels as $key => $label)
                <div class="bonusmalus-level-item" data-level="{{ $key }}">
                  {{ $label }}
                </div>
              @endforeach
            </div>
            <h6 class="text-muted mb-3">{{ __('admin/employees.bonusmalus-history') }}</h6>
            <div class="bonusmalus-history">
              <p class="text-muted font-italic">{{ __('admin/employees.bonusmalus-no-history') }}</p>
            </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('global.cancel') }}</button>
        <button class="btn btn-primary bonusmalus-submit">{{ __('global.save') }}</button>
      </div>
    </div>
  </div>
</div>

<style>
/* Bonusmalus Levels Container */
.bonusmalus-levels {
  max-height: 450px;
  overflow-y: auto;
}

/* Individual Level Item */
.bonusmalus-level-item {
  padding: 0.75rem 1rem;
  margin-bottom: 0.25rem;
  background-color: #fff;
  border: 2px solid #dee2e6;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 500;
}

.bonusmalus-level-item:hover {
  background-color: #f8f9fa;
  border-color: #adb5bd;
  transition: all 0.2s ease;

}

.bonusmalus-level-item.selected {
  background-color: #007bff;
  color: white;
  border-color: #007bff;
  transition: all 0.2s ease;
}

/* History section */
.bonusmalus-history {
  background: #f8f9fa;
  border-radius: 0.25rem;
  padding: 1rem;
  min-height: 100px;
  max-height: 450px;
  overflow-y: auto;
}

.bonusmalus-history p {
  margin-bottom: 0.5rem;
  padding: 0.5rem;
  background: white;
  border-radius: 0.25rem;
  border-left: 3px solid #007bff;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.9rem;
}

.bonusmalus-history p span {
  font-weight: 600;
}

.bonusmalus-history p:last-child {
  margin-bottom: 0;
}
</style>
<script>
  var bonusMalus = @json(__('global.bonus-malus'));
  var selectedLevel = null;
  
  function initBonusMalusModal(uid, userName){
    swal_loader.fire();
    $('#bonusmalus-modal').attr('data-id', uid);
    
    // ✅ ADDED: Show user name in subtitle if provided
    if (userName) {
      $('#bonusmalus-modal .modal-subtitle').text(userName).show();
    } else {
      $('#bonusmalus-modal .modal-subtitle').hide();
    }
    
    $.ajax({
      url: "{{ route('admin.employee.bonusmalus.get') }}",
      data: { id: uid },
    })
    .done(function(response){
      $('.bonusmalus-history').html('');
      
      if(response.length > 1){
        for(let i = 1; i < response.length; i++){
          const date = response[i].month.substring(0, 7).replace('-', '.');
          const level = bonusMalus[response[i].level];
          $('.bonusmalus-history').append(
            '<p><span>' + date + '</span><span>' + level + '</span></p>'
          );
        }
      } else {
        $('.bonusmalus-history').html('<p class="text-muted font-italic">{{ __('admin/employees.bonusmalus-no-history') }}</p>');
      }
      
      // SET SELECTION BEFORE OPENING MODAL
      selectedLevel = response[0].level;
      $('.bonusmalus-level-item').removeClass('selected');
      const $selected = $('.bonusmalus-level-item[data-level="' + response[0].level + '"]');
      $selected.addClass('selected');
      
      swal_loader.close();
      
      // OPEN MODAL FIRST
      $('#bonusmalus-modal').modal();
      
      // SCROLL AFTER MODAL IS FULLY SHOWN
      $('#bonusmalus-modal').one('shown.bs.modal', function () {
        if ($selected[0]) {
          $selected[0].scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' });
        }
      });
    });
  }
  
  $(document).ready(function(){
    $(document).on('click', '.bonusmalus-level-item', function(){
      selectedLevel = $(this).attr('data-level');
      $('.bonusmalus-level-item').removeClass('selected');
      $(this).addClass('selected');
    });
    
    $('.bonusmalus-submit').click(function(){
      const uid = $('#bonusmalus-modal').attr('data-id');
      
      if(selectedLevel === null) {
        Swal.fire({
          icon: 'warning',
          title: '{{ __('global.swal-warning') }}',
          text: '{{ __('admin/employees.bonusmalus-select-warning') }}'
        });
        return;
      }
      
      swal_confirm.fire({
        title: '{{ __('admin/employees.change-bonusmalus-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();
          $.ajax({
            url: "{{ route('admin.employee.bonusmalus.set') }}",
            data: {
              id: uid,
              level: selectedLevel,
            },
            successMessage: '{{ __('admin/employees.change-bonusmalus-success') }}',
          });
        }
      });
    });
  });
</script>