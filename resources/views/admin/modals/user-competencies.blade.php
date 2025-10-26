{{-- resources/views/admin/modals/user-competencies.blade.php --}}
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="user-competencies-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">{{ __('admin/employees.competencies') }}</h5>
          <small class="modal-subtitle text-muted" style="display: none;"></small>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        {{-- Single unified competency list --}}
        <div class="competency-list">
          {{-- All competencies populated dynamically --}}
        </div>

        <div class="tile tile-button trigger-new-competency">{{ __('admin/employees.add-new-competencies') }}</div>
        
      </div>
      <div class="modal-footer">
                <button class="btn btn-primary save-competency">{{ __('admin/employees.save-competencies') }}</button>
       </div>
    </div>
  </div>
</div>

<style>
/* Simplified single list design */
#user-competencies-modal .modal-body {
  display: flex;
  flex-direction: column;
  gap: 1em;
  height: 100%;
}

#user-competencies-modal .competency-list {
  display: flex;
  flex-direction: column;
  gap: 0.75em;
  flex-grow: 1;
  max-height: 60vh;
  overflow-y: auto;
  padding: 0.5em;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
}

/* Empty state message */
#user-competencies-modal .competency-list:empty::after {
  content: '{{ __("admin/employees.no-competencies-added") }}';
  display: block;
  text-align: center;
  padding: 2rem;
  color: #9ca3af;
  font-style: italic;
}

/* Competency item type styling */
#user-competencies-modal .competency-item.from-group {
  background: white;
  border-left: 4px solid #3b82f6;
  padding: 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

#user-competencies-modal .competency-item.from-group i {
  color: #9ca3af;
  cursor: not-allowed;
  opacity: 0.5;
}

#user-competencies-modal .competency-item.manual {
  background: white;
  border-left: 4px solid #10b981;
  padding: 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

#user-competencies-modal .competency-item.manual i {
  color: #dc2626;
  cursor: pointer;
}

#user-competencies-modal .competency-item.both-sources {
  background: white;
  border-left: 4px solid #f59e0b;
  padding: 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

#user-competencies-modal .competency-item.both-sources i {
  color: #dc2626;
  cursor: pointer;
}

/* Common item styling */
#user-competencies-modal .competency-item {
  display: flex;
  gap: 1em;
  align-items: center;
  transition: all 0.2s ease;
}

#user-competencies-modal .competency-item i {
  display: flex;
  font-size: 1.2em;
  justify-content: center;
  align-items: center;
  min-width: 20px;
}

#user-competencies-modal .competency-item div {
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: 0.25em;
}

#user-competencies-modal .competency-item div p {
  font-weight: 600;
  margin: 0;
  color: #1f2937;
}

#user-competencies-modal .competency-item div .group-badge {
  font-size: 0.75em;
  color: #6b7280;
  font-style: italic;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

#user-competencies-modal .competency-item div .group-badge i {
  font-size: 0.9em;
  min-width: auto;
}

#user-competencies-modal .competency-item div .source-badge {
  display: inline-block;
  padding: 0.125rem 0.5rem;
  font-size: 0.7em;
  font-weight: 600;
  text-transform: uppercase;
  border-radius: 3px;
}

#user-competencies-modal .competency-item div .source-badge.manual {
  background: #10b981;
  color: white;
}

#user-competencies-modal .competency-item div .source-badge.group {
  background: #3b82f6;
  color: white;
}

#user-competencies-modal .competency-item div .source-badge.both {
  background: #f59e0b;
  color: white;
}
</style>

