{{-- resources/views/js/superadmin/ceorank-defaults.blade.php --}}
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin.ceoranks.css') }}">

<script>
// 🔥 CRITICAL: Enable global rank mode for superadmin
window.globalRankMode = true;

// Route mapping: admin routes → superadmin routes
// The admin modal JavaScript uses these admin routes, we intercept and redirect them
const ROUTE_MAP = {
    "{{ route('admin.ceoranks.get') }}": "{{ route('superadmin.ceorank-defaults.get') }}",
    "{{ route('admin.ceoranks.save') }}": "{{ route('superadmin.ceorank-defaults.save') }}",
    "{{ route('admin.ceoranks.remove') }}": "{{ route('superadmin.ceorank-defaults.remove') }}",
    "{{ route('admin.ceoranks.translations.get') }}": "{{ route('superadmin.ceorank-defaults.translations.get') }}",
    "{{ route('admin.ceoranks.translate-name') }}": "{{ route('superadmin.ceorank-defaults.translate-name') }}",
    "{{ route('admin.languages.available') }}": "{{ route('superadmin.ceorank-defaults.languages.get') }}",
    "{{ route('admin.languages.selected') }}": "{{ route('superadmin.ceorank-defaults.languages.get') }}",
};

// Override jQuery AJAX to intercept and redirect admin routes to superadmin routes
$(document).ready(function() {
    const originalAjax = $.ajax;
    
    $.ajax = function(options) {
        // Handle both $.ajax(url, settings) and $.ajax(settings) formats
        if (typeof options === 'string') {
            const url = options;
            options = arguments[1] || {};
            options.url = url;
        }
        
        // Redirect admin routes to superadmin routes
        if (options.url && ROUTE_MAP[options.url]) {
            console.log('Redirecting:', options.url, '→', ROUTE_MAP[options.url]);
            options.url = ROUTE_MAP[options.url];
        }
        
        return originalAjax.call(this, options);
    };
    
    // Preserve jQuery AJAX properties
    for (let prop in originalAjax) {
        if (originalAjax.hasOwnProperty(prop)) {
            $.ajax[prop] = originalAjax[prop];
        }
    }
    
    // Initialize tooltips
    tippy('[data-tippy-content]', {
        placement: 'top',
        arrow: true,
    });
    
    console.log('🔥 Superadmin CEO Rank Defaults: Global mode enabled');
    console.log('🔥 AJAX interceptor active');
    console.log('Route mappings:', ROUTE_MAP);
});

// Note: The modal functions (openCeoRankModal, etc.) are defined in admin/modals/ceorank.blade.php
// They will automatically use our redirected routes via the jQuery AJAX interceptor above.
</script>