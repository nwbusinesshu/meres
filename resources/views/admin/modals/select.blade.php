<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="select-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="search-row">
          <span>{{ __('select.search') }}</span>
          <div>
            <input type="text" class="form-control select-search-input">
            <i class="fa fa-ban select-clear-search" data-tippy-content="{{ __('select.clear-search') }}"></i>
          </div>
        </div>

        <!-- Multi-select controls -->
        <div class="select-controls" style="display: none;">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <button type="button" class="btn btn-sm btn-outline-primary select-all-btn">
                <i class="fa fa-check-double"></i> {{ __('select.select-all') }}
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary clear-all-btn">
                <i class="fa fa-times"></i> {{ __('select.clear-all') }}
              </button>
            </div>
            <div class="selected-count">
              <span class="badge badge-info">0 {{ __('select.selected') }}</span>
            </div>
          </div>
        </div>

        <div class="select-list">
            
        </div>

        <!-- Multi-select action buttons -->
        <div class="select-actions" style="display: none;">
          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fa fa-arrow-left"></i> {{ __('global.cancel') }}
            </button>
            <button type="button" class="btn btn-primary add-selected-btn" disabled>
              <i class="fa fa-plus"></i> {{ __('select.add-selected') }} (<span class="selected-count-text">0</span>)
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
  $('.select-clear-search').click(function(){
    $('.select-search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
  });

  // Multi-select functionality
  let selectedItems = [];
  let currentSelectFunction = null;
  let isMultiSelectMode = false;

  // Select All functionality
  $('.select-all-btn').click(function(){
    $('.select-modal-item:not(.hidden)').each(function(){
      if (!$(this).hasClass('selected')) {
        $(this).addClass('selected');
        updateSelectedItems();
      }
    });
  });

  // Clear All functionality
  $('.clear-all-btn').click(function(){
    $('.select-modal-item').removeClass('selected');
    updateSelectedItems();
  });

  // Add Selected button functionality
  $('.add-selected-btn').click(function(){
    if (selectedItems.length > 0 && currentSelectFunction) {
      // Process each selected item
      selectedItems.forEach(function(item) {
        // Create a temporary jQuery object with the item data
        let $tempItem = $('<div>').attr({
          'data-id': item.id,
          'data-name': item.name
        });
        
        // Call the select function in the context of the temp item
        currentSelectFunction.call($tempItem[0]);
      });

      // Clear selections and close modal
      clearSelections();
      $('#select-modal').modal('hide');
    }
  });

  function updateSelectedItems() {
    selectedItems = [];
    $('.select-modal-item.selected').each(function(){
      selectedItems.push({
        id: $(this).attr('data-id'),
        name: $(this).attr('data-name')
      });
    });

    const count = selectedItems.length;
    $('.selected-count .badge').text(count + ' {{ __('select.selected') }}');
    $('.selected-count-text').text(count);
    $('.add-selected-btn').prop('disabled', count === 0);

    // Update visual state
    if (count > 0) {
      $('.add-selected-btn').removeClass('btn-secondary').addClass('btn-primary');
    } else {
      $('.add-selected-btn').removeClass('btn-primary').addClass('btn-secondary');
    }
  }

  function clearSelections() {
    selectedItems = [];
    $('.select-modal-item').removeClass('selected');
    updateSelectedItems();
  }

  // Enhanced openSelectModal function with body padding fix
  window.openSelectModal = function({
    title, ajaxRoute,
    parentSelector = null,
    itemData = function(item){ return {
      id: item.id,
      name: item.name,
      top: null,
      bottom: null,
    }; },
    selectFunction = function(){},
    exceptArray = [],
    exceptFunction = function(item){
      return !exceptArray.includes(item.id);
    },
    emptyMessage = '',
    multiSelect = true  // Enable multi-select by default
  }){
    $('#select-modal .modal-title').html(title);
    
    // Store the select function and mode
    currentSelectFunction = selectFunction;
    isMultiSelectMode = multiSelect;
    
    // UPDATED: Don't hide parent modal, just increase z-index for layering
    if(parentSelector !== null){
      // Set higher z-index for select modal to appear on top
      $('#select-modal').css('z-index', '1060');
      $(parentSelector).css('z-index', '1050');
    }
    
    swal_loader.fire();

    $.ajax({
      url: ajaxRoute,
    })
    .done(function(response){
      $('.select-list').html('');
      $('.select-search-input').val('');
      $('.select-search-input').prop('readonly', false);
      clearSelections();

      response = response.filter(exceptFunction);

      if(response.length == 0){
        $(".select-list").append('<div class="select-modal-empty"><p>'+emptyMessage+'</p><button class="btn btn-outline-secondary" data-dismiss="modal">{{ __('global.back') }}</button></div>');
        $('.select-search-input').prop('readonly', true);
        $('.select-controls').hide();
        $('.select-actions').hide();
      } else {
        response.forEach((item) => {
          var data = itemData(item);
          var html = '<div class="select-modal-item" data-id="'+data.id+'" data-name="'+data.name+'">';
          
          html += '<div class="item-content">';
          if(typeof data.top != 'undefined' && data.top !== null){
            html+='<span>'+data.top+'</span>';
          }
          html+='<p>'+data.name+'</p>';
          if(typeof data.bottom != 'undefined' && data.bottom !== null){
            html+='<span>'+data.bottom+'</span>';
          }
          html += '</div>';
          html+='</div>';
          $(".select-list").append(html);
        });

        // Show/hide controls based on mode
        if (multiSelect) {
          $('.select-controls').show();
          $('.select-actions').show();
        } else {
          $('.select-controls').hide();
          $('.select-actions').hide();
        }
      }

      swal_loader.close();
      
      // ENHANCED: Modal close event handler with body padding fix
      $("#select-modal").off('hidden.bs.modal');
      $("#select-modal").on('hidden.bs.modal', function (e) {
        // Reset z-index when select modal closes (existing functionality)
        if(parentSelector !== null){
          $(parentSelector).css('z-index', '');
          $('#select-modal').css('z-index', '');
        }
        
        // FIX: Force remove body padding-right that Bootstrap sometimes leaves behind
        $('body').css('padding-right', '');
        
        // FIX: Ensure modal-open class is removed (safety check)
        $('body').removeClass('modal-open');
        
        // FIX: Check if any other modals are still open
        // If no other modals are open, ensure body is properly reset
        setTimeout(function() {
          if ($('.modal.show').length === 0) {
            $('body').css({
              'padding-right': '',
              'overflow': ''
            });
            $('body').removeClass('modal-open');
          }
        }, 50); // Small delay to ensure Bootstrap has finished its cleanup
      });

      // Handle item clicks
      $(document).undelegate('.select-modal-item','click');
      if (multiSelect) {
        // In multi-select mode, clicking toggles selection
        $(document).delegate('.select-modal-item','click', function(e) {
          $(this).toggleClass('selected');
          updateSelectedItems();
        });
      } else {
        // In single-select mode, use original behavior
        $(document).delegate('.select-modal-item','click', selectFunction);
      }

      // Handle search
      $(".select-search-input").off('keyup');
      $(".select-search-input").on('keyup', function(e){
        if(e.keyCode != 13){ return; }
        swal_loader.fire();
        search = $(this).val().toLowerCase();

        $('.select-modal-item').addClass('hidden');
        $('.select-modal-empty').remove();

        $('.select-modal-item').each(function(){
          if($(this).attr('data-name').toLowerCase().includes(search)){
            $(this).removeClass('hidden');
          }
        });

        if($('.select-modal-item:not(.hidden)').length == 0){
          $(".select-list").append('<div class="select-modal-empty"><p>'+emptyMessage+'</p><button class="btn btn-outline-secondary" data-dismiss="modal">{{ __('global.back') }}</button></div>');
        }
        swal_loader.close();
      });

      $("#select-modal").modal();
    });
  };

  // GLOBAL FIX: Add this general handler for all modals to prevent body padding issues
  $(document).on('hidden.bs.modal', '.modal', function () {
    // Only reset body styles if no other modals are open
    setTimeout(function() {
      if ($('.modal.show').length === 0) {
        $('body').css({
          'padding-right': '',
          'overflow': ''
        });
        $('body').removeClass('modal-open');
      }
    }, 50); // Small delay to ensure Bootstrap has finished its cleanup
  });
});
</script>

