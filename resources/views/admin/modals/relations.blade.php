{{-- resources/views/admin/modals/relations.blade.php - COMPLETE FILE --}}
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
/* Capsule toggle button for relation type */
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

/* Disabled state for self-relation and restricted buttons */
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

/* NEW: Disabled individual button */
.relation-type-toggle .toggle-option:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  background: #f5f5f5;
  color: #999;
}

/* Conflict badge */
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

/* NEW: Restriction info badge */
.relation-item .restriction-badge {
  display: inline-block;
  background: #6c757d;
  color: white;
  padding: 0.15rem 0.5rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 500;
  margin-left: 0.5rem;
}

/* Cannot remove indicator */
.relation-item.cant-remove i {
  opacity: 0.3;
  cursor: not-allowed !important;
}
</style>

<script>
// ============================================================================
// PHASE 1: EASY SETUP OFF - BUTTON RESTRICTIONS
// ============================================================================

// Get Easy Relation Setup setting from server
const EASY_RELATION_SETUP = {{ isset($easyRelationSetup) && $easyRelationSetup ? 'true' : 'false' }};

// GLOBAL: Store all relations for conflict checking
let storedAllRelations = [];
let currentUserId = null;

console.log('Relations Modal: Easy Setup Mode =', EASY_RELATION_SETUP);

/**
 * Get the inverse relation type
 */
function getInverseType(type) {
  switch(type) {
    case 'colleague': return 'colleague';
    case 'subordinate': return 'superior';
    case 'superior': return 'subordinate';
    default: return 'colleague';
  }
}

/**
 * Get human-readable relation type name
 */
function getRelationTypeName(type) {
  const names = {
    'colleague': '{{ __('userrelationtypes.colleague') }}',
    'subordinate': '{{ __('userrelationtypes.subordinate') }}',
    'superior': '{{ __('userrelationtypes.superior') }}'
  };
  return names[type] || type;
}

/**
 * Check if reverse relation exists and return required type
 * Returns null if no restriction, or the required type if restricted
 */
function getReverseRelationRestriction(targetId) {
  console.log('=== getReverseRelationRestriction ===');
  console.log('Looking for reverse relation from:', targetId, 'to:', currentUserId);
  console.log('All relations:', storedAllRelations);
  
  // Check if B→A exists in storedAllRelations
  const reverseRelation = storedAllRelations.find(r => {
    // Ensure type-safe comparison by converting to numbers
    const matches = parseInt(r.user_id) === parseInt(targetId) && parseInt(r.target_id) === parseInt(currentUserId);
    console.log('Checking relation:', r, 'Matches:', matches);
    return matches;
  });
  
  console.log('Found reverse relation:', reverseRelation);
  
  if (!reverseRelation) {
    console.log('No reverse relation found for target:', targetId);
    return null; // No restriction - all buttons available
  }
  
  // Reverse exists - return the inverse type (what A→B must be)
  const inverseType = getInverseType(reverseRelation.type);
  console.log('Reverse relation type:', reverseRelation.type, '→ Required forward type:', inverseType);
  return inverseType;
}

/**
 * Add a relation item to the list with optional button restrictions
 */
