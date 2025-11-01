<script>
  $(document).ready(function(){
    $(function() {
    // Check if there's an unpaid initial payment (trial period)
    var hasUnpaidInitialPayment = {{ DB::table('payments')
        ->where('organization_id', session('org_id'))
        ->whereNull('assessment_id')
        ->where('status', '!=', 'paid')
        ->exists() ? 'true' : 'false' }};

    // Block assessment modal opening during trial
    if (hasUnpaidInitialPayment) {
        // Intercept the assessment modal trigger
        $(document).on('click', '[data-toggle="modal"][data-target="#assessmentModal"], .btn-assessment-modal', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            swal.fire({
                icon: 'warning',
                title: '{{ __("payment.trial.assessment_blocked") }}',
                html: '{{ __("payment.trial.active_message", ["days" => 5]) }}<br><br>' +
                      '<a href="{{ route("admin.payments.index") }}" class="btn btn-primary">' +
                      '{{ __("payment.trial.pay_now") }} <i class="fas fa-arrow-right"></i></a>',
                showConfirmButton: true,
                confirmButtonText: 'OK',
                showCancelButton: false
            });
            
            return false;
        });
    }
    
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