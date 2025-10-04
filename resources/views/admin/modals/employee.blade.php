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
      $('#employee-modal').attr('data-id', 0);
      $('#employee-modal .modal-title').html('{{ __('admin/employees.new-employee') }}');
      $('#employee-modal .trigger-submit').html('{{ __('admin/employees.new-employee') }}');

      $('#employee-modal .name').val('');
      $('#employee-modal .email').val('').prop('readonly', false);
      $('#employee-modal .type').val('normal');
      $('#employee-modal .auto-level-up').prop('checked', false);
      $('#employee-modal .position').val('');

      clearTypeLocks();

      swal_loader.close();
      $('#employee-modal').modal();
    } else {
      $('#employee-modal').attr('data-id', uid);
      $('#employee-modal .modal-title').html('{{ __('admin/employees.modify-employee') }}');
      $('#employee-modal .trigger-submit').html('{{ __('admin/employees.modify') }}');

      $.ajax({
        method: 'POST',                       // route POST, nem GET
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
          .done(function(){
            swal_loader.close();
            const msg = (uid && parseInt(uid) > 0)
              ? "{{ __('admin/employees.modify-employee-success') }}"
              : "{{ __('admin/employees.new-employee-success') }}";
            Swal.fire('{{ __('global.success') }}', msg, 'success').then(() => window.location.reload());
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