<style>
/* Enhanced styles for multi-select functionality - NO CHECKBOXES */
#select-modal .select-controls {
  padding: 0.75rem;
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  margin-bottom: 0.5rem;
}

#select-modal .select-actions {
  padding-top: 1rem;
  border-top: 1px solid #dee2e6;
  margin-top: 1rem;
}

#select-modal .select-modal-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border-left: 3px solid var(--silver_chalice);
  color: var(--silver_chalice);
  cursor: pointer;
  transition: all 0.3s ease;
  margin-bottom: 0.25rem;
  border: 2px solid transparent;
}

#select-modal .select-modal-item:hover {
  color: var(--outer_space);
  border-left-color: var(--outer_space);
  background-color: var(--dim);
}

/* UPDATED: Enhanced selected state styling */
#select-modal .select-modal-item.selected {
  background: linear-gradient(45deg, #e3f2fd, #f3e5f5);
  border: 2px solid #2196f3;
  color: #1976d2;
  font-weight: 600;
  box-shadow: 0 4px 8px rgba(33, 150, 243, 0.2);
}

#select-modal .select-modal-item.selected::before {
  content: '✓';
  position: absolute;
  right: 1rem;
  font-size: 1.2rem;
  color: #2196f3;
  font-weight: bold;
}

#select-modal .select-modal-item {
  position: relative; /* For the checkmark positioning */
}

#select-modal .item-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-start;
  text-align: left;
}

#select-modal .item-content span {
  font-size: 0.9em;
  font-weight: normal;
  font-style: italic;
}

#select-modal .item-content p {
  font-size: 1.1em;
  font-weight: bold;
  margin: 0;
}

#select-modal .selected-count .badge {
  font-size: 0.85rem;
  padding: 0.375rem 0.75rem;
}

#select-modal .add-selected-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

/* UPDATED: Modal layering styles */
.modal.show {
  display: block !important;
}

.modal-backdrop {
  opacity: 0.5;
}

/* Ensure select modal appears above parent modal */
#select-modal.modal {
  z-index: 1060 !important;
}

#select-modal .modal-backdrop {
  z-index: 1055 !important;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
  #select-modal .select-controls .d-flex {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  #select-modal .select-actions .d-flex {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  #select-modal .select-actions .btn {
    width: 100%;
  }
}
</style>