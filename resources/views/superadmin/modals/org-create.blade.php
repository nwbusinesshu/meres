<div class="modal fade" id="modal-org-create">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">{{ __('titles.superadmin.new-org') }}</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <form id="form-org-create" method="POST" action="{{ route('superadmin.org.store') }}">
        @csrf
        <div class="modal-body">
          {{-- Cégnév --}}
          <div class="form-group">
            <label for="org-name">{{ __('global.name') }}</label>
            <input type="text" name="org_name" id="org-name" class="form-control" required>
          </div>

          {{-- Adószám --}}
          <div class="form-group">
            <label for="tax-number">{{ __('global.tax-number') }}</label>
            <input type="text" name="tax_number" id="tax-number" class="form-control">
          </div>

          {{-- Számlázási cím --}}
          <div class="form-group">
            <label for="billing-address">{{ __('global.billing-address') }}</label>
            <input type="text" name="billing_address" id="billing-address" class="form-control">
          </div>

          {{-- Előfizetés típusa --}}
          <div class="form-group">
          <label for="subscription-type">{{ __('global.subscription-type') }}</label>
          <select name="subscription_type" id="subscription-type" class="form-control">
            <option value="">{{ __('global.select') }}</option>
            <option value="free">{{ __('global.subscription-free') }}</option>
            <option value="pro">{{ __('global.subscription-pro') }}</option>
          </select>
          </div>

          {{-- Admin név --}}
          <div class="form-group">
            <label for="admin-name">{{ __('global.admin-name') }}</label>
            <input type="text" name="admin_name" id="admin-name" class="form-control">
          </div>

          {{-- Admin email --}}
          <div class="form-group">
            <label for="admin-email">{{ __('global.admin-email') }}</label>
            <input type="email" name="admin_email" id="admin-email" class="form-control">
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
$(document).on('click', '.trigger-new', function() {
  $('#modal-org-create').modal('show');
});
</script>
