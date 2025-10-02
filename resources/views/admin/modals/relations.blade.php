<style>
/* Capsule toggle button for relation type */
.relation-list .relation-type-toggle {
  display: inline-flex !important;
  flex-wrap: nowrap !important;
  white-space: nowrap !important;
  flex: 0 0 auto;            /* fix szélesség, a tartalma határozza meg */
  flex-shrink: 0 !important; /* NE zsugorodjon a szülő flex miatt */
  overflow: hidden;
  border: 2px solid var(--info);
  background: var(--alabaster);
  max-width: 70%;
}

/* A gombok se nyúljanak, se törjenek */
.relation-list .relation-type-toggle .toggle-option {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto !important;
  padding: 0.4em 1em;
  white-space: nowrap;
  border: 0;
}

.relation-type-toggle .toggle-option:hover {
  background: rgba(68, 163, 188, 0.1);
}

.relation-type-toggle .toggle-option.active {
  background: var(--info);
  color: white;
  font-weight: 600;
}

.relation-type-toggle .toggle-option:first-child {
  border-right: 1px solid var(--info);
}

/* Disabled state for self-relation */
.relation-type-toggle.disabled {
  opacity: 0.5;
  pointer-events: none;
  border-color: var(--silver_chalice);
}

.relation-type-toggle.disabled .toggle-option {
  cursor: not-allowed;
  color: var(--silver_chalice);
}
</style>

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

        </div>
        <div class="tile tile-button trigger-new-relation">{{ __('admin/employees.add-new-relation') }}</div>
        <button class="btn btn-primary save-relation">{{ __('admin/employees.save-relation') }}</button>
      </div>
    </div>
  </div>
</div>

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
                let currentTypeText = conflict.current_type === 'subordinate' ? 'beosztott' : 'kolléga';
                let existingTypeText = conflict.existing_reverse_type === 'subordinate' ? 'beosztott' : 'kolléga';
                let expectedTypeText = conflict.expected_reverse_type === 'subordinate' ? 'beosztott' : 'kolléga';
                
                conflictHtml += '<li style="margin: 5px 0;">';
                conflictHtml += '<strong>' + conflict.target_name + '</strong>: ';
                conflictHtml += 'Ön ' + currentTypeText + ' viszonyban értékeli, de ';
                conflictHtml += conflict.target_name + ' már ' + existingTypeText + ' viszonyban értékeli Önt. ';
                conflictHtml += 'Automatikusan ' + expectedTypeText + ' viszonyra változik.';
                conflictHtml += '</li>';
              });
              
              conflictHtml += '</ul>';
              conflictHtml += '<p style="margin-top: 10px;"><strong>Mit szeretne tenni?</strong></p>';
              conflictHtml += '</div>';
              
              // Show conflict dialog with Cancel and Fix buttons
              Swal.fire({
                title: 'Kapcsolat ütközések',
                html: conflictHtml,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Javítás és mentés',
                cancelButtonText: 'Mégse',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                width: '600px',
              }).then((fixResult) => {
                if (fixResult.isConfirmed) {
                  // User clicked "Fix" - resend with force_fix flag
                  swal_loader.fire();
                  
                  $.ajax({
                    url: "{{ route('admin.employee.relations.save') }}",
                    method: 'POST',
                    data: {
                      id: $('#relations-modal').attr('data-id'),
                      relations: relations,
                      force_fix: true
                    },
                    success: function(fixResponse) {
                      swal_loader.close();
                      Swal.fire({
                        icon: 'success',
                        title: 'Siker',
                        text: '{{ __('admin/employees.save-relation-success') }}',
                        timer: 1500,
                        showConfirmButton: false
                      }).then(() => {
                        window.location.reload();
                      });
                    },
                    error: function(xhr) {
                      swal_loader.close();
                      let errorMsg = 'Hiba történt a mentés során.';
                      if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                      }
                      Swal.fire({
                        icon: 'error',
                        title: 'Hiba',
                        text: errorMsg
                      });
                    }
                  });
                }
              });
              
            } else {
              // No conflicts - success!
              swal_loader.close();
              Swal.fire({
                icon: 'success',
                title: 'Siker',
                text: '{{ __('admin/employees.save-relation-success') }}',
                timer: 1500,
                showConfirmButton: false
              }).then(() => {
                window.location.reload();
              });
            }
          },
          error: function(xhr) {
            swal_loader.close();
            let errorMsg = 'Hiba történt a mentés során.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
              errorMsg = xhr.responseJSON.message;
            }
            Swal.fire({
              icon: 'error',
              title: 'Hiba',
              text: errorMsg
            });
          }
        });
      }
    });
  });
});
</script>