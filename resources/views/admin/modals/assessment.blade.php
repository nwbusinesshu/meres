<div class="modal fade modal-drawer" tabindex="-1" role="dialog" id="assessment-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('admin/home.assessment') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-row">
          <div class="form-group w-100">
            <label>{{ __('admin/home.due') }}</label>
            <input type="date"
                   class="form-control assessment-due"
                   min="{{ date('Y-m-d',strtotime('+1 Day')) }}">
          </div>
        </div>

        {{-- VÁLASZTÓ (gomb formájú, egymás alatt) --}}
        <div class="form-row">
          <div class="form-group w-100">
            <label class="d-block mb-2">{{ __('admin/home.assessment') }} – kör</label>

            <div class="assessment-scope">
              {{-- Teljes cégben (aktív, választható) --}}
              <input type="radio" id="scope-org" name="assessment-scope" class="assessment-scope__radio" checked>
              <label for="scope-org" class="assessment-scope__option">
                <div class="assessment-scope__text">
                  <span class="assessment-scope__title">Futtatás teljes cégben</span>
                  <span class="assessment-scope__desc">Minden, a szervezethez tartozó felhasználóra</span>
                </div>
              </label>

              {{-- Kiválasztott részlegekben (tiltott, SOON) --}}
              <input type="radio" id="scope-depts" name="assessment-scope" class="assessment-scope__radio" disabled>
              <label for="scope-depts" class="assessment-scope__option assessment-scope__option--disabled" title="Hamarosan elérhető">
                <div class="assessment-scope__text">
                  <span class="assessment-scope__title">Futtatás kiválasztott részlegekben</span>
                  <span class="assessment-scope__desc">Csak meghatározott részlegekben</span>
                </div>
                <span class="badge badge-warning">SOON</span>
              </label>
            </div>
          </div>
        </div>

        <div class="tile tile-warning">
          <p>{!! __('admin/home.assessment-warning') !!}</p>
        </div>

        <button class="btn btn-primary save-assessment">{{ __('admin/home.save-assessment') }}</button>
      </div>
    </div>
  </div>
</div>

<style> /* Radio elrejtése */
  .assessment-scope__radio {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }

  /* Gomb-szerű opciók */
   .assessment-scope__option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    width: 100%;
    margin-bottom: .6rem;
    padding: .9rem 1rem;
    border: 1px solid #e5e7eb;
    background: #fff;
    cursor: pointer;
    transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    box-shadow: 0 1px 3px rgba(16, 24, 40, .04);
  }
   .assessment-scope__option:hover {
    border-color: #cfd4da;
    box-shadow: 0 2px 12px rgba(16, 24, 40, .06);
  }

  /* Disabled (SOON) opció megjelenése */
   .assessment-scope__option--disabled {
    opacity: .6;
    cursor: not-allowed;
    background: #fafafa;
  }

  /* Kiválasztott állapot – szépen beszínezve (info kékhez igazítva) */
   #scope-org:checked + .assessment-scope__option,
   #scope-depts:checked + .assessment-scope__option {
    border-color: #17a2b8;
    box-shadow: 0 0 0 .2rem rgba(23,162,184,.15);
    background: #e9f7fb;
  }

  /* Szövegek */
   .assessment-scope__text {
    display: flex;
    flex-direction: column;
  }
   .assessment-scope__title {
    font-weight: 600;
    line-height: 1.2;
  }
   .assessment-scope__desc {
    font-size: .875rem;
    color: #6c757d;
  }
</style>

<script>
function openAssessmentModal(id = 0){
  swal_loader.fire();
  $('#assessment-modal').attr('data-id', id ?? 0);
  $('#assessment-modal .assessment-due').val("{{ date('Y-m-d', strtotime('+7 Days')) }}");

  // Radio-k alaphelyzetbe
  $('#scope-org').prop('checked', true);
  $('#scope-depts').prop('checked', false);

  $('#assessment-modal .tile-warning').addClass("hidden");
  if(id==0){
    swal_loader.close();
    $('#assessment-modal .tile-warning').removeClass("hidden");
    $('#assessment-modal').modal();
  }else{
    swal_loader.fire();
    $.ajax({
      url: "{{ route('admin.assessment.get') }}",
      data: { id: id },
    })
    .done(function(response){
      $('#assessment-modal .assessment-due').val((response.due_at || '').split(' ')[0] || "{{ date('Y-m-d', strtotime('+7 Days')) }}");
      swal_loader.close();
      $('#assessment-modal').modal();
    })
    .fail(function(){
      swal_loader.close();
      Swal.fire('Hiba', 'Nem sikerült betölteni az értékelést.', 'error');
    });
  }
}

$(document).ready(function(){
  $('#assessment-modal .save-assessment').click(function(){
    swal_confirm.fire({
      title: '{{ __('admin/home.save-assessment-confirm') }}'
    }).then((result) => {
      if (result.isConfirmed) {
        swal_loader.fire();
        $.ajax({
          url: "{{ route('admin.assessment.save') }}",
          data: {
            id: $('#assessment-modal').attr('data-id'),
            due: $('#assessment-modal .assessment-due').val()
            // A scope most még csak UI – nem küldjük a szervernek.
          },
          successMessage: "{{ __('admin/home.save-assessment-success') }}",
        });
      }
    });
  });
});
</script>
