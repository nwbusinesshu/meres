<script>
$(function(){

  // ---------- HELPERS ----------
  function getUserIdFromAny(el){
    // legközelebbi bármely [data-id] -> user id (működik <tr> és .user-row esetén is)
    const $root = $(el).closest('[data-id]');
    return $root.length ? ($root.data('id') || null) : null;
  }
  function getDeptIdFromAny(el){
    // kártyás: .dept-block[data-dept-id], táblás: <tr data-id>
    return $(el).closest('.dept-block').data('dept-id')
        || $(el).closest('tr').data('id')
        || null;
  }
  function hasLegacyTable(){
    return !!document.querySelector('.tile.userlist table tbody');
  }

  // ---------- ÚJ DOLGOZÓ ----------
  $(document).on('click', '.trigger-new', function(){
    if (typeof openEmployeeModal === 'function') openEmployeeModal();
  });

  // ---------- FELHASZNÁLÓ GOMBOK (datas / relations / competencies / bonusmalus / remove) ----------
  $(document).on('click', '.datas, .relations, .competencies, .bonusmalus, .remove', function(){
    const id = getUserIdFromAny(this);
    if (!id) return console.warn('Nincs user data-id a soron.');

    if (this.classList.contains('datas')) {
      if (typeof openEmployeeModal === 'function') openEmployeeModal(id);
      return;
    }
    if (this.classList.contains('relations')) {
      if (typeof initRelationsModal === 'function') initRelationsModal(id);
      return;
    }
    if (this.classList.contains('competencies')) {
      if (typeof initCompetenciesModal === 'function') initCompetenciesModal(id);
      return;
    }
    if (this.classList.contains('bonusmalus')) {
      if (typeof openBonusMalusModal === 'function') openBonusMalusModal(id);
      return;
    }
    if (this.classList.contains('remove')) {
      if (typeof swal_confirm !== 'undefined' && swal_confirm.fire){
        swal_confirm.fire({
          title: '{{ __('admin/employees.remove-confirm') }}',
          text:  '{{ __('admin/employees.remove-confirm-text') }}'
        }).then((r) => {
          if (!r.isConfirmed) return;
          if (typeof swal_loader !== 'undefined' && swal_loader.fire) swal_loader.fire();
          $.ajax({
            url: "{{ route('admin.employee.remove') }}",
            data: { id },
            successMessage: "{{ __('admin/employees.remove-success') }}",
          });
        });
      }
      return;
    }
  });

  // ---------- JELSZÓ VISSZAÁLLÍTÁS ----------
  $(document).on('click', '.password-reset', async function(){
    const container = this.closest('[data-id]');
    const userId = container?.dataset?.id;
    if (!userId) return;

    const hasSwal = !!(window.Swal && typeof Swal.fire === 'function');
    let proceed = false;
    if (hasSwal) {
      const r = await Swal.fire({
        title: 'Jelszó visszaállítás?',
        html: 'Ez <strong>törli</strong> a jelenlegi jelszót (ha volt), és új jelszó-beállító levelet küld.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'OK',
        cancelButtonText: 'Mégse'
      });
      proceed = r.isConfirmed;
    } else {
      proceed = window.confirm('Ez törli a jelszót és új beállító levelet küld. Folytatod?');
    }
    if (!proceed) return;

    try {
      const resp = await fetch("{{ route('admin.employee.password-reset') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ user_id: userId })
      });
      if (!resp.ok) {
        const err = await resp.json().catch(() => ({}));
        throw new Error(err?.message || ('HTTP ' + resp.status));
      }
      // UI: belépési mód -> OAuth
      const modeEl = container.querySelector('.login-mode');
      if (modeEl) modeEl.textContent = 'OAuth';
      if (hasSwal) await Swal.fire({ icon:'success', title:'Elküldve', text:'A jelszó-visszaállító e-mailt kiküldtük.' });
    } catch (err) {
      console.error(err);
      if (hasSwal) await Swal.fire({ icon:'error', title:'Hiba', text:'Nem sikerült elküldeni a visszaállító levelet.' });
      else alert('Nem sikerült elküldeni a visszaállító levelet.');
    }
  });

  // ---------- KERESŐ (csak legacy táblán) ----------
  $(document).on('keyup', '.search-input', function(e){
    if (e.key !== 'Enter') return;
    if (!hasLegacyTable()) return;

    if (typeof swal_loader !== 'undefined' && swal_loader.fire) swal_loader.fire();

    const url = new URL(window.location.href);
    const search = (this.value || '').toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(tr => tr.classList.add('hidden'));
    document.querySelectorAll('tbody tr:not(.no-employee)').forEach(tr => {
      const firstTd = tr.querySelector('td');
      const text = (firstTd?.innerHTML || '').toLowerCase();
      if (text.includes(search)) tr.classList.remove('hidden');
    });

    url.searchParams.delete('search');
    if (search.length) url.searchParams.set('search', search);
    window.history.replaceState(null, null, url);

    const anyVisible = document.querySelectorAll('tbody tr:not(.no-employee):not(.hidden)').length > 0;
    const noEmp = document.querySelector('tr.no-employee');
    if (noEmp) noEmp.classList.toggle('hidden', anyVisible);

    if (typeof swal_loader !== 'undefined' && swal_loader.close) swal_loader.close();
  });

  // clear search
  $(document).on('click', '.clear-search', function(){
    const input = document.querySelector('.search-input');
    if (!input) return;
    input.value = '';
    input.dispatchEvent(new KeyboardEvent('keyup', { key:'Enter' }));
  });

  // visszatöltés URL-ből
  (function(){
    const url = new URL(window.location.href);
    if (url.searchParams.has('search')) {
      const input = document.querySelector('.search-input');
      if (input) {
        input.value = url.searchParams.get('search');
        input.dispatchEvent(new KeyboardEvent('keyup', { key:'Enter' }));
      }
    }
  })();

  // ---------- RÉSZLEG: ÚJ LÉTREHOZÁSA (CREATE MODAL) ----------
  $(document).on('click', '.trigger-new-dept', function(){
    $('#dept-error').addClass('d-none').text('');
    $('#department-modal').attr('data-id', ''); // üres = CREATE
    $('#department-modal .modal-title').text('Új részleg létrehozása');
    $('#department-modal .trigger-submit-dept').text('Létrehozás');
    $('#department-modal .dept-name').val('');
    $('#department-modal .dept-manager').val('');

    var hasOption = $('#department-modal .dept-manager option[value!=""]').length > 0;
    if (!hasOption) {
      Swal.fire({ icon:'info', title:'Nincs választható vezető', text:'Előbb hozz létre legalább egy manager felhasználót.' });
      return;
    }
    $('#department-modal').modal();
  });

  // ---------- RÉSZLEG: SZERKESZTÉS (PREFILL) ----------
  $(document).on('click', '.dept-edit', function(){
    const id = getDeptIdFromAny(this);
    if (!id) return;

    swal_loader.fire();
    fetch("{{ route('admin.employee.department.get') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({ id })
    })
    .then(async r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(data => {
      $('#department-modal .modal-title').text('Részleg szerkesztése');
      $('#department-modal .trigger-submit-dept').text('Mentés');
      $('#department-modal').attr('data-id', String(data.department.id));
      $('#department-modal .dept-name').val(data.department.department_name);

      const sel = $('#department-modal .dept-manager');
      sel.empty();
      sel.append($('<option>', { value:'', text:'— válassz —' }));
      (data.eligibleManagers || []).forEach(function(m){
        const txt = m.email ? (m.name + ' (' + m.email + ')') : m.name;
        sel.append($('<option>', { value:m.id, text:txt }));
      });
      sel.val(String(data.department.manager_id));

      $('#dept-error').addClass('d-none').text('');
      swal_loader.close();
      $('#department-modal').modal();
    })
    .catch(err => {
      swal_loader.close();
      Swal.fire({ icon:'error', title:'Hiba', text:'Nem sikerült betölteni a részleg adatait.' });
      console.error(err);
    });
  });

  // ---------- RÉSZLEG: CREATE/UPDATE SUBMIT ----------
  $(document).on('click', '.trigger-submit-dept', function(){
    const id   = $('#department-modal').attr('data-id');
    const name = $('#department-modal .dept-name').val().trim();
    const mid  = $('#department-modal .dept-manager').val();

    if (!name || !mid) {
      $('#dept-error').removeClass('d-none').text('Add meg a részleg nevét és válassz vezetőt.');
      return;
    }

    const isEdit = !!id;
    const url    = isEdit ? "{{ route('admin.employee.department.update') }}" : "{{ route('admin.employee.department.store') }}";
    const title  = isEdit ? 'Változtatások mentése?' : 'Részleg létrehozása?';
    const okText = isEdit ? 'Részleg frissítve.' : 'Részleg létrehozva.';

    swal_confirm.fire({
      title: title,
      text: isEdit ? 'A kiválasztott vezetőre is ellenőrzünk (nem vezethet másik aktív részleget).' : 'A manager egy időben csak egy részleget vezethet.'
    }).then((res) => {
      if(!res.isConfirmed) return;

      swal_loader.fire();
      const payload = isEdit
        ? { id: Number(id), department_name: name, manager_id: Number(mid) }
        : { department_name: name, manager_id: Number(mid) };

      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(payload)
      })
      .then(async r => {
        if (!r.ok) {
          const j = await r.json().catch(()=>({}));
          throw new Error(j?.message || ('HTTP ' + r.status));
        }
        return r.json();
      })
      .then(() => {
        Swal.fire({ icon:'success', title:'OK', text: okText }).then(() => window.location.reload());
      })
      .catch(err => {
        swal_loader.close();
        $('#dept-error').removeClass('d-none').text(String(err.message || err));
      });
    });
  });

  // ---------- RÉSZLEG: TAGOK KEZELÉSE (MODAL) ----------
  function addMemberItem(uid, name, email){
    const mail = email ? ' <span class="text-muted small">(' + email + ')</span>' : '';
    $('.dept-members-list').append(
      '<div class="dept-member-item" data-id="'+uid+'">' +
        '<i class="fa fa-trash-alt" data-tippy-content="Eltávolítás"></i>' +
        '<div><p>'+name+mail+'</p></div>' +
      '</div>'
    );
  }
  function initDeptMembersModal(deptId){
    $('#dept-members-modal').attr('data-id', deptId);
    swal_loader.fire();
    $.ajax({
      url: "{{ route('admin.employee.department.members') }}",
      method: 'POST',
      data: { department_id: deptId },
    })
    .done(function(resp){
      $('.dept-members-list').html('');
      (resp.members || []).forEach(function(m){
        addMemberItem(m.id, m.name, m.email);
      });
      if (window.tippy) tippy('.dept-members-list [data-tippy-content]');
      swal_loader.close();
      $('#dept-members-modal').modal();
    })
    .fail(function(){
      swal_loader.close();
      Swal.fire({ icon:'error', title:'Hiba', text:'Nem sikerült betölteni a tagokat.' });
    });
  }
  $(document).on('click', '.dept-members', function(){
    const deptId = getDeptIdFromAny(this);
    if (!deptId) return console.warn('Nincs department ID.');
    initDeptMembersModal(deptId);
  });
  $(document).on('click', '.trigger-add-member', function(){
    const deptId = $('#dept-members-modal').attr('data-id');
    var except = [];
    $('#dept-members-modal .dept-member-item').each(function(){
      except.push($(this).data('id')*1);
    });
    openSelectModal({
      title: "Dolgozó kiválasztása",
      parentSelector: '#dept-members-modal',
      ajaxRoute: "{{ route('admin.employee.department.eligible') }}?department_id="+deptId,
      itemData: function(item){ return { id:item.id, name:item.name, top:null, bottom:item.email || null }; },
      selectFunction: function(){
        const uid = $(this).attr('data-id');
        const name = $(this).attr('data-name');
        const email = $(this).find('span').last().text().replace(/[()]/g,'') || '';
        if ($('#dept-members-modal .dept-member-item[data-id="'+uid+'"]').length === 0) {
          addMemberItem(uid, name, email);
          if (window.tippy) tippy('.dept-members-list [data-tippy-content]');
        }
        $('#select-modal').modal('hide');
      },
      exceptArray: except,
      emptyMessage: 'Nincs választható dolgozó'
    });
  });
  $(document).on('click', '#dept-members-modal .dept-member-item i', function(){
    $(this).closest('.dept-member-item').remove();
  });
  $(document).on('click', '.save-dept-members', function(){
    const deptId = $('#dept-members-modal').attr('data-id');
    var ids = [];
    $('#dept-members-modal .dept-member-item').each(function(){
      ids.push($(this).data('id')*1);
    });
    swal_confirm.fire({ title: 'Részleg tagjainak mentése?' })
      .then(function(r){
        if (!r.isConfirmed) return;
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.employee.department.members.save') }}",
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify({ department_id: deptId, user_ids: ids }),
          successMessage: 'Mentve',
        });
      });
  });

  // ---------- RÉSZLEGKÁRTYÁK ÖSSZEHAJTHATÓ ----------
  (function(){
    const orgId = "{{ (int)session('org_id') }}";
    const KEY = 'dept_collapse_state_org_' + orgId;

    function loadState(){ try { return JSON.parse(sessionStorage.getItem(KEY) || '{}'); } catch(e){ return {}; } }
    function saveState(state){ sessionStorage.setItem(KEY, JSON.stringify(state)); }
    function applyState(){
      const state = loadState();
      document.querySelectorAll('.dept-block[data-dept-id]').forEach(block => {
        const id = block.getAttribute('data-dept-id');
        const body = block.querySelector('.dept-body');
        const header = block.querySelector('.dept-header');
        const caret = header?.querySelector('.caret');
        const collapsed = state[id] === true;
        if (!body) return;
        if (collapsed) { body.style.display = 'none'; caret && caret.classList.add('rot'); }
        else { body.style.display = ''; caret && caret.classList.remove('rot'); }
      });
    }
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.js-dept-toggle');
      if (!btn) return;
      const block = btn.closest('.dept-block');
      const id = block.getAttribute('data-dept-id');
      const body = block.querySelector('.dept-body');
      const caret = btn.querySelector('.caret');
      const state = loadState();
      const willCollapse = body && body.style.display !== 'none';
      if (body) body.style.display = willCollapse ? 'none' : '';
      if (caret) caret.classList.toggle('rot', willCollapse);
      state[id] = !!willCollapse;
      saveState(state);
    });
    applyState();
  })();

});
</script>
