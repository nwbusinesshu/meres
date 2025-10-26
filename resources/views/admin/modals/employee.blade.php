{{-- resources/views/admin/modals/employee.blade.php --}}
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="employee-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"></h5>
          <small class="modal-subtitle text-muted" style="display: none;"></small>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>{{ __('global.name') }}</label>
            <input type="text" class="form-control name">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>{{ __('global.email') }}</label>
            <input type="email" class="form-control email">
          </div>
        </div>
        @php
        use Illuminate\Support\Facades\Auth;
        use App\Models\Enums\OrgRole;  // ✅ CHANGED: Use OrgRole instead of UserType
        use App\Services\AssessmentService;
        $user = Auth::user();
        $orgId = session('org_id');
        $orgRole = session('org_role');  // ✅ ADDED: Get org role from session
        $isSuperadmin = $user && $user->type === 'superadmin';  // ✅ FIXED: Direct string check
        $isAdmin = $orgRole === OrgRole::ADMIN;  // ✅ ADDED: Check if current user is admin
        $hasOrg = session()->has('org_id');
        $enableMultiLevel = \App\Services\OrgConfigService::getBool((int)session('org_id'), 'enable_multi_level', false);
        $showBonuses = false;
        if ($isAdmin && $orgId) {  // ✅ CHANGED: Use $isAdmin instead of MyAuth
            $showBonusMalus = \App\Services\OrgConfigService::getBool((int)$orgId, 'show_bonus_malus', true);
            $enableBonusCalculation = \App\Services\OrgConfigService::getBool((int)$orgId, 'enable_bonus_calculation', false);
            $showBonuses = $showBonusMalus && $enableBonusCalculation;
        }
        @endphp

        <div class="form-row" data-enable-multi="{{ $enableMultiLevel ? '1' : '0' }}">
          <div class="form-group">
            <label>{{ __('global.type') }}</label>
            <select class="form-control type">
              {{-- ✅ FIXED: Changed "normal" to "employee" to match OrgRole enum --}}
              <option value="employee">{{ __('usertypes.normal') }}</option>
              {{-- ✅ ADDED: Admin option (only visible to admins) --}}
              @if($enableMultiLevel)
                <option value="manager">{{ __('usertypes.manager') }}</option>
              @endif
              <option value="ceo">{{ __('usertypes.ceo') }}</option>
            </select>
             <small class="form-text text-muted type-help d-none"></small>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>{{ __('admin/employees.position') }}</label>
            <input type="text" class="form-control position">
          </div>

          {{-- WAGE INPUT SECTION --}}
          @if($showBonuses)
          <div class="form-group">
            <label>{{ __('admin/bonuses.net-wage') }}</label>
            <div class="input-group">
                <input type="number" 
                       class="form-control" 
                       id="user-wage" 
                       placeholder="500000" 
                       step="1000"
                       min="0">
                <select class="form-control" id="user-currency" style="max-width: 100px;">
                    <option value="HUF">HUF</option>
                    <option value="EUR">EUR</option>
                    <option value="USD">USD</option>
                    <option value="GBP">GBP</option>
                    <option value="RON">RON</option>
                </select>
            </div>
            <small class="form-text text-muted">
                {{ __('admin/bonuses.wage-help-text') }}
            </small>
          </div>
          @endif
        </div>

        
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary trigger-submit"></button>
        <button class="btn btn-sm btn-outline-success trigger-mass-import">
        <i class="fa fa-file-upload"></i> {{ __('admin/employees.mass-import') }}
    </button>
      </div>     
    </div>
  </div>
</div>

