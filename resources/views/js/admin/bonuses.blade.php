<script>
$(document).ready(function() {
    const csrfToken = '{{ csrf_token() }}';

    // Assessment selector change
    $('#assessment-selector').on('change', function() {
        const assessmentId = $(this).val();
        if (assessmentId) {
            window.location.href = '{{ route("admin.bonuses.index") }}?assessment_id=' + assessmentId;
        }
    });

    // Toggle payment status
    $('.toggle-payment').on('change', function() {
        const $checkbox = $(this);
        const bonusId = $checkbox.data('bonus-id');
        const isPaid = $checkbox.is(':checked');

        $.ajax({
            url: '{{ route("admin.bonuses.payment.toggle") }}',
            method: 'POST',
            data: {
                _token: csrfToken,
                bonus_id: bonusId,
                is_paid: isPaid
            },
            success: function(response) {
                if (response.ok) {
                    Swal.fire({
                        toast: true,
                        position: 'bottom-end',
                        icon: 'success',
                        title: isPaid ? '{{ __("admin/bonuses.marked-paid") }}' : '{{ __("admin/bonuses.marked-unpaid") }}',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    // Reload page to update stats
                    setTimeout(() => location.reload(), 2000);
                }
            },
            error: function() {
                $checkbox.prop('checked', !isPaid); // Revert
                Swal.fire({
                    icon: 'error',
                    title: '{{ __("admin/bonuses.error") }}',
                    text: '{{ __("admin/bonuses.toggle-failed") }}'
                });
            }
        });
    });

    // Configure multipliers modal
    $('.trigger-config-multipliers').on('click', function() {
        openMultiplierConfigModal();
    });
});

function openMultiplierConfigModal() {
    // Load current config
    $.ajax({
        url: '{{ route("admin.bonuses.config.get") }}',
        method: 'POST',
        data: { _token: '{{ csrf_token() }}' },
        success: function(response) {
            if (response.ok) {
                populateMultiplierModal(response.config);
                $('#bonus-config-modal').modal('show');
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: '{{ __("admin/bonuses.error") }}',
                text: '{{ __("admin/bonuses.config-load-failed") }}'
            });
        }
    });
}

function populateMultiplierModal(config) {
    const $tbody = $('#multiplier-table tbody');
    $tbody.empty();
    
    // Level names mapping
    const levelNames = {
        1: 'M04', 2: 'M03', 3: 'M02', 4: 'M01',
        5: 'A00',
        6: 'B01', 7: 'B02', 8: 'B03', 9: 'B04', 10: 'B05',
        11: 'B06', 12: 'B07', 13: 'B08', 14: 'B09', 15: 'B10'
    };
    
    config.forEach(item => {
        const levelName = levelNames[item.level] || 'Level ' + item.level;
        $tbody.append(`
            <tr>
                <td><strong>${item.level}</strong></td>
                <td>${levelName}</td>
                <td>
                    <input type="number" 
                           class="form-control multiplier-input" 
                           data-level="${item.level}"
                           value="${item.multiplier}"
                           step="0.25"
                           min="0"
                           max="15">
                </td>
            </tr>
        `);
    });
}

function saveMultiplierConfig() {
    const multipliers = [];
    $('.multiplier-input').each(function() {
        multipliers.push({
            level: $(this).data('level'),
            multiplier: parseFloat($(this).val())
        });
    });

    $.ajax({
        url: '{{ route("admin.bonuses.config.save") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            multipliers: multipliers
        },
        success: function(response) {
            if (response.ok) {
                $('#bonus-config-modal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: '{{ __("admin/bonuses.config-saved") }}',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: '{{ __("admin/bonuses.error") }}',
                text: '{{ __("admin/bonuses.config-save-failed") }}'
            });
        }
    });
}

function resetMultipliersToDefault() {
    Swal.fire({
        title: '{{ __("admin/bonuses.reset-confirm-title") }}',
        text: '{{ __("admin/bonuses.reset-confirm-text") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ __("global.yes") }}',
        cancelButtonText: '{{ __("global.cancel") }}'
    }).then((result) => {
        if (result.isConfirmed) {
            // Default Hungarian multipliers
            const defaults = {
                1: 0.00, 2: 0.40, 3: 0.70, 4: 0.90, 5: 1.00,
                6: 1.50, 7: 2.00, 8: 2.75, 9: 3.50, 10: 4.25,
                11: 5.25, 12: 6.25, 13: 7.25, 14: 8.25, 15: 9.25
            };
            
            $('.multiplier-input').each(function() {
                const level = $(this).data('level');
                $(this).val(defaults[level]);
            });
            
            Swal.fire({
                toast: true,
                position: 'bottom-end',
                icon: 'info',
                title: '{{ __("admin/bonuses.reset-done") }}',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}
</script>