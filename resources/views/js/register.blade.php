<script>
document.addEventListener('DOMContentLoaded', function () {
  const tile = document.querySelector('.tile.register-tile');
  const form = document.getElementById('register-form');
  const steps = Array.from(form.querySelectorAll('.reg-step'));
  const nextBtns = form.querySelectorAll('.next-step');
  const prevBtns = form.querySelectorAll('.prev-step');
  const countrySel = form.querySelector('select[name="country_code"]');
  const summaryBox = steps[steps.length - 1].querySelector('.summary');

  let current = 0;

  // Language strings from Laravel
  const lang = {
    required: '{{ __("register.validation.required") }}',
    invalidEmail: '{{ __("register.validation.invalid_email") }}',
    employeeLimitMin: '{{ __("register.validation.employee_limit_min") }}',
    taxNumberRequired: '{{ __("register.validation.tax_number_required") }}',
    euVatFormatHu: '{{ __("register.validation.eu_vat_format_hu") }}',
    euVatRequired: '{{ __("register.validation.eu_vat_required") }}',
    networkError: '{{ __("register.validation.network_error") }}',
    serverError: '{{ __("register.validation.server_error") }}',
    checkboxOn: '{{ __("register.summary.checkbox_on") }}',
    checkboxOff: '{{ __("register.summary.checkbox_off") }}',
    summaryAdmin: '{{ __("register.summary.admin") }}',
    summaryCompanyName: '{{ __("register.summary.company_name") }}',
    summaryEmployeeCount: '{{ __("register.summary.employee_count") }}',
    summaryEmployeeUnit: '{{ __("register.summary.employee_unit") }}',
    summaryBillingAddress: '{{ __("register.summary.billing_address") }}',
    summaryPhone: '{{ __("register.summary.phone") }}',
    summaryTaxIdentification: '{{ __("register.summary.tax_identification") }}',
    summaryTaxNumber: '{{ __("register.summary.tax_number") }}',
    summaryEuVat: '{{ __("register.summary.eu_vat") }}',
    summarySettings: '{{ __("register.summary.settings") }}',
    summaryAiTelemetry: '{{ __("register.summary.ai_telemetry") }}',
    summaryMultiLevel: '{{ __("register.summary.multi_level") }}',
    summaryBonusMalus: '{{ __("register.summary.bonus_malus") }}',
    step1Title: '{{ __("register.steps.step1_title") }}',
    step1Subtitle: '{{ __("register.steps.step1_subtitle") }}',
    step2Title: '{{ __("register.steps.step2_title") }}',
    step2Subtitle: '{{ __("register.steps.step2_subtitle") }}',
    step3Title: '{{ __("register.steps.step3_title") }}',
    step3Subtitle: '{{ __("register.steps.step3_subtitle") }}',
    step4Title: '{{ __("register.steps.step4_title") }}',
    step4Subtitle: '{{ __("register.steps.step4_subtitle") }}'
  };

  // --- EU country list ---
  const EU_CC = [
    'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR',
    'DE','GR','IE','IT','LV','LT','LU','MT','NL','PL',
    'PT','RO','SK','SI','ES','SE','HU'
  ];
  
  function isEU(cc) {
    return EU_CC.includes((cc || '').toUpperCase());
  }

  // --- Toggle tax fields based on country selection ---
  function toggleTaxFields() {
    const cc = (countrySel?.value || 'HU').toUpperCase();
    const isHu = (cc === 'HU');
    const eu = isEU(cc);

    const huOnly = form.querySelector('.hu-only');
    const euVat = form.querySelector('.eu-vat');
    const regionField = form.querySelector('.region-field');

    // Region: hidden for HU, optional for others
    if (regionField) {
      if (isHu) {
        regionField.style.display = 'none';
        regionField.querySelector('input').removeAttribute('required');
        regionField.querySelector('input').value = '';
      } else {
        regionField.style.display = '';
        regionField.querySelector('input').removeAttribute('required');
      }
    }

    // Tax number: required for HU, hidden for others
    if (huOnly) {
      if (isHu) {
        huOnly.style.display = '';
        huOnly.querySelector('input').setAttribute('required', 'required');
      } else {
        huOnly.style.display = 'none';
        huOnly.querySelector('input').removeAttribute('required');
        huOnly.querySelector('input').value = '';
      }
    }

    // EU VAT: optional for HU, required for other EU, hidden for non-EU
    if (euVat) {
      if (eu) {
        euVat.style.display = '';
        if (isHu) {
          euVat.querySelector('input').removeAttribute('required');
        } else {
          euVat.querySelector('input').setAttribute('required', 'required');
        }
      } else {
        euVat.style.display = 'none';
        euVat.querySelector('input').removeAttribute('required');
        euVat.querySelector('input').value = '';
      }
    }
  }

  countrySel?.addEventListener('change', toggleTaxFields);
  toggleTaxFields();

  // --- Animation system ---
  let animating = false;
  function animate(direction) {
    if (!tile) return;
    animating = true;
    const cls = direction === 'next' ? 'anim-next' : 'anim-prev';
    tile.classList.add(cls);
    const onEnd = () => {
      tile.classList.remove(cls);
      tile.removeEventListener('animationend', onEnd);
      animating = false;
    };
    tile.addEventListener('animationend', onEnd);
  }

  function showStep(i) {
    steps.forEach((s, idx) => s.hidden = (idx !== i));
    current = i;
    updateAside(i);
  }

  // --- Validation helpers ---
  function markInvalid(input, on) {
    if (!input) return;
    const method = on ? 'add' : 'remove';
    input.classList[method]('is-invalid');
  }

  function clearInvalids(container) {
    container.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    container.querySelectorAll('.error-msg').forEach(el => el.remove());
  }

  function addErrorBelow(input, msg) {
    const existing = input.parentElement.querySelector('.error-msg');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = 'error-msg text-danger mt-1';
    div.style.fontSize = '0.875rem';
    div.textContent = msg;
    input.parentElement.appendChild(div);
  }

  function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function requiredFilled(container, fieldNames) {
    let ok = true;
    fieldNames.forEach(name => {
      const input = container.querySelector(`[name="${name}"]`);
      if (!input) return;
      
      const isHidden = input.offsetParent === null;
      if (isHidden) return;
      
      const val = (input.value || '').trim();
      if (val === '') {
        markInvalid(input, true);
        addErrorBelow(input, lang.required);
        ok = false;
      } else {
        markInvalid(input, false);
      }
    });
    return ok;
  }

  // --- Tax/VAT validation ---
  function validateTaxNumbers(stepEl) {
    const cc = (countrySel?.value || 'HU').toUpperCase();
    const isHu = (cc === 'HU');
    const eu = isEU(cc);
    
    const taxInput = stepEl.querySelector('[name="tax_number"]');
    const euInput = stepEl.querySelector('[name="eu_vat_number"]');
    
    let ok = true;

    if (isHu) {
      if (taxInput && taxInput.offsetParent !== null) {
        const val = (taxInput.value || '').trim();
        const good = val !== '' && val.length >= 8;
        markInvalid(taxInput, !good);
        if (!good) {
          ok = false;
          addErrorBelow(taxInput, lang.taxNumberRequired);
        }
      }
      
      if (euInput && euInput.offsetParent !== null && euInput.value.trim() !== '') {
        const val = euInput.value.trim().toUpperCase();
        const good = /^[A-Z]{2}[A-Za-z0-9]{2,12}$/.test(val);
        markInvalid(euInput, !good);
        if (!good) {
          ok = false;
          addErrorBelow(euInput, lang.euVatFormatHu);
        }
      }
    } else if (eu) {
      if (euInput && euInput.offsetParent !== null) {
        const val = (euInput.value || '').trim().toUpperCase();
        const good = /^[A-Z]{2}[A-Za-z0-9]{2,12}$/.test(val);
        markInvalid(euInput, !good);
        if (!good) {
          ok = false;
          addErrorBelow(euInput, lang.euVatRequired);
        }
      }
    }

    return ok;
  }

  // --- Step validation ---
  function validateStep(idx) {
    const stepEl = steps[idx];
    if (!stepEl) return false;
    clearInvalids(stepEl);

    // STEP 0: Admin name + email + employee_limit
    if (idx === 0) {
      const ok1 = requiredFilled(stepEl, ['admin_name', 'admin_email', 'employee_limit']);
      
      const emailInput = stepEl.querySelector('[name="admin_email"]');
      let ok2 = true;
      if (emailInput) {
        const good = validateEmail(emailInput.value);
        markInvalid(emailInput, !good);
        if (!good) {
          ok2 = false;
          addErrorBelow(emailInput, lang.invalidEmail);
        }
      }
      
      const empLimitInput = stepEl.querySelector('[name="employee_limit"]');
      let ok3 = true;
      if (empLimitInput && empLimitInput.value) {
        const val = parseInt(empLimitInput.value);
        if (isNaN(val) || val < 1) {
          markInvalid(empLimitInput, true);
          addErrorBelow(empLimitInput, lang.employeeLimitMin);
          ok3 = false;
        }
      }
      
      return ok1 && ok2 && ok3;
    }

    // STEP 1: Company + address + tax
    if (idx === 1) {
      const requiredFields = [
        'org_name', 'country_code',
        'postal_code', 'city', 'street', 'house_number', 'phone'
      ];
      
      const ok1 = requiredFilled(stepEl, requiredFields);
      const ok2 = validateTaxNumbers(stepEl);
      
      return ok1 && ok2;
    }

    // STEP 2: Settings
    if (idx === 2) {
      return true;
    }

    // STEP 3: Summary
    if (idx === 3) return true;

    return true;
  }

  // --- Server-side validation (AJAX) ---
  const csrf = form.querySelector('input[name="_token"]')?.value || '';
  
  async function serverValidateStep(stepIndex) {
    if (stepIndex !== 0 && stepIndex !== 1) return { ok: true };

    const payload = new FormData();
    payload.append('step', String(stepIndex));
    
    if (stepIndex === 0) {
      payload.append('admin_email', form.querySelector('[name="admin_email"]')?.value || '');
    } else if (stepIndex === 1) {
      payload.append('country_code', form.querySelector('[name="country_code"]')?.value || '');
      payload.append('tax_number', form.querySelector('[name="tax_number"]')?.value || '');
      payload.append('eu_vat_number', form.querySelector('[name="eu_vat_number"]')?.value || '');
    }

    const res = await fetch('{{ route('register.validate-step') }}', {
      method: 'POST',
      headers: { 
        'X-Requested-With': 'XMLHttpRequest', 
        'X-CSRF-TOKEN': csrf, 
        'Accept': 'application/json' 
      },
      body: payload
    }).catch(() => null);

    if (!res) return { ok: false, errors: { _: lang.networkError } };
    if (!res.ok) {
      const json = await res.json().catch(() => ({}));
      return { ok: false, errors: json.errors || { _: lang.serverError } };
    }

    const json = await res.json().catch(() => ({}));
    if (!json.ok) {
      const stepEl = steps[stepIndex];
      if (stepEl && json.errors) {
        for (const field in json.errors) {
          const input = stepEl.querySelector(`[name="${field}"]`);
          if (input) {
            markInvalid(input, true);
            addErrorBelow(input, json.errors[field]);
          }
        }
      }
      return { ok: false, errors: json.errors || {} };
    }

    return { ok: true };
  }

  // --- Build summary ---
  function buildSummary() {
    const S = (name) => form.querySelector(`[name="${name}"]`)?.value || '';
    const B = (name) => form.querySelector(`[name="${name}"]`)?.checked ? lang.checkboxOn : lang.checkboxOff;

    const summaryHTML = `
      <dl>
        <dt>${lang.summaryAdmin}</dt>
        <dd>${S('admin_name')} &lt;${S('admin_email')}&gt;</dd>

        <dt>${lang.summaryCompanyName}</dt>
        <dd>${S('org_name')}</dd>

        <dt>${lang.summaryEmployeeCount}</dt>
        <dd>${S('employee_limit')} ${lang.summaryEmployeeUnit}</dd>

        <dt>${lang.summaryBillingAddress}</dt>
        <dd>
          ${S('country_code')} ${S('postal_code')}, ${S('region') ? S('region') + ' ' : ''}${S('city')}<br>
          ${S('street')} ${S('house_number')}<br>
          ${lang.summaryPhone}: ${S('phone')}
        </dd>

        <dt>${lang.summaryTaxIdentification}</dt>
        <dd>${lang.summaryTaxNumber}: ${S('tax_number') || '—'}; ${lang.summaryEuVat}: ${S('eu_vat_number') || '—'}</dd>

        <dt>${lang.summarySettings}</dt>
        <dd>${lang.summaryAiTelemetry}: ${B('ai_telemetry_enabled')} | ${lang.summaryMultiLevel}: ${B('enable_multi_level')} | ${lang.summaryBonusMalus}: ${B('show_bonus_malus')}</dd>
      </dl>
    `;
    if (summaryBox) summaryBox.innerHTML = summaryHTML;
  }

  // --- Navigation ---
  async function goNext() {
    if (animating) return;
    
    if (!validateStep(current)) return;

    const server = await serverValidateStep(current);
    if (!server.ok) return;

    if (current === 2) buildSummary();

    if (current < steps.length - 1) {
      animate('next');
      showStep(current + 1);
    }
  }

  function goPrev() {
    if (animating) return;
    if (current > 0) {
      animate('prev');
      showStep(current - 1);
    }
  }

  nextBtns.forEach(b => b.addEventListener('click', goNext));
  prevBtns.forEach(b => b.addEventListener('click', goPrev));

  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      const isLast = (current === steps.length - 1);
      if (!isLast) {
        e.preventDefault();
        goNext();
      }
    }
  });

  form.addEventListener('submit', function (e) {
    for (let i = 0; i < steps.length - 1; i++) {
      if (!validateStep(i)) {
        e.preventDefault();
        if (i < current) animate('prev'); 
        else animate('next');
        showStep(i);
        return false;
      }
    }
    return true;
  });

  // --- Setup guide step texts ---
  const flowTitle = document.getElementById('flow-title');
  const flowSubtitle = document.getElementById('flow-subtitle');
  const STEP_TEXTS = [
    { title: lang.step1Title, sub: lang.step1Subtitle },
    { title: lang.step2Title, sub: lang.step2Subtitle },
    { title: lang.step3Title, sub: lang.step3Subtitle },
    { title: lang.step4Title, sub: lang.step4Subtitle }
  ];

  function updateAside(stepIndex) {
    const txt = STEP_TEXTS[stepIndex] || STEP_TEXTS[0];
    if (flowTitle) flowTitle.textContent = txt.title;
    if (flowSubtitle) flowSubtitle.textContent = txt.sub;
  }

  showStep(0);
});
</script>