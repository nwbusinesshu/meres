<script>
  $(document).ready(function(){
    const url = new URL(window.location.href);

    $('.trigger-new').click(function(){
      openEmployeeModal();
    });

    $('.datas').click(function(){
      openEmployeeModal($(this).parents('tr').attr('data-id'));
    });

    $('.remove').click(function(){
      swal_confirm.fire({
        title: '{{ __('admin/employees.remove-confirm') }}',
        text: '{{ __('admin/employees.remove-confirm-text') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();
          $.ajax({
            url: "{{ route('admin.employee.remove') }}",
            data: { id: $(this).parents('tr').attr('data-id') },
            successMessage: "{{ __('admin/employees.remove-success') }}",
          });
        }
      });
    });

    $('.search-input').keyup(function(e){
      if(e.keyCode != 13){ return; }
      swal_loader.fire();
      search = $(this).val().toLowerCase();
      $('tbody tr').addClass('hidden');
      $('tbody tr:not(.no-employee)').each(function(){
        if($(this).find('td').first().html().toLowerCase().includes(search)){
          $(this).removeClass('hidden');
        }
      });

      url.searchParams.delete('search');
      if(search.length != 0){
        url.searchParams.set('search', search);
      }
      window.history.replaceState(null, null, url);

      if($('tbody tr:not(.no-employee):not(.hidden)').length == 0){
        $('.no-employee').removeClass('hidden');
      }
      swal_loader.close();
    });

    if(url.searchParams.has('search')){
      $('.search-input').val(url.searchParams.get('search'))
        .trigger(jQuery.Event('keyup', { keyCode: 13 }));
    }

    $('.clear-search').click(function(){
      $('.search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
    });

    $('.relations').click(function(){
      initRelationsModal($(this).parents('tr').attr('data-id'));
    });

    $('.competencies').click(function(){
      initCompetenciesModal($(this).parents('tr').attr('data-id'));
    });

    $('.bonusmalus').click(function(){
      openBonusMalusModal($(this).parents('tr').attr('data-id'));
    });
  });
</script>