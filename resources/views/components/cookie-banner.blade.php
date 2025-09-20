{{-- resources/views/components/cookie-banner.blade.php --}}
@if($cookieConsent['showBanner'] ?? false)
<div id="cookie-consent-banner" class="cookie-consent-banner" data-position="{{ config('cookie-consent.banner.position', 'bottom') }}">
    <div class="cookie-consent-container">
        <div class="cookie-consent-content">
            <div class="cookie-consent-text">
                <h4>🍪 Süti beállítások</h4>
                <p>
                    Ez a weboldal sütiket használ a jobb felhasználói élmény biztosítása érdekében. 
                    Kérjük, válassza ki, hogy mely sütiket fogadja el.
                    <a href="#" class="cookie-policy-link" onclick="window.CookieManager.openPolicy(); return false;">
                        További információ
                    </a>
                </p>
            </div>
            
            <div class="cookie-consent-actions">
                <button type="button" class="btn btn-link btn-sm" id="banner-cookie-settings-btn">
                    Beállítások
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="banner-cookie-necessary-btn">
                    Csak szükségesek
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="banner-cookie-accept-all-btn">
                    Összes elfogadása
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.cookie-consent-banner {
    position: fixed;
    left: 0;
    right: 0;
    z-index: 999;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    border-top: 1px solid #dee2e6;
    box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.1);
    animation: slideInUp 0.4s ease-out;
}

.cookie-consent-banner[data-position="bottom"] {
    bottom: 0;
}

.modal-drawer {
    z-index: 9999;
}

.cookie-consent-banner[data-position="top"] {
    top: 0;
    animation: slideInDown 0.4s ease-out;
    border-top: none;
    border-bottom: 1px solid #dee2e6;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
}

.cookie-consent-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
}

.cookie-consent-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.cookie-consent-text h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    color: #333;
}

.cookie-consent-text p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}

.cookie-policy-link {
    color: var(--primary, #007bff);
    text-decoration: underline;
    cursor: pointer;
}

.cookie-consent-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

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

@keyframes slideInUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideInDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (max-width: 768px) {
    .cookie-consent-content {
        flex-direction: column;
        text-align: center;
    }
    
    .cookie-consent-actions {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .cookie-consent-actions .btn {
        flex: 1;
        min-width: 120px;
    }
}
</style>
{{-- Simplified Banner JavaScript --}}
<script>
(function() {
    'use strict';
    
    const banner = document.getElementById('cookie-consent-banner');
    if (!banner) return;
    
    // Banner-specific button event listeners
    document.getElementById('banner-cookie-accept-all-btn')?.addEventListener('click', function() {
        acceptAllCookies();
    });
    
    document.getElementById('banner-cookie-necessary-btn')?.addEventListener('click', function() {
        acceptNecessaryCookies();
    });
    
    document.getElementById('banner-cookie-settings-btn')?.addEventListener('click', function() {
        // Use global cookie manager to open settings
        if (window.CookieManager) {
            window.CookieManager.openSettings();
        }
    });
    
    function acceptAllCookies() {
        sendConsent({
            necessary: true,
            analytics: true
        });
    }
    
    function acceptNecessaryCookies() {
        sendConsent({
            necessary: true,
            analytics: false
        });
    }
    
    function sendConsent(preferences) {
        fetch('/cookie-consent/store', {
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
                hideBanner();
                // Trigger custom event for other scripts
                window.dispatchEvent(new CustomEvent('cookieConsentGiven', {
                    detail: { consent: data.consent }
                }));
                
                // Show success message using global manager
                if (window.CookieManager) {
                    window.CookieManager.showMessage('Süti beállítások mentve!', 'success');
                }
            }
        })
        .catch(error => {
            console.error('Cookie consent error:', error);
            if (window.CookieManager) {
                window.CookieManager.showMessage('Hiba történt a mentés során!', 'error');
            }
        });
    }
    
    function hideBanner() {
        if (banner) {
            banner.style.animation = banner.dataset.position === 'top' 
                ? 'slideInDown 0.3s ease-out reverse'
                : 'slideInUp 0.3s ease-out reverse';
            
            setTimeout(() => {
                banner.remove();
            }, 300);
        }
    }
})();
</script>
@endif