@extends('layouts.master')

@section('head-extra')
@endsection

@section('content')
<h1>Globális kompetenciák</h1>

<div class="fixed-row">
  <div class="tile tile-info competency-search">
    <span>{{ $_('search') }}</span>
    <div>
      <input type="text" class="form-control competency-search-input" placeholder="{{ $_('search') }}...">
      <i class="fa fa-ban competency-clear-search" data-tippy-content="{{ $_('search-clear') }}"></i>
    </div>
  </div>
  <div class="tile tile-button create-competency">
    <span><i class="fa fa-circle-plus"></i>{{ $_('create-competency') }}</span>
  </div>
</div>

<div class="competency-list competency-list--global-crud">
  @forelse ($globals as $comp)
    <div class="tile competency-item" data-id="{{ $comp->id }}" data-name="{{ $comp->name }}">
      <div class="bar">
        <span><i class="fa fa-caret-down"></i>{{ $comp->name }} </span>
        <button class="btn btn-outline-danger remove-competency" data-tippy-content="{{ $_('remove-competency') }}"><i class="fa fa-trash-alt"></i></button>
        <button class="btn btn-outline-warning modify-competency" data-tippy-content="{{ $_('modify-competency') }}"><i class="fa fa-file-pen"></i></button>
      </div>

      <div class="questions hidden">
        <div class="tile tile-button create-question">{{ $_('create-question') }}</div>

        @foreach ($comp->questions as $q)
          <div class="question-item" data-id="{{ $q->id }}">
            <div>
              <span>{{ $_('question') }} #{{ $loop->index+1 }}</span>
              <div>
                <button class="btn btn-outline-danger remove-question" data-tippy-content="{{ $_('question-remove') }}"><i class="fa fa-trash-alt"></i></button>
                <button class="btn btn-outline-warning modify-question" data-tippy-content="{{ $_('question-modify') }}"><i class="fa fa-file-pen"></i></button>
              </div>
            </div>
            <div>
              <p>{{ $q->question }}</p>
              <p>{{ $q->question_self }}</p>
            </div>
            <div>
              <p>{{ $_('min-label') }}<span>{{ $q->min_label }}</span></p>
              <p>{{ $_('max-label') }}<span>{{ $q->max_label }}</span></p>
              <p>{{ $_('scale') }}<span>{{ $q->max_value }}</span></p>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @empty
    <div class="no-competency"><p>{{ $_('no-competency') }}</p></div>
  @endforelse
</div>

@endsection

@section('modals')
  @include('admin.modals.competency')
  @include('admin.modals.competencyq')
@endsection

