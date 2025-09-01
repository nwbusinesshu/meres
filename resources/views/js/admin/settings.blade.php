<script>
document.addEventListener('DOMContentLoaded', function() {
  const T = {
    confirm:        @json(__('admin/settings.settings.confirm')),
    warn_strict_on: @json(__('admin/settings.settings.warn_strict_on')),
    warn_ai_on:     @json(__('admin/settings.settings.warn_ai_on')),
    warn_ai_off:    @json(__('admin/settings.settings.warn_ai_off')),
    saved:          @json(__('admin/settings.settings.saved')),
    error:          @json(__('admin/settings.settings.error')),
    yes:            @json(__('global.swal-confirm')), // vagy ahol a gombok vannak
    no:             @json(__('global.swal-cancel')),
  };

  const strictEl = document.getElementById('toggle-strict');
  const aiEl     = document.getElementById('toggle-ai');

  function postToggle(key, value) {
    return fetch("{{ route('admin.settings.toggle') }}", {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}','Content-Type':'application/json' },
      body: JSON.stringify({ key, value })
    }).then(r => r.json());
  }

  function warnConfirm(text) {
    return Swal.fire({
      icon: 'warning',
      title: T.confirm,
      text,
      showCancelButton: true,
      confirmButtonText: T.yes,
      cancelButtonText: T.no
    }).then(res => res.isConfirmed);
  }

  function okToast(msg) {
    return Swal.fire({ icon: 'success', title: msg, timer: 1200, showConfirmButton: false });
  }

  strictEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const ok = await warnConfirm(T.warn_strict_on);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('strict_anonymous_mode', nextVal ? 1 : 0);
      if (nextVal) { aiEl.checked = false; aiEl.setAttribute('disabled','disabled'); }
      else { aiEl.removeAttribute('disabled'); }
      okToast(T.saved);
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon:'error', title:T.error, text:String(err) });
    }
  });

  aiEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const ok = await warnConfirm(nextVal ? T.warn_ai_on : T.warn_ai_off);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('ai_telemetry_enabled', nextVal ? 1 : 0);
      okToast(T.saved);
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon:'error', title:T.error, text:String(err) });
    }
  });
});
</script>
