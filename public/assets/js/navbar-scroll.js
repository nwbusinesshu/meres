// Navbar scroll effect
(function() {
  let ticking = false;
  
  function updateNavbarState() {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    if (scrollTop > 20) {
      document.body.classList.add('navbar-scrolled');
    } else {
      document.body.classList.remove('navbar-scrolled');
    }
    
    ticking = false;
  }
  
  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  
  function init() {
    // Run once on load
    updateNavbarState();
    
    // Listen to scroll events
    window.addEventListener('scroll', function() {
      if (!ticking) {
        window.requestAnimationFrame(updateNavbarState);
        ticking = true;
      }
    });
  }
})();