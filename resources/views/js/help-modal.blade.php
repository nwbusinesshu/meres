<script>
$(document).ready(function() {

  /* =======================================================================
     CONFIGURATION & STATE
     ======================================================================= */
  
  const isDebugMode = {{ config('app.debug') ? 'true' : 'false' }};
  
  // Session storage keys
  const STORAGE_KEY_MODAL_STATE = 'helpModalState';
  const STORAGE_KEY_CURRENT_SESSION = 'helpCurrentSession';
  const STORAGE_KEY_FIRST_LOGIN_SHOWN = 'help_first_login_shown';
  
  // Current state variables
  let helpModalOpen = false;
  let currentViewKey = '';
  let currentUserRole = '';
  let currentLocale = '';
  let currentPageTitle = '';
  let currentActiveTab = 'ai-support';
  let currentSessionId = null;
  let isSendingMessage = false;
  let conversationListOpen = false;

  /* =======================================================================
     SESSION STORAGE HELPERS
     ======================================================================= */
  
  function saveModalState() {
    try {
      sessionStorage.setItem(STORAGE_KEY_MODAL_STATE, JSON.stringify({
        wasOpen: helpModalOpen,
        activeTab: currentActiveTab
      }));
    } catch (e) {
      // Silently fail if sessionStorage is not available
    }
  }
  
  function getModalState() {
    try {
      const state = sessionStorage.getItem(STORAGE_KEY_MODAL_STATE);
      return state ? JSON.parse(state) : { wasOpen: false, activeTab: 'about-page' };
    } catch (e) {
      return { wasOpen: false, activeTab: 'about-page' };
    }
  }
  
  function clearModalState() {
    try {
      sessionStorage.removeItem(STORAGE_KEY_MODAL_STATE);
    } catch (e) {
      // Silently fail
    }
  }
  
  function saveCurrentSession(sessionId) {
    try {
      if (sessionId) {
        sessionStorage.setItem(STORAGE_KEY_CURRENT_SESSION, sessionId);
      } else {
        sessionStorage.removeItem(STORAGE_KEY_CURRENT_SESSION);
      }
    } catch (e) {
      // Silently fail
    }
  }
  
  function getCurrentSession() {
    try {
      return sessionStorage.getItem(STORAGE_KEY_CURRENT_SESSION) || null;
    } catch (e) {
      return null;
    }
  }

  // First login tracking functions
function isFirstLoginShown() {
  try {
    return sessionStorage.getItem(STORAGE_KEY_FIRST_LOGIN_SHOWN) === 'true';
  } catch (e) {
    return false;
  }
}

function markFirstLoginShown() {
  try {
    sessionStorage.setItem(STORAGE_KEY_FIRST_LOGIN_SHOWN, 'true');
  } catch (e) {
    // Silently fail
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
    
    // Load current session from storage
    currentSessionId = getCurrentSession();
    
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

    setTimeout(function() {
    checkFirstLogin();
    }, 500);
    
    if (isDebugMode) {
      console.log('[Help System] Initialized:', {
        viewKey: currentViewKey,
        role: currentUserRole,
        locale: currentLocale,
        pageTitle: currentPageTitle,
        restoredState: state,
        currentSessionId: currentSessionId
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

  // Check if this is first login and auto-open help
function checkFirstLogin() {
  // Read first login flag from meta tag
  const isFirstLogin = $('meta[name="app-first-login"]').attr('content') === 'true';
  
  if (isFirstLogin && !isFirstLoginShown()) {
    if (isDebugMode) {
      console.log('[Help System] First login detected, opening welcome modal');
    }
    
    // Mark as shown so it doesn't repeat
    markFirstLoginShown();
    
    // Open modal and switch to AI tab
    currentActiveTab = 'ai-support';
    openHelpModal(false); // Use animation for better UX
    
    // Show welcome message after modal opens
    setTimeout(function() {
      showFirstLoginWelcome();
    }, 500);
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
  
  // Make API call to fetch help content
  $.ajax({
    url: '/help/content',
    method: 'GET',
    data: {
      view_key: currentViewKey,
      locale: currentLocale
    },
    skipGlobalErrorHandler: true, // <-- NEW: Custom flag to prevent global error popup
    success: function(response) {
      if (response.success && response.content) {
        // Display the HTML content
        $content.html(response.content);
        
        if (isDebugMode) {
          console.log('[Help Modal] Content loaded successfully', {
            viewKey: currentViewKey,
            locale: currentLocale,
            metadata: response.metadata
          });
        }
      } else {
        showContentNotFound();
      }
    },
    error: function(xhr) {
      // Handle 404 (not found) gracefully
      if (xhr.status === 404) {
        showContentNotFound();
      } else {
        // Show error for other failures
        $content.html(`
          <div class="help-content-error">
            <i class="fa fa-exclamation-triangle"></i>
            <p><strong>{{ __('help.error-loading-content') }}</strong></p>
            <p>{{ __('help.try-again-later') }}</p>
          </div>
        `);
      }
      
      if (isDebugMode) {
        console.error('[Help Modal] Failed to load content:', {
          status: xhr.status,
          viewKey: currentViewKey,
          locale: currentLocale
        });
      }
    }
  });
}

function showContentNotFound() {
  const $content = $('.help-static-content');
  $content.html(`
    <div class="help-content-not-found">
      <i class="fa fa-book-open"></i>
      <p><strong>{{ __('help.content-not-found') }}</strong></p>
      <p>{{ __('help.content-coming-soon') }}</p>
    </div>
  `);
}
// FIXED: Simplified welcome message visibility logic
function loadAiSupportContent() {
  // Enable the chat interface
  enableChatInterface();
  
  // Clear chat history first
  $('.help-chat-history').empty();
  
  // Load current session if exists
  if (currentSessionId) {
    // Hide welcome message when loading existing session
    $('.help-welcome-message').hide();
    loadChatSession(currentSessionId);
    
    if (isDebugMode) {
      console.log('[Help Modal] Loading existing session:', currentSessionId);
    }
  } else {
    // No session - check if first login
    const isFirstLogin = $('meta[name="app-first-login"]').attr('content') === 'true';
    const firstLoginShown = isFirstLoginShown();
    
    if (isFirstLogin && !firstLoginShown) {
      // First login - hide default welcome, show personalized
      $('.help-welcome-message').hide();
      showFirstLoginWelcome();
      
      if (isDebugMode) {
        console.log('[Help Modal] Showing first login welcome');
      }
    } else {
      // Normal usage - show default welcome
      $('.help-welcome-message').show();
      
      if (isDebugMode) {
        console.log('[Help Modal] Showing default welcome message');
      }
    }
    
    scrollChatToBottom();
  }
}

  // Show personalized welcome message for first-time users
// Show personalized welcome message for first-time users
  // MODIFIED: Hide default welcome and show personalized message
function showFirstLoginWelcome() {
  const userName = '{{ session("uname") ?? "User" }}';
  const orgRole = '{{ session("org_role") ?? "" }}';
  
  let welcomeMessage = `Üdvözlünk a rendszerben, ${userName}! 👋\n\n`;
  welcomeMessage += `Én vagyok a QUARMA360 app fejlett AI súgója. Segíthetek navigálni az alkalmazásban és válaszolok minden kérdésedre. Pontosan ismerem a program használatát és azt is láthatom, amit éppen te. Engem itt, a képernyő bal oldalán a kék súgó gombra nyomva mindig megtalálsz, és a korábbi beszélgetéseinket is meg tudod nézni.\n\n`;
  
  // Add role-specific tips
  if (orgRole === 'admin' || orgRole === 'ceo') {
    welcomeMessage += `📊 Admin funkcióid:\n`;
    welcomeMessage += `• Munkatársak kezelése\n`;
    welcomeMessage += `• Értékelések indítása\n`;
    welcomeMessage += `• Szervezeti beállítások\n\n`;
    welcomeMessage += `Kérdezz bármit a rendszer használatával kapcsolatban!`;
    welcomeMessage += `Első lépésként a regisztrációkor megadott létszámadatok alapján fizetési kötelezettséged keletkezett. Kérlek ezt a fizetések oldalon rendezd, utána tudunk továbbhaladni.`;
  } else if (orgRole === 'manager') {
    welcomeMessage += `👥 Vezető funkcióid:\n`;
    welcomeMessage += `• Csapattagjaid értékelése\n`;
    welcomeMessage += `• Értékelési eredmények megtekintése\n\n`;
    welcomeMessage += `Kérdezz bármit a rendszer használatával kapcsolatban!`;
  } else {
    welcomeMessage += `🎯 Gyakran kérdezett:\n`;
    welcomeMessage += `• Hogyan töltsek ki egy értékelést?\n`;
    welcomeMessage += `• Hol találom az eredményeimet?\n`;
    welcomeMessage += `• Hogyan változtatom a beállításaimat?\n\n`;
    welcomeMessage += `Bátran kérdezz bármit!`;
  }
  
  // UPDATED: Hide the default welcome message
  $('.help-welcome-message').hide();
  
  // Display the personalized welcome message in UI immediately
  appendAiMessage(welcomeMessage);
  
  // Create a new session and save the welcome message to database
  $.ajax({
    url: '{{ route("help.chat.session.new") }}',
    method: 'POST',
    data: {
      view_key: currentViewKey,
      locale: currentLocale,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      if (response.success && response.session) {
        currentSessionId = response.session.id;
        saveCurrentSession(currentSessionId);
        
        // Save welcome message to the session
        saveWelcomeMessageToDb(welcomeMessage);
        
        if (isDebugMode) {
          console.log('[Help System] First login welcome session created:', currentSessionId);
        }
      }
    },
    error: function(xhr) {
      if (isDebugMode) {
        console.error('[Help System] Failed to create welcome session:', xhr);
      }
    }
  });
  
  if (isDebugMode) {
    console.log('[Help System] First login welcome message displayed');
  }
}

  // Save welcome message to database (NEW HELPER FUNCTION)
  function saveWelcomeMessageToDb(welcomeMessage) {
    $.ajax({
      url: '{{ route("help.chat.send") }}',
      method: 'POST',
      data: {
        message: 'Üdvözlet', // User's implicit greeting
        session_id: currentSessionId,
        view_key: currentViewKey,
        locale: currentLocale,
        welcome_mode: 1,
        welcome_response: welcomeMessage, // Pre-generated response
        _token: '{{ csrf_token() }}'
      },
      success: function(response) {
        if (isDebugMode) {
          console.log('[Help System] Welcome message saved to database');
        }
      },
      error: function(xhr) {
        if (isDebugMode) {
          console.error('[Help System] Failed to save welcome message:', xhr);
        }
      }
    });
  }

  /* =======================================================================
     CHAT FUNCTIONALITY
     ======================================================================= */
  
  function enableChatInterface() {
    // Remove disabled state from input and button
    $('.help-chat-input').prop('disabled', false);
    $('.help-chat-send').prop('disabled', false);
  }
  
 function loadChatSession(sessionId) {
  if (isDebugMode) {
    console.log('[Help Chat] Loading session:', sessionId);
  }
  
  $.ajax({
    url: `/help/chat/session/${sessionId}`,
    method: 'GET',
    success: function(response) {
      if (response.success && response.session && response.session.messages) {
        // Clear history before rendering
        $('.help-chat-history').empty();
        
        // Render all messages
        renderMessages(response.session.messages);
        
        // Update current session
        currentSessionId = response.session.id;
        saveCurrentSession(currentSessionId);
        
        if (isDebugMode) {
          console.log('[Help Chat] Session loaded successfully:', {
            sessionId: response.session.id,
            messageCount: response.session.messages.length
          });
        }
      }
    },
    error: function(xhr) {
      if (isDebugMode) {
        console.error('[Help Chat] Failed to load session:', xhr);
      }
      // Clear invalid session
      currentSessionId = null;
      saveCurrentSession(null);
      $('.help-chat-history').empty();
      $('.help-welcome-message').show();
    }
  });
}
  
  function renderMessages(messages) {
    const $history = $('.help-chat-history');
    $history.empty();
    
    messages.forEach(function(msg) {
      if (msg.role === 'user') {
        appendUserMessage(msg.content, false);
      } else if (msg.role === 'assistant') {
        appendAiMessage(msg.content, false);
      }
    });
    
    scrollChatToBottom();
  }
  
  function appendUserMessage(message, saveToDb = true) {
    const $history = $('.help-chat-history');
    const $bubble = $(`
      <div class="help-user-bubble">
        <div class="help-bubble-content">
          <p>${escapeHtml(message)}</p>
        </div>
        <div class="help-bubble-icon">
          <i class="fa fa-user"></i>
        </div>
      </div>
    `);
    
    $history.append($bubble);
    scrollChatToBottom();
  }
  
  function appendAiMessage(message, saveToDb = true) {
    const $history = $('.help-chat-history');
    const $bubble = $(`
      <div class="help-ai-bubble">
        <div class="help-bubble-icon">
          <i class="fa fa-robot"></i>
        </div>
        <div class="help-bubble-content">
          <p>${escapeHtml(message).replace(/\n/g, '<br>')}</p>
        </div>
      </div>
    `);
    
    $history.append($bubble);
    scrollChatToBottom();
  }
  
  function appendLoadingMessage() {
    const $history = $('.help-chat-history');
    const $bubble = $(`
      <div class="help-ai-bubble help-ai-loading">
        <div class="help-bubble-icon">
          <i class="fa fa-robot"></i>
        </div>
        <div class="help-bubble-content">
          <div class="help-typing-indicator">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
      </div>
    `);
    
    $history.append($bubble);
    scrollChatToBottom();
  }
  
  function removeLoadingMessage() {
    $('.help-ai-loading').remove();
  }
  
  function scrollChatToBottom() {
    const $messages = $('.help-chat-messages');
    $messages.scrollTop($messages[0].scrollHeight);
  }
  
  function sendChatMessage() {
  if (isSendingMessage) {
    return; // Prevent multiple simultaneous sends
  }
  
  const $input = $('.help-chat-input');
  const message = $input.val().trim();
  
  if (!message) {
    return; // Don't send empty messages
  }
  
  // Append user message
  appendUserMessage(message);
  
  // Clear input
  $input.val('');
  autoResizeTextarea($input[0]);
  
  // Disable input while sending
  isSendingMessage = true;
  $input.prop('disabled', true);
  $('.help-chat-send').prop('disabled', true);
  
  // Show loading indicator
  appendLoadingMessage();
  
  // Send to API
  $.ajax({
    url: '{{ route("help.chat.send") }}',
    method: 'POST',
    data: {
      message: message,
      session_id: currentSessionId,
      view_key: currentViewKey,
      locale: currentLocale,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      removeLoadingMessage();
      
      // UPDATED: Check if response is a function call request
      if (response.success && response.function_call) {
        // AI wants to load additional documents
        handleFunctionCall(response.function_call, message, response.session_id);
        return;
      }
      
      // Normal AI response
      if (response.success && response.response) {
        appendAiMessage(response.response);
        
        // Update current session ID if new session was created
        if (response.session_id) {
          currentSessionId = response.session_id;
          saveCurrentSession(currentSessionId);
        }
      } else {
        appendAiMessage('{{ __("help.ai-error-generic") }}');
      }
    },
    error: function(xhr) {
      removeLoadingMessage();
      
      let errorMessage = '{{ __("help.ai-error-generic") }}';
      
      if (xhr.status === 429) {
        errorMessage = '{{ __("help.ai-error-rate-limit") }}';
      } else if (xhr.responseJSON && xhr.responseJSON.error) {
        errorMessage = xhr.responseJSON.error;
      }
      
      appendAiMessage(errorMessage);
      
      if (isDebugMode) {
        console.error('[Help Chat] Error:', xhr);
      }
    },
    complete: function() {
      // Re-enable input
      isSendingMessage = false;
      $input.prop('disabled', false);
      $('.help-chat-send').prop('disabled', false);
      $input.focus();
    }
  });
}

function handleFunctionCall(functionCall, originalMessage, sessionId) {
  if (functionCall.name !== 'load_help_documents') {
    appendAiMessage('{{ __("help.ai-error-generic") }}');
    return;
  }
  
  const viewKeys = functionCall.arguments.view_keys || [];
  
  if (!viewKeys.length) {
    appendAiMessage('{{ __("help.ai-error-generic") }}');
    return;
  }
  
  if (isDebugMode) {
    console.log('[Help Chat] Loading additional documents:', viewKeys);
  }
  
  // Show thinking indicator
  appendThinkingMessage();
  
  // Call load-docs endpoint
  $.ajax({
    url: '{{ route("help.chat.load-docs") }}',
    method: 'POST',
    data: {
      session_id: sessionId,
      view_keys: viewKeys,
      original_message: originalMessage,
      _token: '{{ csrf_token() }}'
    },
    success: function(response) {
      removeThinkingMessage();
      
      if (response.success && response.response) {
        appendAiMessage(response.response);
        
        // Update session ID
        if (response.session_id) {
          currentSessionId = response.session_id;
          saveCurrentSession(currentSessionId);
        }
        
        if (isDebugMode) {
          console.log('[Help Chat] Successfully loaded additional docs:', response.loaded_docs);
        }
      } else {
        appendAiMessage('{{ __("help.ai-error-generic") }}');
      }
    },
    error: function(xhr) {
      removeThinkingMessage();
      
      let errorMessage = '{{ __("help.ai-error-load-session") }}';
      
      if (xhr.responseJSON && xhr.responseJSON.error) {
        errorMessage = xhr.responseJSON.error;
      }
      
      appendAiMessage(errorMessage);
      
      if (isDebugMode) {
        console.error('[Help Chat] Load docs error:', xhr);
      }
    }
  });
}

function appendThinkingMessage() {
  const $history = $('.help-chat-history');
  const $bubble = $(`
    <div class="help-ai-bubble help-ai-thinking">
      <div class="help-bubble-icon">
        <i class="fa fa-robot"></i>
      </div>
      <div class="help-bubble-content">
        <div class="help-thinking-text">
          <i class="fa fa-brain fa-pulse"></i>
          <span>{{ __('help.ai-thinking') }}</span>
        </div>
      </div>
    </div>
  `);
  
  $history.append($bubble);
  scrollChatToBottom();
}

function removeThinkingMessage() {
  $('.help-ai-thinking').remove();
}


  /* =======================================================================
     CONVERSATION LIST
     ======================================================================= */
  
  function openConversationList() {
    conversationListOpen = true;
    
    // Show loading
    $('.help-conversations-list').html(`
      <div class="help-loading">
        <img src="{{ asset('assets/loader/loader.svg') }}" alt="Loading..." class="help-loader-svg">
        <p>{{ __('help.loading-conversations') }}</p>
      </div>
    `);
    
    $('.help-conversations-sidebar').addClass('open');
    
    // Load conversations
    $.ajax({
      url: '{{ route("help.chat.sessions") }}',
      method: 'GET',
      success: function(response) {
        if (response.success && response.sessions) {
          renderConversationList(response.sessions);
        } else {
          $('.help-conversations-list').html(`
            <div class="help-empty-state">
              <i class="fa fa-comments"></i>
              <p>{{ __('help.no-conversations') }}</p>
            </div>
          `);
        }
      },
      error: function(xhr) {
        $('.help-conversations-list').html(`
          <div class="help-error-state">
            <i class="fa fa-exclamation-triangle"></i>
            <p>{{ __('help.error-loading-conversations') }}</p>
          </div>
        `);
        
        if (isDebugMode) {
          console.error('[Help Chat] Failed to load conversations:', xhr);
        }
      }
    });
  }
  
  function closeConversationList() {
    conversationListOpen = false;
    $('.help-conversations-sidebar').removeClass('open');
  }
  
  function renderConversationList(sessions) {
    if (!sessions || sessions.length === 0) {
      $('.help-conversations-list').html(`
        <div class="help-empty-state">
          <i class="fa fa-comments"></i>
          <p>{{ __('help.no-conversations') }}</p>
        </div>
      `);
      return;
    }
    
    const $list = $('.help-conversations-list');
    $list.empty();
    
    sessions.forEach(function(session) {
      const isActive = session.id === currentSessionId;
      const date = new Date(session.last_message_at);
      const formattedDate = formatRelativeDate(date);
      
      const $item = $(`
        <div class="help-conversation-item ${isActive ? 'active' : ''}" data-session-id="${session.id}">
          <div class="help-conversation-info">
            <h4 class="help-conversation-title">${escapeHtml(session.title)}</h4>
            <p class="help-conversation-preview">${escapeHtml(session.preview.substring(0, 60))}${session.preview.length > 60 ? '...' : ''}</p>
            <span class="help-conversation-date">${formattedDate} • ${session.message_count} {{ __('help.messages') }}</span>
          </div>
          <button class="help-conversation-delete" data-session-id="${session.id}" title="{{ __('help.delete-conversation') }}">
            <i class="fa fa-trash"></i>
          </button>
        </div>
      `);
      
      $list.append($item);
    });
  }
  
  function startNewConversation() {
    // Clear current session
    currentSessionId = null;
    saveCurrentSession(null);
    
    // Clear chat history
    $('.help-chat-history').empty();
    
    // Close conversation list
    closeConversationList();
    
    // Focus input
    $('.help-chat-input').focus();
    
    if (isDebugMode) {
      console.log('[Help Chat] Started new conversation');
    }
  }
  
  function formatRelativeDate(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return '{{ __("help.just-now") }}';
    if (diffMins < 60) return diffMins + ' {{ __("help.minutes-ago") }}';
    if (diffHours < 24) return diffHours + ' {{ __("help.hours-ago") }}';
    if (diffDays === 1) return '{{ __("help.yesterday") }}';
    if (diffDays < 7) return diffDays + ' {{ __("help.days-ago") }}';
    
    return date.toLocaleDateString(currentLocale);
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
     UTILITY FUNCTIONS
     ======================================================================= */
  
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
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

  // Textarea auto-resize
  $(document).on('input', '.help-chat-input', function() {
    autoResizeTextarea(this);
  });

  // Enter key to send
  $(document).on('keydown', '.help-chat-input', function(e) {
    if (e.key === 'Enter' && !e.shiftKey && !$(this).prop('disabled')) {
      e.preventDefault();
      sendChatMessage();
    }
  });

  // Send button click
  $(document).on('click', '.help-chat-send', function() {
    if (!$(this).prop('disabled')) {
      sendChatMessage();
    }
  });

  // Conversations button click
  $(document).on('click', '.help-conversations-btn', function() {
    if (conversationListOpen) {
      closeConversationList();
    } else {
      openConversationList();
    }
  });

  // New conversation button
  $(document).on('click', '.help-new-conversation-btn', function() {
    startNewConversation();
  });

  // Load conversation
  $(document).on('click', '.help-conversation-item', function(e) {
    // Don't trigger if clicking delete button
    if ($(e.target).closest('.help-conversation-delete').length) {
      return;
    }
    
    const sessionId = $(this).data('session-id');
    loadChatSession(sessionId);
    closeConversationList();
  });

  // Delete conversation
$(document).on('click', '.help-conversation-delete', function(e) {
    e.stopPropagation();
    
    const sessionId = $(this).data('session-id');
    
    $.ajax({
      url: `/help/chat/session/${sessionId}`,
      method: 'DELETE',
      data: {
        _token: '{{ csrf_token() }}'
      },
      success: function(response) {
        if (response.success) {
          // If deleted current session, start new
          if (sessionId === currentSessionId) {
            startNewConversation();
          }
          // Reload conversation list
          openConversationList();
        }
      },
      error: function(xhr) {
        if (isDebugMode) {
          console.error('[Help Chat] Failed to delete conversation:', xhr);
        }
      }
    });
  });

  // Close conversation list when clicking outside
  $(document).on('click', '.help-conversations-sidebar-overlay', function() {
    closeConversationList();
  });

  // Keyboard shortcuts
  $(document).on('keydown', function(e) {
    // ESC to close modal (only if explicitly pressed, not from other interactions)
    if (e.key === 'Escape' && helpModalOpen && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
      if (conversationListOpen) {
        closeConversationList();
      } else {
        closeHelpModal();
      }
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

  // Re-initialize on page navigation
  $(document).on('pageChanged', function() {
    if (isDebugMode) {
      console.log('[Help System] Page changed, re-initializing...');
    }
    initializeHelpSystem();
  });
  
  // Also listen for window load
  $(window).on('load', function() {
    const state = getModalState();
    if (state.wasOpen && !helpModalOpen) {
      if (isDebugMode) {
        console.log('[Help System] Restoring modal after page navigation');
      }
      currentActiveTab = state.activeTab;
      
      $('body').addClass('help-no-transition');
      $('.page-container').addClass('help-no-transition');
      $('#help-modal').addClass('help-no-transition');
      
      openHelpModal(true);
      
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
      clearSession: function() {
        currentSessionId = null;
        saveCurrentSession(null);
        $('.help-chat-history').empty();
        console.log('[Help System] Session cleared');
      },
      getState: function() {
        return {
          open: helpModalOpen,
          viewKey: currentViewKey,
          role: currentUserRole,
          locale: currentLocale,
          activeTab: currentActiveTab,
          currentSessionId: currentSessionId,
          conversationListOpen: conversationListOpen
        };
      }
    };
    console.log('[Help System] Debug mode enabled. Access via window.HelpModal');
  }

});
</script>