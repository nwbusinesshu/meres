<script>
(function () {
  window.CSRF = window.CSRF || (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
  function ajax(method, url, data) {
     return fetch(url, {
       method: method || 'POST',
       headers: {
         'Content-Type': 'application/json',
         'X-CSRF-TOKEN': window.CSRF
       },
       body: method === 'GET' ? undefined : JSON.stringify(data || {})
     }).then(async (r) => {
      if (!r.ok) {
        const t = await r.text().catch(() => '');
        throw new Error(t || ('HTTP ' + r.status));
      }
      // lehet, hogy nincs JSON (204), ezért try
      try { return await r.json(); }
      catch { return {}; }
    });
  }

  function closest(el, selector) {
    while (el && el.nodeType === 1) {
      if (el.matches(selector)) return el;
      el = el.parentElement;
    }
    return null;
  }

  // összehajtható csoport
  document.addEventListener('click', function (e) {
    const bar = closest(e.target, '.competency-item .bar');
    if (bar) {
      const wrap = bar.parentElement.querySelector('.questions');
      if (wrap) wrap.classList.toggle('hidden');
    }
  });

  // KOMPETENCIA: létrehozás
  document.addEventListener('click', function (e) {
    const btn = closest(e.target, '.create-competency');
    if (!btn) return;

    const modal = document.getElementById('competency-modal');
    modal.querySelector('input[name="id"]').value = ''; // új
    modal.querySelector('input[name="name"]').value = '';
    $(modal).modal('show');

    const saveBtn = modal.querySelector('.btn-save');
    saveBtn.onclick = function () {
      const name = modal.querySelector('input[name="name"]').value.trim();
      if (!name) return alert('Név kötelező');

      ajax('POST', window.SuperadminCompetencyRoutes.save, { name })
        .then(() => location.href = window.SuperadminCompetencyRoutes.index)
        .catch(err => alert('Hiba: ' + err.message));
    };
  });

  // KOMPETENCIA: módosítás
  document.addEventListener('click', function (e) {
    const btn = closest(e.target, '.modify-competency');
    if (!btn) return;

    const item = closest(btn, '.competency-item');
    const id = item.getAttribute('data-id');
    const name = item.getAttribute('data-name');

    const modal = document.getElementById('competency-modal');
    modal.querySelector('input[name="id"]').value = id;
    modal.querySelector('input[name="name"]').value = name || '';
    $(modal).modal('show');

    const saveBtn = modal.querySelector('.btn-save');
    saveBtn.onclick = function () {
      const newName = modal.querySelector('input[name="name"]').value.trim();
      if (!newName) return alert('Név kötelező');

      ajax('POST', window.SuperadminCompetencyRoutes.save, { id, name: newName })
        .then(() => location.href = window.SuperadminCompetencyRoutes.index)
        .catch(err => alert('Hiba: ' + err.message));
    };
  });

  // KOMPETENCIA: törlés
  document.addEventListener('click', function (e) {
    const btn = closest(e.target, '.remove-competency');
    if (!btn) return;

    const item = closest(btn, '.competency-item');
    const id = item.getAttribute('data-id');
    const name = item.getAttribute('data-name') || 'Ismeretlen';

    if (!confirm(`Biztosan törlöd a(z) "${name}" kompetenciát?`)) return;

    ajax('POST', window.SuperadminCompetencyRoutes.remove, { id })
      .then(() => location.href = window.SuperadminCompetencyRoutes.index)
      .catch(err => alert('Hiba: ' + err.message));
  });

  // KÉRDÉS: létrehozás
  document.addEventListener('click', function (e) {
    const btn = closest(e.target, '.create-question');
    if (!btn) return;

    const compItem = closest(btn, '.competency-item');
    const compId = compItem.getAttribute('data-id');

    const modal = document.getElementById('competencyq-modal');
    modal.querySelector('input[name="id"]').value = '';
    modal.querySelector('input[name="compId"]').value = compId;

    modal.querySelector('textarea[name="question"]').value = '';
    modal.querySelector('textarea[name="questionSelf"]').value = '';
    modal.querySelector('input[name="minLabel"]').value = '';
    modal.querySelector('input[name="maxLabel"]').value = '';
    modal.querySelector('input[name="scale"]').value = '5';

    $(modal).modal('show');

    modal.querySelector('.btn-save').onclick = function () {
      const payload = {
        compId,
        question: modal.querySelector('textarea[name="question"]').value.trim(),
        questionSelf: modal.querySelector('textarea[name="questionSelf"]').value.trim(),
        minLabel: modal.querySelector('input[name="minLabel"]').value.trim(),
        maxLabel: modal.querySelector('input[name="maxLabel"]').value.trim(),
        scale: Number(modal.querySelector('input[name="scale"]').value || 5),
      };
      if (!payload.question || !payload.questionSelf || !payload.minLabel || !payload.maxLabel) {
        return alert('Minden mező kötelező.');
      }

      ajax('POST', window.SuperadminCompetencyRoutes.qSave, payload)
        .then(() => location.href = window.SuperadminCompetencyRoutes.index)
        .catch(err => alert('Hiba: ' + err.message));
    };
  });

  // KÉRDÉS: módosítás
  document.addEventListener('click', function (e) {
    const btn = closest(e.target, '.modify-question');
    if (!btn) return;

    const qItem = closest(btn, '.question-item');
    const qId = qItem.getAttribute('data-id');

    const modal = document.getElementById('competencyq-modal');

    // betöltjük a kérdést
    fetch(window.SuperadminCompetencyRoutes.qGet + '?id=' + encodeURIComponent(qId), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => r.json())
      .then(q => {
        modal.querySelector('input[name="id"]').value = q.id;
        modal.querySelector('input[name="compId"]').value = q.competency_id;

        modal.querySelector('textarea[name="question"]').value = q.question || '';
        modal.querySelector('textarea[name="questionSelf"]').value = q.question_self || '';
        modal.querySelector('input[name="minLabel"]').value = q.min_label || '';
        modal.querySelector('input[name="maxLabel"]').value = q.max_label || '';
        modal.querySelector('input[name="scale"]').value = q.max_value || 5;

        $(modal).modal('show');

        modal.querySelector('.btn-save').onclick = function () {
          const payload = {
            id: q.id,
            compId: q.competency_id,
            question: modal.querySelector('textarea[name="question"]').value.trim(),
            questionSelf: modal.querySelector('textarea[name="questionSelf"]').value.trim(),
            minLabel: modal.querySelector('input[name="minLabel"]').value.trim(),
            maxLabel: modal.querySelector('input[name="maxLabel"]').value.trim(),
            scale: Number(modal.querySelector('input[name="scale"]').value || 5),
          };
          ajax('POST', window.SuperadminCompetencyRoutes.qSave, payload)
            .then(() => location.href = window.SuperadminCompetencyRoutes.index)
            .catch(err => alert('Hiba: ' + err.message));
        };
      })
      .catch(err => alert('Hiba a kérdés betöltésekor: ' + err.message));
  });

  // KÉRDÉS: törlés
  document.addEventListener('click', function (e) {
    const btn = closest(e.target, '.remove-question');
    if (!btn) return;

    const qItem = closest(btn, '.question-item');
    const qId = qItem.getAttribute('data-id');

    if (!confirm('Biztosan törlöd a kérdést?')) return;

    ajax('POST', window.SuperadminCompetencyRoutes.qRemove, { id: qId })
      .then(() => location.href = window.SuperadminCompetencyRoutes.index)
      .catch(err => alert('Hiba: ' + err.message));
  });

  // KERESŐ (ha benne hagytad a markupot)
  const searchInput = document.querySelector('.competency-search-input');
  const clearBtn = document.querySelector('.competency-clear-search');
  function filterList() {
    const q = (searchInput.value || '').toLowerCase();
    document.querySelectorAll('.competency-item').forEach(item => {
      const name = (item.getAttribute('data-name') || '').toLowerCase();
      item.style.display = name.includes(q) ? '' : 'none';
    });
  }
  if (searchInput) {
    searchInput.addEventListener('input', filterList);
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      searchInput.value = '';
      filterList();
    });
  }

})();
</script>