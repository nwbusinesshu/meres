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
$(document).on('submit', '#form-org-create', function(e) {
  e.preventDefault();

  let $form = $(this);
  let data = {
    org_name: $form.find('[name="org_name"]').val(),
    tax_number: $form.find('[name="tax_number"]').val(),
    billing_address: $form.find('[name="billing_address"]').val(),
    subscription_type: $form.find('[name="subscription_type"]').val(),
    admin_name: $form.find('[name="admin_name"]').val(),
    admin_email: $form.find('[name="admin_email"]').val(),
    _token: '{{ csrf_token() }}'
  };

  $.post('{{ route('superadmin.org.store') }}', data)
    .done(function(res) {
      if (res.success) {
        $('#modal-org-create').modal('hide');
        location.reload();
      }
    })
    .fail(function(xhr) {
      let errors = xhr.responseJSON.errors;
      let msg = '';
      for (let field in errors) {
        msg += errors[field][0] + '\n';
      }
      alert(msg || 'Hiba történt.');
    });
});
</script>

<script>
$(document).on('click', '.edit-org', function () {
  let $row = $(this).closest('tr');
  let orgId = $row.data('id');

  $('#edit-org-id').val(orgId);
  $('#edit-org-name').val('');
  $('#edit-tax-number').val('');
  $('#edit-billing-address').val('');
  $('#edit-subscription-type').val('');
  $('#admin-name').val('');
  $('#admin-email').val('');
  $('#admin-remove').val(0);

  $.get(`/superadmin/org/${orgId}/data`, function (data) {
    $('#edit-org-name').val(data.org_name);
    $('#edit-tax-number').val(data.tax_number);
    $('#edit-billing-address').val(data.billing_address);
    $('#edit-subscription-type').val(data.subscription_type);

    if (data.admin_name && data.admin_email) {
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
    alert('Nem sikerült lekérni a cég adatait.');
  });
});
</script>

<script>
$(document).on('submit', '#form-org-edit', function(e) {
  e.preventDefault();

  let data = {
    _token: '{{ csrf_token() }}',
    org_id: $('#edit-org-id').val(),
    org_name: $('#edit-org-name').val(),
    tax_number: $('#edit-tax-number').val(),
    billing_address: $('#edit-billing-address').val(),
    subscription_type: $('#edit-subscription-type').val(),
    admin_name: $('#admin-name').val(),
    admin_email: $('#admin-email').val(),
    admin_remove: $('#admin-remove').val(),
  };

  $.post('{{ route('superadmin.org.update') }}', data)
    .done(function(res) {
      if (res.success) {
        $('#modal-org-edit').modal('hide');
        location.reload();
      }
    })
    .fail(function(xhr) {
      let errors = xhr.responseJSON.errors;
      let msg = '';
      for (let field in errors) {
        msg += errors[field][0] + '\n';
      }
      alert(msg || 'Hiba történt.');
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
    alert('Nem sikerült törölni.');
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