<script>
  // -- Segédfüggvények a típus-korlátozásokhoz -------------------------------

  function ensureTypeHintEl() {
    const $group = $('#employee-modal .type').closest('.form-group');
    if (!$group.find('.type-hint').length) {
      $group.append('<small class="form-text text-muted type-hint d-none"></small>');
    }
    return $group.find('.type-hint');
  }

  function clearTypeLocks() {
    const $type = $('#employee-modal .type');
    const $hint = ensureTypeHintEl();

    // minden tiltás feloldása
    $type.prop('disabled', false);
    $type.find('option').prop('disabled', false);

    // hint elrejtése
    $hint.text('').addClass('d-none');
  }

  function applyTypeLocksFromResponse(response) {
    const $type = $('#employee-modal .type');
    const $hint = ensureTypeHintEl();

    // Alap: minden szabad; aztán ráhúzzuk a tiltást a szabályok szerint
    clearTypeLocks();

    // ✅ UPDATED: Check for 'manager' role instead of 'manager' type
    // 1) Ha részlegvezető (és role=manager): teljes tiltás
    if (response.is_dept_manager && response.type === 'manager') {
      $type.val('manager');
      $type.prop('disabled', true);
      $hint
        .text('{{ __('admin/employees.type-locked-dept-manager') }}')
        .removeClass('d-none');
      return; // a teljes tiltás mindent lefed
    }

    // ✅ UPDATED: Check for 'employee' role instead of 'normal' type
    // 2) Ha részlegtag és employee: teljes tiltás
    if (response.is_in_department && response.type === 'employee') {
      // biztos ami biztos: employee-re állítjuk és lezárjuk
      $type.val('employee');
      $type.prop('disabled', true);
      $hint
        .text('{{ __('admin/employees.type-locked-dept-member') }}')
        .removeClass('d-none');
      return;
    }

    // Egyéb esetben nincs tiltás
  }

  // -- Modal megnyitása -------------------------------------------------------

  function openEmployeeModal(uid = null){
    swal_loader.fire();

    if(uid == null){
      // ========== NEW EMPLOYEE ==========
      $('#employee-modal').attr('data-id', 0);
      $('#employee-modal .modal-title').html('{{ __('admin/employees.new-employee') }}');
      $('#employee-modal .modal-subtitle').hide(); // ✅ HIDE subtitle when creating new
      $('#employee-modal .trigger-submit').html('{{ __('admin/employees.new-employee') }}');

      $('#employee-modal .name').val('');
      $('#employee-modal .email').val('').prop('readonly', false);
      $('#employee-modal .type').val('employee');  // ✅ CHANGED: Default to 'employee' instead of 'normal'
      $('#employee-modal .auto-level-up').prop('checked', false);
      $('#employee-modal .position').val('');
      
      $('#employee-modal #user-wage').val('');
      $('#employee-modal #user-currency').val('HUF');

      clearTypeLocks();

      swal_loader.close();
      $('#employee-modal').modal();
    } else {
      // ========== EDIT EXISTING EMPLOYEE ==========
      $('#employee-modal').attr('data-id', uid);
      $('#employee-modal .modal-title').html('{{ __('admin/employees.modify-employee') }}');
      $('#employee-modal .trigger-submit').html('{{ __('admin/employees.modify') }}');

      $.ajax({
        method: 'POST',
        url: "{{ route('admin.employee.get') }}",
        data: {
          id: uid,
          _token: "{{ csrf_token() }}"
        }
      })
      .done(function(response){
        $('#employee-modal .name').val(response.name);
        $('#employee-modal .email').val(response.email).prop('readonly', true);
        
        // ✅ ADDED: Show subtitle with user's name when editing
        $('#employee-modal .modal-subtitle').text(response.name).show();
        
        // ✅ FIXED: response.type now returns OrgRole values ('employee', 'admin', 'manager', 'ceo')
        $('#employee-modal .type').val(response.type);
        
        $('#employee-modal .auto-level-up').prop('checked', response.has_auto_level_up == 1);
        $('#employee-modal .position').val(response.position || '');

        // Load wage data
        $.ajax({
          method: 'POST',
          url: "{{ route('admin.bonuses.wage.get') }}",
          data: {
            user_id: uid,
            _token: "{{ csrf_token() }}"
          }
        })
        .done(function(wageResponse){
          if (wageResponse.ok && wageResponse.wage) {
            $('#employee-modal #user-wage').val(wageResponse.wage.net_wage);
            $('#employee-modal #user-currency').val(wageResponse.wage.currency);
          } else {
            $('#employee-modal #user-wage').val('');
            $('#employee-modal #user-currency').val('HUF');
          }
        })
        .fail(function(){
          // Silently fail - wage is optional
          $('#employee-modal #user-wage').val('');
          $('#employee-modal #user-currency').val('HUF');
        });

        // Üzleti tiltások (részlegvezető / részlegtag)
        applyTypeLocksFromResponse(response);

        swal_loader.close();
        $('#employee-modal').modal();
      })
      .fail(function(){
        swal_loader.close();
        Swal.fire('{{ __('global.error') }}', '{{ __('admin/employees.error-loading-employee') }}', 'error');
      });
    }
  }

  // -- Mentés gomb ------------------------------------------------------------

