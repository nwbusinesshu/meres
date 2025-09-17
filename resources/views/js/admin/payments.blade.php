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

    $btn.prop('disabled', true);

    $.post(START_URL, { _token: csrf, payment_id: id })
      .done(function(resp){
        if (resp && resp.success && resp.redirect_url) {
          window.location.href = resp.redirect_url;
        } else {
          swal.fire('{{ __("payment.swal.start_unknown_title") }}', '{{ __("payment.swal.start_unknown_text") }}', 'warning');
        }
      })
      .fail(function(xhr){
        let msg = '{{ __("payment.swal.start_fail_text") }}';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        swal.fire('{{ __("payment.swal.start_fail_title") }}', msg, 'error');
      })
      .always(function(){
        $btn.prop('disabled', false);
      });
  });
});
}
</script>
