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
        <button class="btn btn-primary save-competency-group">{{ __('admin/competencies.save-group') }}</button>

      </div>

    </div>
  </div>
</div>

<style>
/* Competency group modal styling - consistent with departmentuser modal */
#competency-group-modal .modal-body {
  display: flex;
  flex-direction: column;
  gap: 1em;
}

#competency-group-modal .btn {
  width: 100%;
}

#competency-group-modal .competency-group-list {
  display: flex;
  flex-direction: column;
  gap: 1em;
  max-height: 300px;
  height: 300px;
  overflow-y: scroll;
  padding-right: 0.5em;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  padding: 1rem;
}

/* Competency items styled like dept-member-item */
.group-competency-item {
  display: flex;
  gap: 1em;
  border-bottom: 3px solid var(--info);
  padding-bottom: 0.5em;
}

.group-competency-item i {
  display: flex;
  font-size: 1.2em;
  color: var(--danger);
  justify-content: center;
  align-items: center;
  cursor: pointer;
}

.group-competency-item .item-content {
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 0.5em;
}

.group-competency-item .item-content p {
  font-weight: bold;
  margin: 0;
}

.group-competency-item .item-content span {
  font-size: 0.9em;
  font-weight: normal;
  font-style: italic;
  color: var(--silver_chalice);
}

/* Empty state styling */
.competency-group-list:empty::after {
  content: "{{ __('admin/competencies.no-competencies-selected') }}";
  display: block;
  text-align: center;
  padding: 2rem;
  color: var(--silver_chalice);
  font-style: italic;
  font-weight: bold;
}

/* Form styling */
#competency-group-modal .form-group {
  margin-bottom: 1rem;
}

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
          bottom: item.description || null
        }; 
      },
      selectFunction: function(){
        // This function is called for each selected item in multi-select mode
        const selectedId = $(this).attr('data-id');
        const selectedName = $(this).attr('data-name');
        const selectedDescription = $(this).find('.item-content span').last().text() || null;
        
        // Check if competency already exists to prevent duplicates
        if ($('#competency-group-modal .group-competency-item[data-id="' + selectedId + '"]').length === 0) {
          // FIXED: Now using the global function
          window.addGroupCompetencyItem(selectedId, selectedName, selectedDescription);
        }
      },
      exceptArray: exceptArray,
      exceptFunction: function(item){
        return !exceptArray.includes(item.id);
      },
      emptyMessage: "{{ __('admin/competencies.no-available-competencies') }}",
      multiSelect: true
    });
  });

  // Save competency group
  $(document).on('click', '.save-competency-group', function(){
    const groupName = $('.group-name-input').val().trim();
    
    if (!groupName) {
      swal.fire({
        icon: 'warning',
        title: '{{ __('admin/competencies.group-name-required') }}',
        text: '{{ __('admin/competencies.please-enter-group-name') }}'
      });
      return;
    }

    const competencyIds = [];
    $('#competency-group-modal .group-competency-item').each(function(){
      competencyIds.push($(this).attr('data-id') * 1);
    });

    if (competencyIds.length === 0) {
      swal.fire({
        icon: 'warning', 
        title: '{{ __('admin/competencies.no-competencies-selected') }}',
        text: '{{ __('admin/competencies.please-select-competencies') }}'
      });
      return;
    }

    swal_loader.fire();

    const isEdit = $('#competency-group-modal').attr('data-id');
    
    $.ajax({
      url: "{{ route('admin.competency-group.save') }}",
      method: 'POST',
      data: {
        id: isEdit || null,
        name: groupName,
        competency_ids: competencyIds,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        if (response.ok) {
          swal_loader.close();
          
          // Show success message
          swal.fire({
            icon: 'success',
            title: isEdit ? 
              '{{ __('admin/competencies.group-updated-success') }}' : 
              '{{ __('admin/competencies.group-created-success') }}',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            $('#competency-group-modal').modal('hide');
            location.reload(); // Reload to show the new group
          });
        }
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