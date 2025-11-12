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
    warn_employees_see_bonuses_on:  @json(__('admin/settings.settings.warn_employees_see_bonuses_on')),
    warn_employees_see_bonuses_off: @json(__('admin/settings.settings.warn_employees_see_bonuses_off')),
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
      const response = await postToggle('strict_anonymous_mode', wasChecked);

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
      const response = await postToggle('ai_telemetry_enabled', wasChecked);

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
        ? @json(__('admin/settings.settings.warn_enable_bonus_calc_on'))
        : @json(__('admin/settings.settings.warn_enable_bonus_calc_off'));

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

<script>
// ========== API KEY MANAGEMENT ==========
(function() {
  // Translation strings passed from PHP
  const T = {
    loading:                  @json(__('admin/settings.settings.api_loading')),
    noKey:                    @json(__('admin/settings.settings.api_no_key')),
    keyLastChars:             @json(__('admin/settings.settings.api_key_last_chars')),
    badgeActive:              @json(__('admin/settings.settings.api_badge_active')),
    badgeRevoked:             @json(__('admin/settings.settings.api_badge_revoked')),
    metaName:                 @json(__('admin/settings.settings.api_meta_name')),
    metaCreated:              @json(__('admin/settings.settings.api_meta_created')),
    metaCreatedBy:            @json(__('admin/settings.settings.api_meta_created_by')),
    metaLastUsed:             @json(__('admin/settings.settings.api_meta_last_used')),
    metaRequests24h:          @json(__('admin/settings.settings.api_meta_requests_24h')),
    metaNeverUsed:            @json(__('admin/settings.settings.api_meta_never_used')),
    btnGenerate:              @json(__('admin/settings.settings.api_btn_generate')),
    btnRevoke:                @json(__('admin/settings.settings.api_btn_revoke')),
    btnCopy:                  @json(__('admin/settings.settings.api_btn_copy')),
    btnCopied:                @json(__('admin/settings.settings.api_btn_copied')),
    modalGenerateTitle:       @json(__('admin/settings.settings.api_modal_generate_title')),
    modalGenerateNameLabel:   @json(__('admin/settings.settings.api_modal_generate_name_label')),
    modalGenerateNamePlaceholder: @json(__('admin/settings.settings.api_modal_generate_name_placeholder')),
    modalGenerateNameHelp:    @json(__('admin/settings.settings.api_modal_generate_name_help')),
    modalGenerateConfirm:     @json(__('admin/settings.settings.api_modal_generate_confirm')),
    modalRevokeTitle:         @json(__('admin/settings.settings.api_modal_revoke_title')),
    modalRevokeText:          @json(__('admin/settings.settings.api_modal_revoke_text')),
    modalRevokeConfirm:       @json(__('admin/settings.settings.api_modal_revoke_confirm')),
    validationNameRequired:   @json(__('admin/settings.settings.api_validation_name_required')),
    validationNameTooShort:   @json(__('admin/settings.settings.api_validation_name_too_short')),
    generateSuccess:          @json(__('admin/settings.settings.api_generate_success')),
    revokeSuccess:            @json(__('admin/settings.settings.api_revoke_success')),
    copySuccess:              @json(__('admin/settings.settings.api_copy_success')),
    generateError:            @json(__('admin/settings.settings.api_generate_error')),
    revokeError:              @json(__('admin/settings.settings.api_revoke_error')),
    loadError:                @json(__('admin/settings.settings.api_load_error')),
    generating:               @json(__('admin/settings.settings.api_generating')),
    revoking:                 @json(__('admin/settings.settings.api_revoking')),
    error:                    @json(__('global.error')),
    cancel:                   @json(__('global.cancel')),
    unknownUser:              @json(__('admin/settings.settings.api_unknown_user')),
  };

  // Toast helper (already exists in settings page)
  function showToast(msg, icon = 'success') {
    Swal.fire({
      toast: true,
      position: 'bottom',
      icon: icon,
      title: msg,
      timer: 2000,
      showConfirmButton: false,
    });
  }

  // Load API keys on page load
  loadApiKeys();

  function loadApiKeys() {
    fetch("{{ route('admin.settings.api-keys.index') }}", {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      }
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        renderApiKeys(data.keys);
      }
    })
    .catch(err => {
      console.error('Error loading API keys:', err);
      document.getElementById('api-key-status').innerHTML = `
        <div class="alert alert-danger">
          ${T.loadError}
        </div>
      `;
    });
  }

  function renderApiKeys(keys) {
    const container = document.getElementById('api-key-status');
    
    // Find active key
    const activeKey = keys.find(k => k.is_active);
    
    if (!activeKey) {
      // No active key - show generate button
      container.innerHTML = `
        <div class="no-api-key">
          <i class="fas fa-key"></i>
          <p>${T.noKey}</p>
          <button class="btn btn-primary" id="generate-api-key">
            <i class="fas fa-plus"></i> ${T.btnGenerate}
          </button>
        </div>
      `;
      
      document.getElementById('generate-api-key').addEventListener('click', showGenerateModal);
    } else {
      // Active key exists - show details
      const createdDate = new Date(activeKey.created_at).toLocaleDateString('hu-HU');
      const lastUsed = activeKey.last_used_at 
        ? new Date(activeKey.last_used_at).toLocaleDateString('hu-HU') + ' ' + new Date(activeKey.last_used_at).toLocaleTimeString('hu-HU')
        : T.metaNeverUsed;
      
      container.innerHTML = `
        <div class="api-key-info">
          <div>
            <span class="api-key-badge active">${T.badgeActive}</span>
          </div>
          
          <div class="api-key-display">
            <div class="key-label">${T.keyLastChars}</div>
            <div class="key-value">••••••••••••••••••••••••••••••••${activeKey.last_chars}</div>
          </div>
          
          <div class="api-key-meta">
            <div class="api-key-meta-item">
              <strong>${T.metaName}</strong>
              <span>${activeKey.name}</span>
            </div>
            <div class="api-key-meta-item">
              <strong>${T.metaCreated}</strong>
              <span>${createdDate}</span>
            </div>
            <div class="api-key-meta-item">
              <strong>${T.metaCreatedBy}</strong>
              <span>${activeKey.created_by_name || T.unknownUser}</span>
            </div>
            <div class="api-key-meta-item">
              <strong>${T.metaLastUsed}</strong>
              <span>${lastUsed}</span>
            </div>
            <div class="api-key-meta-item">
              <strong>${T.metaRequests24h}</strong>
              <span>${activeKey.requests_24h || 0}</span>
            </div>
          </div>
          
          <div class="api-key-actions">
            <button class="btn btn-danger" id="revoke-api-key" data-key-id="${activeKey.id}">
              <i class="fas fa-ban"></i> ${T.btnRevoke}
            </button>
          </div>
        </div>
      `;
      
      document.getElementById('revoke-api-key').addEventListener('click', revokeApiKey);
    }
  }

  function showGenerateModal() {
    Swal.fire({
      title: T.modalGenerateTitle,
      html: `
        <div style="text-align: left;">
          <label for="api-key-name" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
            ${T.modalGenerateNameLabel}
          </label>
          <input 
            type="text" 
            id="api-key-name" 
            class="swal2-input" 
            placeholder="${T.modalGenerateNamePlaceholder}"
            style="width: 100%; margin: 0;"
            maxlength="50"
          >
          <small style="color: #6c757d; display: block; margin-top: 0.5rem;">
            ${T.modalGenerateNameHelp}
          </small>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: T.modalGenerateConfirm,
      cancelButtonText: T.cancel,
      confirmButtonColor: '#28a745',
      preConfirm: () => {
        const name = document.getElementById('api-key-name').value.trim();
        if (!name) {
          Swal.showValidationMessage(T.validationNameRequired);
          return false;
        }
        if (name.length < 3) {
          Swal.showValidationMessage(T.validationNameTooShort);
          return false;
        }
        return name;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        generateApiKey(result.value);
      }
    });
  }

  function generateApiKey(name) {
    // Show loader
    Swal.fire({
      title: T.generating,
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    fetch("{{ route('admin.settings.api-keys.generate') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({ name: name })
    })
    .then(r => r.json())
    .then(data => {
      Swal.close();
      
      if (data.success) {
        showToast(data.message);
        
        // Show the API key in modal (one-time display)
        document.getElementById('api-key-display').value = data.key.key;
        $('#api-key-modal').modal('show');
        
        // Reload keys after modal is closed
        $('#api-key-modal').on('hidden.bs.modal', function() {
          loadApiKeys();
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: T.error,
          text: data.message
        });
      }
    })
    .catch(err => {
      Swal.close();
      console.error('Error generating API key:', err);
      Swal.fire({
        icon: 'error',
        title: T.error,
        text: T.generateError
      });
    });
  }

  function revokeApiKey(e) {
    const keyId = e.currentTarget.getAttribute('data-key-id');
    
    Swal.fire({
      title: T.modalRevokeTitle,
      text: T.modalRevokeText,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: T.modalRevokeConfirm,
      cancelButtonText: T.cancel,
      confirmButtonColor: '#dc3545'
    }).then((result) => {
      if (result.isConfirmed) {
        // Show loader
        Swal.fire({
          title: T.revoking,
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        fetch("{{ route('admin.settings.api-keys.revoke') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          },
          body: JSON.stringify({ key_id: keyId })
        })
        .then(r => r.json())
        .then(data => {
          Swal.close();
          
          if (data.success) {
            showToast(data.message);
            loadApiKeys(); // Reload the keys
          } else {
            Swal.fire({
              icon: 'error',
              title: T.error,
              text: data.message
            });
          }
        })
        .catch(err => {
          Swal.close();
          console.error('Error revoking API key:', err);
          Swal.fire({
            icon: 'error',
            title: T.error,
            text: T.revokeError
          });
        });
      }
    });
  }

  // Copy to clipboard functionality
  document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'copy-api-key') {
      const input = document.getElementById('api-key-display');
      input.select();
      document.execCommand('copy');
      
      // Change button text temporarily
      const btn = e.target;
      const originalHTML = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check"></i> ' + T.btnCopied;
      btn.classList.remove('btn-outline-secondary');
      btn.classList.add('btn-success');
      
      setTimeout(() => {
        btn.innerHTML = originalHTML;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline-secondary');
      }, 2000);
      
      showToast(T.copySuccess);
    }
  });
})();
</script>