@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>{{ __('titles.superadmin.dashboard') }}</h1>
<div>
  <div class="tile tile-info search-tile">
    <p>{{ __('global.search') }}</p>
    <div>
      <input type="text" class="form-control search-input" @if ($organizations->count() < 5) readonly @endif>
      <i class="fa fa-ban clear-search" data-tippy-content="{{ __('global.clear-search') }}"></i>
    </div>
  </div>
  <div class="tile tile-button trigger-new">
    <span><i class="fa fa-plus"></i>{{ __('titles.superadmin.new-org') }}</span>
  </div>
</div>

<div class="tile orglist">
  <table class="table table-hover">
    <thead>
      <th>{{ __('global.name') }}</th>
      <th>{{ __('global.admin') }}</th>
      <th>{{ __('global.created') }}</th>
      <th>{{ __('global.actions') }}</th>
    </thead>
    <tbody>
      @foreach ($organizations as $org)
        <tr data-id="{{ $org->id }}">
          <td data-col="{{ __('global.name') }}">{{ $org->name }}</td>
          <td data-col="{{ __('global.admin') }}">{{ $org->admin_name ?? '-' }}</td>
          <td data-col="{{ __('global.created') }}">{{ $org->created_at->format('Y-m-d') }}</td>
          <td data-col="{{ __('global.actions') }}">
            <div>
              <button class="btn btn-outline-primary edit-org" data-tippy-content="{{ __('global.edit') }}">
                <i class="fa fa-pen"></i>
              </button>
              <button class="btn btn-outline-danger remove-org" data-tippy-content="{{ __('global.delete') }}">
                <i class="fa fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      @endforeach
      <tr class="no-org @if($organizations->count() != 0) hidden @endif">
        <td colspan="4">{{ __('global.no-org') }}</td>
      </tr>
    </tbody>
  </table>
</div>
@endsection

@section('scripts')
@include('superadmin.modals.org-create')
@include('superadmin.modals.org-edit', ['admin' => $org->users()->wherePivot('role', 'admin')->first()])

<script>
$(document).on('submit', '#form-org-create', function(e) {
    e.preventDefault();

    let $form = $(this);
    let data = {
        org_name: $form.find('[name="org_name"]').val(),
        admin_name: $form.find('[name="admin_name"]').val(),
        admin_email: $form.find('[name="admin_email"]').val(),
        _token: '{{ csrf_token() }}'
    };

    $.post('{{ route('superadmin.org.store') }}', data)
        .done(function(res) {
            if (res.success) {
                $('#modal-org-create').modal('hide');
                location.reload(); // később cserélhetjük táblázatfrissítésre
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
$(document).on('click', '.edit-org', function() {
    let $row = $(this).closest('tr');
    let orgId = $row.data('id');

    $('#edit-org-id').val(orgId);
    $('#edit-org-name').val($row.find('[data-col="{{ __('global.name') }}"]').text().trim());
    $('#edit-admin-name').val($row.find('[data-col="{{ __('global.admin') }}"]').text().trim());
    $('#edit-admin-email').val(''); // Ezt AJAX-ból is lekérhetjük később

    $('#modal-org-edit').modal('show');
});

$(document).on('submit', '#form-org-edit', function(e) {
    e.preventDefault();

    let data = {
        _token: '{{ csrf_token() }}',
        org_id: $('#edit-org-id').val(),
        org_name: $('#edit-org-name').val(),
        admin_name: $('#edit-admin-name').val(),
        admin_email: $('#edit-admin-email').val(),
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

$(document).on('click', '.remove-org', function() {
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

$(document).on('click', '#remove-admin-btn', function() {
  $('#current-admin').remove();
  $('#new-admin-fields').removeClass('d-none');
  $('input[name="admin_remove"]').val(1);
});

</script>

@endsection

@foreach ($organizations as $org)
  @php
    $admin = $org->users()->wherePivot('role', 'admin')->first();
  @endphp
  @include('superadmin.modals.org-edit', compact('org', 'admin'))
@endforeach



