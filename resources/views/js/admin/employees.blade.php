<script>
  $(document).ready(function(){
    const url = new URL(window.location.href);

    $('.trigger-new').click(function(){
      openEmployeeModal();
    });

    $('.datas').click(function(){
      openEmployeeModal($(this).parents('tr').attr('data-id'));
    });

    $('.remove').click(function(){
      swal_confirm.fire({
        title: '{{ __('admin/employees.remove-confirm') }}',
        text: '{{ __('admin/employees.remove-confirm-text') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          swal_loader.fire();
          $.ajax({
            url: "{{ route('admin.employee.remove') }}",
            data: { id: $(this).parents('tr').attr('data-id') },
            successMessage: "{{ __('admin/employees.remove-success') }}",
          });
        }
      });
    });

    $('.search-input').keyup(function(e){
      if(e.keyCode != 13){ return; }
      swal_loader.fire();
      search = $(this).val().toLowerCase();
      $('tbody tr').addClass('hidden');
      $('tbody tr:not(.no-employee)').each(function(){
        if($(this).find('td').first().html().toLowerCase().includes(search)){
          $(this).removeClass('hidden');
        }
      });

      url.searchParams.delete('search');
      if(search.length != 0){
        url.searchParams.set('search', search);
      }
      window.history.replaceState(null, null, url);

      if($('tbody tr:not(.no-employee):not(.hidden)').length == 0){
        $('.no-employee').removeClass('hidden');
      }
      swal_loader.close();
    });

    if(url.searchParams.has('search')){
      $('.search-input').val(url.searchParams.get('search'))
        .trigger(jQuery.Event('keyup', { keyCode: 13 }));
    }

    $('.clear-search').click(function(){
      $('.search-input').val('').trigger(jQuery.Event('keyup', { keyCode: 13 }));
    });

    $('.relations').click(function(){
      initRelationsModal($(this).parents('tr').attr('data-id'));
    });

    $('.competencies').click(function(){
      initCompetenciesModal($(this).parents('tr').attr('data-id'));
    });

    $('.bonusmalus').click(function(){
      openBonusMalusModal($(this).parents('tr').attr('data-id'));
    });
  });
</script>
 <script>
    (function () {
      // Delegált, vanilla JS eseménykezelő (nem kell jQuery)
      function on(el, event, selector, handler) {
        el.addEventListener(event, function (e) {
          const target = e.target.closest(selector);
          if (target) handler(e, target);
        });
      }

      on(document, 'click', '.password-reset', async function (e, btn) {
        const tr = btn.closest('tr');
        const userId = tr?.dataset?.id;
        if (!userId) return;

        const hasSwal = !!(window.Swal && typeof Swal.fire === 'function');

        // Megerősítés — SweetAlert2, ha van; különben native confirm
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

          // UI frissítés: belépési mód most "OAuth"
          const modeEl = tr.querySelector('.login-mode');
          if (modeEl) modeEl.textContent = 'OAuth';

          if (hasSwal) {
            await Swal.fire({ icon: 'success', title: 'Elküldve', text: 'A jelszó-visszaállító e-mailt kiküldtük.' });
          }
        } catch (err) {
          console.error(err);
          if (hasSwal) {
            await Swal.fire({ icon: 'error', title: 'Hiba', text: 'Nem sikerült elküldeni a visszaállító levelet.' });
          } else {
            alert('Nem sikerült elküldeni a visszaállító levelet.');
          }
        }
      });
    })();
  </script>