function addNewRelationItem(uid, name, type = 'colleague', restrictedType = null) {
  console.log('addNewRelationItem called:', {uid, name, type, restrictedType, easySetup: EASY_RELATION_SETUP});
  
  let buttonsHtml = '';
  
  if (EASY_RELATION_SETUP) {
    // EASY SETUP ON: Show 3 buttons (all enabled)
    buttonsHtml = ''
      + '<button type="button" class="toggle-option ' + (type === 'colleague' ? 'active' : '') + '" data-value="colleague">'
      + '{{ __('userrelationtypes.colleague') }}'
      + '</button>'
      + '<button type="button" class="toggle-option ' + (type === 'subordinate' ? 'active' : '') + '" data-value="subordinate">'
      + '{{ __('userrelationtypes.subordinate') }}'
      + '</button>'
      + '<button type="button" class="toggle-option ' + (type === 'superior' ? 'active' : '') + '" data-value="superior">'
      + '{{ __('userrelationtypes.superior') }}'
      + '</button>';
  } else {
    // EASY SETUP OFF: Show 3 buttons with restrictions if needed
    if (restrictedType) {
      // Show buttons but disable the non-matching ones
      buttonsHtml = ''
        + '<button type="button" class="toggle-option ' + (restrictedType === 'colleague' ? 'active' : '') + '" data-value="colleague" ' + (restrictedType !== 'colleague' ? 'disabled' : '') + '>'
        + '{{ __('userrelationtypes.colleague') }}'
        + '</button>'
        + '<button type="button" class="toggle-option ' + (restrictedType === 'subordinate' ? 'active' : '') + '" data-value="subordinate" ' + (restrictedType !== 'subordinate' ? 'disabled' : '') + '>'
        + '{{ __('userrelationtypes.subordinate') }}'
        + '</button>'
        + '<button type="button" class="toggle-option ' + (restrictedType === 'superior' ? 'active' : '') + '" data-value="superior" ' + (restrictedType !== 'superior' ? 'disabled' : '') + '>'
        + '{{ __('userrelationtypes.superior') }}'
        + '</button>';
    } else {
      // No restriction - all buttons enabled
      buttonsHtml = ''
        + '<button type="button" class="toggle-option ' + (type === 'colleague' ? 'active' : '') + '" data-value="colleague">'
        + '{{ __('userrelationtypes.colleague') }}'
        + '</button>'
        + '<button type="button" class="toggle-option ' + (type === 'subordinate' ? 'active' : '') + '" data-value="subordinate">'
        + '{{ __('userrelationtypes.subordinate') }}'
        + '</button>'
        + '<button type="button" class="toggle-option ' + (type === 'superior' ? 'active' : '') + '" data-value="superior">'
        + '{{ __('userrelationtypes.superior') }}'
        + '</button>';
    }
  }
  
  // Build the relation item HTML
  let itemHtml = ''
    + '<div class="relation-item" data-id="' + uid + '">'
    + '<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.remove-relation') }}"></i>'
    + '<div>'
    + '<p>' + name;
  
  // Add restriction badge if applicable
  if (!EASY_RELATION_SETUP && restrictedType) {
    itemHtml += '<span class="restriction-badge" data-tippy-content="{{ __('admin/employees.relation-restricted-tooltip') }}">'
      + '{{ __('admin/employees.relation-restricted') }}'
      + '</span>';
  }
  
  itemHtml += '</p>'
    + '<div class="relation-type-toggle" data-value="' + (restrictedType || type) + '"' + (restrictedType ? ' data-restricted="' + restrictedType + '"' : '') + '>'
    + buttonsHtml
    + '</div>'
    + '</div>'
    + '</div>';
  
  $('.relation-list').append(itemHtml);
}

/**
 * Load relations modal for a user
 */
function initRelationsModal(uid) {
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
  .done(function(response) {
    $('.relation-list').html('');
    
    console.log('=== LOADING RELATIONS ===');
    console.log('User ID:', uid);
    console.log('All Relations:', response);
    
    // Store ALL relations globally for conflict checking
    storedAllRelations = response;
    currentUserId = parseInt(uid); // CRITICAL: Convert to number for matching!
    
    // Find self relation
    const selfRelation = response.find(item => item.user_id == uid && item.target_id == uid);
    if (!selfRelation) {
      console.error('Self relation not found!');
      swal_loader.close();
      return;
    }
    
    const user = selfRelation.user;
    
    // Add self relation (disabled, cannot remove)
    $('.relation-list').append(''
      + '<div class="relation-item cant-remove" data-id="' + user.id + '">'
      + '<i class="fa fa-trash-alt"></i>'
      + '<div>'
      + '<p>' + user.name + '</p>'
      + '<div class="relation-type-toggle disabled" data-value="self">'
      + '<button type="button" class="toggle-option active" data-value="self">'
      + '{{ __('userrelationtypes.self') }}'
      + '</button>'
      + '</div>'
      + '</div>'
      + '</div>');
    
    // Filter: Get relations FROM current user (not self)
    const relationsFromUser = response.filter(item => 
      item.user_id == uid && item.target_id != uid
    );
    
    console.log('Relations FROM user:', relationsFromUser);
    
    // Display each relation
    // Check for restrictions even on EXISTING relations
    relationsFromUser.forEach(item => {
      console.log('Loading existing relation:', item.target.name, 'Type:', item.type);
      
      // Check for restrictions even on EXISTING relations
      let restrictedType = null;
      if (!EASY_RELATION_SETUP) {
        restrictedType = getReverseRelationRestriction(item.target.id);
      }
      
      addNewRelationItem(item.target.id, item.target.name, item.type, restrictedType);
    });
    
    // Initialize tooltips
    if (window.tippy) {
      tippy('.relation-list [data-tippy-content]');
    }
    
    swal_loader.close();
    $('#relations-modal').modal();
  })
  .fail(function(xhr, status, error) {
    console.error('Failed to load relations:', error);
    swal_loader.close();
    swal.fire({
      icon: 'error',
      title: '{{ __('global.error') }}',
      text: '{{ __('admin/employees.failed-to-load-relations') }}'
    });
  });
}

