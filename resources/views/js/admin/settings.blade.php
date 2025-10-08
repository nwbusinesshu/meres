<script>
document.addEventListener('DOMContentLoaded', function() {
  const T = {
    confirm:                  @json(__('admin/settings.settings.confirm')),
    warn_strict_on:           @json(__('admin/settings.settings.warn_strict_on')),
    warn_ai_on:               @json(__('admin/settings.settings.warn_ai_on')),
    warn_ai_off:              @json(__('admin/settings.settings.warn_ai_off')),
    warn_multi_on:            @json(__('admin/settings.settings.warn_multi_on')),
    warn_bonus_malus_off:     @json(__('admin/settings.settings.warn_bonus_malus_off')),
    warn_bonus_malus_on:      @json(__('admin/settings.settings.warn_bonus_malus_on')),
    warn_easy_relation_off:   @json(__('admin/settings.settings.warn_easy_relation_off')),
    warn_easy_relation_on:    @json(__('admin/settings.settings.warn_easy_relation_on')),
    warn_force_oauth_2fa_on:  @json(__('admin/settings.settings.warn_force_oauth_2fa_on')),
    warn_force_oauth_2fa_off: @json(__('admin/settings.settings.warn_force_oauth_2fa_off')),
    // ✅ NEW: Bonuses toggle warnings
    warn_employees_see_bonuses_on:  "Ha bekapcsolod, a dolgozók látni fogják saját bónusz/malus összegüket az eredmények oldalon.",
    warn_employees_see_bonuses_off: "Ha kikapcsolod, a dolgozók NEM fogják látni a bónusz/malus összegeket.",
    saved:                    @json(__('admin/settings.settings.saved')),
    error:                    @json(__('admin/settings.settings.error')),
    yes:                      @json(__('global.swal-confirm')),
    no:                       @json(__('global.swal-cancel')),
  };

  const strictEl = document.getElementById('toggle-strict');
  const aiEl     = document.getElementById('toggle-ai');
  const multiEl  = document.getElementById('toggle-multi');
  const bonusMalusEl = document.getElementById('toggle-bonus-malus');
  const easyRelationEl = document.getElementById('toggle-easy-relation');
  const forceOauth2faEl = document.getElementById('toggle-force-oauth-2fa');
  // ✅ NEW: Bonuses visibility toggle
  const employeesSeeBonusesEl = document.getElementById('toggle-employees-see-bonuses');

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
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({ key, value })
    }).then(r=>r.json());
  }

  // ========== STRICT ANON ==========
  if (strictEl) {
    strictEl.addEventListener('change', async function(e){
      e.preventDefault();
      const wasChecked = this.checked;
      this.checked = !wasChecked; // visszaállítjuk, amíg a user nincs OK-val

      const res = await Swal.fire({
        title: T.confirm,
        text: wasChecked ? T.warn_strict_on : '',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no
      });

      if (!res.isConfirmed) {
        this.checked = !wasChecked; // maradt a régiben
        return;
      }

      postToggle('strict_anonymous_mode', wasChecked).then(data => {
        if (data.ok) {
          this.checked = wasChecked;
          if (wasChecked && aiEl) aiEl.checked = false;
          if (wasChecked && aiEl) aiEl.disabled = true;
          if (!wasChecked && aiEl) aiEl.disabled = false;
        } else {
          this.checked = !wasChecked;
        }
      }).catch(()=>{
        this.checked = !wasChecked;
      });
    });
  }

  // ========== AI TELEMETRY ==========
  if (aiEl) {
    aiEl.addEventListener('change', async function(e){
      e.preventDefault();
      const wasChecked = this.checked;
      this.checked = !wasChecked;

      const res = await Swal.fire({
        title: T.confirm,
        text: wasChecked ? T.warn_ai_on : T.warn_ai_off,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no
      });

      if (!res.isConfirmed) {
        this.checked = !wasChecked;
        return;
      }

      postToggle('ai_telemetry_enabled', wasChecked).then(data => {
        if (data.ok) {
          this.checked = wasChecked;
        } else {
          Swal.fire(T.error, data.message || '', 'error');
          this.checked = !wasChecked;
        }
      }).catch(()=>{
        this.checked = !wasChecked;
      });
    });
  }

  // ========== MULTI-LEVEL ==========
  if (multiEl) {
    multiEl.addEventListener('change', async function(e){
      e.preventDefault();
      if (!this.checked) {
        this.checked = true;
        return;
      }
      const wasChecked = this.checked;
      this.checked = false;

      const res = await Swal.fire({
        title: T.confirm,
        text: T.warn_multi_on,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no
      });

      if (!res.isConfirmed) {
        return;
      }

      postToggle('enable_multi_level', true).then(data => {
        if (data.ok) {
          if (data.already_on) {
            this.checked = true;
            return;
          }
          if (data.enabled) {
            Swal.fire(T.saved, 'A többszintű részlegkezelés bekapcsolva.', 'success').then(()=>{
              window.location.reload();
            });
          }
        } else {
          this.checked = false;
        }
      }).catch(()=>{
        this.checked = false;
      });
    });
  }

  // ========== BONUS/MALUS VISIBILITY ==========
  if (bonusMalusEl) {
    bonusMalusEl.addEventListener('change', async function(e){
      e.preventDefault();
      const wasChecked = this.checked;
      this.checked = !wasChecked;

      const res = await Swal.fire({
        title: T.confirm,
        text: wasChecked ? T.warn_bonus_malus_on : T.warn_bonus_malus_off,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no
      });

      if (!res.isConfirmed) {
        this.checked = !wasChecked;
        return;
      }

      postToggle('show_bonus_malus', wasChecked).then(data => {
        if (data.ok) {
          this.checked = wasChecked;
        } else {
          this.checked = !wasChecked;
        }
      }).catch(()=>{
        this.checked = !wasChecked;
      });
    });
  }

  // ========== EASY RELATION SETUP ==========
  if (easyRelationEl) {
    easyRelationEl.addEventListener('change', async function(e){
      e.preventDefault();
      const wasChecked = this.checked;
      this.checked = !wasChecked;

      const res = await Swal.fire({
        title: T.confirm,
        text: wasChecked ? T.warn_easy_relation_on : T.warn_easy_relation_off,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no
      });

      if (!res.isConfirmed) {
        this.checked = !wasChecked;
        return;
      }

      postToggle('easy_relation_setup', wasChecked).then(data => {
        if (data.ok) {
          this.checked = wasChecked;
        } else {
          this.checked = !wasChecked;
        }
      }).catch(()=>{
        this.checked = !wasChecked;
      });
    });
  }

  // ========== FORCE OAUTH 2FA ==========
  if (forceOauth2faEl) {
    forceOauth2faEl.addEventListener('change', async function(e){
      e.preventDefault();
      const wasChecked = this.checked;
      this.checked = !wasChecked;

      const res = await Swal.fire({
        title: T.confirm,
        text: wasChecked ? T.warn_force_oauth_2fa_on : T.warn_force_oauth_2fa_off,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no
      });

      if (!res.isConfirmed) {
        this.checked = !wasChecked;
        return;
      }

      postToggle('force_oauth_2fa', wasChecked).then(data => {
        if (data.ok) {
          this.checked = wasChecked;
        } else {
          this.checked = !wasChecked;
        }
      }).catch(()=>{
        this.checked = !wasChecked;
      });
    });
  }

  // ✅ NEW: ========== EMPLOYEES SEE BONUSES ==========
  if (employeesSeeBonusesEl) {
    employeesSeeBonusesEl.addEventListener('change', async function(e){
      e.preventDefault();
      const wasChecked = this.checked;
      this.checked = !wasChecked;

      const res = await Swal.fire({
        title: T.confirm,
        text: wasChecked ? T.warn_employees_see_bonuses_on : T.warn_employees_see_bonuses_off,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no
      });

      if (!res.isConfirmed) {
        this.checked = !wasChecked;
        return;
      }

      postToggle('employees_see_bonuses', wasChecked).then(data => {
        if (data.ok) {
          this.checked = wasChecked;
        } else {
          this.checked = !wasChecked;
        }
      }).catch(()=>{
        this.checked = !wasChecked;
      });
    });
  }

});
</script>