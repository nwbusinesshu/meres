<div class="modal" id="modal-org-edit">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">{{ __('titles.superadmin.edit-org') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <form method="POST" id="form-org-edit">
        @csrf
        <input type="hidden" name="org_id" id="edit-org-id">
        <input type="hidden" name="admin_remove" id="admin-remove" value="0">

        <div class="modal-body">
          <div class="form-group">
            <label for="edit-org-name">{{ __('global.name') }}</label>
            <input type="text" name="name" id="edit-org-name" class="form-control" required>
          </div>

          @if (isset($admin) && $admin)
          <div class="form-group" id="current-admin">
            <label>{{ __('global.admin') }}</label>
            <div class="alert alert-light d-flex justify-content-between align-items-center">
              <div>
                <strong>{{ $admin->name }}</strong><br>
                <small>{{ $admin->email }}</small>
              </div>
              <button type="button" class="btn btn-sm btn-danger" id="remove-admin-btn"><i class="fa fa-times"></i></button>
            </div>
          </div>
          @endif

          <div id="new-admin-fields" class="{{ isset($admin) && $admin ? 'd-none' : '' }}">
            <div class="form-group">
              <label for="admin-name">{{ __('global.admin-name') }}</label>
              <input type="text" name="admin_name" id="admin-name" class="form-control">
            </div>
            <div class="form-group">
              <label for="admin-email">{{ __('global.admin-email') }}</label>
              <input type="email" name="admin_email" id="admin-email" class="form-control">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('global.cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('global.save') }}</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
  $(document).on('click', '#remove-admin-btn', function () {
    $('#current-admin').remove(); // Eltávolítjuk a megjelenített admin blokkot
    $('#new-admin-fields').removeClass('d-none'); // Előhozzuk az új admin inputokat
    $('#admin-remove').val('1'); // Backendnek jelezzük, hogy törölni kell
  });
</script>