$(document).ready(function() {
  
  // Add new relation button
  $('.trigger-new-relation').click(function() {
    console.log('=== ADD NEW RELATION CLICKED ===');
    
    var exceptArray = [];
    $('#relations-modal .relation-item').each(function() {
      exceptArray.push($(this).attr('data-id') * 1);
    });
    
    console.log('Except array:', exceptArray);
    console.log('Current user ID:', currentUserId);
    console.log('Easy Setup mode:', EASY_RELATION_SETUP);
    
    openSelectModal({
      title: "{{ __('select.title') }}",
      parentSelector: '#relations-modal',
      ajaxRoute: "{{ route('admin.employee.all') }}",
      itemData: function(item) {
        return {
          id: item.id,
          name: item.name,
          top: null,
          bottom: item.email || null
        };
      },
      selectFunction: function() {
        const selectedId = parseInt($(this).attr('data-id'));
        const selectedName = $(this).attr('data-name');
        
        console.log('=== USER SELECTED FROM MODAL ===');
        console.log('Selected ID:', selectedId, 'Type:', typeof selectedId);
        console.log('Selected Name:', selectedName);
        console.log('Current User ID:', currentUserId, 'Type:', typeof currentUserId);
        console.log('Easy Setup:', EASY_RELATION_SETUP);
        
        if ($('#relations-modal .relation-item[data-id="' + selectedId + '"]').length === 0) {
          // Check if reverse relation exists and restricts button options
          let restrictedType = null;
          
          if (!EASY_RELATION_SETUP) {
            console.log('Easy Setup OFF - checking for restrictions...');
            restrictedType = getReverseRelationRestriction(selectedId);
            console.log('Restriction result:', restrictedType);
          } else {
            console.log('Easy Setup ON - no restrictions applied');
          }
          
          // Add with default type or restricted type
          addNewRelationItem(selectedId, selectedName, restrictedType || 'colleague', restrictedType);
          
          if (window.tippy) {
            tippy('.relation-list [data-tippy-content]');
          }
        } else {
          console.log('User already in list, skipping');
        }
      },
      exceptArray: exceptArray,
      multiSelect: true,
      emptyMessage: '{{ __('admin/employees.no-employees') }}'
    });
  });
  
  // Remove relation button
  $(document).on('click', '.relation-item:not(.cant-remove) i', function() {
    $(this).closest('.relation-item').remove();
  });
  
  // Handle toggle button clicks
  $(document).on('click', '.relation-type-toggle:not(.disabled) .toggle-option:not(:disabled)', function() {
    const $toggle = $(this).closest('.relation-type-toggle');
    const $item = $(this).closest('.relation-item');
    const value = $(this).data('value');
    const targetId = parseInt($item.attr('data-id'));
    
    // Update UI
    $toggle.find('.toggle-option').removeClass('active');
    $(this).addClass('active');
    $toggle.attr('data-value', value);
    
    // Clear any existing badges
    $item.find('.conflict-badge').remove();
    
    // REAL-TIME VALIDATION
    if (EASY_RELATION_SETUP) {
      // Easy Setup ON: Check for bidirectional subordinate conflict OR reverse relation update
      const reverseRelation = storedAllRelations.find(r => 
        parseInt(r.user_id) === targetId && parseInt(r.target_id) === currentUserId
      );
      
      if (reverseRelation) {
        // Check if it's a bidirectional subordinate (blocking conflict)
        if (value === 'subordinate' && reverseRelation.type === 'subordinate') {
          $item.find('p').append('<span class="conflict-badge">⚠ {{ __('admin/employees.bidirectional-subordinate-error') }}</span>');
          console.warn('Bidirectional subordinate conflict detected:', targetId, '↔', currentUserId);
        } else {
          // Show update warning
          const expectedInverse = getInverseType(value);
          if (reverseRelation.type !== expectedInverse) {
            const targetName = $item.find('p').first().text().trim();
            $item.find('p').append('<span class="conflict-badge">⚠ {{ __('admin/employees.bidirectional-update-warning', ['name' => '']) }}' + targetName + '</span>');
            console.log('Bidirectional update will occur:', {
              forward: currentUserId + '→' + targetId + '=' + value,
              reverseCurrent: reverseRelation.type,
              reverseWillBe: expectedInverse
            });
          }
        }
      }
    } else {
      // Easy Setup OFF: Check for inconsistent reverse relation
      const reverseRelation = storedAllRelations.find(r => 
        parseInt(r.user_id) === targetId && parseInt(r.target_id) === currentUserId
      );
      
      if (reverseRelation) {
        const expectedType = getInverseType(reverseRelation.type);
        
        if (value !== expectedType) {
          $item.find('p').append('<span class="conflict-badge">⚠ {{ __('admin/employees.inconsistent-relation-error') }}</span>');
          console.warn('Inconsistent relation detected:', {
            forward: currentUserId + '→' + targetId + '=' + value,
            reverse: targetId + '→' + currentUserId + '=' + reverseRelation.type,
            expected: expectedType
          });
        }
      }
    }
  });
  
  // Save relations
  $('.save-relation').click(function() {
    
    // PRE-SAVE VALIDATION: Check for conflicts and updates
    let blockingConflicts = [];
    let bidirectionalUpdates = [];
    
    $('#relations-modal .relation-item:not(.cant-remove)').each(function() {
      const $item = $(this);
      const $toggle = $item.find('.relation-type-toggle');
      const type = $toggle.attr('data-value');
      const targetId = parseInt($item.attr('data-id'));
      const targetName = $item.find('p').first().text().trim();
      
      if (EASY_RELATION_SETUP) {
        // Easy Setup ON: Check bidirectional subordinate (blocking) and collect updates
        const reverseRelation = storedAllRelations.find(r => 
          parseInt(r.user_id) === targetId && parseInt(r.target_id) === currentUserId
        );
        
        if (reverseRelation) {
          // Check for bidirectional subordinate (blocking)
          if (type === 'subordinate' && reverseRelation.type === 'subordinate') {
            blockingConflicts.push({
              id: targetId,
              name: targetName,
              message: '{{ __('admin/employees.bidirectional-subordinate-error') }}'
            });
          } else {
            // Check if reverse relation will be updated
            const expectedInverse = getInverseType(type);
            if (reverseRelation.type !== expectedInverse) {
              bidirectionalUpdates.push({
                id: targetId,
                name: targetName,
                currentType: reverseRelation.type,
                newType: expectedInverse,
                currentTypeName: getRelationTypeName(reverseRelation.type),
                newTypeName: getRelationTypeName(expectedInverse)
              });
            }
          }
        }
      } else {
        // Easy Setup OFF: Check reverse relation consistency (blocking)
        const reverseRelation = storedAllRelations.find(r => 
          parseInt(r.user_id) === targetId && parseInt(r.target_id) === currentUserId
        );
        
        if (reverseRelation) {
          const expectedType = getInverseType(reverseRelation.type);
          
          if (type !== expectedType) {
            blockingConflicts.push({
              id: targetId,
              name: targetName,
              message: '{{ __('admin/employees.inconsistent-relation-error') }}'
            });
          }
        }
      }
    });
    
    // If blocking conflicts found, BLOCK save completely
    if (blockingConflicts.length > 0) {
      let conflictHtml = '<div style="text-align: left;">';
      conflictHtml += '<p><strong>{{ __('admin/employees.relation-conflicts-detected') }}</strong></p>';
      conflictHtml += '<ul style="margin: 10px 0;">';
      
      blockingConflicts.forEach(function(conflict) {
        conflictHtml += '<li><strong>' + conflict.name + '</strong>: ' + conflict.message + '</li>';
      });
      
      conflictHtml += '</ul>';
      conflictHtml += '<p>{{ __('admin/employees.fix-conflicts-first') }}</p>';
      conflictHtml += '</div>';
      
      swal.fire({
        title: '{{ __('admin/employees.cannot-save') }}',
        html: conflictHtml,
        icon: 'error',
        confirmButtonText: '{{ __('global.swal-ok') }}'
      });
      
      return; // BLOCK save completely
    }
    
    // If bidirectional updates exist, show warning confirmation
    if (bidirectionalUpdates.length > 0) {
      let updateHtml = '<div style="text-align: left;">';
      updateHtml += '<p><strong>{{ __('admin/employees.bidirectional-updates-intro') }}</strong></p>';
      updateHtml += '<ul style="margin: 10px 0; list-style: none; padding-left: 0;">';
      
      bidirectionalUpdates.forEach(function(update) {
        updateHtml += '<li style="margin-bottom: 8px;"><strong>' + update.name + '</strong>: ';
        updateHtml += '<span style="color: #dc3545;">' + update.currentTypeName + '</span> → ';
        updateHtml += '<span style="color: #28a745;">' + update.newTypeName + '</span>';
        updateHtml += '</li>';
      });
      
      updateHtml += '</ul>';
      updateHtml += '</div>';
      
      swal.fire({
        title: '{{ __('admin/employees.bidirectional-updates-detected') }}',
        html: updateHtml,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ __('admin/employees.proceed-with-updates') }}',
        cancelButtonText: '{{ __('global.swal-cancel') }}',
        confirmButtonColor: COLOR_SUCCESS,
        cancelButtonColor: COLOR_SECONDARY
      }).then((result) => {
        if (result.isConfirmed) {
          proceedWithSave();
        }
      });
    } else {
      // No updates needed, proceed with normal confirmation
      swal_confirm.fire({
        title: '{{ __('admin/employees.save-relation-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          proceedWithSave();
        }
      });
    }
  });
  // Extracted save function
