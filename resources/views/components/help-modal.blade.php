{{-- Help Modal - Custom Drawer (no Bootstrap modal classes) --}}
<div class="help-drawer" id="help-modal" role="dialog" aria-labelledby="help-modal-title" aria-hidden="true">
  <div class="help-drawer-dialog">
    <div class="help-drawer-content">
      
      {{-- Modal Header with Tabs --}}
      <div class="help-drawer-header">
        <div class="help-header-content">
          <h5 class="help-drawer-title" id="help-modal-title">{{ __('help.modal-title') }}</h5>
          <p class="help-page-title">{{ __('help.loading') }}</p>
        </div>
        <button type="button" class="help-drawer-close" aria-label="{{ __('global.close') }}">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      {{-- Tab Navigation --}}
      <div class="help-tabs">
        <button class="help-tab active" data-tab="about-page">
          <i class="fa fa-book"></i>
          <span>{{ __('help.tab-about-page') }}</span>
        </button>
        <button class="help-tab" data-tab="ai-support">
          <i class="fa fa-comments"></i>
          <span>{{ __('help.tab-ai-support') }}</span>
        </button>
      </div>

      {{-- Modal Body --}}
      <div class="help-drawer-body">
        
        {{-- About Page Tab Content --}}
        <div class="help-tab-content active" id="help-tab-about-page">
          <div class="help-static-content">
            <div class="help-loading">
              <i class="fa fa-spinner fa-spin"></i>
              <p>{{ __('help.loading-content') }}</p>
            </div>
          </div>
        </div>

        {{-- AI Support Tab Content --}}
        <div class="help-tab-content" id="help-tab-ai-support">
          
          {{-- Conversations Toolbar --}}
          <div class="help-ai-toolbar">
            <button class="help-conversations-btn" title="{{ __('help.view-conversations') }}">
              <i class="fa fa-history"></i>
              <span>{{ __('help.conversations') }}</span>
            </button>
            <button class="help-new-conversation-btn" title="{{ __('help.new-conversation') }}">
              <i class="fa fa-plus"></i>
              <span>{{ __('help.new') }}</span>
            </button>
          </div>

          <div class="help-ai-container">
            
            {{-- Chat Messages Area --}}
            <div class="help-chat-messages">
              <div class="help-welcome-message">
                <div class="help-ai-bubble">
                  <div class="help-bubble-icon">
                    <i class="fa fa-robot"></i>
                  </div>
                  <div class="help-bubble-content">
                    <p><strong>{{ __('help.ai-welcome-title') }}</strong></p>
                    <p>{{ __('help.ai-welcome-message') }}</p>
                  </div>
                </div>
              </div>
              
              {{-- Chat history will be appended here --}}
              <div class="help-chat-history"></div>
            </div>

            {{-- Chat Input Area --}}
            <div class="help-chat-input-container">
              <div class="help-chat-input-wrapper">
                <textarea 
                  class="help-chat-input" 
                  placeholder="{{ __('help.ai-input-placeholder') }}" 
                  rows="1"
                ></textarea>
                <button class="help-chat-send">
                  <i class="fa fa-paper-plane"></i>
                </button>
              </div>
              <span>{{ __('help.ai-warning') }}</span>
            </div>

          </div>
        </div>

      </div>

    </div>
  </div>

  {{-- Conversations Sidebar --}}
  <div class="help-conversations-sidebar">
    <div class="help-conversations-sidebar-overlay"></div>
    <div class="help-conversations-sidebar-content">
      <div class="help-conversations-header">
        <h3>{{ __('help.conversations') }}</h3>
        <button class="help-conversations-close">
          <i class="fa fa-times"></i>
        </button>
      </div>
      <div class="help-conversations-list">
        {{-- Conversations will be loaded here dynamically --}}
      </div>
    </div>
  </div>

</div>