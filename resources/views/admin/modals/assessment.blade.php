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
                  <span class="assessment-scope__title">{{ __('admin/home.scope-company-title') }}</span>
                  <span class="assessment-scope__desc">{{ __('admin/home.scope-company-desc') }}</span>
                </div>
              </label>

              {{-- Kiválasztott részlegekben (tiltott, SOON) --}}
              <input type="radio" id="scope-depts" name="assessment-scope" class="assessment-scope__radio" disabled>
                <label for="scope-depts" class="assessment-scope__option assessment-scope__option--disabled" title="{{ __('admin/home.scope-coming-soon') }}">
                  <div class="assessment-scope__text">
                    <span class="assessment-scope__title">{{ __('admin/home.scope-departments-title') }}</span>
                    <span class="assessment-scope__desc">{{ __('admin/home.scope-departments-desc') }}</span>
                  </div>
                  <span class="badge badge-warning">{{ __('admin/home.scope-soon-badge') }}</span>
                </label>
            </div>
          </div>
        </div>

        {{-- Assessment Info Block (only visible when modifying) --}}
        <div class="assessment-info-block" style="display: none;">
          <div class="form-group">
            <label class="d-block mb-2">{{ __('admin/home.assessment-info-title') }}</label>
            <div class="assessment-info-content">
              <div class="info-row">
                <span class="info-label">{{ __('admin/home.assessment-info-method') }}:</span>
                <span class="info-value" id="assessment-method">—</span>
              </div>
              <div class="info-row">
                <span class="info-label">{{ __('admin/home.assessment-info-upper-threshold') }}:</span>
                <span class="info-value" id="assessment-upper">—</span>
              </div>
              <div class="info-row">
                <span class="info-label">{{ __('admin/home.assessment-info-lower-threshold') }}:</span>
                <span class="info-value" id="assessment-lower">—</span>
              </div>
            </div>
          </div>
        </div>

        <div class="tile tile-warning">
          <p>{!! __('admin/home.assessment-warning') !!}</p>
        </div>    
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary save-assessment">{{ __('admin/home.save-assessment') }}</button>
      </div>
    </div>
  </div>
</div>

<style> 
/* Radio elrejtése */
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
  
  /* Hover letiltása disabled opcióra */
   .assessment-scope__option--disabled:hover {
    border-color: #e5e7eb;
    box-shadow: 0 1px 3px rgba(16, 24, 40, .04);
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

  /* Assessment Info Block Styling */
  .assessment-info-block {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 0.25rem;
  }

  .assessment-info-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .assessment-info-content .info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
  }

  .assessment-info-content .info-row:last-child {
    border-bottom: none;
  }

  .assessment-info-content .info-label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
  }

  .assessment-info-content .info-value {
    color: #6c757d;
    font-weight: 500;
  }
</style>

<script>
function openAssessmentModal(id = 0){
  swal_loader.fire();
  $('#assessment-modal').attr('data-id', id ?? 0);
  $('#assessment-modal .assessment-due').val("{{ date('Y-m-d', strtotime('+7 Days')) }}");

  // Radio-k alaphelyzetbe (enable for creating new assessment)
  $('#scope-org').prop('checked', true).prop('disabled', false);
  $('#scope-depts').prop('checked', false);
  $('label[for="scope-org"]').removeClass('assessment-scope__option--disabled');

  // Hide assessment info block by default
  $('.assessment-info-block').hide();

  $('#assessment-modal .tile-warning').addClass("hidden");
  
  if(id == 0){
    // Creating new assessment
    swal_loader.close();
    $('#assessment-modal .tile-warning').removeClass("hidden");
    $('#assessment-modal').modal();
  } else {
    // Modifying existing assessment - disable mode switcher and show info
    $('#scope-org').prop('disabled', true);
    $('label[for="scope-org"]').addClass('assessment-scope__option--disabled');
    
    swal_loader.fire();
    $.ajax({
      url: "{{ route('admin.assessment.get') }}",
      data: { id: id },
    })
    .done(function(response){
      // Set due date - handle both date formats
      let dueDate = response.due_at || '';
      if (dueDate) {
        // Extract just the date part (YYYY-MM-DD)
        dueDate = dueDate.split('T')[0].split(' ')[0];
      }
      $('#assessment-modal .assessment-due').val(dueDate || "{{ date('Y-m-d', strtotime('+7 Days')) }}");
      
      // Show and populate assessment info block
      if (response.threshold_method) {
        $('.assessment-info-block').show();
        
        // Method name translation mapping
        const methodNames = {
          'fixed': '{{ __("admin/settings.settings.mode.options.fixed") }}',
          'hybrid': '{{ __("admin/settings.settings.mode.options.hybrid") }}',
          'dynamic': '{{ __("admin/settings.settings.mode.options.dynamic") }}',
          'suggested': '{{ __("admin/settings.settings.mode.options.suggested") }}'
        };
        
        $('#assessment-method').text(methodNames[response.threshold_method] || response.threshold_method);
        
        // Upper threshold
        if (response.normal_level_up !== null && response.normal_level_up !== undefined) {
          $('#assessment-upper').text(response.normal_level_up + ' {{ __("admin/home.assessment-info-points") }}');
        } else {
          $('#assessment-upper').text('{{ __("admin/home.assessment-info-will-be-calculated") }}');
        }
        
        // Lower threshold
        if (response.normal_level_down !== null && response.normal_level_down !== undefined) {
          $('#assessment-lower').text(response.normal_level_down + ' {{ __("admin/home.assessment-info-points") }}');
        } else {
          $('#assessment-lower').text('{{ __("admin/home.assessment-info-will-be-calculated") }}');
        }
      }
      
      swal_loader.close();
      $('#assessment-modal').modal();
    })
    .fail(function(){
      swal_loader.close();
      Swal.fire('{{ __("admin/home.assessment-load-error-title") }}', '{{ __("admin/home.assessment-load-error-message") }}', 'error');
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