$(document).ready(function(){
  $('.trigger-submit').click(function(){
    const uid = $('#employee-modal').attr('data-id');

    swal_confirm.fire({
      title: uid && parseInt(uid) > 0
        ? '{{ __('admin/employees.modify-employee-confirm') }}'
        : '{{ __('admin/employees.new-employee-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();

        // ========== STEP 1: SAVE EMPLOYEE ==========
        $.ajax({
          method: 'POST',
          url: "{{ route('admin.employee.save') }}",
          data: {
            id: uid,
            name:  $('#employee-modal .name').val(),
            email: $('#employee-modal .email').val(),
            type:  $('#employee-modal .type').val(),  // ✅ Now sends OrgRole values
            position: $('#employee-modal .position').val(),
            autoLevelUp:  $('#employee-modal .auto-level-up').is(':checked') ? 1 : 0,
            _token: "{{ csrf_token() }}"
          }
        })
        .done(function(response){
          const netWage = $('#employee-modal #user-wage').val();
          const currency = $('#employee-modal #user-currency').val();

          // ========== STEP 2: SAVE WAGE (if provided) ==========
          if (netWage && parseFloat(netWage) > 0) {
            const userIdForWage = uid && parseInt(uid) > 0 ? uid : response.user_id;

            console.log('💰 Saving wage:', {
              user_id: userIdForWage,
              net_wage: netWage,
              currency: currency
            });

            $.ajax({
              method: 'POST',
              url: "{{ route('admin.bonuses.wage.save') }}",
              data: {
                user_id: userIdForWage,
                net_wage: netWage,
                currency: currency,
                _token: "{{ csrf_token() }}"
              },
              success: function(wageResponse){
                console.log('✅ Wage saved successfully:', wageResponse);
                $('#employee-modal').modal('hide');
              },
              successMessage: uid && parseInt(uid) > 0
                ? '{{ __('admin/employees.modify-employee-success') }}'
                : '{{ __('admin/employees.new-employee-success') }}',
              error: function(xhr){
                swal_loader.close();
                let wageErrorMsg = '{{ __('admin/bonuses.wage-save-error') }}';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                  wageErrorMsg = xhr.responseJSON.message;
                }
                // Show warning but still close modal and reload
                Swal.fire({
                  icon: 'warning',
                  title: '{{ __('global.warning') }}',
                  html: '{{ __('admin/employees.employee-saved-wage-failed') }}<br><br>' + wageErrorMsg
                }).then(() => {
                  $('#employee-modal').modal('hide');
                  window.location.reload();
                });
              }
            });
          } else {
            // No wage - close modal and reload
            $('#employee-modal').modal('hide');
            
            // Manually trigger reload with session flash
            sessionStorage.setItem('employee_save_toast', uid && parseInt(uid) > 0
              ? '{{ __('admin/employees.modify-employee-success') }}'
              : '{{ __('admin/employees.new-employee-success') }}');
            window.location.reload();
          }
        })
        .fail(function(xhr){
          swal_loader.close();
          let msg = '{{ __('admin/employees.error-server') }}';
          if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
          Swal.fire('{{ __('global.error') }}', msg, 'error');
          // Modal stays open on error
        });
      }
    });
  });
});

// Show toast after reload
$(document).ready(function(){
  const toastMsg = sessionStorage.getItem('employee_save_toast');
  if (toastMsg) {
    sessionStorage.removeItem('employee_save_toast');
    toast('success', toastMsg);
  }
});
</script>
<script>
$(document).on('click', '.trigger-mass-import', function(){
    $('#employee-modal').modal('hide');
    $('#employee-import-modal').modal('show');
});

</script>