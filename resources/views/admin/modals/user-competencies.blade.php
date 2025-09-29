{{-- resources/views/admin/modals/user-competencies.blade.php --}}
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="user-competencies-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/employees.competencies') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        {{-- Group-based competencies section --}}
        <div class="competency-groups-section">
          <h6 class="section-header">
            <i class="fa fa-users"></i> {{ __('admin/employees.from-groups') }}
          </h6>
          <div class="competency-list competency-list-groups">
            {{-- Populated dynamically --}}
          </div>
        </div>

        {{-- Manual competencies section --}}
        <div class="competency-manual-section">
          <h6 class="section-header">
            <i class="fa fa-hand-pointer"></i> {{ __('admin/employees.manually-added') }}
          </h6>
          <div class="competency-list competency-list-manual">
            {{-- Populated dynamically --}}
          </div>
        </div>

        <div class="tile tile-button trigger-new-competency">{{ __('admin/employees.add-new-competencies') }}</div>
        <button class="btn btn-primary save-competency">{{ __('admin/employees.save-competencies') }}</button>
      </div>
    </div>
  </div>
</div>

<style>
/* Competency sections styling */
#user-competencies-modal .modal-body {
  display: flex;
  flex-direction: column;
  gap: 1.5em;
}

#user-competencies-modal .section-header {
  font-weight: 600;
  margin-bottom: 0.5rem;
  padding: 0.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

#user-competencies-modal .competency-groups-section .section-header {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

#user-competencies-modal .competency-manual-section .section-header {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

#user-competencies-modal .competency-list {
  display: flex;
  flex-direction: column;
  gap: 0.75em;
  max-height: 200px;
  overflow-y: auto;
  padding: 0.5em;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
}

#user-competencies-modal .competency-list:empty::after {
  content: attr(data-empty-message);
  display: block;
  text-align: center;
  padding: 1rem;
  color: #9ca3af;
  font-style: italic;
}

#user-competencies-modal .competency-list-groups:empty::after {
  content: '{{ __('admin/employees.no-group-competencies') }}';
}

#user-competencies-modal .competency-list-manual:empty::after {
  content: '{{ __('admin/employees.no-manual-competencies') }}';
}

/* Group-based competencies - blue border only */
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

/* Manual competencies - green border only */
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

/* Shared competencies - orange border only */
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

/* Common competency item styling */
#user-competencies-modal .competency-item {
  display: flex;
  gap: 1em;
  align-items: center;
  transition: all 0.2s ease;
}

#user-competencies-modal .competency-item:hover {
  transform: translateX(2px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
  border-radius: 9999px;
  font-size: 0.7em;
  font-weight: 600;
  text-transform: uppercase;
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
  // Determine the class and container
  let itemClass = '';
  let container = '';
  let iconHtml = '';
  let badgeHtml = '';
  
  if (isFromGroup && isManual) {
    // Both sources
    itemClass = 'both-sources';
    container = '.competency-list-manual'; // Show in manual section since it can be removed
    iconHtml = '<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.remove-manual-only') }}"></i>';
    badgeHtml = `<span class="source-badge both">{{ __('admin/employees.both-sources') }}</span>`;
    if (groupNames) {
      badgeHtml += `<span class="group-badge"><i class="fa fa-users"></i>${groupNames}</span>`;
    }
  } else if (isFromGroup) {
    // Only from group(s)
    itemClass = 'from-group';
    container = '.competency-list-groups';
    iconHtml = '<i class="fa fa-lock" data-tippy-content="{{ __('admin/employees.managed-by-group') }}"></i>';
    badgeHtml = `<span class="source-badge group">{{ __('admin/employees.from-group') }}</span>`;
    if (groupNames) {
      badgeHtml += `<span class="group-badge"><i class="fa fa-users"></i>${groupNames}</span>`;
    }
  } else {
    // Only manual
    itemClass = 'manual';
    container = '.competency-list-manual';
    iconHtml = '<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.remove-competency') }}"></i>';
    badgeHtml = `<span class="source-badge manual">{{ __('admin/employees.manual') }}</span>`;
  }
  
  $(container).append(`
    <div class="competency-item ${itemClass}" data-id="${uid}">
      ${iconHtml}
      <div>
        <p>${name}</p>
        ${badgeHtml}
      </div>
    </div>
  `);
}

function initCompetenciesModal(uid){
  $('#user-competencies-modal').attr('data-id', uid);
  swal_loader.fire();
  $.ajax({
    url: "{{ route('admin.employee.competencies') }}",
    data: { id: uid },
  })
  .done(function(response){
    // Clear both lists
    $('.competency-list-groups').html('');
    $('.competency-list-manual').html('');

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
        }; },
        selectFunction: function(){
          const selectedId = $(this).attr('data-id');
          const selectedName = $(this).attr('data-name');
          
          // Check if competency already exists
          if ($('#user-competencies-modal .competency-item[data-id="'+selectedId+'"]').length === 0) {
            // Add as manual only
            addCompetencyItem(selectedId, selectedName, false, true, null);
            tippy('#user-competencies-modal [data-tippy-content]');
          }
        },
        exceptArray: exceptArray,
        multiSelect: true,
        emptyMessage: '{{ __('admin/competencies.no-competency') }}'
      });
    });

    // Only allow removing manual or both-source competencies
    $(document).on('click', '#user-competencies-modal .competency-item.manual i, #user-competencies-modal .competency-item.both-sources i', function(){
      $(this).parents('.competency-item').remove();
    });

    $('.save-competency').click(function(){
      swal_confirm.fire({
        title: '{{ __('admin/employees.save-competencies-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();

          var comps = [];

          // Collect ALL competencies (both from groups and manual)
          $('#user-competencies-modal .competency-item').each(function(){
            comps.push($(this).attr('data-id')*1);
          });

          $.ajax({
            url: "{{ route('admin.employee.competencies.save') }}",
            data: {
              id: $('#user-competencies-modal').attr('data-id'),
              competencies: comps
            },
            successMessage: "{{ __('admin/employees.save-competencies-success') }}",
          });
        }
      });
    });
  });
}
</script>