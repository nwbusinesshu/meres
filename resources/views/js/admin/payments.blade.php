<script>
  if (window.__PAYMENTS_JS_INIT__) {
  // már inicializálva
} else {
  window.__PAYMENTS_JS_INIT__ = true;
$(function(){
  const START_URL   = '{{ route('admin.payments.start') }}';
  const REFRESH_URL = '{{ route('admin.payments.refresh') }}';
  const csrf        = '{{ csrf_token() }}';

  

  // Visszatérés a Barionból: csak akkor kérdezzük le a státuszt, ha paymentId paraméter van az URL-ben
  const url   = new URL(window.location.href);
  const param = url.searchParams.get('paymentId') || url.searchParams.get('PaymentId') || url.searchParams.get('Id');

  if (param) {
    $.post(REFRESH_URL, { _token: csrf, barion_payment_id: param })
      .done(function(resp){
        if (resp && resp.success) {
          if (resp.status === 'paid') {
            swal.fire('{{ __("payment.swal.paid_title") }}', '{{ __("payment.swal.paid_text") }}', 'success')
              .then(() => window.location.href = '{{ route('admin.payments.index') }}');
          } else if (resp.status === 'failed') {
            swal.fire('{{ __("payment.swal.failed_title") }}', '{{ __("payment.swal.failed_text") }}', 'error');
          } else {
            // pending → ne idegesítsük a usert
            console.log('Payment pending...');
          }
        }
      })
      .always(function(){
        // Tisztítsuk az URL-t, hogy ne fusson újra
        url.searchParams.delete('paymentId');
        url.searchParams.delete('PaymentId');
        url.searchParams.delete('Id');
        window.history.replaceState({}, document.title, url.toString());
      });
  }

  // Fizetés indítása
  $(document).on('click', '.btn-start-payment', function(){
    const $btn = $(this);
    const id   = $btn.data('id');

    // Check if this payment is blocked (data attribute from blade)
    const isBlocked = $btn.data('blocked');
    const remainingMinutes = $btn.data('remaining-minutes');

    if (isBlocked) {
      swal.fire({
        icon: 'warning',
        title: '{{ __("payment.swal.payment_blocked_title") }}',
        text: '{{ __("payment.swal.payment_blocked_text") }}',
        confirmButtonText: 'OK'
      });
      return;
    }

    $btn.prop('disabled', true);

    // Show persistent connecting overlay - NO TIMER, stays until we close it
    const connectingSwal = swal.fire({
      icon: 'info',
      title: '{{ __("payment.swal.connecting_barion_title") }}',
      html: '{{ __("payment.swal.connecting_barion_text") }}<br><br><small>{{ __("payment.swal.connecting_barion_wait") }}</small>',
      showConfirmButton: false,
      allowOutsideClick: false,
      allowEscapeKey: false,
      allowEnterKey: false,
      showCloseButton: false,
      // NO TIMER - stays open until manually closed
      didOpen: () => {
        swal.showLoading();
      }
    });

    $.post(START_URL, { _token: csrf, payment_id: id })
      .done(function(resp){
        if (resp && resp.success && resp.redirect_url) {
          // Update message to show we're redirecting
          swal.update({
            icon: 'success',
            title: '{{ __("payment.swal.redirecting_title") }}',
            html: '{{ __("payment.swal.redirecting_text") }}'
          });
          
          // Redirect after brief moment to show success message
          setTimeout(() => {
            window.location.href = resp.redirect_url;
          }, 1000);
        } else {
          connectingSwal.close();
          swal.fire('{{ __("payment.swal.start_unknown_title") }}', '{{ __("payment.swal.start_unknown_text") }}', 'warning');
          $btn.prop('disabled', false);
        }
      })
      .fail(function(xhr){
        connectingSwal.close();
        let msg = '{{ __("payment.swal.start_fail_text") }}';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        swal.fire('{{ __("payment.swal.start_fail_title") }}', msg, 'error');
        $btn.prop('disabled', false);
      });
  });
});
}
// Prevent loader for download links
$(document).on('click', 'a.no-loader', function(e) {
  // Don't trigger any global loader
  e.stopPropagation();
  
  // Optional: Show a brief toast message
  toast('info', '{{ __("payment.invoice.downloading") }}');
});

// Alternative: If there's still a global loader showing, hide it after click
$(document).on('click', 'a[href*="payments/invoice"]', function() {
  // Hide any loaders after a short delay (file download should start by then)
  setTimeout(function() {
    if (typeof swal_loader !== 'undefined' && swal_loader.close) {
      swal_loader.close();
    }
    if (typeof Swal !== 'undefined' && Swal.close) {
      Swal.close();
    }
  }, 500);
});
</script>