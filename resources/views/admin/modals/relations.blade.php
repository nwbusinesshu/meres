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

.relation-type-toggle .toggle-option:hover:not(.active) {
  background: rgba(0, 123, 255, 0.08);
  color: #333;
}

.relation-type-toggle .toggle-option.active {
  background: var(--info);
  color: white;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}

.relation-type-toggle .toggle-option:first-child {
  border-right: 1px solid #e0e0e0;
}

.relation-type-toggle .toggle-option.active:first-child {
  border-right-color: var(--info);
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
function addNewRelationItem(uid, name, type = 'colleague'){
  $('.relation-list').append(''
    +'<div class="relation-item" data-id="'+uid+'">'
    +'<i class="fa fa-trash-alt" data-tippy-content="{{ __('admin/employees.remove-relation') }}"></i>'
    +'<div>'
    +'<p>'+name+'</p>'
    +'<div class="relation-type-toggle" data-value="'+type+'">'
    +'<button type="button" class="toggle-option '+(type === 'colleague' ? 'active' : '')+'" data-value="colleague">'
    +'{{ __('userrelationtypes.colleague') }}'
    +'</button>'
    +'<button type="button" class="toggle-option '+(type === 'subordinate' ? 'active' : '')+'" data-value="subordinate">'
    +'{{ __('userrelationtypes.subordinate') }}'
    +'</button>'
    +'</div>'
    +'</div>'
    +'</div>');
}

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
    user = response.filter(item => item.target_id == uid)[0].target;
    response = response.filter(item => item.target_id != uid);

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

    response.forEach(item => {
      addNewRelationItem(item.target.id, item.target.name, item.type);
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
  $(document).on('click', '.relation-type-toggle:not(.disabled) .toggle-option', function(){
    const $toggle = $(this).closest('.relation-type-toggle');
    const value = $(this).data('value');
    
    // Update UI
    $toggle.find('.toggle-option').removeClass('active');
    $(this).addClass('active');
    
    // Update stored value
    $toggle.attr('data-value', value);
  });

  $('.save-relation').click(function(){
    swal_confirm.fire({
      title: '{{ __('admin/employees.save-relation-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();

        var relations = [];

        $('.relation-item:not(.cant-remove)').each(function(){
          const $toggle = $(this).find('.relation-type-toggle');
          relations.push({
            target_id: $(this).attr('data-id')*1,
            type: $toggle.attr('data-value')
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
              conflictHtml += '<p><strong>A következő kapcsolatokban ütközések vannak:</strong></p>';
              conflictHtml += '<ul style="margin: 10px 0;">';
              
              response.conflicts.forEach(function(conflict) {
                let currentTypeText = conflict.current_type === 'subordinate' ? 
                  '{{ __('userrelationtypes.subordinate') }}' : 
                  '{{ __('userrelationtypes.colleague') }}';
                let oppositeTypeText = conflict.opposite_type === 'subordinate' ? 
                  '{{ __('userrelationtypes.subordinate') }}' : 
                  '{{ __('userrelationtypes.colleague') }}';
                
                conflictHtml += `<li>${conflict.target_name}: Te "${currentTypeText}", ő "${oppositeTypeText}"</li>`;
              });
              
              conflictHtml += '</ul>';
              conflictHtml += '<p>Folytatod a mentést? Ez felülírja az ellentétes beállításokat.</p>';
              conflictHtml += '</div>';
              
              // Ask for confirmation to override
              swal.fire({
                title: 'Kapcsolat ütközések',
                html: conflictHtml,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Igen, mentés',
                cancelButtonText: 'Mégsem'
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
                      force: true  // Override conflicts
                    },
                    success: function(finalResponse) {
                      swal_loader.close();
                      $('#relations-modal').modal('hide');
                      swal.fire({
                        icon: 'success',
                        title: 'Mentve',
                        text: finalResponse.message,
                        timer: 2000
                      });
                      location.reload();
                    },
                    error: function(xhr) {
                      swal_loader.close();
                      swal.fire({
                        icon: 'error',
                        title: 'Hiba',
                        text: xhr.responseJSON?.message || 'Hiba történt a mentés során'
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
                title: 'Mentve',
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
              title: 'Hiba',
              text: xhr.responseJSON?.message || 'Hiba történt a mentés során'
            });
          }
        });
      }
    });
  });
});
</script>