function proceedWithSave() {
  var relations = [];
  
  // Build relations array - SEND EXACTLY WHAT'S SELECTED
  $('#relations-modal .relation-item:not(.cant-remove)').each(function() {
    const $toggle = $(this).find('.relation-type-toggle');
    let type = $toggle.attr('data-value');
    const targetId = $(this).attr('data-id') * 1;
    
    relations.push({
      target_id: targetId,
      type: type
    });
  });
  
  console.log('Saving relations:', relations);
  
  // Start loader
  swal_loader.fire();
  
  // ✅ STANDARDIZED: Use successMessage for automatic toast handling
  $.ajax({
    url: "{{ route('admin.employee.relations.save') }}",
    method: 'POST',
    data: {
      id: $('#relations-modal').attr('data-id'),
      relations: relations
    },
    successMessage: '{{ __('admin/employees.relations-saved') }}',
    success: function(response) {
      // ✅ Close modal ONLY on success - global handler will reload and show toast
      $('#relations-modal').modal('hide');
    },
    error: function(xhr) {
      swal_loader.close();
      
      // Handle backend conflicts (safety net)
      if (xhr.responseJSON && xhr.responseJSON.has_conflicts) {
        let conflictHtml = '<div style="text-align: left;">';
        conflictHtml += '<p><strong>{{ __('admin/employees.relation-conflicts-detected') }}</strong></p>';
        conflictHtml += '<ul>';
        xhr.responseJSON.conflicts.forEach(function(conflict) {
          conflictHtml += '<li>' + conflict.target_name + ': ' + conflict.message + '</li>';
        });
        conflictHtml += '</ul>';
        conflictHtml += '</div>';
        
        swal.fire({
          icon: 'error',
          title: '{{ __('admin/employees.cannot-save') }}',
          html: conflictHtml
        });
        return;
      }
      
      // Generic error - modal stays open
      let errorMessage = '{{ __('admin/employees.failed-to-save') }}';
      if (xhr.responseJSON && xhr.responseJSON.message) {
        errorMessage = xhr.responseJSON.message;
      }
      
      swal.fire({
        icon: 'error',
        title: '{{ __('global.error') }}',
        text: errorMessage
      });
    }
  });
}
 
});
</script>