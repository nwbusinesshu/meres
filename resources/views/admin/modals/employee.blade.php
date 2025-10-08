{{-- resources/views/admin/modals/employee.blade.php --}}
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="employee-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"></h5>
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
          $enableMultiLevel = \App\Services\OrgConfigService::getBool((int)session('org_id'), 'enable_multi_level', false);
        @endphp

        <div class="form-row" data-enable-multi="{{ $enableMultiLevel ? '1' : '0' }}">
          <div class="form-group">
            <label>{{ __('global.type') }}</label>
            <select class="form-control type">
              <option value="normal">{{ __('usertypes.normal') }}</option>
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
        </div>

        
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary trigger-submit"></button>
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

    // 1) Ha részlegvezető (és type=manager): teljes tiltás
    if (response.is_dept_manager && response.type === 'manager') {
      $type.val('manager');
      $type.prop('disabled', true);
      $hint
        .text('{{ __('admin/employees.type-locked-dept-manager') }}')
        .removeClass('d-none');
      return; // a teljes tiltás mindent lefed
    }

    // 2) Ha részlegtag és normal: teljes tiltás (kért módosítás)
    if (response.is_in_department && response.type === 'normal') {
      // biztos ami biztos: normalra állítjuk és lezárjuk
      $type.val('normal');
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
      $('#employee-modal .trigger-submit').html('{{ __('admin/employees.new-employee') }}');

      $('#employee-modal .name').val('');
      $('#employee-modal .email').val('').prop('readonly', false);
      $('#employee-modal .type').val('normal');
      $('#employee-modal .auto-level-up').prop('checked', false);
      $('#employee-modal .position').val('');
      
      // ✅ FIX: Use correct ID selectors
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
            // ✅ FIX: Use correct ID selectors
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
              type:  $('#employee-modal .type').val(),
              position: $('#employee-modal .position').val(),
              autoLevelUp:  $('#employee-modal .auto-level-up').is(':checked') ? 1 : 0,
              _token: "{{ csrf_token() }}"
            }
          })
          // ✅ FIX: Add response parameter to capture user_id
          .done(function(response){
            const msg = (uid && parseInt(uid) > 0)
              ? "{{ __('admin/employees.modify-employee-success') }}"
              : "{{ __('admin/employees.new-employee-success') }}";

            // ✅ FIX: Use correct ID selectors
            const netWage = $('#employee-modal #user-wage').val();
            const currency = $('#employee-modal #user-currency').val();

            // ========== STEP 2: SAVE WAGE (if provided) ==========
            if (netWage && parseFloat(netWage) > 0) {
              // ✅ FIX: Use response.user_id for new employees
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
                }
              })
              .done(function(wageResponse){
                console.log('✅ Wage saved successfully:', wageResponse);
                swal_loader.close();
                Swal.fire('{{ __('global.success') }}', msg, 'success').then(() => window.location.reload());
              })
              .fail(function(xhr){
                console.error('❌ Wage save failed:', xhr.responseJSON);
                swal_loader.close();
                let wageErrorMsg = '{{ __('admin/bonuses.wage-save-error') }}';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                  wageErrorMsg = xhr.responseJSON.message;
                }
                Swal.fire('{{ __('global.warning') }}', msg + '<br><br>' + wageErrorMsg, 'warning')
                  .then(() => window.location.reload());
              });
            } else {
              // No wage data - just show success and reload
              swal_loader.close();
              Swal.fire('{{ __('global.success') }}', msg, 'success').then(() => window.location.reload());
            }
          })
          .fail(function(xhr){
            swal_loader.close();
            let msg = '{{ __('admin/employees.error-server') }}';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            Swal.fire('{{ __('global.error') }}', msg, 'error');
          });
        }
      });
    });
  });
</script>