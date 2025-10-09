<script>
$(document).ready(function() {
    // Assessment selector change
    $('#assessment-selector').on('change', function() {
        const assessmentId = $(this).val();
        if (assessmentId) {
            window.location.href = "{{ route('admin.bonuses.index') }}?assessment_id=" + assessmentId;
        }
    });

    // Toggle payment status
    $('.toggle-payment').on('change', function() {
        const bonusId = $(this).data('bonus-id');
        const isPaid = $(this).is(':checked');
        
        $.ajax({
            url: "{{ route('admin.bonuses.payment.toggle') }}",
            method: 'POST',
            data: {
                bonus_id: bonusId,
                is_paid: isPaid ? 1 : 0,
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.ok) {
                    // Reload to update stats
                    window.location.reload();
                }
            },
            error: function() {
                // Revert checkbox on error
                $(this).prop('checked', !isPaid);
                Swal.fire('Error', '{{ __('admin/bonuses.payment-update-error') }}', 'error');
            }
        });
    });
});

$(document).ready(function() {
    // Toggle payment status
    $('.toggle-payment').on('change', function() {
        const bonusId = $(this).data('bonus-id');
        const isPaid = $(this).is(':checked');
        const checkbox = $(this);
        
        $.ajax({
            url: "{{ route('admin.bonuses.payment.toggle') }}",
            method: 'POST',
            data: {
                bonus_id: bonusId,
                is_paid: isPaid ? 1 : 0,
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.ok) {
                    // Reload to update stats
                    window.location.reload();
                }
            },
            error: function() {
                // Revert checkbox on error
                checkbox.prop('checked', !isPaid);
                Swal.fire('Error', '{{ __('admin/bonuses.payment-update-error') }}', 'error');
            }
        });
    });
});
</script>