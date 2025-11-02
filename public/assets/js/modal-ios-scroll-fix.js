/**
 * iOS Modal Scroll Position Fix
 * Handles scroll position preservation when modals use position:fixed on body
 * Add this to your global JS file or include as separate file after jQuery and Bootstrap
 */

(function() {
  'use strict';
  
  let scrollPosition = 0;
  
  /**
   * Save current scroll position and lock body
   */
  function lockBody() {
    // Save current scroll position
    scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    // Apply position fixed with negative top to maintain visual position
    document.body.style.top = `-${scrollPosition}px`;
    document.body.classList.add('modal-open');
    
    // Prevent scroll on HTML element too (iOS fix)
    document.documentElement.style.overflow = 'hidden';
  }
  
  /**
   * Restore scroll position and unlock body
   */
  function unlockBody() {
    // Remove fixed positioning
    document.body.classList.remove('modal-open');
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    
    // Restore HTML overflow
    document.documentElement.style.overflow = '';
    
    // Restore scroll position
    window.scrollTo(0, scrollPosition);
    scrollPosition = 0;
  }
  
  /**
   * Check if any modals are currently open
   */
  function hasOpenModals() {
    return document.querySelectorAll('.modal.show').length > 0;
  }
  
  // Listen for Bootstrap modal events
  $(document).on('show.bs.modal', '.modal', function(e) {
    // Only lock if no other modals are open (prevent double-locking)
    if (!hasOpenModals()) {
      lockBody();
    }
  });
  
  $(document).on('hidden.bs.modal', '.modal', function(e) {
    // Small delay to ensure Bootstrap cleanup is complete
    setTimeout(function() {
      // Only unlock if no other modals are open
      if (!hasOpenModals()) {
        unlockBody();
      }
    }, 50);
  });
  
  // Handle page visibility change (iOS can be quirky with app switching)
  document.addEventListener('visibilitychange', function() {
    if (document.hidden && hasOpenModals()) {
      // Page is hidden with modal open, ensure body stays locked
      lockBody();
    }
  });
  
  // Cleanup on page unload
  window.addEventListener('beforeunload', function() {
    if (hasOpenModals()) {
      unlockBody();
    }
  });
  
})();