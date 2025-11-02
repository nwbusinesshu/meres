{{-- Privacy Policy Acknowledgment Modal (Employee First Login) --}}
<div id="privacy-acknowledgment-modal" 
     class="modal fade modal-drawer" 
     tabindex="-1" 
     role="dialog" 
     data-backdrop="static" 
     data-keyboard="false"
     data-lang-saving="{{ __('privacy.js_saving') }}"
     data-lang-success-title="{{ __('privacy.js_success_title') }}"
     data-lang-success-message="{{ __('privacy.js_success_message') }}"
     data-lang-error-title="{{ __('privacy.js_error_title') }}"
     data-lang-error-message="{{ __('privacy.js_error_message') }}"
     data-lang-error-fallback="{{ __('privacy.js_error_fallback') }}">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fa fa-shield-alt text-primary"></i>
          {{ __('privacy.modal_title') }}
        </h5>
        {{-- No close button - modal is non-dismissible --}}
      </div>
      
      <div class="modal-body">
        <p class="mb-3">{!! __('privacy.modal_intro') !!}</p>
        
        <div class="privacy-points">
          <ul class="fa-ul">
            <li>
              <span class="fa-li"><i class="fa fa-check text-success"></i></span>
              {!! __('privacy.point_1') !!}
            </li>
            <li>
              <span class="fa-li"><i class="fa fa-check text-success"></i></span>
              {!! __('privacy.point_2') !!}
            </li>
            <li>
              <span class="fa-li"><i class="fa fa-check text-success"></i></span>
              {!! __('privacy.point_3') !!}
            </li>
          </ul>
        </div>
        
        <div class="alert alert-info mt-3">
          <i class="fa fa-info-circle"></i>
          {!! __('privacy.modal_note', [
            'privacy_link' => '<a href="' . config('app.privacy_policy_url', '#') . '" target="_blank">' . __('privacy.privacy_policy_link') . '</a>'
          ]) !!}
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-block" id="acknowledge-privacy-btn">
          <i class="fa fa-check-circle"></i>
          {{ __('privacy.acknowledge_button') }}
        </button>
      </div>
      
    </div>
  </div>
</div>

<style>

#privacy-acknowledgment-modal .modal-header {
  background-color: #f8f9fa;
  border-bottom: 2px solid #dee2e6;
}

#privacy-acknowledgment-modal .modal-title {
  font-size: 1.25rem;
  font-weight: 600;
}

#privacy-acknowledgment-modal .privacy-points {
  background-color: #f8f9fa;
  padding: 1rem;
  margin-bottom: 1rem;
}

#privacy-acknowledgment-modal .privacy-points ul {
  margin-bottom: 0;
}

#privacy-acknowledgment-modal .privacy-points li {
  margin-bottom: 0.75rem;
}

#privacy-acknowledgment-modal .privacy-points li:last-child {
  margin-bottom: 0;
}

#privacy-acknowledgment-modal .alert-info {
  border-left: 3px solid #17a2b8;
}

#privacy-acknowledgment-modal #acknowledge-privacy-btn {
  font-weight: 600;
  padding: 0.75rem;
  animation: pulse-glow 2s ease-in-out infinite;
}

#privacy-acknowledgment-modal #acknowledge-privacy-btn:hover {
  animation: none;
  transform: scale(1);
}

/* Pulsing glow animation */
@keyframes pulse-glow {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7);
    transform: scale(1);
  }
  50% {
    box-shadow: 0 0 20px 5px rgba(0, 123, 255, 0.4);
  }
}
</style>

<script>
  /**
 * Privacy Policy Acknowledgment Modal (Employee First Login)
 * 
 * Shows a non-dismissible modal on first login if privacy_policy_accepted_at is NULL
 * Saves acknowledgment timestamp + IP when user clicks "I Acknowledge"
 */
(function() {
  'use strict';

  // Get translations from modal data attributes
  const $modal = $('#privacy-acknowledgment-modal');
  const lang = {
    saving: $modal.data('lang-saving'),
    successTitle: $modal.data('lang-success-title'),
    successMessage: $modal.data('lang-success-message'),
    errorTitle: $modal.data('lang-error-title'),
    errorMessage: $modal.data('lang-error-message'),
    errorFallback: $modal.data('lang-error-fallback')
  };

  // Check if user needs to acknowledge privacy policy
  function checkPrivacyAcknowledgment() {
    const needsAcknowledgment = $('meta[name="needs-privacy-acknowledgment"]').attr('content') === 'true';
    
    if (needsAcknowledgment) {
      console.log('[Privacy Acknowledgment] User needs to acknowledge privacy policy');
      showPrivacyModal();
    }
  }

  // Show privacy acknowledgment modal (non-dismissible)
  function showPrivacyModal() {
    $modal.modal('show');
  }

  // Handle acknowledgment button click
  $('#acknowledge-privacy-btn').on('click', function() {
    const $btn = $(this);
    const originalText = $btn.html();
    
    // Disable button and show loading
    $btn.prop('disabled', true)
        .html('<i class="fa fa-spinner fa-spin"></i> ' + lang.saving);
    
    // Send AJAX request to save acknowledgment
    $.ajax({
      url: '/profile-settings/acknowledge-privacy',
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        if (response.success) {
          // Update meta tag so it won't show again
          $('meta[name="needs-privacy-acknowledgment"]').attr('content', 'false');
          
          // Hide modal
          $modal.modal('hide');
          
          // Show success message
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'success',
              title: lang.successTitle,
              text: response.message || lang.successMessage,
              timer: 2000,
              showConfirmButton: false
            });
          }
          
          console.log('[Privacy Acknowledgment] Successfully saved');
        } else {
          throw new Error(response.error || 'Failed to save acknowledgment');
        }
      },
      error: function(xhr, status, error) {
        console.error('[Privacy Acknowledgment] Error:', error);
        
        // Re-enable button
        $btn.prop('disabled', false).html(originalText);
        
        // Show error message
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'error',
            title: lang.errorTitle,
            text: lang.errorMessage
          });
        } else {
          alert(lang.errorFallback);
        }
      }
    });
  });

  // Initialize on document ready
  $(document).ready(function() {
    checkPrivacyAcknowledgment();
  });

})();
</script>