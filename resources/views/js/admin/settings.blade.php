<script>
document.addEventListener('DOMContentLoaded', function() {
  const T = {
    confirm:                 @json(__('admin/settings.settings.confirm')),
    warn_strict_on:          @json(__('admin/settings.settings.warn_strict_on')),
    warn_ai_on:              @json(__('admin/settings.settings.warn_ai_on')),
    warn_ai_off:             @json(__('admin/settings.settings.warn_ai_off')),
    warn_multi_on:           @json(__('admin/settings.settings.warn_multi_on')),
    warn_bonus_malus_off:    @json(__('admin/settings.settings.warn_bonus_malus_off')),
    warn_bonus_malus_on:     @json(__('admin/settings.settings.warn_bonus_malus_on')),
    warn_easy_relation_off:  @json(__('admin/settings.settings.warn_easy_relation_off')),
    warn_easy_relation_on:   @json(__('admin/settings.settings.warn_easy_relation_on')),
    warn_force_oauth_2fa_on: @json(__('admin/settings.settings.warn_force_oauth_2fa_on')),
    warn_force_oauth_2fa_off:@json(__('admin/settings.settings.warn_force_oauth_2fa_off')),
    saved:                   @json(__('admin/settings.settings.saved')),
    error:                   @json(__('admin/settings.settings.error')),
    yes:                     @json(__('global.swal-confirm')),
    no:                      @json(__('global.swal-cancel')),
  };

  const strictEl = document.getElementById('toggle-strict');
  const aiEl     = document.getElementById('toggle-ai');
  const multiEl  = document.getElementById('toggle-multi');
  const bonusMalusEl = document.getElementById('toggle-bonus-malus');
  const easyRelationEl = document.getElementById('toggle-easy-relation');
  const forceOauth2faEl = document.getElementById('toggle-force-oauth-2fa');

  // --- Reload utáni toast ---
  (function showSavedToastOnLoad(){
    const key = 'settings_saved_toast';
    const msg = sessionStorage.getItem(key);
    if (msg) {
      sessionStorage.removeItem(key);
      Swal.fire({
        toast: true,
        position: 'bottom',
        icon: 'success',
        title: msg,
        timer: 1600,
        showConfirmButton: false,
      });
    }
  })();

  function postToggle(key, value) {
    return fetch("{{ route('admin.settings.toggle') }}", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ key, value })
    }).then(async (r) => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
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

  function reloadWithToast(msg) {
    sessionStorage.setItem('settings_saved_toast', msg || T.saved);
    setTimeout(() => window.location.reload(), 50);
  }

  strictEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const ok = await warnConfirm(T.warn_strict_on);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('strict_anonymous_mode', nextVal ? '1' : '0');
      reloadWithToast(T.saved);
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon: 'error', title: T.error, text: String(err) });
    }
  });

  aiEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const warnMsg = nextVal ? T.warn_ai_on : T.warn_ai_off;
    const ok = await warnConfirm(warnMsg);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('ai_telemetry_enabled', nextVal ? '1' : '0');
      reloadWithToast(nextVal ? 'AI telemetria bekapcsolva.' : 'AI telemetria kikapcsolva.');
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon: 'error', title: T.error, text: String(err) });
    }
  });

  multiEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const ok = await warnConfirm(T.warn_multi_on);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('enable_multi_level', nextVal ? '1' : '0');
      reloadWithToast('Multi-level részlegkezelés bekapcsolva.');
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon: 'error', title: T.error, text: String(err) });
    }
  });

  bonusMalusEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const warnMsg = nextVal ? T.warn_bonus_malus_on : T.warn_bonus_malus_off;
    const ok = await warnConfirm(warnMsg);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('show_bonus_malus', nextVal ? '1' : '0');
      reloadWithToast(nextVal ? 'Bonus/Malus megjelenítés bekapcsolva.' : 'Bonus/Malus megjelenítés kikapcsolva.');
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon: 'error', title: T.error, text: String(err) });
    }
  });

  easyRelationEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const warnMsg = nextVal ? T.warn_easy_relation_on : T.warn_easy_relation_off;
    const ok = await warnConfirm(warnMsg);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('easy_relation_setup', nextVal ? '1' : '0');
      reloadWithToast(nextVal ? 'Egyszerűsített kapcsolatbeállítás bekapcsolva.' : 'Egyszerűsített kapcsolatbeállítás kikapcsolva.');
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon: 'error', title: T.error, text: String(err) });
    }
  });

  forceOauth2faEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const warnMsg = nextVal ? T.warn_force_oauth_2fa_on : T.warn_force_oauth_2fa_off;
    const ok = await warnConfirm(warnMsg);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('force_oauth_2fa', nextVal ? '1' : '0');
      reloadWithToast(nextVal 
        ? '2FA kényszerítés OAuth belépéseknél bekapcsolva.' 
        : '2FA kényszerítés OAuth belépéseknél kikapcsolva.');
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon: 'error', title: T.error, text: String(err) });
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
    panes.forEach(p => p.classList.remove('active'));
    const pane = document.querySelector('.mode-pane.mode-' + mode);
    if (pane) pane.classList.add('active');
    if (hiddenMode) hiddenMode.value = mode;
  }

  function getCurrentMode(){
    const r = $('input[name="threshold_mode"]:checked');
    return r ? r.value : (hiddenMode?.value || 'fixed');
  }

  radios.forEach(radio => {
    radio.addEventListener('change', function(){
      setActivePane(this.value);
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