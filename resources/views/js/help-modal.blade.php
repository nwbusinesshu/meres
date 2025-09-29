<script>
$(document).ready(function() {
  
  /* =======================================================================
     HELP MODAL - GLOBAL VARIABLES
     ======================================================================= */
  
  let helpModalOpen = false;
  let currentViewKey = '';
  let currentUserRole = '';
  let currentLocale = '';
  let currentPageTitle = '';
  let currentActiveTab = 'about-page';
  
  // Session storage keys
  const STORAGE_KEY_MODAL_OPEN = 'help_modal_open';
  const STORAGE_KEY_ACTIVE_TAB = 'help_modal_active_tab';
  const STORAGE_KEY_CHAT_HISTORY = 'help_chat_history';
  const STORAGE_KEY_SESSION_ID = 'help_session_id';

  /* =======================================================================
     READ META TAGS & INITIALIZE
     ======================================================================= */
  
  // Check if DEBUG mode is enabled
  const isDebugMode = typeof DEBUG !== 'undefined' && (DEBUG === true || DEBUG === 'true' || DEBUG === '1');
  
  /* =======================================================================
     SESSION STORAGE HELPERS
     ======================================================================= */
  
  function saveModalState() {
    try {
      sessionStorage.setItem(STORAGE_KEY_MODAL_OPEN, helpModalOpen ? '1' : '0');
      sessionStorage.setItem(STORAGE_KEY_ACTIVE_TAB, currentActiveTab);
    } catch (e) {
      if (isDebugMode) {
        console.warn('[Help System] Could not save to sessionStorage:', e);
      }
    }
  }
  
  function getModalState() {
    try {
      return {
        wasOpen: sessionStorage.getItem(STORAGE_KEY_MODAL_OPEN) === '1',
        activeTab: sessionStorage.getItem(STORAGE_KEY_ACTIVE_TAB) || 'about-page'
      };
    } catch (e) {
      if (isDebugMode) {
        console.warn('[Help System] Could not read from sessionStorage:', e);
      }
      return { wasOpen: false, activeTab: 'about-page' };
    }
  }
  
  function clearModalState() {
    try {
      sessionStorage.removeItem(STORAGE_KEY_MODAL_OPEN);
    } catch (e) {
      // Silently fail
    }
  }
  
  function saveChatHistory(history) {
    try {
      sessionStorage.setItem(STORAGE_KEY_CHAT_HISTORY, JSON.stringify(history));
    } catch (e) {
      if (isDebugMode) {
        console.warn('[Help System] Could not save chat history:', e);
      }
    }
  }
  
  function getChatHistory() {
    try {
      const history = sessionStorage.getItem(STORAGE_KEY_CHAT_HISTORY);
      return history ? JSON.parse(history) : [];
    } catch (e) {
      if (isDebugMode) {
        console.warn('[Help System] Could not load chat history:', e);
      }
      return [];
    }
  }
  
  function saveSessionId(sessionId) {
    try {
      sessionStorage.setItem(STORAGE_KEY_SESSION_ID, sessionId);
    } catch (e) {
      // Silently fail
    }
  }
  
  function getSessionId() {
    try {
      return sessionStorage.getItem(STORAGE_KEY_SESSION_ID) || null;
    } catch (e) {
      return null;
    }
  }
  
  /* =======================================================================
     READ META TAGS & INITIALIZE
     ======================================================================= */
  
  function initializeHelpSystem() {
    // Read meta tags
    currentViewKey = $('meta[name="app-view-key"]').attr('content') || '';
    currentUserRole = $('meta[name="app-user-role"]').attr('content') || 'guest';
    currentLocale = $('meta[name="app-locale"]').attr('content') || 'hu';
    
    // Get page title from document title or h1
    currentPageTitle = document.title.split(' - ')[0] || $('h1').first().text() || '{{ __("help.modal-title") }}';
    
    // Update modal title
    updateModalPageTitle();
    
    // Check if modal was open in previous page
    const state = getModalState();
    if (state.wasOpen && !helpModalOpen) {
      // Restore modal without animation on page load
      currentActiveTab = state.activeTab;
      
      // Add a temporary class to disable transitions
      $('body').addClass('help-no-transition');
      $('.page-container').addClass('help-no-transition');
      $('#help-modal').addClass('help-no-transition');
      
      // Open instantly
      openHelpModal(true); // true = skip animation
      
      // Remove no-transition class after a brief moment
      setTimeout(function() {
        $('body').removeClass('help-no-transition');
        $('.page-container').removeClass('help-no-transition');
        $('#help-modal').removeClass('help-no-transition');
      }, 50);
      
    } else if (helpModalOpen) {
      // Modal is already open, just refresh content
      loadCurrentTabContent();
    }
    
    if (isDebugMode) {
      console.log('[Help System] Initialized:', {
        viewKey: currentViewKey,
        role: currentUserRole,
        locale: currentLocale,
        pageTitle: currentPageTitle,
        restoredState: state
      });
    }
  }

  /* =======================================================================
     MODAL OPEN/CLOSE HANDLERS
     ======================================================================= */
  
  function openHelpModal(skipAnimation = false) {
    if (skipAnimation) {
      // Instant open (no animation)
      $('#help-modal').css('display', 'block').addClass('show');
      $('body').addClass('help-modal-open');
    } else {
      // Animated open - sync both animations
      $('#help-modal').css('display', 'block'); // Make visible first
      
      // Trigger both animations simultaneously after a tiny delay
      // This ensures CSS sees the initial state before transitioning
      setTimeout(function() {
        $('body').addClass('help-modal-open');  // Push page
        $('#help-modal').addClass('show');       // Slide drawer
      }, 10);
    }
    
    helpModalOpen = true;
    
    // Save state to session storage
    saveModalState();
    
    // Switch to the correct tab
    if (currentActiveTab !== 'about-page') {
      switchTab(currentActiveTab, true); // true = skip saving state again
    } else {
      // Load content for the current tab
      loadCurrentTabContent();
    }
    
    if (isDebugMode) {
      console.log('[Help Modal] Opened for view:', currentViewKey);
    }
  }

  function closeHelpModal() {
    // Remove both classes simultaneously for synced animations
    $('body').removeClass('help-modal-open');
    $('#help-modal').removeClass('show');
    
    // Hide after animation completes (300ms transition)
    setTimeout(function() {
      $('#help-modal').css('display', 'none');
    }, 300);
    
    helpModalOpen = false;
    
    // Clear state from session storage
    clearModalState();
    
    if (isDebugMode) {
      console.log('[Help Modal] Closed');
    }
  }

  /* =======================================================================
     TAB SWITCHING
     ======================================================================= */
  
  function switchTab(tabName, skipSave = false) {
    // Update tab buttons
    $('.help-tab').removeClass('active');
    $(`.help-tab[data-tab="${tabName}"]`).addClass('active');
    
    // Update tab content
    $('.help-tab-content').removeClass('active');
    $(`#help-tab-${tabName}`).addClass('active');
    
    // Update current active tab
    currentActiveTab = tabName;
    
    // Save state
    if (!skipSave) {
      saveModalState();
    }
    
    // Load content for the new tab
    loadCurrentTabContent();
    
    if (isDebugMode) {
      console.log('[Help Modal] Switched to tab:', tabName);
    }
  }

  /* =======================================================================
     CONTENT LOADING
     ======================================================================= */
  
  function loadCurrentTabContent() {
    const activeTab = $('.help-tab.active').data('tab');
    
    if (activeTab === 'about-page') {
      loadAboutPageContent();
    } else if (activeTab === 'ai-support') {
      loadAiSupportContent();
    }
  }

  function loadAboutPageContent() {
    const $content = $('.help-static-content');
    
    // Show loading state with custom loader
    $content.html(`
      <div class="help-loading">
        <img src="{{ asset('assets/loader/loader.svg') }}" alt="Loading..." class="help-loader-svg">
        <p>{{ __('help.loading-content') }}</p>
      </div>
    `);
    
    // TODO: Future API call to load help content
    // For now, show "content not found" after a delay
    setTimeout(function() {
      $content.html(`
        <div class="help-content-not-found">
          <i class="fa fa-book-open"></i>
          <p><strong>{{ __('help.content-not-found') }}</strong></p>
          <p>{{ __('help.content-coming-soon') }}</p>
        </div>
      `);
    }, 500);
    
    /* FUTURE API IMPLEMENTATION:
    $.ajax({
      url: '/help/page',
      method: 'GET',
      data: {
        view: currentViewKey,
        locale: currentLocale
      },
      success: function(response) {
        $content.html(response.content_html);
      },
      error: function() {
        $content.html(`
          <div class="help-content-not-found">
            <i class="fa fa-book-open"></i>
            <p><strong>{{ __('help.content-not-found') }}</strong></p>
            <p>{{ __('help.content-coming-soon') }}</p>
          </div>
        `);
      }
    });
    */
  }

  function loadAiSupportContent() {
    // AI Support content is static for now
    // Future: Initialize chat session here
    
    if (isDebugMode) {
      console.log('[Help Modal] AI Support tab loaded (placeholder)');
    }
    
    /* FUTURE API IMPLEMENTATION:
    $.ajax({
      url: '/support/session',
      method: 'POST',
      data: {
        view_key: currentViewKey,
        locale: currentLocale,
        role: currentUserRole
      },
      success: function(response) {
        // Store session_id for future messages
        window.helpChatSessionId = response.session_id;
      }
    });
    */
  }

  /* =======================================================================
     UPDATE PAGE TITLE
     ======================================================================= */
  
  function updateModalPageTitle() {
    let displayTitle = currentPageTitle;
    
    // If view key is available and looks like a route, try to make it readable
    if (currentViewKey && currentViewKey !== 'undefined' && currentViewKey !== '') {
      const parts = currentViewKey.split('.');
      if (parts.length > 1) {
        displayTitle = parts[parts.length - 1].charAt(0).toUpperCase() + parts[parts.length - 1].slice(1);
      }
    }
    
    $('.help-page-title').text(displayTitle);
  }

  /* =======================================================================
     AUTO-RESIZE TEXTAREA
     ======================================================================= */
  
  function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
  }

  /* =======================================================================
     EVENT HANDLERS
     ======================================================================= */
  
  // Floating help button click
  $(document).on('click', '.floating-help-btn', function(e) {
    e.preventDefault();
    if (!helpModalOpen) {
      openHelpModal();
    }
  });

  // Tab switching
  $(document).on('click', '.help-tab', function() {
    const tabName = $(this).data('tab');
    switchTab(tabName);
  });

  // ONLY close button closes the drawer
  $(document).on('click', '.help-drawer-close', function() {
    closeHelpModal();
  });
  
  // Prevent clicks inside drawer from propagating
  $(document).on('click', '#help-modal', function(e) {
    e.stopPropagation();
  });

  // Textarea auto-resize (for future use when enabled)
  $(document).on('input', '.help-chat-input', function() {
    autoResizeTextarea(this);
  });

  // Enter key to send (for future use)
  $(document).on('keydown', '.help-chat-input', function(e) {
    if (e.key === 'Enter' && !e.shiftKey && !$(this).prop('disabled')) {
      e.preventDefault();
      // TODO: Send message
      if (isDebugMode) {
        console.log('[Help Chat] Enter pressed (not yet implemented)');
      }
    }
  });

  // Send button click (for future use)
  $(document).on('click', '.help-chat-send', function() {
    if (!$(this).prop('disabled')) {
      // TODO: Send message
      if (isDebugMode) {
        console.log('[Help Chat] Send clicked (not yet implemented)');
      }
    }
  });

  // Keyboard shortcuts
  $(document).on('keydown', function(e) {
    // ESC to close modal (only if explicitly pressed, not from other interactions)
    if (e.key === 'Escape' && helpModalOpen && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
      closeHelpModal();
    }
    
    // Ctrl/Cmd + H to toggle help
    if ((e.ctrlKey || e.metaKey) && e.key === 'h') {
      e.preventDefault();
      if (helpModalOpen) {
        closeHelpModal();
      } else {
        openHelpModal();
      }
    }
  });

  /* =======================================================================
     INITIALIZE ON PAGE LOAD
     ======================================================================= */
  
  initializeHelpSystem();

  // Re-initialize on page navigation (for SPA-like behavior or full page loads)
  $(document).on('pageChanged', function() {
    if (isDebugMode) {
      console.log('[Help System] Page changed, re-initializing...');
    }
    initializeHelpSystem();
  });
  
  // Also listen for DOMContentLoaded in case of full page navigation
  $(window).on('load', function() {
    // Check if we need to restore modal after full page load
    const state = getModalState();
    if (state.wasOpen && !helpModalOpen) {
      if (isDebugMode) {
        console.log('[Help System] Restoring modal after page navigation');
      }
      currentActiveTab = state.activeTab;
      
      // Add no-transition class
      $('body').addClass('help-no-transition');
      $('.page-container').addClass('help-no-transition');
      $('#help-modal').addClass('help-no-transition');
      
      openHelpModal(true);
      
      // Remove no-transition class after brief moment
      setTimeout(function() {
        $('body').removeClass('help-no-transition');
        $('.page-container').removeClass('help-no-transition');
        $('#help-modal').removeClass('help-no-transition');
      }, 50);
    }
  });

  if (isDebugMode) {
    console.log('[Help System] Event handlers attached');
  }

  /* =======================================================================
     GLOBAL ACCESS (for debugging)
     ======================================================================= */
  
  if (isDebugMode) {
    window.HelpModal = {
      open: openHelpModal,
      close: closeHelpModal,
      switchTab: switchTab,
      reload: initializeHelpSystem,
      clearState: clearModalState,
      getState: function() {
        return {
          open: helpModalOpen,
          viewKey: currentViewKey,
          role: currentUserRole,
          locale: currentLocale,
          activeTab: currentActiveTab,
          sessionId: getSessionId(),
          chatHistory: getChatHistory()
        };
      }
    };
    console.log('[Help System] Debug mode enabled. Access via window.HelpModal');
  }

});
</script>