<script>
function addCompetencyItem(uid, name, isFromGroup, isManual, groupNames) {
  // Determine the class and styling
  let itemClass = '';
  let iconHtml = '';
  let badgeHtml = '';
  
  if (isFromGroup && isManual) {
    // Both sources
    itemClass = 'both-sources';
    iconHtml = '<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.remove-manual-only') }}"></i>';
    badgeHtml = `<span class="source-badge both">{{ __('admin/employees.both-sources') }}</span>`;
    if (groupNames) {
      badgeHtml += `<span class="group-badge"><i class="fa fa-users"></i>${groupNames}</span>`;
    }
  } else if (isFromGroup) {
    // Only from group(s)
    itemClass = 'from-group';
    iconHtml = '<i class="fa fa-lock" data-tippy-content="{{ __('admin/employees.managed-by-group') }}"></i>';
    badgeHtml = `<span class="source-badge group">{{ __('admin/employees.from-group') }}</span>`;
    if (groupNames) {
      badgeHtml += `<span class="group-badge"><i class="fa fa-users"></i>${groupNames}</span>`;
    }
  } else {
    // Only manual
    itemClass = 'manual';
    iconHtml = '<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.remove-competency') }}"></i>';
    badgeHtml = `<span class="source-badge manual">{{ __('admin/employees.manual') }}</span>`;
  }
  
  // Append to the single list
  $('.competency-list').append(`
    <div class="competency-item ${itemClass}" data-id="${uid}">
      ${iconHtml}
      <div>
        <p>${name}</p>
        ${badgeHtml}
      </div>
    </div>
  `);
}

function initCompetenciesModal(uid, userName){
  $('#user-competencies-modal').attr('data-id', uid);
  
  // ✅ ADDED: Show user name in subtitle if provided
  if (userName) {
    $('#user-competencies-modal .modal-subtitle').text(userName).show();
  } else {
    $('#user-competencies-modal .modal-subtitle').hide();
  }
  
  swal_loader.fire();
  $.ajax({
    url: "{{ route('admin.employee.competencies') }}",
    data: { id: uid },
  })
  .done(function(response){
    // Clear the list
    $('.competency-list').html('');

    response.forEach(item => {
      addCompetencyItem(
        item.id, 
        item.name, 
        item.is_from_group, 
        item.is_manual,
        item.group_names
      );
    });

    tippy('#user-competencies-modal [data-tippy-content]');

    swal_loader.close();

    $('#user-competencies-modal').modal();
  });
}

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
        top: null,
        bottom: item.description || null
      }; },
      selectFunction: function(){
        const selectedId = $(this).attr('data-id');
        const selectedName = $(this).attr('data-name');
        
        if ($('#user-competencies-modal .competency-item[data-id="'+selectedId+'"]').length === 0) {
          // Add as manual competency
          addCompetencyItem(selectedId, selectedName, false, true, null);
          tippy('#user-competencies-modal [data-tippy-content]');
        }
      },
      exceptArray: exceptArray,
      multiSelect: true,
      emptyMessage: '{{ __('admin/employees.no-competencies') }}'
    });
  });

  // Handle remove for manual competencies only
  $(document).on('click', '#user-competencies-modal .competency-item.manual i, #user-competencies-modal .competency-item.both-sources i', function(){
    $(this).closest('.competency-item').remove();
  });

  $('.save-competency').click(function(){
  swal_confirm.fire({
    title: '{{ __('admin/employees.save-competencies-confirm') }}'
  }).then((result) => {
    if (result.isConfirmed) {
      swal_loader.fire();

      var competencies = [];

      // Only get manual competencies (excluding those that are ONLY from groups)
      $('#user-competencies-modal .competency-item.manual, #user-competencies-modal .competency-item.both-sources').each(function(){
        competencies.push($(this).attr('data-id')*1);
      });

      // ✅ STANDARDIZED: Use successMessage and close modal in success callback
      $.ajax({
        url: "{{ route('admin.employee.competencies.save') }}",
        method: 'POST',
        data: {
          id: $('#user-competencies-modal').attr('data-id'),
          competencies: competencies
        },
        success: function(response){
          // Close modal only on success
          $('#user-competencies-modal').modal('hide');
        },
        successMessage: '{{ __('admin/employees.save-competencies-success') }}',
        error: function(xhr){
          swal_loader.close();
          Swal.fire({
            icon: 'error',
            title: '{{ __('global.error') }}',
            text: xhr.responseJSON?.message || '{{ __('global.error-occurred') }}'
          });
          // Modal stays open on error
        }
      });
    }
  });
});
}); 
</script>