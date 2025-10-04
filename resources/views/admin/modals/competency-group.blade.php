{{-- resources/views/admin/modals/competency-group.blade.php --}}
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="competency-group-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/competencies.create-competency-group') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        {{-- Group name input --}}
        <div class="form-group">
          <label for="group-name-input">{{ __('admin/competencies.group-name') }}</label>
          <input type="text" class="form-control group-name-input" placeholder="{{ __('admin/competencies.enter-group-name') }}">
        </div>

        {{-- Selected competencies list --}}
        <div class="competency-group-list">
          {{-- Dynamic content will be populated here --}}
        </div>

        {{-- Action buttons (consistent with departmentuser modal pattern) --}}
        <div class="tile tile-button trigger-add-competencies">{{ __('admin/competencies.add-competencies') }}</div>
        

      </div>

      <div class="modal-footer">
        <button class="btn btn-primary save-competency-group">{{ __('admin/competencies.save-group') }}</button>
      </div>

    </div>
  </div>
</div>

<style>
/* Minimal modal-specific styling - main layout handled by admin.employees.css */
#competency-group-modal .form-group label {
  font-weight: 600;
  margin-bottom: 0.5rem;
  display: block;
}

#competency-group-modal .form-control {
  width: 100%;
}
</style>

<script>
// FIXED: Move addGroupCompetencyItem function to global scope so it can be accessed by initEditCompetencyGroupModal
window.addGroupCompetencyItem = function(competencyId, competencyName, competencyDescription = null){
  const descriptionDisplay = competencyDescription ? 
    `<span>${competencyDescription}</span>` : '';
  
  $('.competency-group-list').append(`
    <div class="group-competency-item" data-id="${competencyId}">
      <i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/competencies.remove-competency') }}"></i>
      <div class="item-content">
        <p>${competencyName}</p>
        ${descriptionDisplay}
      </div>
    </div>
  `);
};

// Initialize competency group modal functions
(function(){
  
  // Remove competency from group
  $(document).on('click', '.group-competency-item i', function(){
    $(this).closest('.group-competency-item').remove();
  });

  // Open competency selection modal
  $(document).on('click', '.trigger-add-competencies', function(){
    var exceptArray = [];
    $('#competency-group-modal .group-competency-item').each(function(){
      exceptArray.push($(this).attr('data-id') * 1);
    });
    
    // Use the enhanced multi-select modal
    openSelectModal({
      title: "{{ __('admin/competencies.select-competencies') }}",
      parentSelector: '#competency-group-modal',
      ajaxRoute: "{{ route('admin.competency.all') }}",
      itemData: function(item){ 
        return {
          id: item.id,
          name: item.name,
          top: null,
          bottom: item.description || null
        };
      },
      selectFunction: function(){
        const competencyId = $(this).attr('data-id');
        const competencyName = $(this).attr('data-name');
        const description = $(this).find('.item-content span').text() || null;
        
        // Check if not already added
        if ($('#competency-group-modal .group-competency-item[data-id="'+competencyId+'"]').length === 0) {
          window.addGroupCompetencyItem(competencyId, competencyName, description);
          if (window.tippy) tippy('.competency-group-list [data-tippy-content]');
        }
      },
      exceptArray: exceptArray,
      multiSelect: true,
      emptyMessage: '{{ __('admin/competencies.no-competencies') }}'
    });
  });

  // Save competency group
  $(document).on('click', '.save-competency-group', function(){
    const groupId = $('#competency-group-modal').attr('data-id');
    const groupName = $('.group-name-input').val().trim();
    
    // Validation
    if (!groupName) {
      swal.fire({
        icon: 'warning',
        title: '{{ __('global.validation-error') }}',
        text: '{{ __('admin/competencies.group-name-required') }}'
      });
      return;
    }
    
    var competencyIds = [];
    $('#competency-group-modal .group-competency-item').each(function(){
      competencyIds.push($(this).data('id') * 1);
    });
    
    if (competencyIds.length === 0) {
      swal.fire({
        icon: 'warning',
        title: '{{ __('global.validation-error') }}',
        text: '{{ __('admin/competencies.select-at-least-one-competency') }}'
      });
      return;
    }
    
    swal_confirm.fire({
      title: groupId ? '{{ __('admin/competencies.update-group-confirm') }}' : '{{ __('admin/competencies.create-group-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        
        $.ajax({
          url: "{{ route('admin.competency-group.save') }}",
          method: 'POST',
          data: {
            id: groupId,
            name: groupName,
            competency_ids: competencyIds
          },
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          },
          success: function(response) {
            swal_loader.close();
            swal.fire({
              icon: 'success',
              title: '{{ __('global.success') }}',
              text: groupId ? 
                '{{ __('admin/competencies.group-updated-success') }}' : 
                '{{ __('admin/competencies.group-created-success') }}',
              timer: 2000,
              showConfirmButton: false
            }).then(() => {
              $('#competency-group-modal').modal('hide');
              location.reload(); // Reload to show the new group
            });
          },
          error: function(xhr) {
            swal_loader.close();
            // Handle validation errors
            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
              const errors = Object.values(xhr.responseJSON.errors).flat();
              swal.fire({
                icon: 'error',
                title: '{{ __('global.validation-error') }}',
                html: errors.join('<br>')
              });
            } else {
              swal.fire({
                icon: 'error',
                title: '{{ __('global.error') }}',
                text: xhr.responseJSON?.message || '{{ __('global.error-occurred') }}'
              });
            }
          }
        });
      }
    });
  });

})();

// FIXED: Initialize create new group modal - now uses global function
window.initCreateCompetencyGroupModal = function() {
  $('#competency-group-modal').removeAttr('data-id');
  $('#competency-group-modal .modal-title').text("{{ __('admin/competencies.create-competency-group') }}");
  $('.group-name-input').val('');
  $('.competency-group-list').html('');
  $('#competency-group-modal').modal('show');
};

// FIXED: Initialize edit group modal - now uses global function
window.initEditCompetencyGroupModal = function(groupId, groupName, competencies) {
  $('#competency-group-modal').attr('data-id', groupId);
  $('#competency-group-modal .modal-title').text("{{ __('admin/competencies.edit-competency-group') }}");
  $('.group-name-input').val(groupName);
  $('.competency-group-list').html('');
  
  // FIXED: Add existing competencies to the list using the global function
  if (competencies && competencies.length > 0) {
    competencies.forEach(function(comp) {
      window.addGroupCompetencyItem(comp.id, comp.name, comp.description || '');
    });
  }
  
  $('#competency-group-modal').modal('show');
};
</script>