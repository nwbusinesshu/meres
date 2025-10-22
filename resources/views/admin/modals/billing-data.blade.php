{{-- resources/views/admin/modals/billing-data.blade.php --}}
<div class="modal fade modal-drawer" id="billing-data-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fa fa-building"></i>
          {{ __('payment.billing_data.title') }}
        </h5>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>
      
      <div class="modal-body">
        <form id="billing-data-form">
          @csrf
          
          {{-- Company Name --}}
          <div class="form-group">
            <label for="company_name">{{ __('payment.billing_data.company_name') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="company_name" name="company_name" required>
          </div>

          {{-- Tax Number --}}
          <div class="form-group">
            <label for="tax_number">{{ __('payment.billing_data.tax_number') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="tax_number" name="tax_number" required>
            <small class="form-text text-muted">{{ __('payment.billing_data.tax_number_hint') }}</small>
          </div>

          {{-- EU VAT Number --}}
          <div class="form-group">
            <label for="eu_vat_number">{{ __('payment.billing_data.eu_vat_number') }}</label>
            <input type="text" class="form-control" id="eu_vat_number" name="eu_vat_number">
            <small class="form-text text-muted">{{ __('payment.billing_data.eu_vat_hint') }}</small>
          </div>

          {{-- Country Code --}}
          <div class="form-group">
            <label for="country_code">{{ __('payment.billing_data.country_code') }} <span class="text-danger">*</span></label>
            <select class="form-control" id="country_code" name="country_code" required>
              <option value="HU">{{ __('payment.billing_data.countries.HU') }}</option>
              <option value="AT">{{ __('payment.billing_data.countries.AT') }}</option>
              <option value="DE">{{ __('payment.billing_data.countries.DE') }}</option>
              <option value="SK">{{ __('payment.billing_data.countries.SK') }}</option>
              <option value="RO">{{ __('payment.billing_data.countries.RO') }}</option>
              <option value="HR">{{ __('payment.billing_data.countries.HR') }}</option>
              <option value="SI">{{ __('payment.billing_data.countries.SI') }}</option>
              <option value="RS">{{ __('payment.billing_data.countries.RS') }}</option>
            </select>
          </div>

          {{-- Address Fields --}}
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="postal_code">{{ __('payment.billing_data.postal_code') }}</label>
                <input type="text" class="form-control" id="postal_code" name="postal_code">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="city">{{ __('payment.billing_data.city') }}</label>
                <input type="text" class="form-control" id="city" name="city">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="region">{{ __('payment.billing_data.region') }}</label>
            <input type="text" class="form-control" id="region" name="region">
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label for="street">{{ __('payment.billing_data.street') }}</label>
                <input type="text" class="form-control" id="street" name="street">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="house_number">{{ __('payment.billing_data.house_number') }}</label>
                <input type="text" class="form-control" id="house_number" name="house_number">
              </div>
            </div>
          </div>

          {{-- Phone --}}
          <div class="form-group">
            <label for="phone">{{ __('payment.billing_data.phone') }}</label>
            <input type="text" class="form-control" id="phone" name="phone">
          </div>

        </form>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          {{ __('global.cancel') }}
        </button>
        <button type="button" class="btn btn-primary" id="save-billing-data">
          <i class="fa fa-save"></i> {{ __('global.save') }}
        </button>
      </div>
      
    </div>
  </div>
</div>

<script>
$(function(){
  const BILLING_GET_URL = '{{ route('admin.payments.billing.get') }}';
  const BILLING_SAVE_URL = '{{ route('admin.payments.billing.save') }}';
  const csrf = '{{ csrf_token() }}';

  // =========================================
  // BILLING DATA MODAL
  // =========================================

  // Open billing data modal
  $(document).on('click', '#open-billing-data-modal', function(){
    const modal = $('#billing-data-modal');
    
    // Load existing billing data
    $.post(BILLING_GET_URL, { _token: csrf })
      .done(function(resp){
        if (resp && resp.success) {
          const data = resp.data;
          const profile = data.profile || {};
          
          // Fill form fields
          $('#company_name').val(data.organization_name || '');
          $('#tax_number').val(profile.tax_number || '');
          $('#eu_vat_number').val(profile.eu_vat_number || '');
          $('#country_code').val(profile.country_code || 'HU');
          $('#postal_code').val(profile.postal_code || '');
          $('#city').val(profile.city || '');
          $('#region').val(profile.region || '');
          $('#street').val(profile.street || '');
          $('#house_number').val(profile.house_number || '');
          $('#phone').val(profile.phone || '');
        }
      })
      .fail(function(){
        toast('error', '{{ __("payment.billing_data.load_error") }}');
      })
      .always(function(){
        // Show modal
        modal.modal('show');
      });
  });

  // Save billing data
  $(document).on('click', '#save-billing-data', function(){
    const $btn = $(this);
    const form = $('#billing-data-form');
    
    // Validate required fields
    if (!form[0].checkValidity()) {
      form[0].reportValidity();
      return;
    }

    // Gather form data
    const formData = {
      _token: csrf,
      company_name: $('#company_name').val().trim(),
      tax_number: $('#tax_number').val().trim(),
      eu_vat_number: $('#eu_vat_number').val().trim(),
      country_code: $('#country_code').val(),
      postal_code: $('#postal_code').val().trim(),
      city: $('#city').val().trim(),
      region: $('#region').val().trim(),
      street: $('#street').val().trim(),
      house_number: $('#house_number').val().trim(),
      phone: $('#phone').val().trim()
    };

    $btn.prop('disabled', true);

    $.post(BILLING_SAVE_URL, formData)
      .done(function(resp){
        if (resp && resp.success) {
          toast('success', '{{ __("payment.billing_data.save_success") }}');
          $('#billing-data-modal').modal('hide');
        } else {
          toast('error', resp.message || '{{ __("payment.billing_data.save_error") }}');
        }
      })
      .fail(function(xhr){
        let msg = '{{ __("payment.billing_data.save_error") }}';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        toast('error', msg);
      })
      .always(function(){
        $btn.prop('disabled', false);
      });
  });
});
</script>