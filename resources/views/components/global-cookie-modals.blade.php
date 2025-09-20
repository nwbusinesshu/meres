{{-- Cookie Settings Modal --}}
<div class="modal fade modal-drawer" id="global-cookie-settings-modal" tabindex="-1" aria-labelledby="globalCookieSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="globalCookieSettingsModalLabel">🍪 Süti beállítások</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-4">
                    Személyre szabhatja, hogy mely típusú sütiket szeretné engedélyezni. 
                    A választását bármikor módosíthatja.
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
                                <strong>Szükséges sütik</strong>
                                <span class="badge badge-primary badge-sm ml-2">Kötelező</span>
                            </label>
                        </div>
                        <p class="text-muted small mb-2">
                            Ezek a sütik elengedhetetlenek a weboldal megfelelő működéséhez és nem kapcsolhatók ki.
                        </p>
                        <div class="cookie-details">
                            <small class="text-muted">
                                <strong>Használt sütik:</strong>
                                <span class="d-block">• session cookie: Munkamenet azonosító a bejelentkezéshez</span>
                                <span class="d-block">• CSRF token: Biztonsági token a támadások ellen</span>
                                <span class="d-block">• auth cookie: Bejelentkezési állapot megőrzése</span>
                                <span class="d-block">• cookie_consent: Süti beállítások tárolása</span>
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
                                <strong>Analitikai sütik</strong>
                                <span class="badge badge-secondary badge-sm ml-2">Opcionális</span>
                            </label>
                        </div>
                        <p class="text-muted small mb-2">
                            Ezek a sütik segítenek megérteni, hogyan használják a látogatók a weboldalt. Névtelen statisztikák készítéséhez használjuk.
                        </p>
                        <div class="cookie-details">
                            <small class="text-muted">
                                <strong>Használt sütik:</strong>
                                <span class="d-block">• telemetry: Felhasználói viselkedés nyomon követése</span>
                                <span class="d-block">• usage_stats: Oldal használati statisztikák</span>
                                <span class="d-block">• performance_data: Oldal teljesítmény mérése</span>
                            </small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Mégse</button>
                <button type="button" class="btn btn-primary" id="global-save-cookie-preferences">
                    Beállítások mentése
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
                <h5 class="modal-title" id="globalCookiePolicyModalLabel">🍪 Süti Szabályzat</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <h3>Mi az a süti?</h3>
                <p>
                    A sütik kis szöveges fájlok, amelyeket a weboldal az Ön számítógépére vagy mobileszközére ment, 
                    amikor meglátogatja a weboldalt. Lehetővé teszik a weboldal számára, hogy emlékezzen az Ön 
                    műveletére és preferenciáira egy bizonyos időn keresztül.
                </p>
                
                <h3>Hogyan használjuk a sütiket?</h3>
                <p>Weboldalunk két típusú sütit használ:</p>
                
                {{-- Szükséges sütik --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            Szükséges sütik
                            <span class="badge badge-primary ml-2">Kötelező</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <p>
                            Ezek a sütik elengedhetetlenek a weboldal megfelelő működéséhez és nem kapcsolhatók ki.
                            Ezen sütik nélkül a weboldal nem működne megfelelően.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Süti neve</th>
                                        <th>Célkitűzés</th>
                                        <th>Érvényesség</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>session cookie</code></td>
                                        <td>Munkamenet azonosító a bejelentkezéshez</td>
                                        <td>Böngésző bezárásáig</td>
                                    </tr>
                                    <tr>
                                        <td><code>CSRF token</code></td>
                                        <td>Biztonsági token a támadások ellen</td>
                                        <td>Böngésző bezárásáig</td>
                                    </tr>
                                    <tr>
                                        <td><code>auth cookie</code></td>
                                        <td>Bejelentkezési állapot megőrzése</td>
                                        <td>30 nap (ha bejelölve)</td>
                                    </tr>
                                    <tr>
                                        <td><code>cookie_consent</code></td>
                                        <td>Süti beállítások tárolása</td>
                                        <td>1 év</td>
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
                            Analitikai sütik
                            <span class="badge badge-secondary ml-2">Opcionális</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <p>
                            Ezek a sütik segítenek megérteni, hogyan használják a látogatók a weboldalt. 
                            Névtelen statisztikák készítéséhez használjuk őket a felhasználói élmény javítása érdekében.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Süti neve</th>
                                        <th>Célkitűzés</th>
                                        <th>Érvényesség</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>telemetry</code></td>
                                        <td>Felhasználói viselkedés nyomon követése</td>
                                        <td>Böngésző bezárásáig</td>
                                    </tr>
                                    <tr>
                                        <td><code>usage_stats</code></td>
                                        <td>Oldal használati statisztikák</td>
                                        <td>30 nap</td>
                                    </tr>
                                    <tr>
                                        <td><code>performance_data</code></td>
                                        <td>Oldal teljesítmény mérése</td>
                                        <td>7 nap</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <h3>Süti beállítások kezelése</h3>
                <p>
                    A süti beállításait bármikor módosíthatja. Az analitikai sütiket bármikor ki- vagy bekapcsolhatja 
                    anélkül, hogy ez befolyásolná a weboldal alapvető funkcióit.
                </p>
                
                <div class="alert alert-info">
                    <h5>📞 Kapcsolat</h5>
                    <p class="mb-1">Ha kérdése van a süti szabályzatunkkal kapcsolatban:</p>
                    <ul class="mb-0">
                        <li>Email: <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a></li>
                        <li>Weboldal: <a href="{{ url('/') }}">{{ config('app.name') }}</a></li>
                    </ul>
                </div>
                
                <p class="text-muted small">
                    <strong>Utolsó frissítés:</strong> {{ date('Y. F j.') }}<br>
                    <strong>Verzió:</strong> {{ config('cookie-consent.version', '1.0') }}
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Bezárás</button>
                <button type="button" class="btn btn-primary" data-dismiss="modal" id="global-cookie-settings-from-policy">
                    Beállítások módosítása
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
                    this.showMessage('Süti beállítások sikeresen mentve!', 'success');
                    
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
                this.showMessage('Hiba történt a beállítások mentésekor!', 'error');
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
                this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mentés...';
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