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
        {{-- ✅ NEW: Support Tickets Tab --}}
        <button class="help-tab" data-tab="support-tickets">
          <i class="fa fa-ticket-alt"></i>
          <span>{{ __('support.tab-title') }}</span>
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

        {{-- ✅ NEW: Support Tickets Tab Content --}}
        <div class="help-tab-content" id="help-tab-support-tickets">
          
          {{-- Tickets Toolbar --}}
          <div class="help-tickets-toolbar">
            <button class="help-new-ticket-btn" title="{{ __('support.new-ticket') }}">
              <i class="fa fa-plus"></i>
              <span>{{ __('support.create-ticket') }}</span>
            </button>
          </div>

          {{-- Tickets List View --}}
          <div class="help-tickets-list-view">
            <div class="help-tickets-list">
              <div class="help-loading">
                <i class="fa fa-spinner fa-spin"></i>
                <p>{{ __('support.loading-tickets') }}</p>
              </div>
            </div>
          </div>

          {{-- Ticket Detail View (hidden by default) --}}
          <div class="help-ticket-detail-view" style="display: none;">
            <div class="help-ticket-detail-header">
              <button class="help-back-to-tickets-btn">
                <i class="fa fa-arrow-left"></i>
                <span>{{ __('support.back-to-list') }}</span>
              </button>
            </div>
            <div class="help-ticket-detail-content">
              {{-- Ticket messages will be loaded here --}}
            </div>
            <div class="help-ticket-reply-container">
              <div class="help-ticket-reply-wrapper">
                <textarea 
                  class="help-ticket-reply-input" 
                  placeholder="{{ __('support.reply-placeholder') }}" 
                  rows="1"
                ></textarea>
                <button class="help-ticket-reply-send">
                  <i class="fa fa-paper-plane"></i>
                </button>
              </div>
            </div>
          </div>

          {{-- New Ticket Form (hidden by default) --}}
          <div class="help-new-ticket-form" style="display: none;">
            <div class="help-new-ticket-header">
              <button class="help-cancel-ticket-btn">
                <i class="fa fa-arrow-left"></i>
                <span>{{ __('support.back-to-list') }}</span>
              </button>
              <h4>{{ __('support.new-ticket') }}</h4>
            </div>
            <div class="help-new-ticket-body">
              <div class="form-group">
                <label>{{ __('support.title') }}</label>
                <input type="text" class="form-control ticket-title-input" placeholder="{{ __('support.title-placeholder') }}">
              </div>
              <div class="form-group">
                <label>{{ __('support.priority') }}</label>
                <select class="form-control ticket-priority-select">
                  <option value="low">{{ __('support.priority-low') }}</option>
                  <option value="medium" selected>{{ __('support.priority-medium') }}</option>
                  <option value="high">{{ __('support.priority-high') }}</option>
                  <option value="urgent">{{ __('support.priority-urgent') }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>{{ __('support.message') }}</label>
                <textarea class="form-control ticket-message-input" rows="5" placeholder="{{ __('support.message-placeholder') }}"></textarea>
              </div>
              <button class="btn btn-primary help-submit-ticket-btn">
                <i class="fa fa-paper-plane"></i>
                {{ __('support.create-ticket') }}
              </button>
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