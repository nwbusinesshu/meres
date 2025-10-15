{{-- resources/views/admin/modals/relations.blade.php --}}
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="relations-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/employees.relations') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="relation-list">
          {{-- Relations populated dynamically --}}
        </div>
        <div class="tile tile-button trigger-new-relation">{{ __('admin/employees.add-new-relation') }}</div>
        
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary save-relation">{{ __('admin/employees.save-relation') }}</button>
      </div>
    </div>
  </div>
</div>

<style>
/* Capsule toggle button for relation type - COMPACT ELEGANT PILL */
.relation-list .relation-type-toggle {
  display: inline-flex !important;
  flex-direction: row !important;
  border: 1.5px solid #e0e0e0;
  border-radius: 16px;
  overflow: hidden;
  background: #f8f9fa;
  width: auto !important;
  max-width: none !important;
  margin-top: 0.3rem;
  height: 28px;
}

.relation-list .relation-type-toggle .toggle-option {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem 0.65rem;
  white-space: nowrap;
  border: 0;
  background: transparent;
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.15s ease;
  cursor: pointer;
  color: #666;
  line-height: 1;
}

.relation-type-toggle .toggle-option:hover:not(.active):not(:disabled) {
  background: rgba(0, 123, 255, 0.08);
  color: #333;
}

.relation-type-toggle .toggle-option.active {
  background: var(--info);
  color: white;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.relation-type-toggle .toggle-option:not(:last-child) {
  border-right: 1px solid #e0e0e0;
}

.relation-type-toggle .toggle-option.active:not(:last-child) {
  border-right-color: var(--info);
}

/* Read-only superior display for Easy Setup OFF */
.relation-type-toggle .toggle-option:disabled {
  opacity: 0.6;
  cursor: not-allowed !important;
  background: #f0f0f0;
}

/* Conflict badge for Easy Setup OFF */
.relation-item .conflict-badge {
  display: inline-block;
  background: #dc3545;
  color: white;
  padding: 0.15rem 0.5rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  margin-left: 0.5rem;
}

/* Disabled state for self-relation */
.relation-type-toggle.disabled {
  opacity: 0.5;
  pointer-events: none;
  border-color: #d0d0d0;
  background: #f0f0f0;
}

.relation-type-toggle.disabled .toggle-option {
  cursor: not-allowed;
  color: #999;
}
</style>

<script>
// CRITICAL: Get Easy Relation Setup setting from server
// Use isset to safely check if variable exists
const EASY_RELATION_SETUP = {{ isset($easyRelationSetup) && $easyRelationSetup ? 'true' : 'false' }};
console.log('EASY_RELATION_SETUP loaded:', EASY_RELATION_SETUP); // Debug log

// UPDATED: Add relation item with conditional button display
function addNewRelationItem(uid, name, type = 'colleague', isReadOnlySuperior = false){
  let buttonsHtml = '';
  
  if (EASY_RELATION_SETUP) {
    // EASY SETUP ON: Show 3 buttons (colleague, subordinate, superior)
    buttonsHtml = ''
      +'<button type="button" class="toggle-option '+(type === 'colleague' ? 'active' : '')+'" data-value="colleague">'
      +'{{ __('userrelationtypes.colleague') }}'
      +'</button>'
      +'<button type="button" class="toggle-option '+(type === 'subordinate' ? 'active' : '')+'" data-value="subordinate">'
      +'{{ __('userrelationtypes.subordinate') }}'
      +'</button>'
      +'<button type="button" class="toggle-option '+(type === 'superior' ? 'active' : '')+'" data-value="superior">'
      +'{{ __('userrelationtypes.superior') }}'
      +'</button>';
  } else {
    // EASY SETUP OFF: Show 2 buttons with conditional labeling
    const superiorLabel = '{{ __('userrelationtypes.superior') }}';
    const colleagueLabel = '{{ __('userrelationtypes.colleague') }}';
    const subordinateLabel = '{{ __('userrelationtypes.subordinate') }}';
    
    // If it's read-only superior, show as "Superior" but disabled subordinate
    const firstLabel = isReadOnlySuperior ? superiorLabel : colleagueLabel;
    const firstValue = isReadOnlySuperior ? 'superior' : 'colleague';
    const firstActive = (type === 'colleague' || type === 'superior') ? 'active' : '';
    
    buttonsHtml = ''
      +'<button type="button" class="toggle-option '+firstActive+'" data-value="'+firstValue+'" '+(isReadOnlySuperior ? 'disabled' : '')+'>'
      +firstLabel
      +'</button>'
      +'<button type="button" class="toggle-option '+(type === 'subordinate' ? 'active' : '')+'" data-value="subordinate" '+(isReadOnlySuperior ? 'disabled' : '')+'>'
      +subordinateLabel
      +'</button>';
  }
  
  $('.relation-list').append(''
    +'<div class="relation-item" data-id="'+uid+'" data-readonly-superior="'+(isReadOnlySuperior ? '1' : '0')+'">'
    +'<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.remove-relation') }}"></i>'
    +'<div>'
    +'<p>'+name+'</p>'
    +'<div class="relation-type-toggle" data-value="'+type+'">'
    +buttonsHtml
    +'</div>'
    +'</div>'
    +'</div>');
}

