<script>
document.addEventListener('DOMContentLoaded', function() {
  const T = {
    confirm:        @json(__('admin/settings.settings.confirm')),
    warn_strict_on: @json(__('admin/settings.settings.warn_strict_on')),
    warn_ai_on:     @json(__('admin/settings.settings.warn_ai_on')),
    warn_ai_off:    @json(__('admin/settings.settings.warn_ai_off')),
    warn_multi_on:  'Biztosan bekapcsolod a Többszintű részlegkezelést? A döntés végleges, később nem kapcsolható ki. Mielőtt bekapcsolod, tájékozódj a következményeiről a dokumentációban!',
    warn_bonus_malus_off: 'Biztosan elrejted a Bonus/Malus kategóriákat? A besorolások továbbra is számolódnak, de nem lesznek láthatók a felhasználói felületen.',
    warn_bonus_malus_on: 'Biztosan megjeleníted a Bonus/Malus kategóriákat a felhasználói felületen?',
    warn_easy_relation_off: 'Biztosan kikapcsolod az egyszerűsített kapcsolatbeállítást? Ezután a kapcsolatokat manuálisan kell beállítani mindkét irányban.',
warn_easy_relation_on: 'Biztosan bekapcsolod az egyszerűsített kapcsolatbeállítást? A kapcsolatok automatikusan kétirányúan állítódnak be.',
    saved:          @json(__('admin/settings.settings.saved')),
    saved:          @json(__('admin/settings.settings.saved')),
    error:          @json(__('admin/settings.settings.error')),
    yes:            @json(__('global.swal-confirm')),
    no:             @json(__('global.swal-cancel')),
  };

  const strictEl = document.getElementById('toggle-strict');
  const aiEl     = document.getElementById('toggle-ai');
  const multiEl  = document.getElementById('toggle-multi');
  const bonusMalusEl = document.getElementById('toggle-bonus-malus');  // ADD THIS LINE
  const easyRelationEl = document.getElementById('toggle-easy-relation');


  // --- Reload utáni toast ---
  (function showSavedToastOnLoad(){
    const key = 'settings_saved_toast';
    const msg = sessionStorage.getItem(key);
    if (msg) {
      sessionStorage.removeItem(key);
      Swal.fire({
        toast: true,
        position: 'bottom',   // lent középen
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
    // jelzés elmentése a következő betöltésre
    sessionStorage.setItem('settings_saved_toast', msg || T.saved);
    // kis késleltetéssel töltsük újra, hogy a UI ne villogjon
    setTimeout(() => window.location.reload(), 50);
  }

  strictEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const ok = await warnConfirm(T.warn_strict_on);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('strict_anonymous_mode', nextVal ? 1 : 0);
      // UI állapot azonnal (opcionális), a reload úgyis frissít mindent
      if (nextVal) { if (aiEl) { aiEl.checked = false; aiEl.setAttribute('disabled','disabled'); } }
      else { aiEl?.removeAttribute('disabled'); }
      reloadWithToast(T.saved);
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
      reloadWithToast(T.saved);
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon:'error', title:T.error, text:String(err) });
    }
  });

  multiEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;

    // csak bekapcsolásra van értelme (kikapcsolás nem engedélyezett)
    if (!nextVal) { 
      // visszapattintjuk vizuálisan is
      e.target.checked = true;
      return;
    }

    const ok = await warnConfirm(T.warn_multi_on);
    if (!ok) { e.target.checked = false; return; }

    try {
      await postToggle('enable_multi_level', 1);
      // végleges: azonnal tiltjuk a kapcsolót, és újratöltünk
      e.target.setAttribute('disabled','disabled');
      reloadWithToast(T.saved);
    } catch (err) {
      e.target.checked = false;
      Swal.fire({ icon:'error', title:T.error, text:String(err) });
    }
  });

  // NEW: Bonus/Malus toggle handler
  bonusMalusEl?.addEventListener('change', async (e) => {
    const nextVal = e.target.checked;
    const warnMsg = nextVal ? T.warn_bonus_malus_on : T.warn_bonus_malus_off;
    const ok = await warnConfirm(warnMsg);
    if (!ok) { e.target.checked = !nextVal; return; }

    try {
      await postToggle('show_bonus_malus', nextVal ? '1' : '0');
      reloadWithToast(nextVal ? 'Bonus/Malus kategóriák megjelenítése bekapcsolva.' : 'Bonus/Malus kategóriák elrejtve.');
    } catch (err) {
      e.target.checked = !nextVal;
      Swal.fire({ icon: 'error', title: T.error, text: String(err) });
    }
  });

  // NEW: Easy Relation Setup toggle handler
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

