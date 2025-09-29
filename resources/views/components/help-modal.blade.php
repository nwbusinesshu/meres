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
              <div class="help-chat-history">
                {{-- Example user message (for styling reference) --}}
                <div class="help-user-bubble" style="display: none;">
                  <div class="help-bubble-content">
                    <p>Example user message</p>
                  </div>
                  <div class="help-bubble-icon">
                    <i class="fa fa-user"></i>
                  </div>
                </div>

                {{-- Example AI response (for styling reference) --}}
                <div class="help-ai-bubble" style="display: none;">
                  <div class="help-bubble-icon">
                    <i class="fa fa-robot"></i>
                  </div>
                  <div class="help-bubble-content">
                    <p>Example AI response</p>
                  </div>
                </div>
              </div>
            </div>

            {{-- Chat Input Area --}}
            <div class="help-chat-input-container">
              <div class="help-ai-notice">
                <i class="fa fa-info-circle"></i>
                <span>{{ __('help.ai-coming-soon') }}</span>
              </div>
              <div class="help-chat-input-wrapper">
                <textarea 
                  class="help-chat-input" 
                  placeholder="{{ __('help.ai-input-placeholder') }}" 
                  rows="1"
                  disabled
                ></textarea>
                <button class="help-chat-send" disabled>
                  <i class="fa fa-paper-plane"></i>
                </button>
              </div>
            </div>

          </div>
        </div>

      </div>

    </div>
  </div>
</div>