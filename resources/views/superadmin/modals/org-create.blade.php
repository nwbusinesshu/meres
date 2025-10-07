<div class="modal fade modal-drawer" id="modal-org-create">
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

          {{-- Számlázási adatok (strukturált) --}}
          <div class="form-group">
            <label>{{ __('superadmin/dashboard.billing-data') }}</label>
            <div class="form-row">
              <div class="col-md-3">
                {{-- Ország (ISO kód tárolás) --}}
                <select name="country_code" id="country-code" class="form-control"></select>
              </div>
              <div class="col-md-3">
                <input type="text" name="postal_code" id="postal-code" class="form-control" placeholder="{{ __('superadmin/dashboard.postal-code-placeholder') }}">
              </div>
              <div class="col-md-6">
                <input type="text" name="region" id="region" class="form-control" placeholder="{{ __('superadmin/dashboard.region-placeholder') }}">
              </div>
            </div>
            <div class="form-row mt-2">
              <div class="col-md-4">
                <input type="text" name="city" id="city" class="form-control" placeholder="{{ __('superadmin/dashboard.city-placeholder') }}">
              </div>
              <div class="col-md-5">
                <input type="text" name="street" id="street" class="form-control" placeholder="{{ __('superadmin/dashboard.street-placeholder') }}">
              </div>
              <div class="col-md-3">
                <input type="text" name="house_number" id="house-number" class="form-control" placeholder="{{ __('superadmin/dashboard.house-number-placeholder') }}">
              </div>
            </div>
          </div>

          {{-- Adóazonosítók --}}
          <div class="form-group">
            <label>{{ __('superadmin/dashboard.tax-data') }}</label>
            <div class="form-row">
              <div class="col-md-6">
                <input type="text" name="tax_number" id="tax-number" class="form-control" placeholder="{{ __('superadmin/dashboard.tax-number-placeholder') }}">
              </div>
              <div class="col-md-6">
                <input type="text" name="eu_vat_number" id="eu-vat-number" class="form-control" placeholder="{{ __('superadmin/dashboard.eu-vat-placeholder') }}">
              </div>
            </div>
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
  // Országlista feltöltése + HU alap
  if (!$('#country-code').data('populated')) {
    populateCountries($('#country-code'));
    $('#country-code').data('populated', true);
  }
  $('#country-code').val('HU');

  // Kötelezőség / láthatóság beállítás
  updateCreateTaxVisibility();
  $('#country-code').off('change._country').on('change._country', updateCreateTaxVisibility);

  $('#modal-org-create').modal('show');
});
</script>