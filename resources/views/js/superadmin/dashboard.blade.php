<script>
const HU_TAX_REGEX = /^\d{8}-\d-\d{2}$/;
const EU_VAT_REGEX = /^[A-Z]{2}[A-Za-z0-9]{2,12}$/;

function updateCreateTaxVisibility() {
  const code = ($('#country-code').val() || '').toUpperCase();
  const $taxCol = $('#tax-number').closest('.col-md-6');
  const $euCol  = $('#eu-vat-number').closest('.col-md-6');

  if (code === 'HU') {
    $taxCol.show();  $('#tax-number').prop('required', true);
    $euCol.show();   $('#eu-vat-number').prop('required', false);
  } else {
    $taxCol.hide();  $('#tax-number').prop('required', false).val('');
    $euCol.show();   $('#eu-vat-number').prop('required', true);
  }
}

function updateEditTaxVisibility() {
  const code = ($('#edit-country-code').val() || '').toUpperCase();
  const $taxCol = $('#edit-tax-number').closest('.col-md-6');
  const $euCol  = $('#edit-eu-vat-number').closest('.col-md-6');

  if (code === 'HU') {
    $taxCol.show();  $('#edit-tax-number').prop('required', true);
    $euCol.show();   $('#edit-eu-vat-number').prop('required', false);
  } else {
    $taxCol.hide();  $('#edit-tax-number').prop('required', false).val('');
    $euCol.show();   $('#edit-eu-vat-number').prop('required', true);
  }
}
</script>

