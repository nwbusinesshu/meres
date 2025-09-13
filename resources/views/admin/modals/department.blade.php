@if(!empty($enableMultiLevel) && $enableMultiLevel)
<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="department-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Új részleg létrehozása</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">

        {{-- HIBAHELY: ide írjuk a szerver oldali hibát, ha jön --}}
        <div class="alert alert-danger d-none" id="dept-error"></div>

        <div class="form-row">
          <div class="form-group" style="width:100%;">
            <label>Részleg neve</label>
            <input type="text" class="form-control dept-name" maxlength="255" placeholder="Pl. Értékesítés">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group" style="width:100%;">
            <label>Vezető</label>
            <select class="form-control dept-manager">
              <option value="">— válassz —</option>
              @forelse($eligibleManagers as $m)
                <option value="{{ $m->id }}">{{ $m->name }} @if($m->email) ({{ $m->email }}) @endif</option>
              @empty
                <option value="" disabled>Nincs választható manager</option>
              @endforelse
            </select>
            <small class="form-text text-muted">
              Csak olyan manager választható, aki még nem vezet részleget.
            </small>
          </div>
        </div>

        <div class="d-flex justify-content-end">
          <button type="button" class="btn btn-light mr-2" data-dismiss="modal">Mégse</button>
          <button type="button" class="btn btn-primary trigger-submit-dept">Létrehozás</button>
        </div>
      </div>

    </div>
  </div>
</div>
@endif
@if(!empty($enableMultiLevel) && $enableMultiLevel)
<script>
  $(document).ready(function(){

    // --- CREATE modal megnyitása marad, csak biztos ami biztos: töröljük az id-t és beállítjuk a feliratokat ---
    $('.trigger-new-dept').on('click', function(){
      $('#dept-error').addClass('d-none').text('');
      $('#department-modal').attr('data-id', ''); // üres = CREATE mód

      $('#department-modal .modal-title').text('Új részleg létrehozása');
      $('#department-modal .trigger-submit-dept').text('Létrehozás');

      $('#department-modal .dept-name').val('');
      // manager lista a szerveroldalról jön renderkor; ha dinamikusan akarod frissíteni, lehet külön endpointra is húzni
      $('#department-modal .dept-manager').val('');

      var hasOption = $('#department-modal .dept-manager option[value!=""]').length > 0;
      if (!hasOption) {
        Swal.fire({ icon:'info', title:'Nincs választható vezető', text:'Előbb hozz létre legalább egy manager felhasználót.' });
        return;
      }
      $('#department-modal').modal();
    });

    // --- EDIT: prefill ---
    $(document).on('click', '.dept-edit', function(){
      const id = $(this).closest('tr').data('id');
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
        // Cím + gomb
        $('#department-modal .modal-title').text('Részleg szerkesztése');
        $('#department-modal .trigger-submit-dept').text('Mentés');

        // ID beállítás: EDIT mód
        $('#department-modal').attr('data-id', String(data.department.id));

        // Név
        $('#department-modal .dept-name').val(data.department.department_name);

        // Manager lista FRISSÍTÉSE: (jelenlegi + többi elérhető)
        const sel = $('#department-modal .dept-manager');
        sel.empty();
        sel.append($('<option>', { value: '', text: '— válassz —' }));
        (data.eligibleManagers || []).forEach(function(m){
          const txt = m.email ? (m.name + ' (' + m.email + ')') : m.name;
          sel.append($('<option>', { value: m.id, text: txt }));
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

    // --- SUBMIT: create vagy update ---
    $('.trigger-submit-dept').on('click', function(){
      const id   = $('#department-modal').attr('data-id'); // ha van => update
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
          // a ti $.ajax helperetek helyett itt sima fetch van — adjunk visszajelzést és frissítsünk
          Swal.fire({ icon:'success', title:'OK', text: okText }).then(() => {
            window.location.reload();
          });
        })
        .catch(err => {
          swal_loader.close();
          $('#dept-error').removeClass('d-none').text(String(err.message || err));
        });
      });
    });

  });
</script>

@endif

