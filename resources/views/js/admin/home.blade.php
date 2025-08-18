<script>
  $(document).ready(function(){
    $('.create-assessment').click(function(){
      openAssessmentModal();
    });

    $('.modify-assessment').click(function(){
      openAssessmentModal($(this).attr('data-id'));
    });

    $('.close-assessment').click(function(){
      swal_confirm.fire({
        title: '{{ __('admin/home.close-assessment-confirm') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();
          $.ajax({
            url: "{{ route('admin.assessment.close') }}",
            data: {
              id: $(this).attr('data-id')
            },
            successMessage: "{{ __('admin/home.close-assessment-success') }}",
          });
        }
      });
    });
  });
</script>