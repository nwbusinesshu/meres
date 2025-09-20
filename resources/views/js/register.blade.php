<script>
document.addEventListener('DOMContentLoaded', function () {
  const tile = document.querySelector('.tile.register-tile');
  const form = document.getElementById('register-form');
  const steps = Array.from(form.querySelectorAll('.reg-step'));
  const nextBtns = form.querySelectorAll('.next-step');
  const prevBtns = form.querySelectorAll('.prev-step');
  const countrySel = form.querySelector('select[name="country_code"]');
  const euOnly = form.querySelector('.eu-only');
  const nonEuOnly = form.querySelector('.non-eu-only');
  const summaryBox = steps[steps.length - 1].querySelector('.summary');

  let current = 0;

  // --- EU/HU logika (mezők mutatása/elrejtése) ---
  const EU_CC = [
  'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR',
  'DE','GR','IE','IT','LV','LT','LU','MT','NL','PL',
  'PT','RO','SK','SI','ES','SE','HU'
];
function isEU(cc) {
  return EU_CC.includes((cc || '').toUpperCase());
}

  function toggleTaxFields() {
  const cc = (countrySel?.value || 'HU').toUpperCase();
  const isHu = (cc === 'HU');
  const eu = isEU(cc);

  const huOnly = form.querySelector('.hu-only');
  const euVat  = form.querySelector('.eu-vat');

  if (huOnly) huOnly.style.display = isHu ? '' : 'none';

  if (euVat) {
    // magyarországnál opcionális (megjelenik, de nem kötelező)
    // más EU országnál kötelező
    // EU-n kívül is opcionális
    euVat.style.display = (isHu || eu) ? '' : 'none';
    if (eu && !isHu) {
      euVat.querySelector('input').setAttribute('required', 'required');
    } else {
      euVat.querySelector('input').removeAttribute('required');
    }
  }
}


  countrySel?.addEventListener('change', toggleTaxFields);
  toggleTaxFields();

  // --- Lépés-animációk (teljes tile csúszik) ---
  let animating = false;
  function animate(direction) {
    // direction: 'next' | 'prev'
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


  // --- Validáció (lépésenként) ---
  function markInvalid(input, on) {
    if (!input) return;
    const method = on ? 'add' : 'remove';
    input.classList[method]('is-invalid');
  }

  function clearInvalids(stepEl) {
    stepEl.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    stepEl.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
  }

  function addErrorBelow(input, msg) {
    if (!input) return;
    // csak 1 hibaüzenet mezőnként
    if (input.nextElementSibling && input.nextElementSibling.classList.contains('invalid-feedback')) return;
    const fb = document.createElement('div');
    fb.className = 'invalid-feedback';
    fb.style.display = 'block';
    fb.textContent = msg;
    input.after(fb);
  }

  function requiredFilled(stepEl, names) {
    let ok = true;
    names.forEach(n => {
      const input = stepEl.querySelector(`[name="${n}"]`);
      if (!input) return;
      const val = (input.value || '').trim();
      const good = val.length > 0;
      markInvalid(input, !good);
      if (!good) ok = false;
    });
    return ok;
  }

  function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((email || '').trim());
  }

  function validateTaxNumbers(stepEl) {
  const cc = (countrySel?.value || 'HU').toUpperCase();
  const isHu = (cc === 'HU');
  const eu = isEU(cc);

  const taxInput = stepEl.querySelector('[name="tax_number"]');
  const euInput  = stepEl.querySelector('[name="eu_vat_number"]');
  let ok = true;

  if (isHu) {
    // HU: kötelező adószám
    if (taxInput) {
      const val = (taxInput.value || '').trim();
      const good = val.length >= 6;
      markInvalid(taxInput, !good);
      if (!good) { ok = false; addErrorBelow(taxInput, 'Érvényes adószám szükséges.'); }
    }
    // EU VAT opcionális: ha van, ellenőrizzük formátumát
    if (euInput && euInput.value.trim() !== '') {
      const good = /^[A-Z]{2}[A-Za-z0-9]{2,12}$/.test(euInput.value.trim());
      markInvalid(euInput, !good);
      if (!good) { ok = false; addErrorBelow(euInput, 'Érvényes EU VAT szám szükséges.'); }
    }
  } else if (eu) {
    // EU, de nem HU: kötelező EU VAT
    if (euInput) {
      const val = (euInput.value || '').trim();
      const good = /^[A-Z]{2}[A-Za-z0-9]{2,12}$/.test(val);
      markInvalid(euInput, !good);
      if (!good) { ok = false; addErrorBelow(euInput, 'Érvényes EU VAT szám szükséges.'); }
    }
  } else {
    // EU-n kívül: EU VAT opcionális
    if (euInput && euInput.value.trim() !== '') {
      const good = /^[A-Z]{2}[A-Za-z0-9]{2,12}$/.test(euInput.value.trim());
      markInvalid(euInput, !good);
      if (!good) { ok = false; addErrorBelow(euInput, 'Érvényes EU VAT szám szükséges.'); }
    }
  }

  return ok;
}


  function validateStep(idx) {
    const stepEl = steps[idx];
    if (!stepEl) return false;
    clearInvalids(stepEl);

    // STEP1: admin név + email
    if (idx === 0) {
      const ok1 = requiredFilled(stepEl, ['admin_name','admin_email']);
      const emailInput = stepEl.querySelector('[name="admin_email"]');
      let ok2 = true;
      if (emailInput) {
        const good = validateEmail(emailInput.value);
        markInvalid(emailInput, !good);
        if (!good) {
          ok2 = false;
          addErrorBelow(emailInput, 'Adj meg érvényes e-mail címet.');
        }
      }
      return ok1 && ok2;
    }

    // STEP2: org + cím + tax/eu vat logika
    if (idx === 1) {
      const req = ['org_name','country_code','city','street','house_number'];
      const ok1 = requiredFilled(stepEl, req);
      const ok2 = validateTaxNumbers(stepEl);
      return ok1 && ok2;
    }

    // STEP3: nincsen kötelező, minden opcionális
    if (idx === 2) {
      return true;
    }

    // STEP4: összegzés
    if (idx === 3) return true;

    return true;
  }

  // --- SZERVER oldali validáció (AJAX) — email & adóazonosító egyediség ---
  const csrf = form.querySelector('input[name="_token"]')?.value || '';
  async function serverValidateStep(stepIndex) {
    if (stepIndex !== 0 && stepIndex !== 1) return { ok: true };

    const payload = new FormData();
    payload.append('step', String(stepIndex));
    if (stepIndex === 0) {
      payload.append('admin_email', form.querySelector('[name="admin_email"]')?.value || '');
    } else if (stepIndex === 1) {
      payload.append('country_code', form.querySelector('[name="country_code"]')?.value || '');
      payload.append('tax_number',   form.querySelector('[name="tax_number"]')?.value || '');
      payload.append('eu_vat_number',form.querySelector('[name="eu_vat_number"]')?.value || '');
    }

    const res = await fetch('{{ route('register.validate-step') }}', {
      method: 'POST',
      headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' },
      body: payload
    }).catch(() => null);

    if (!res) return { ok:false, errors:{ _:'Hálózati hiba. Próbáld újra.' } };

    const data = await res.json().catch(() => ({ ok:false, errors:{ _:'Ismeretlen hiba.' } }));

    // szerver hibák kirajzolása az adott lépésre
    if (!data.ok && data.errors) {
      const stepEl = steps[stepIndex];
      // korábbi hibák törlése
      stepEl.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      stepEl.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

      Object.entries(data.errors).forEach(([name, msg]) => {
        // ha nem mezőszintű a hiba (pl. _), tegyük az első címke alá
        const input = stepEl.querySelector(`[name="${name}"]`) || stepEl.querySelector('input,select,textarea');
        if (!input) return;
        input.classList.add('is-invalid');
        const fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        fb.style.display = 'block';
        fb.textContent = String(msg);
        input.after(fb);
      });
    }
    return data;
  }

  // --- Összegzés felépítése (lépés 4 előtt) ---
  function buildSummary() {
    const data = new FormData(form);
    const S = (name) => (data.get(name) || '').toString().trim();
    const B = (name) => data.get(name) ? 'Be' : 'Ki';

    const summaryHTML = `
      <dl>
        <dt>Admin</dt>
        <dd>${S('admin_name')} &lt;${S('admin_email')}&gt;</dd>

        <dt>Cégnév</dt>
        <dd>${S('org_name')}</dd>

        <dt>Számlázási cím</dt>
        <dd>
          ${S('country_code')} ${S('postal_code') || ''}, ${S('region') || ''} ${S('city')}<br>
          ${S('street')} ${S('house_number')}<br>
          Tel: ${S('phone') || '—'}
        </dd>

        <dt>Adóazonosítás</dt>
        <dd>Adószám: ${S('tax_number') || '—'}; EU ÁFA: ${S('eu_vat_number') || '—'}</dd>

        <dt>Beállítások</dt>
        <dd>AI telemetria: ${B('ai_telemetry_enabled')} | Multi-level: ${B('enable_multi_level')} | Bonus/Malus: ${B('show_bonus_malus')}</dd>
      </dl>
    `;
    if (summaryBox) summaryBox.innerHTML = summaryHTML;
  }

  // --- Lépésvezérlés ---
  async function goNext() {
    if (animating) return;
    // 1) kliens oldali gyors valid
    if (!validateStep(current)) return;

    // 2) szerver oldali (AJAX) valid a lépésre
    const server = await serverValidateStep(current);
    if (!server.ok) return; // a hibák már megjelennek

    // 3) ha a következő a 4. lépés (összegzés), előbb építsük fel
    if (current === 2) buildSummary();

    // 4) anim + váltás
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

  // Enter: csak az adott lépésen értelmesen működjön
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      const isLast = (current === steps.length - 1);
      if (!isLast) {
        e.preventDefault();
        goNext();
      }
    }
  });

  // Submit előtt végső validáció – (step3 után már összeszedtük)
  form.addEventListener('submit', function (e) {
    for (let i = 0; i < steps.length - 1; i++) {
      if (!validateStep(i)) {
        e.preventDefault();
        if (i < current) animate('prev'); else animate('next');
        showStep(i);
        return false;
      }
    }
    return true;
  });

  // indulás
  showStep(0);
});

  // Setup guide lépéscímek
  const flowTitle = document.getElementById('flow-title');
  const flowSubtitle = document.getElementById('flow-subtitle');
  const STEP_TEXTS = [
    {
      title: 'Regisztráljon egy admin felhasználót!',
      sub:   'Az admin mindent kezel: felhasználók, értékelések, beállítások. Adja meg az admin nevét és e-mail címét.'
    },
    {
      title: 'Adja meg a céges és számlázási adatokat',
      sub:   'A számlázáshoz kérjük a címadatokat és adóazonosítót. EU-s ország esetén EU ÁFA-szám szükséges.'
    },
    {
      title: 'Válassza ki az alapbeállításokat',
      sub:   'AI telemetria, multi-level részlegkezelés és Bonus/Malus megjelenítés. Ezek később módosíthatók (a multi-level nem).'
    },
    {
      title: 'Ellenőrizze és véglegesítse',
      sub:   'Nézze át az összegzést. A befejezés után e-mailben kap linket a jelszó beállításához.'
    }
  ];
  function updateAside(i){
    if (!flowTitle || !flowSubtitle) return;
    const t = STEP_TEXTS[i] || STEP_TEXTS[0];
    flowTitle.textContent = t.title;
    flowSubtitle.textContent = t.sub;
  }


</script>