<script>
$(document).ready(function () {
  const url = new URL(window.location.href);

  $('.search-input').keyup(function (e) {
    if (e.keyCode !== 13) return;

    let search = $(this).val().toLowerCase();
    $('tbody tr').addClass('hidden');

    $('tbody tr:not(.no-org)').each(function () {
      const orgName = $(this).find('td').first().text().toLowerCase();
      if (orgName.includes(search)) {
        $(this).removeClass('hidden');
      }
    });

    url.searchParams.delete('search');
    if (search.length > 0) {
      url.searchParams.set('search', search);
    }
    window.history.replaceState(null, null, url);

    if ($('tbody tr:not(.no-org):not(.hidden)').length === 0) {
      $('.no-org').removeClass('hidden');
    }
  });

  if (url.searchParams.has('search')) {
    $('.search-input')
      .val(url.searchParams.get('search'))
      .trigger(jQuery.Event('keyup', { keyCode: 13 }));
  }

  $('.clear-search').click(function () {
    $('.search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
  });
});
</script>

<script>
// Country options from Laravel translations
const COUNTRY_OPTIONS = @json(array_map(function($code, $name) {
  return ['code' => $code, 'name' => $name];
}, array_keys(__('global.countries')), __('global.countries')));

function populateCountries($select, selectedCode) {
  if (!$select || $select.length === 0) return;
  $select.empty();
  // placeholder
  $select.append($('<option>', {value: '', text: '{{ __("global.select") }}'}));
  COUNTRY_OPTIONS.forEach(c => {
    $select.append($('<option>', {value: c.code, text: `${c.name} (${c.code})`}));
  });
  const val = (selectedCode || 'HU').toUpperCase();
  $select.val(val);
}
</script>

<script>
$(document).on('submit', '#form-org-create', function(e) {
  e.preventDefault();

  const code = ($('#country-code').val() || '').toUpperCase();
  const tax  = ($('#tax-number').val() || '').trim();
  const eu   = ($('#eu-vat-number').val() || '').trim().toUpperCase();

  // Client-side validation
  if (code === 'HU') {
    if (!tax || !HU_TAX_REGEX.test(tax)) {
      alert('{{ __("global.validation-hu-tax-number") }}');
      return;
    }
    if (eu && !EU_VAT_REGEX.test(eu)) {
      alert('{{ __("global.validation-eu-vat-format") }}');
      return;
    }
  } else {
    if (!eu || !EU_VAT_REGEX.test(eu)) {
      alert('{{ __("global.validation-eu-vat-required") }}');
      return;
    }
  }

  let $form = $(this);
  let data = {
    _token: '{{ csrf_token() }}',

    org_name: $form.find('[name="org_name"]').val(),
    subscription_type: $form.find('[name="subscription_type"]').val(),

    country_code: code,
    postal_code: $form.find('[name="postal_code"]').val(),
    region: $form.find('[name="region"]').val(),
    city: $form.find('[name="city"]').val(),
    street: $form.find('[name="street"]').val(),
    house_number: $form.find('[name="house_number"]').val(),

    tax_number: tax,
    eu_vat_number: eu,

    admin_name: $form.find('[name="admin_name"]').val(),
    admin_email: $form.find('[name="admin_email"]').val()
  };

  $.post('{{ route('superadmin.org.store') }}', data)
    .done(function(res) {
      if (res.success) {
        $('#modal-org-create').modal('hide');
        location.reload();
      }
    })
    .fail(function(xhr) {
      let errors = (xhr.responseJSON && xhr.responseJSON.errors) ? xhr.responseJSON.errors : null;
      let msg = '';
      if (errors) {
        for (let field in errors) { msg += errors[field][0] + '\n'; }
      }
      alert(msg || '{{ __("global.error-occurred") }}');
    });
});
</script>

<script>
$(document).on('click', '.edit-org', function () {
  let $row = $(this).closest('tr');
  let orgId = $row.data('id');

  $('#edit-org-id').val(orgId);

  // Populate country list once
  if (!$('#edit-country-code').data('populated')) {
    populateCountries($('#edit-country-code'));
    $('#edit-country-code').data('populated', true);
  }

  // Reset form
  $('#edit-org-name').val('');
  $('#edit-subscription-type').val('');
  $('#edit-country-code').val('HU');
  $('#edit-postal-code').val('');
  $('#edit-region').val('');
  $('#edit-city').val('');
  $('#edit-street').val('');
  $('#edit-house-number').val('');
  $('#edit-tax-number').val('');
  $('#edit-eu-vat-number').val('');
  $('#admin-name').val('');
  $('#admin-email').val('');
  $('#admin-remove').val(0);

  $.get(`/superadmin/org/${orgId}/data`, function (data) {
    $('#edit-org-name').val(data.org_name || '');
    $('#edit-subscription-type').val(data.subscription_type || '');

    const code = (data.country_code || 'HU').toUpperCase();
    populateCountries($('#edit-country-code'), code);

    $('#edit-postal-code').val(data.postal_code || '');
    $('#edit-region').val(data.region || '');
    $('#edit-city').val(data.city || '');
    $('#edit-street').val(data.street || '');
    $('#edit-house-number').val(data.house_number || '');

    $('#edit-tax-number').val(data.tax_number || '');
    $('#edit-eu-vat-number').val((data.eu_vat_number || '').toUpperCase());

    // Update required fields and visibility
    updateEditTaxVisibility();
    $('#edit-country-code').off('change._country').on('change._country', updateEditTaxVisibility);

    if ((data.admin_name || '') && (data.admin_email || '')) {
      $('#admin-current-name').text(data.admin_name);
      $('#admin-current-email').text(data.admin_email);
      $('#current-admin').removeClass('d-none');
      $('#new-admin-fields').addClass('d-none');
    } else {
      $('#current-admin').addClass('d-none');
      $('#new-admin-fields').removeClass('d-none');
    }

    $('#modal-org-edit').modal('show');
  }).fail(function () {
    alert('{{ __("global.fetch-org-data-failed") }}');
  });
});
</script>

<script>
$(document).on('submit', '#form-org-edit', function(e) {
  e.preventDefault();

  const code = ($('#edit-country-code').val() || '').toUpperCase();
  const tax  = ($('#edit-tax-number').val() || '').trim();
  const eu   = ($('#edit-eu-vat-number').val() || '').trim().toUpperCase();

  if (code === 'HU') {
    if (!tax || !HU_TAX_REGEX.test(tax)) {
      alert('{{ __("global.validation-hu-tax-number") }}');
      return;
    }
    if (eu && !EU_VAT_REGEX.test(eu)) {
      alert('{{ __("global.validation-eu-vat-format") }}');
      return;
    }
  } else {
    if (!eu || !EU_VAT_REGEX.test(eu)) {
      alert('{{ __("global.validation-eu-vat-required") }}');
      return;
    }
  }

  let data = {
    _token: '{{ csrf_token() }}',
    org_id: $('#edit-org-id').val(),
    org_name: $('#edit-org-name').val(),
    subscription_type: $('#edit-subscription-type').val(),

    country_code: code,
    postal_code: $('#edit-postal-code').val(),
    region: $('#edit-region').val(),
    city: $('#edit-city').val(),
    street: $('#edit-street').val(),
    house_number: $('#edit-house-number').val(),

    tax_number: tax,
    eu_vat_number: eu,

    admin_name: $('#admin-name').val(),
    admin_email: $('#admin-email').val(),
    admin_remove: $('#admin-remove').val()
  };

  $.post('{{ route('superadmin.org.update') }}', data)
    .done(function(res) {
      if (res.success) {
        $('#modal-org-edit').modal('hide');
        location.reload();
      }
    })
    .fail(function(xhr) {
      let errors = (xhr.responseJSON && xhr.responseJSON.errors) ? xhr.responseJSON.errors : null;
      let msg = '';
      if (errors) {
        for (let field in errors) { msg += errors[field][0] + '\n'; }
      }
      alert(msg || '{{ __("global.error-occurred") }}');
    });
});
</script>

<script>
$(document).on('click', '.remove-org', function () {
  if (!confirm('{{ __("global.confirm-delete") }}')) return;

  let orgId = $(this).closest('tr').data('id');

  $.post('{{ route('superadmin.org.delete') }}', {
    _token: '{{ csrf_token() }}',
    org_id: orgId
  }).done(function(res) {
    if (res.success) {
      location.reload();
    }
  }).fail(function() {
    alert('{{ __("global.delete-failed") }}');
  });
});
</script>

<script>
$(document).on('click', '#remove-admin-btn', function () {
  $('#current-admin').addClass('d-none');
  $('#new-admin-fields').removeClass('d-none');
  $('#admin-remove').val(1);
});
</script>

<script>
$(document).ready(function () {
  const $input = $('.search-input');
  const $clearBtn = $('.clear-search');
  const $rows = $('.org-info-table tbody tr').not('.no-org');
  const $noOrgRow = $('.no-org');

  function filterRows() {
    const search = $input.val().toLowerCase();
    let matchCount = 0;

    $rows.each(function () {
      const textToSearch = [
        $(this).find('td[data-col="{{ __('global.name') }}"]').text(),
        $(this).find('td[data-col="{{ __('global.admin') }}"]').text(),
      ].join(' ').toLowerCase();
      const isMatch = textToSearch.includes(search);

      $(this).toggle(isMatch);
      if (isMatch) matchCount++;
    });

    $noOrgRow.toggle(matchCount === 0);
  }

  $input.on('input', filterRows);

  $clearBtn.on('click', function () {
    $input.val('').trigger('input');
  });

  // If readonly field (e.g., few orgs), do nothing
  if ($input.prop('readonly')) {
    $input.closest('.org-search').addClass('disabled');
  }
});
</script>

<script>
$(document).on('click', '.trigger-new', function() {
  // Populate country list + default HU
  if (!$('#country-code').data('populated')) {
    populateCountries($('#country-code'));
    $('#country-code').data('populated', true);
  }
  $('#country-code').val('HU');

  // Update required/visibility
  updateCreateTaxVisibility();
  $('#country-code').off('change._country').on('change._country', updateCreateTaxVisibility);

  $('#modal-org-create').modal('show');
});
</script>