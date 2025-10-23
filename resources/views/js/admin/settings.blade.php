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
    warn_employees_see_bonuses_on:  "Ha bekapcsolod, a dolgozók látni fogják saját bónusz/malus összegüket az eredmények oldalon.",
    warn_employees_see_bonuses_off: "Ha kikapcsolod, a dolgozók NEM fogják látni a bónusz/malus összegeket.",
    saved:                    @json(__('admin/settings.settings.saved')),
    error:                    @json(__('admin/settings.settings.error')),
    yes:                      @json(__('global.swal-confirm')),
    no:                       @json(__('global.swal-cancel')),
  };

  // ✅ DECLARE ALL ELEMENTS ONCE AT THE TOP
  const strictEl = document.getElementById('toggle-strict');
  const aiEl     = document.getElementById('toggle-ai');
  const multiEl  = document.getElementById('toggle-multi');
  const bonusMalusEl = document.getElementById('toggle-bonus-malus');
  const easyRelationEl = document.getElementById('toggle-easy-relation');
  const forceOauth2faEl = document.getElementById('toggle-force-oauth-2fa');
  const employeesSeeBonusesEl = document.getElementById('toggle-employees-see-bonuses');
  const enableBonusCalculationEl = document.getElementById('toggle-enable-bonus-calculation');
  const configMultipliersBtn = document.querySelector('.trigger-config-multipliers');

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

  // ✅ CASCADING LOGIC: Update dependent fields based on parent toggles
  function updateBonusCascade() {
    const bonusMalusOn = bonusMalusEl && bonusMalusEl.checked;
    const bonusCalculationOn = enableBonusCalculationEl && enableBonusCalculationEl.checked;

    // Rule 1: If bonus-malus is OFF, disable everything
    if (!bonusMalusOn) {
      if (enableBonusCalculationEl) {
        enableBonusCalculationEl.disabled = true;
        enableBonusCalculationEl.checked = false;
      }
      if (employeesSeeBonusesEl) {
        employeesSeeBonusesEl.disabled = true;
        employeesSeeBonusesEl.checked = false;
      }
      if (configMultipliersBtn) {
        configMultipliersBtn.disabled = true;
        configMultipliersBtn.style.opacity = '0.5';
        configMultipliersBtn.style.cursor = 'not-allowed';
      }
    } 
    // Rule 2: If bonus-malus is ON
    else {
      if (enableBonusCalculationEl) {
        enableBonusCalculationEl.disabled = false;
      }
      if (configMultipliersBtn) {
        configMultipliersBtn.disabled = false;
        configMultipliersBtn.style.opacity = '1';
        configMultipliersBtn.style.cursor = 'pointer';
      }

      // Rule 3: If bonus calculation is OFF, disable employees_see_bonuses
      if (!bonusCalculationOn) {
        if (employeesSeeBonusesEl) {
          employeesSeeBonusesEl.disabled = true;
          employeesSeeBonusesEl.checked = false;
        }
      } else {
        if (employeesSeeBonusesEl) {
          employeesSeeBonusesEl.disabled = false;
        }
      }
    }
  }

  // ✅ Run cascade on page load
  updateBonusCascade();

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
      this.checked = !wasChecked;

      const res = await Swal.fire({
        title: T.confirm,
        text: wasChecked ? T.warn_strict_on : T.warn_strict_on,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no,
      });

      if (!res.isConfirmed) return;

      this.checked = wasChecked;
      const response = await postToggle('strict_anon', wasChecked);

      if (response.ok) {
        sessionStorage.setItem('settings_saved_toast', T.saved);
        window.location.reload();
      } else {
        this.checked = !wasChecked;
        Swal.fire('Error', response.error || T.error, 'error');
      }
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
        cancelButtonText: T.no,
      });

      if (!res.isConfirmed) return;

      this.checked = wasChecked;
      const response = await postToggle('ai_telemetry', wasChecked);

      if (response.ok) {
        sessionStorage.setItem('settings_saved_toast', T.saved);
        if (response.reload) {
          window.location.reload();
        } else {
          Swal.fire({
            toast: true,
            position: 'bottom',
            icon: 'success',
            title: T.saved,
            timer: 1600,
            showConfirmButton: false,
          });
        }
      } else {
        this.checked = !wasChecked;
        Swal.fire('Error', response.error || T.error, 'error');
      }
    });
  }

  // ========== MULTI LEVEL ==========
  if (multiEl) {
    multiEl.addEventListener('change', async function(e){
      e.preventDefault();
      const wasChecked = this.checked;
      this.checked = !wasChecked;

      const res = await Swal.fire({
        title: T.confirm,
        text: T.warn_multi_on,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no,
      });

      if (!res.isConfirmed) return;

      this.checked = wasChecked;
      const response = await postToggle('enable_multi_level', wasChecked);

      if (response.ok) {
        sessionStorage.setItem('settings_saved_toast', T.saved);
        window.location.reload();
      } else {
        this.checked = !wasChecked;
        Swal.fire('Error', response.error || T.error, 'error');
      }
    });
  }

  // ========== BONUS MALUS VISIBILITY ==========
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
        cancelButtonText: T.no,
      });

      if (!res.isConfirmed) return;

      this.checked = wasChecked;
      const response = await postToggle('show_bonus_malus', wasChecked);

      if (response.ok) {
        sessionStorage.setItem('settings_saved_toast', T.saved);
        // ✅ Update cascade after toggle
        updateBonusCascade();
        
        if (response.reload) {
          window.location.reload();
        } else {
          Swal.fire({
            toast: true,
            position: 'bottom',
            icon: 'success',
            title: T.saved,
            timer: 1600,
            showConfirmButton: false,
          });
        }
      } else {
        this.checked = !wasChecked;
        Swal.fire('Error', response.error || T.error, 'error');
      }
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
        cancelButtonText: T.no,
      });

      if (!res.isConfirmed) return;

      this.checked = wasChecked;
      const response = await postToggle('easy_relation_setup', wasChecked);

      if (response.ok) {
        sessionStorage.setItem('settings_saved_toast', T.saved);
        if (response.reload) {
          window.location.reload();
        } else {
          Swal.fire({
            toast: true,
            position: 'bottom',
            icon: 'success',
            title: T.saved,
            timer: 1600,
            showConfirmButton: false,
          });
        }
      } else {
        this.checked = !wasChecked;
        Swal.fire('Error', response.error || T.error, 'error');
      }
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
        cancelButtonText: T.no,
      });

      if (!res.isConfirmed) return;

      this.checked = wasChecked;
      const response = await postToggle('force_oauth_2fa', wasChecked);

      if (response.ok) {
        sessionStorage.setItem('settings_saved_toast', T.saved);
        if (response.reload) {
          window.location.reload();
        } else {
          Swal.fire({
            toast: true,
            position: 'bottom',
            icon: 'success',
            title: T.saved,
            timer: 1600,
            showConfirmButton: false,
          });
        }
      } else {
        this.checked = !wasChecked;
        Swal.fire('Error', response.error || T.error, 'error');
      }
    });
  }

  // ========== EMPLOYEES SEE BONUSES ==========
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
        cancelButtonText: T.no,
      });

      if (!res.isConfirmed) return;

      this.checked = wasChecked;
      const response = await postToggle('employees_see_bonuses', wasChecked);

      if (response.ok) {
        sessionStorage.setItem('settings_saved_toast', T.saved);
        if (response.reload) {
          window.location.reload();
        } else {
          Swal.fire({
            toast: true,
            position: 'bottom',
            icon: 'success',
            title: T.saved,
            timer: 1600,
            showConfirmButton: false,
          });
        }
      } else {
        this.checked = !wasChecked;
        Swal.fire('Error', response.error || T.error, 'error');
      }
    });
  }

  // ========== ENABLE BONUS CALCULATION ==========
  if (enableBonusCalculationEl) {
    enableBonusCalculationEl.addEventListener('change', async function(e){
      e.preventDefault();
      const wasChecked = this.checked;
      this.checked = !wasChecked;

      const confirmText = wasChecked 
        ? "Ha bekapcsolod, a rendszer automatikusan számítja a bónuszokat az értékelés lezárásakor."
        : "Ha kikapcsolod, a rendszer NEM fogja automatikusan számítani a bónuszokat.";

      const res = await Swal.fire({
        title: T.confirm,
        text: confirmText,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: T.yes,
        cancelButtonText: T.no,
      });

      if (!res.isConfirmed) return;

      this.checked = wasChecked;
      const response = await postToggle('enable_bonus_calculation', wasChecked);

      if (response.ok) {
        sessionStorage.setItem('settings_saved_toast', T.saved);
        // ✅ Update cascade after toggle
        updateBonusCascade();
        
        if (response.reload) {
          window.location.reload();
        } else {
          Swal.fire({
            toast: true,
            position: 'bottom',
            icon: 'success',
            title: T.saved,
            timer: 1600,
            showConfirmButton: false,
          });
        }
      } else {
        this.checked = !wasChecked;
        Swal.fire('Error', response.error || T.error, 'error');
      }
    });
  }

   // ========== THRESHOLD MODE SWITCHING (RESTORED) ==========
  const modeSwitch = document.getElementById('threshold-mode-switch');
  const hiddenModeInput = document.getElementById('config-mode');
  
  if (modeSwitch && hiddenModeInput) {
    // Listen for mode changes
    modeSwitch.addEventListener('change', function(e) {
      if (e.target.type === 'radio' && e.target.name === 'threshold_mode') {
        const selectedMode = e.target.value;
        
        // Update hidden input
        hiddenModeInput.value = selectedMode;
        
        // Hide all mode panes
        document.querySelectorAll('.mode-pane').forEach(pane => {
          pane.classList.remove('active');
        });
        
        // Show selected mode pane
        const targetPane = document.querySelector(`.mode-pane.mode-${selectedMode}`);
        if (targetPane) {
          targetPane.classList.add('active');
        }
      }
    });
  }
});
</script>