// UPDATED: Transform relation types on load
function initRelationsModal(uid){
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  $('#relations-modal').attr('data-id', uid);
  swal_loader.fire();
  $.ajax({
    url: "{{ route('admin.employee.relations') }}",
    data: { id: uid },
  })
  .done(function(response){
    $('.relation-list').html('');
    
    // DEBUG: Log the full response
    console.log('Full relations response:', response);
    
    // Find current user
    user = response.filter(item => item.target_id == uid)[0].target;
    
    // Get all relations except self
    const relations = response.filter(item => item.target_id != uid);
    
    console.log('Filtered relations (excluding self):', relations);
    console.log('Current user ID:', uid);

    // Add self relation (disabled)
    $('.relation-list').append(''
      +'<div class="relation-item cant-remove" data-id="'+user.id+'">'
      +'<i class="fa fa-trash-alt"></i>'
      +'<div>'
      +'<p>'+user.name+'</p>'
      +'<div class="relation-type-toggle disabled" data-value="self">'
      +'<button type="button" class="toggle-option" data-value="self">'
      +'{{ __('userrelationtypes.self') }}'
      +'</button>'
      +'</div>'
      +'</div>'
      +'</div>');

    // CRITICAL TRANSFORMATION LOGIC:
    relations.forEach(item => {
      let displayType = item.type;
      let isReadOnlySuperior = false;
      
      // Check if there's a reverse relation where THEY have US as subordinate
      const reverseRelation = relations.find(r => 
        r.target_id === item.user_id && r.user_id === item.target_id
      );
      
      // DEBUG: Log what we found
      console.log('Checking relation:', {
        from: item.user_id,
        to: item.target_id,
        type: item.type,
        reverse: reverseRelation ? reverseRelation.type : 'none'
      });
      
      // TRANSFORM: If we have them as "colleague" but they have us as "subordinate"
      // Display it as "superior" 
      if (item.type === 'colleague' && reverseRelation && reverseRelation.type === 'subordinate') {
        displayType = 'superior';
        console.log('TRANSFORMING to superior!');
        
        // If Easy Setup OFF, this superior is read-only (can't be changed)
        if (!EASY_RELATION_SETUP) {
          isReadOnlySuperior = true;
        }
      }
      
      addNewRelationItem(item.target.id, item.target.name, displayType, isReadOnlySuperior);
    });

    tippy('.relation-list [data-tippy-content]');

    swal_loader.close();

    $('#relations-modal').modal();
  });
}

