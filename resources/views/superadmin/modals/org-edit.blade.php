<div class="modal fade" id="modal-org-edit">
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

          {{-- Cégnév --}}
          <div class="form-group">
            <label for="edit-org-name">{{ __('global.name') }}</label>
            <input type="text" name="org_name" id="edit-org-name" class="form-control" required>
          </div>

          {{-- Adószám --}}
          <div class="form-group">
            <label for="edit-tax-number">{{ __('global.tax-number') }}</label>
            <input type="text" name="tax_number" id="edit-tax-number" class="form-control">
          </div>

          {{-- Számlázási cím --}}
          <div class="form-group">
            <label for="edit-billing-address">{{ __('global.billing-address') }}</label>
            <input type="text" name="billing_address" id="edit-billing-address" class="form-control">
          </div>

          {{-- Előfizetés típus --}}
          <div class="form-group">
            <label for="edit-subscription-type">{{ __('global.subscription-type') }}</label>
            <select name="subscription_type" id="edit-subscription-type" class="form-control">
              <option value="">-- {{ __('global.select') }} --</option>
              <option value="free">{{ __('global.subscription-free') }}</option>
              <option value="pro">{{ __('global.subscription-pro') }}</option>
            </select>
          </div>

          {{-- Admin blokk --}}
          <div class="form-group d-none" id="current-admin">
            <label>{{ __('global.admin') }}</label>
            <div class="alert alert-light d-flex justify-content-between align-items-center">
              <div>
                <strong id="admin-current-name">N/A</strong><br>
                <small id="admin-current-email">N/A</small>
              </div>
              <button type="button" class="btn btn-sm btn-danger" id="remove-admin-btn">
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>

          {{-- Új admin mezők --}}
          <div id="new-admin-fields" class="d-none">
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
