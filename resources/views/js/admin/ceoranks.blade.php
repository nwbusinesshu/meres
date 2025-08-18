<script>
  $(document).ready(function(){
    $('.add-rank').click(function(){
      openCeoRankModal();
    });

    $('.modify-rank').click(function(){
      openCeoRankModal($(this).parents('.rank').attr('data-id'));
    });

    $('.remove-rank').click(function(){
      swal_confirm.fire({
        title: '{{ __('admin/ceoranks.remove-rank-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();
          $.ajax({
            url: "{{ route('admin.ceoranks.remove') }}",
            data: {
              id: $(this).parents('.rank').attr('data-id')
            },
            successMessage: "{{ __('admin/ceoranks.remove-rank-success') }}",
          });
        }
      });
    });
  });
</script>