@section('scripts')
<script>
(function () {
  // --- Alapok ---
  window.CSRF = window.CSRF || (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

window.SuperadminCompetencyRoutes = {
  index:  "{{ route('superadmin.competency.index') }}",
  save:   "{{ route('superadmin.competency.save') }}",
  remove: "{{ route('superadmin.competency.remove') }}",
  qGet:   "{{ route('superadmin.competency.q.get') }}",
  qSave:  "{{ route('superadmin.competency.q.save') }}",
  qRemove:"{{ route('superadmin.competency.q.remove') }}"
};



  function ajax(method, url, data) {
    return fetch(url, {
      method: method || 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN': window.CSRF},
      body: method === 'GET' ? undefined : JSON.stringify(data || {})
    }).then(async (r) => {
      if (!r.ok) throw new Error((await r.text().catch(()=>'')) || ('HTTP '+r.status));
      try { return await r.json(); } catch { return {}; }
    });
  }

  // --- Admin modal markuphoz igazodó, ROBUSZTUS mező-olvasók ---
  function val(el, selList) {
    for (const sel of selList) {
      const n = el.querySelector(sel);
      if (n && typeof n.value !== 'undefined') return n.value;
    }
    return '';
  }

  // === KOMPETENCIA: mentés (create/update) ===
  async function doCompetencySave(e) {
    e?.preventDefault?.();
    const modal = document.getElementById('competency-modal')
                 || document.getElementById('competencyModal')
                 || document.querySelector('.modal[id*="competency"]');
    if (!modal) return alert('Hiányzik a competency modal.');

    const id   = (val(modal, ['input[name="id"]','input[name="competency_id"]'])||'').trim();
    const name = (val(modal, ['input[name="name"]','.competency-name','input[name="competency_name"]'])||'').trim();
    if (!name) return alert('Név kötelező');

    const payload = { name: name, organization_id: null }; // <<< GLOBÁLIS
    if (id) payload.id = id;

    try {
      await ajax('POST', Routes.save, payload);
      location.href = Routes.index;
    } catch (err) {
      alert('Mentési hiba: ' + err.message);
    }
    return false;
  }

  // === KOMPETENCIA: törlés ===
  async function doCompetencyRemove(id) {
    if (!id) return;
    if (!confirm('Biztosan törlöd a kompetenciát?')) return;
    try {
      await ajax('POST', Routes.remove, { id: String(id) });
      location.href = Routes.index;
    } catch (err) {
      alert('Törlési hiba: ' + err.message);
    }
  }

  // === KÉRDÉS: mentés (create/update) ===
  async function doQuestionSave(e) {
    e?.preventDefault?.();
    const modal = document.getElementById('competencyq-modal')
                 || document.getElementById('competencyQModal')
                 || document.querySelector('.modal[id*="competencyq"]');
    if (!modal) return alert('Hiányzik a question modal.');

    const id     = (val(modal, ['input[name="id"]','input[name="question_id"]'])||'').trim();
    const compId = (val(modal, ['input[name="compId"]','input[name="competency_id"]'])||'').trim();

    // Admin partialok gyakran a DB mezőneveket használják:
    const question     = (val(modal, ['textarea[name="question"]','textarea[name="question_text"]'])||'').trim();
    const questionSelf = (val(modal, ['textarea[name="question_self"]','textarea[name="questionSelf"]'])||'').trim();
    const minLabel     = (val(modal, ['input[name="min_label"]','input[name="minLabel"]'])||'').trim();
    const maxLabel     = (val(modal, ['input[name="max_label"]','input[name="maxLabel"]'])||'').trim();
    const scaleRaw     = (val(modal, ['input[name="max_value"]','input[name="scale"]'])||'').trim();
    const scale        = Number(scaleRaw || 5);

    if (!compId || !question || !questionSelf || !minLabel || !maxLabel) {
      return alert('Minden mező kötelező.');
    }

    const payload = {
      organization_id: null,          // <<< GLOBÁLIS kérdés
      id: id || undefined,
      compId: compId,
      question, questionSelf, minLabel, maxLabel, scale
    };

    try {
      await ajax('POST', Routes.qSave, payload);
      location.href = Routes.index;
    } catch (err) {
      alert('Kérdés mentési hiba: ' + err.message);
    }
    return false;
  }

  // === KÉRDÉS: törlés ===
  async function doQuestionRemove(id) {
    if (!id) return;
    if (!confirm('Biztosan törlöd a kérdést?')) return;
    try {
      await ajax('POST', Routes.qRemove, { id: String(id) });
      location.href = Routes.index;
    } catch (err) {
      alert('Kérdés törlési hiba: ' + err.message);
    }
  }

  // --- Adapter a GOMBOKRA / admin markuphoz ---
  // Kompetencia mentés gombok (különböző elnevezések támogatása)
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.save-competency, .btn-save, .btn-save-competency, [data-action="save-competency"]');
    if (!btn) return;
    doCompetencySave(e);
  });

  // Kérdés mentés gombok
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.save-competencyq, .btn-save, .btn-save-question, [data-action="save-question"]');
    if (!btn) return;
    doQuestionSave(e);
  });

  // Kompetencia törlés (admin markupban tipikusan data-id vagy onclick hívás van)
  document.addEventListener('click', function (e) {
    const del = e.target.closest('.remove-competency, .btn-delete-competency, [data-action="remove-competency"]');
    if (!del) return;
    e.preventDefault();
    const id = del.getAttribute('data-id')
           || del.dataset?.id
           || del.closest('[data-id]')?.getAttribute('data-id');
    doCompetencyRemove(id);
  });

  // Kérdés törlés
  document.addEventListener('click', function (e) {
    const del = e.target.closest('.remove-question, .btn-delete-question, [data-action="remove-question"]');
    if (!del) return;
    e.preventDefault();
    const id = del.getAttribute('data-id')
           || del.dataset?.id
           || del.closest('[data-id]')?.getAttribute('data-id');
    doQuestionRemove(id);
  });

  // --- Inline onclick védőháló (ha az admin partialban volt pl. competency.save()) ---
  window.competency  = window.competency  || {};
  window.competencyq = window.competencyq || {};
  if (typeof window.competency.save !== 'function')  window.competency.save  = doCompetencySave;
  if (typeof window.competencyq.save !== 'function') window.competencyq.save = doQuestionSave;

})();
</script>
@endsection