$(document).ready(function(){
  $('.trigger-new-relation').click(function(){
    var exceptArray = [];
    $('#relations-modal .relation-item').each(function(){
      exceptArray.push($(this).attr('data-id')*1);
    });
    
    openSelectModal({
      title: "{{ __('select.title') }}",
      parentSelector: '#relations-modal',
      ajaxRoute: "{{ route('admin.employee.all') }}",
      itemData: function(item){ return {
        id: item.id,
        name: item.name,
        top: null,
        bottom: item.email || null
      }; },
      selectFunction: function(){
        const selectedId = $(this).attr('data-id');
        const selectedName = $(this).attr('data-name');
        
        if ($('#relations-modal .relation-item[data-id="'+selectedId+'"]').length === 0) {
          addNewRelationItem(selectedId, selectedName);
          tippy('.relation-list [data-tippy-content]');
        }
      },
      exceptArray: exceptArray,
      multiSelect: true,
      emptyMessage: '{{ __('admin/employees.no-employees') }}'
    });
  });

  $(document).delegate('.relation-item:not(.cant-remove) i', 'click', function(){
    $(this).parents('.relation-item').remove();
  });

  // Handle toggle button clicks
  $(document).on('click', '.relation-type-toggle:not(.disabled) .toggle-option:not(:disabled)', function(){
    const $toggle = $(this).closest('.relation-type-toggle');
    const value = $(this).data('value');
    
    // Update UI
    $toggle.find('.toggle-option').removeClass('active');
    $(this).addClass('active');
    
    // Update stored value
    $toggle.attr('data-value', value);
  });

  // UPDATED: Save with mode-specific logic
  $('.save-relation').click(function(){
    
    // VALIDATION: Check for conflicts BEFORE showing confirm dialog
    if (!EASY_RELATION_SETUP) {
      // Easy Setup OFF: Check for subordinate-subordinate conflicts
      let hasConflicts = false;
      
      $('#relations-modal .relation-item:not(.cant-remove)').each(function(){
        const $item = $(this);
        const $toggle = $item.find('.relation-type-toggle');
        const type = $toggle.attr('data-value');
        
        // Check if this is a subordinate-subordinate conflict
        // (This would be detected by server, but we can show visual feedback)
        if (type === 'subordinate') {
          // Add conflict badge if not already present
          if ($item.find('.conflict-badge').length === 0) {
            $item.find('p').append('<span class="conflict-badge">{{ __('admin/employees.check-reverse') }}</span>');
          }
        }
      });
    }
    
    swal_confirm.fire({
      title: '{{ __('admin/employees.save-relation-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();

        var relations = [];

        // Build relations array with mode-specific transformation
        $('.relation-item:not(.cant-remove)').each(function(){
          const $toggle = $(this).find('.relation-type-toggle');
          const isReadOnly = $(this).attr('data-readonly-superior') === '1';
          let type = $toggle.attr('data-value');
          
          // EASY SETUP ON: Keep "superior" as-is (server will handle it)
          // EASY SETUP OFF: Skip read-only superior relations (they're display-only)
          if (!EASY_RELATION_SETUP && type === 'superior' && isReadOnly) {
            // Don't include read-only superior in the save data
            // The original "colleague" relation already exists
            return; // Skip this iteration
          }
          
          relations.push({
            target_id: $(this).attr('data-id')*1,
            type: type
          });
        });

        // First attempt - check for conflicts
        $.ajax({
          url: "{{ route('admin.employee.relations.save') }}",
          method: 'POST',
          data: {
            id: $('#relations-modal').attr('data-id'),
            relations: relations
          },
          success: function(response) {
            // Check if there are conflicts
            if (response.has_conflicts && response.conflicts && response.conflicts.length > 0) {
              swal_loader.close();
              
              // Build conflict message
              let conflictHtml = '<div style="text-align: left;">';
              conflictHtml += '<p><strong>{{ __('admin/employees.relation-conflicts-intro') }}</strong></p>';
              conflictHtml += '<ul style="margin: 10px 0;">';
              
              response.conflicts.forEach(function(conflict) {
                let currentTypeText = conflict.current_type === 'subordinate' ? 
                  '{{ __('userrelationtypes.subordinate') }}' : 
                  '{{ __('userrelationtypes.colleague') }}';
                let oppositeTypeText = conflict.opposite_type === 'subordinate' ? 
                  '{{ __('userrelationtypes.subordinate') }}' : 
                  '{{ __('userrelationtypes.colleague') }}';
                
                conflictHtml += `<li>${conflict.target_name}: {{ __('admin/employees.relation-conflict-you') }} "${currentTypeText}", {{ __('admin/employees.relation-conflict-they') }} "${oppositeTypeText}"</li>`;
              });
              
              conflictHtml += '</ul>';
              conflictHtml += '<p>{{ __('admin/employees.relation-conflicts-question') }}</p>';
              conflictHtml += '</div>';
              
              // Ask for confirmation to override
              swal.fire({
                title: '{{ __('admin/employees.relation-conflicts-title') }}',
                html: conflictHtml,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '{{ __('admin/employees.relation-save-confirm-button') }}',
                cancelButtonText: '{{ __('global.swal-cancel') }}'
              }).then((confirmResult) => {
                if (confirmResult.isConfirmed) {
                  // Second attempt - force save
                  swal_loader.fire();
                  $.ajax({
                    url: "{{ route('admin.employee.relations.save') }}",
                    method: 'POST',
                    data: {
                      id: $('#relations-modal').attr('data-id'),
                      relations: relations,
                      force_fix: true  // Override conflicts
                    },
                    success: function(finalResponse) {
                      swal_loader.close();
                      $('#relations-modal').modal('hide');
                      swal.fire({
                        icon: 'success',
                        title: '{{ __('global.swal-success') }}',
                        text: finalResponse.message,
                        timer: 2000
                      });
                      location.reload();
                    },
                    error: function(xhr) {
                      swal_loader.close();
                      swal.fire({
                        icon: 'error',
                        title: '{{ __('global.swal-error') }}',
                        text: xhr.responseJSON?.message || '{{ __('admin/employees.relation-save-error-message') }}'
                      });
                    }
                  });
                }
              });
            } else {
              // No conflicts, save successful
              swal_loader.close();
              $('#relations-modal').modal('hide');
              swal.fire({
                icon: 'success',
                title: '{{ __('global.swal-success') }}',
                text: response.message,
                timer: 2000
              });
              location.reload();
            }
          },
          error: function(xhr) {
            swal_loader.close();
            swal.fire({
              icon: 'error',
              title: '{{ __('global.swal-error') }}',
              text: xhr.responseJSON?.message || '{{ __('admin/employees.relation-save-error-message') }}'
            });
          }
        });
      }
    });
  });
});
</script>