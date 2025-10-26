<script>
// Check if we're on mobile (below 768px)
function isMobile() {
  return window.innerWidth < 768;
}

// Store available employees for mobile
var mobileEmployeePool = [];

// Initialize mobile employee pool
function initMobileEmployeePool() {
  if (!isMobile()) return;
  
  mobileEmployeePool = [];
  $('.employee-list .employee').each(function() {
    mobileEmployeePool.push({
      id: $(this).attr('data-id'),
      name: $(this).attr('data-name') || $(this).find('p').text()
    });
  });
}

// Desktop drag-and-drop validation
function isEveryoneRanked(){
  if($('.employee-list .employee').length == 0){
    $('.save-ranks').removeClass('hidden');
  }else{
    $('.save-ranks').addClass('hidden');
  }
}

// Mobile: Check if all employees are ranked
function isMobileEveryoneRanked() {
  if (mobileEmployeePool.length == 0) {
    $('.save-ranks').removeClass('hidden');
  } else {
    $('.save-ranks').addClass('hidden');
  }
}

// Mobile: Update available employees in dropdown
function updateMobileSelect($select) {
  if (!isMobile()) return;
  
  $select.empty();
  
  if (mobileEmployeePool.length === 0) {
    // No more employees to assign
    $select.closest('.mobile-employee-select').removeClass('show').slideUp(200);
    $select.closest('.rank').removeClass('mobile-active');
    return;
  }
  
  // Add available employees to select
  mobileEmployeePool.forEach(function(emp) {
    var $option = $('<option></option>')
      .attr('value', emp.id)
      .text(emp.name);
    $select.append($option);
  });
}

// Mobile: Add employee to category instantly
function addEmployeeToCategory($categoryDiv, employeeId) {
  // Find employee in pool
  var empIndex = mobileEmployeePool.findIndex(e => e.id == employeeId);
  if (empIndex === -1) return false;
  
  var employee = mobileEmployeePool[empIndex];
  
  // Check max constraint
  var max = $categoryDiv.attr('data-max');
  var currentCount = $categoryDiv.find('.employee').length;
  if (max !== 'none' && parseInt(max) <= currentCount) {
    swal_warning.fire({ title: '{{ __('ceorank.max-warning') }}' });
    return false;
  }
  
  // Create employee element for category
  var $newEmployee = $('<div class="tile tile-info employee"></div>')
    .attr('data-id', employee.id)
    .attr('data-name', employee.name)
    .attr('draggable', 'true');
  
  $newEmployee.append('<p>' + employee.name + '</p>');
  
  // Add remove button for mobile
  if (isMobile()) {
    $newEmployee.append('<i class="fa fa-times mobile-remove"></i>');
  }
  
  $categoryDiv.append($newEmployee);
  
  // Remove from pool
  mobileEmployeePool.splice(empIndex, 1);
  
  // Check if all ranked
  if (isMobile()) {
    isMobileEveryoneRanked();
  } else {
    isEveryoneRanked();
  }
  
  return true;
}

// Mobile: Remove employee from category
function removeEmployeeFromCategory($employee) {
  var employeeId = $employee.attr('data-id');
  var employeeName = $employee.attr('data-name') || $employee.find('p').text().replace(/×$/, '').trim();
  
  if (isMobile()) {
    // Add back to mobile pool
    mobileEmployeePool.push({
      id: employeeId,
      name: employeeName
    });
    
    // Sort pool by name for consistency
    mobileEmployeePool.sort((a, b) => a.name.localeCompare(b.name));
    
    $employee.remove();
    isMobileEveryoneRanked();
    
    // Update all open dropdowns
    $('.mobile-employee-select.show .mobile-select').each(function() {
      updateMobileSelect($(this));
    });
  } else {
    // Desktop: add back to visible list
    var $newEmployee = $('<div class="tile tile-info employee"></div>')
      .attr('data-id', employeeId)
      .attr('data-name', employeeName)
      .attr('draggable', 'true');
    
    $newEmployee.append('<p>' + employeeName + '</p>');
    attachDragHandlers($newEmployee);
    
    $('.employee-list .save-ranks').before($newEmployee);
    $employee.remove();
    isEveryoneRanked();
  }
}

// Attach drag handlers for desktop
function attachDragHandlers($element) {
  $element.on('dragstart', function(ev) {
    $(this).css('opacity', '0.4');
    ev.originalEvent.dataTransfer.setData('text', $(this).attr('data-id'));
  });
  
  $element.on('dragend', function(ev) {
    $(this).css('opacity', '1');
  });
}

