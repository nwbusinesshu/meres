{{-- Cookie Settings Modal --}}
<div class="modal fade modal-drawer" id="global-cookie-settings-modal" tabindex="-1" aria-labelledby="globalCookieSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="globalCookieSettingsModalLabel">🍪 {{ __('global.cookie-settings-title') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('global.close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-4">
                    {{ __('global.cookie-settings-description') }}
                </p>
                
                <form id="global-cookie-preferences-form">
                    {{-- Szükséges sütik (mindig be) --}}
                    <div class="cookie-category mb-4">
                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" 
                                   class="custom-control-input" 
                                   id="global-cookie-necessary"
                                   name="necessary"
                                   checked disabled>
                            <label class="custom-control-label" for="global-cookie-necessary">
                                <strong>{{ __('global.cookie-necessary-title') }}</strong>
                                <span class="badge badge-primary badge-sm ml-2">{{ __('global.cookie-required-badge') }}</span>
                            </label>
                        </div>
                        <p class="text-muted small mb-2">
                            {{ __('global.cookie-necessary-description') }}
                        </p>
                        <div class="cookie-details">
                            <small class="text-muted">
                                <strong>{{ __('global.cookie-used-cookies') }}:</strong>
                                <span class="d-block">• session cookie: {{ __('global.cookie-session-desc') }}</span>
                                <span class="d-block">• CSRF token: {{ __('global.cookie-csrf-desc') }}</span>
                                <span class="d-block">• auth cookie: {{ __('global.cookie-auth-desc') }}</span>
                                <span class="d-block">• cookie_consent: {{ __('global.cookie-consent-desc') }}</span>
                            </small>
                        </div>
                    </div>

                    {{-- Analitikai sütik (opcionális) --}}
                    <div class="cookie-category mb-4">
                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" 
                                   class="custom-control-input" 
                                   id="global-cookie-analytics"
                                   name="analytics">
                            <label class="custom-control-label" for="global-cookie-analytics">
                                <strong>{{ __('global.cookie-analytics-title') }}</strong>
                                <span class="badge badge-secondary badge-sm ml-2">{{ __('global.cookie-optional-badge') }}</span>
                            </label>
                        </div>
                        <p class="text-muted small mb-2">
                            {{ __('global.cookie-analytics-description') }}
                        </p>
                        <div class="cookie-details">
                            <small class="text-muted">
                                <strong>{{ __('global.cookie-used-cookies') }}:</strong>
                                <span class="d-block">• telemetry: {{ __('global.cookie-telemetry-desc') }}</span>
                                <span class="d-block">• usage_stats: {{ __('global.cookie-usage-stats-desc') }}</span>
                                <span class="d-block">• performance_data: {{ __('global.cookie-performance-desc') }}</span>
                            </small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('global.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="global-save-cookie-preferences">
                    {{ __('global.cookie-save-settings') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Cookie Policy Modal --}}
<div class="modal fade modal-drawer" id="global-cookie-policy-modal" tabindex="-1" aria-labelledby="globalCookiePolicyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="globalCookiePolicyModalLabel">🍪 {{ __('global.cookie-policy-title') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('global.close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <h3>{{ __('global.cookie-what-is-title') }}</h3>
                <p>
                    {{ __('global.cookie-what-is-description') }}
                </p>
                
                <h3>{{ __('global.cookie-how-we-use-title') }}</h3>
                <p>{{ __('global.cookie-how-we-use-description') }}</p>
                
                {{-- Szükséges sütik --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            {{ __('global.cookie-necessary-title') }}
                            <span class="badge badge-primary ml-2">{{ __('global.cookie-required-badge') }}</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <p>
                            {{ __('global.cookie-necessary-policy-description') }}
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('global.cookie-table-name') }}</th>
                                        <th>{{ __('global.cookie-table-purpose') }}</th>
                                        <th>{{ __('global.cookie-table-duration') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>session cookie</code></td>
                                        <td>{{ __('global.cookie-session-desc') }}</td>
                                        <td>{{ __('global.cookie-duration-session') }}</td>
                                    </tr>
                                    <tr>
                                        <td><code>CSRF token</code></td>
                                        <td>{{ __('global.cookie-csrf-desc') }}</td>
                                        <td>{{ __('global.cookie-duration-session') }}</td>
                                    </tr>
                                    <tr>
                                        <td><code>auth cookie</code></td>
                                        <td>{{ __('global.cookie-auth-desc') }}</td>
                                        <td>{{ __('global.cookie-duration-30-days') }}</td>
                                    </tr>
                                    <tr>
                                        <td><code>cookie_consent</code></td>
                                        <td>{{ __('global.cookie-consent-desc') }}</td>
                                        <td>{{ __('global.cookie-duration-1-year') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Analitikai sütik --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            {{ __('global.cookie-analytics-title') }}
                            <span class="badge badge-secondary ml-2">{{ __('global.cookie-optional-badge') }}</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <p>
                            {{ __('global.cookie-analytics-policy-description') }}
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('global.cookie-table-name') }}</th>
                                        <th>{{ __('global.cookie-table-purpose') }}</th>
                                        <th>{{ __('global.cookie-table-duration') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>telemetry</code></td>
                                        <td>{{ __('global.cookie-telemetry-desc') }}</td>
                                        <td>{{ __('global.cookie-duration-session') }}</td>
                                    </tr>
                                    <tr>
                                        <td><code>usage_stats</code></td>
                                        <td>{{ __('global.cookie-usage-stats-desc') }}</td>
                                        <td>{{ __('global.cookie-duration-30-days') }}</td>
                                    </tr>
                                    <tr>
                                        <td><code>performance_data</code></td>
                                        <td>{{ __('global.cookie-performance-desc') }}</td>
                                        <td>{{ __('global.cookie-duration-7-days') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <h3>{{ __('global.cookie-manage-settings-title') }}</h3>
                <p>
                    {{ __('global.cookie-manage-settings-description') }}
                </p>
                
                <div class="alert alert-info">
                    <h5>📞 {{ __('global.contact') }}</h5>
                    <p class="mb-1">{{ __('global.cookie-contact-description') }}:</p>
                    <ul class="mb-0">
                        <li>{{ __('global.email') }}: <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a></li>
                        <li>{{ __('global.website') }}: <a href="{{ url('/') }}">{{ config('app.name') }}</a></li>
                    </ul>
                </div>
                
                <p class="text-muted small">
                    <strong>{{ __('global.last-updated') }}:</strong> {{ date('Y. F j.') }}<br>
                    <strong>{{ __('global.version') }}:</strong> {{ config('cookie-consent.version', '1.0') }}
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('global.close') }}</button>
                <button type="button" class="btn btn-primary" data-dismiss="modal" id="global-cookie-settings-from-policy">
                    {{ __('global.cookie-modify-settings') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Global Cookie Management CSS --}}
<style>
.cookie-category {
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 0.375rem;
    background: #f8f9fa;
}

.cookie-details {
    margin-top: 0.5rem;
    padding-left: 1rem;
}
</style>

{{-- Global Cookie Management JavaScript --}}
<script>
(function() {
    'use strict';
    
    // Global cookie management functions
    window.CookieManager = {
        
        // Load current consent status and update modal
        loadCurrentSettings: function() {
            fetch('/cookie-consent/status')
                .then(response => response.json())
                .then(data => {
                    if (data.has_consent && data.consent) {
                        document.getElementById('global-cookie-analytics').checked = data.consent.analytics;
                    }
                })
                .catch(error => {
                    console.error('Error loading cookie settings:', error);
                });
        },
        
        // Open settings modal
        openSettings: function() {
            this.loadCurrentSettings();
            $('#global-cookie-settings-modal').modal('show');
        },
        
        // Open policy modal
        openPolicy: function() {
            $('#global-cookie-policy-modal').modal('show');
        },
        
        // Save preferences
        savePreferences: function() {
            const form = document.getElementById('global-cookie-preferences-form');
            const formData = new FormData(form);
            
            const preferences = {
                necessary: true, // Always true
                analytics: formData.has('analytics')
            };
            
            return fetch('/cookie-consent/store', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(preferences)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Trigger custom event for other scripts
                    window.dispatchEvent(new CustomEvent('cookieConsentGiven', {
                        detail: { consent: data.consent }
                    }));
                    
                    // Show success message
                    this.showMessage('{{ __('global.cookie-settings-saved') }}', 'success');
                    
                    // Hide any existing banner
                    const banner = document.getElementById('cookie-consent-banner');
                    if (banner) {
                        banner.remove();
                    }
                    
                    return data;
                } else {
                    throw new Error('Failed to save preferences');
                }
            })
            .catch(error => {
                console.error('Cookie consent error:', error);
                this.showMessage('{{ __('global.cookie-settings-error') }}', 'error');
                throw error;
            });
        },
        
        // Show temporary message
        showMessage: function(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'error' ? 'alert-danger' : 'alert-info';
            
            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
            alert.innerHTML = `
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            `;
            
            document.body.appendChild(alert);
            
            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 3000);
        }
    };
    
    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        
        // Footer cookie settings button
        const footerButton = document.getElementById('footer-cookie-settings');
        if (footerButton) {
            footerButton.addEventListener('click', function(e) {
                e.preventDefault();
                window.CookieManager.openSettings();
            });
        }
        
        // Save preferences button
        const saveButton = document.getElementById('global-save-cookie-preferences');
        if (saveButton) {
            saveButton.addEventListener('click', function() {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> {{ __('global.saving') }}...';
                this.disabled = true;
                
                window.CookieManager.savePreferences()
                    .finally(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        $('#global-cookie-settings-modal').modal('hide');
                    });
            });
        }
        
        // Open settings from policy modal
        const settingsFromPolicyButton = document.getElementById('global-cookie-settings-from-policy');
        if (settingsFromPolicyButton) {
            settingsFromPolicyButton.addEventListener('click', function() {
                $('#global-cookie-policy-modal').modal('hide');
                setTimeout(() => {
                    window.CookieManager.openSettings();
                }, 300);
            });
        }
    });
    
    // Banner integration - if banner buttons exist, connect them to global functions
    function connectBannerButtons() {
        const bannerSettingsBtn = document.getElementById('cookie-settings-btn');
        const bannerSaveBtn = document.getElementById('save-cookie-preferences');
        
        if (bannerSettingsBtn) {
            bannerSettingsBtn.addEventListener('click', function() {
                window.CookieManager.openSettings();
            });
        }
        
        if (bannerSaveBtn) {
            bannerSaveBtn.addEventListener('click', function() {
                const form = document.getElementById('cookie-preferences-form');
                const formData = new FormData(form);
                
                const preferences = {
                    necessary: true,
                    analytics: formData.has('analytics')
                };
                
                window.CookieManager.savePreferences()
                    .then(() => {
                        $('#cookie-settings-modal').modal('hide');
                        
                        // Hide banner
                        const banner = document.getElementById('cookie-consent-banner');
                        if (banner) {
                            banner.style.animation = banner.dataset.position === 'top' 
                                ? 'slideInDown 0.3s ease-out reverse'
                                : 'slideInUp 0.3s ease-out reverse';
                            
                            setTimeout(() => {
                                banner.remove();
                            }, 300);
                        }
                    });
            });
        }
    }
    
    // Try to connect banner buttons when available
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', connectBannerButtons);
    } else {
        connectBannerButtons();
    }
    
})();
</script>