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

<script>
(function(){
  // —— helpers
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));
  const $  = (sel, root=document) => root.querySelector(sel);

  const radios = $$('input[name="threshold_mode"]');
  const panes  = $$('.mode-pane');
  const hiddenMode = $('#config-mode');

  function setActivePane(mode){
    // elrejtés
    panes.forEach(p => p.classList.remove('active'));
    // megjelenítés
    const pane = document.querySelector('.mode-pane.mode-' + mode);
    if (pane) pane.classList.add('active');
    // hidden input frissítése (a config formnak)
    if (hiddenMode) hiddenMode.value = mode;
  }

  function getCurrentMode(){
    const r = $('input[name="threshold_mode"]:checked');
    return r ? r.value : (hiddenMode?.value || 'fixed');
  }

  // Események
  radios.forEach(radio => {
    radio.addEventListener('change', function(){
      setActivePane(this.value);
      // ha azt szeretnéd, hogy a mód azonnal mentődjön:
      // document.getElementById('mode-form').submit();
    });
  });

  // Strict anon -> AI toggle tiltása/engedése kliensoldalon is
  const strict = $('#toggle-strict');
  const ai     = $('#toggle-ai');
  if (strict && ai){
    strict.addEventListener('change', function(){
      ai.disabled = this.checked;
      if (ai.disabled) ai.checked = false;
    });
  }

  // Init
  setActivePane(getCurrentMode());
})();
</script>