$(document).ready(function() {
  
  // ===================================
  // DESKTOP DRAG & DROP
  // ===================================
  if (!isMobile()) {
    $('.employee').on('dragstart', function(ev){
      $(this).css('opacity', '0.4');
      ev.originalEvent.dataTransfer.setData('text', $(this).attr('data-id'));
    });

    $('.employee').on('dragend', function(ev){
      $(this).css('opacity', '1');
    });

    $('.employees, .employee-list').on('drop', function(ev){
      ev.preventDefault();

      var max = $(this).attr('data-max');
      var count = $(this).find('.employee').length;
      if(max != 'none' && max < count+1 ){
        swal_warning.fire({ title: '{{ __('ceorank.max-warning') }}'});
        return false;
      }

      var id = ev.originalEvent.dataTransfer.getData("text");
      $(this).append($('.employee[data-id="'+id+'"]'));

      isEveryoneRanked();
    });

    $('.employees, .employee-list').on('dragover', function(ev){
      ev.preventDefault();
    });
  }
  
  // ===================================
  // MOBILE DROPDOWN INTERFACE
  // ===================================
  if (isMobile()) {
    // Initialize mobile employee pool
    initMobileEmployeePool();
    
    // Handle category click on mobile
    $('.rank').on('click', function(e) {
      if (!isMobile()) return;
      
      // Don't trigger if clicking on an employee or remove button
      if ($(e.target).closest('.employee, .mobile-remove, .mobile-employee-select').length > 0) {
        return;
      }
      
      // Don't open if no employees available
      if (mobileEmployeePool.length === 0) {
        return;
      }
      
      var $rank = $(this);
      var $select = $rank.find('.mobile-employee-select');
      var $selectElement = $rank.find('.mobile-select');
      
      // Toggle this category's dropdown
      if ($select.hasClass('show')) {
        $select.removeClass('show').slideUp(200);
        $rank.removeClass('mobile-active');
      } else {
        // Close all other dropdowns first
        $('.mobile-employee-select.show').removeClass('show').slideUp(200);
        $('.rank').removeClass('mobile-active');
        
        // Open this one
        updateMobileSelect($selectElement);
        $select.addClass('show').slideDown(200);
        $rank.addClass('mobile-active');
      }
    });
    
    // Handle instant selection on option click
    $(document).on('change', '.mobile-select', function(e) {
      e.stopPropagation();
      
      var $select = $(this);
      var $categoryDiv = $select.closest('.rank').find('.employees');
      var selectedValue = $select.val();
      
      if (!selectedValue || selectedValue.length === 0) return;
      
      // Add the selected employee instantly
      var empId = Array.isArray(selectedValue) ? selectedValue[0] : selectedValue;
      if (addEmployeeToCategory($categoryDiv, empId)) {
        // Update the select dropdown
        updateMobileSelect($select);
        
        // If no more employees, close dropdown
        if (mobileEmployeePool.length === 0) {
          $select.closest('.mobile-employee-select').removeClass('show').slideUp(200);
          $select.closest('.rank').removeClass('mobile-active');
        } else {
          // Clear selection to allow picking another
          $select.val([]);
        }
      }
    });
    
    // Handle remove button click on mobile
    $(document).on('click', '.mobile-remove', function(e) {
      e.stopPropagation();
      removeEmployeeFromCategory($(this).closest('.employee'));
    });
  }
  
  // ===================================
  // SAVE RANKING (BOTH DESKTOP & MOBILE)
  // ===================================
  $('.save-ranks').click(function(){
    swal_confirm.fire({
      title: '{{ __('ceorank.save-ranks-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();

        var ranks = [];
        var flag = false;
        $('.employees').each(function(){
          var obj = { 
            rankId: $(this).attr('data-id'),
            employees: [],
          };
          
          var min = $(this).attr('data-min');
          var count = $(this).find('.employee').length;
          if(min != 'none' && min*1 > count ){
            swal_warning.fire({ title: '{{ __('ceorank.min-warning') }}'});
            flag = true;
            return;
          }

          $(this).find('.employee').each(function(){
            obj.employees.push($(this).attr('data-id'));
          });

          ranks.push(obj);
        });
        if(flag){
          return;
        }
        $.ajax({
          url: "{{ route('ceorank.submit') }}",
          data: {
            ranks: ranks
          },
          successMessage: "{{ __('ceorank.save-ranks-success') }}",
          successUrl: "{{ route('home') }}"
        });
      }
    });
  });
  
  // ===================================
  // RESPONSIVE HANDLER
  // ===================================
  var lastWidth = $(window).width();
  $(window).on('resize', function() {
    var currentWidth = $(window).width();
    var wasDesktop = lastWidth >= 768;
    var isNowMobile = currentWidth < 768;
    
    // If switched from desktop to mobile or vice versa
    if (wasDesktop && isNowMobile) {
      location.reload(); // Reload to properly initialize mobile interface
    } else if (!wasDesktop && !isNowMobile) {
      location.reload(); // Reload to properly initialize desktop interface  
    }
    
    lastWidth = currentWidth;
  });
});
</script>