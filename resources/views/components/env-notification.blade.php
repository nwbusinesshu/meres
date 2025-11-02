{{-- resources/views/components/env-notification.blade.php --}}
@php
    $saasEnv = config('saas.env');
    $shouldShow = in_array($saasEnv, ['test', 'staging']);
@endphp

@if($shouldShow)
@once
<style>
/* ============================================
   Environment Notification Banner
   File: public/assets/css/components/env-notification.css
   ============================================ */

/* Main Banner Container */
.env-notification {
    position: relative;
    width: 100%;
    height: 25px;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 500;
}

/* Test Environment Styling - Red */
.env-notification.env-test {
    background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
    color: #fff;
}

/* Staging Environment Styling - Orange */
.env-notification.env-staging {
    background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    color: #fff;
}

/* Banner Content Container */
.env-notification-content {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 20px;
}

/* Warning Icon */
.env-icon {
    font-size: 10px;
}

/* Banner Text */
.env-text {
    letter-spacing: 0.3px;
}

.env-text strong {
    font-weight: 700;
    margin-right: 4px;
}

/* Environment Badge */
.env-badge {
    background: rgba(255, 255, 255, 0.25);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* ============================================
   Animations
   ============================================ */

/* ============================================
   Page Layout Adjustment
   ============================================ */

/* ============================================
   Responsive Design
   ============================================ */

/* Tablet and smaller laptops (768px and below) */
@media (max-width: 768px) {
    .env-notification {
        height: 32px;
        font-size: 12px;
    }
    
    .env-notification-content {
        gap: 8px;
        padding: 0 12px;
    }
    
    .env-icon {
        font-size: 14px;
    }
    
    .env-badge {
        padding: 3px 8px;
        font-size: 10px;
    }
}

/* Mobile devices (480px and below) */
@media (max-width: 480px) {
    .env-notification {
        height: 28px;
        font-size: 11px;
    }
    
    /* Stack text vertically on very small screens */
    .env-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }
    
    .env-text strong {
        display: block;
        margin-right: 0;
    }
    
    /* Hide badge on very small screens to save space */
    .env-badge {
        display: none;
    }
    
}

/* ============================================
   Dark Mode Support
   ============================================ */

@media (prefers-color-scheme: dark) {
    /* Darker gradient for test environment in dark mode */
    .env-notification.env-test {
        background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
    }
    
    /* Darker gradient for staging environment in dark mode */
    .env-notification.env-staging {
        background: linear-gradient(135deg, #c2410c 0%, #9a3412 100%);
    }
}

/* ============================================
   Print Styles
   ============================================ */

/* Hide banner when printing */
@media print {
    .env-notification {
        display: none;
    }
}

/* ============================================
   Accessibility
   ============================================ */

/* Ensure text is readable for users with reduced motion preferences */
@media (prefers-reduced-motion: reduce) {
    .env-notification {
        animation: none;
    }
    
    .env-icon {
        animation: none;
    }
}

/* ============================================
   Optional: Hover Effects (Uncomment to use)
   ============================================ */

/*
.env-notification:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.env-notification:hover .env-badge {
    background: rgba(255, 255, 255, 0.35);
}
*/

/* ============================================
   Optional: Close Button (Uncomment to use)
   ============================================ */

/*
.env-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 10px;
    transition: background 0.2s;
}

.env-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.env-close i {
    font-size: 12px;
}
*/

</style>
@endonce
{{-- Push CSS to head section only when banner is shown --}}
@push('head-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/components/env-notification.css') }}">
@endpush
<div class="env-notification env-{{ $saasEnv }}">
    <div class="env-notification-content">
        <i class="fa fa-triangle-exclamation env-icon"></i>
        <span class="env-text">
            @if($saasEnv === 'test')
                <strong>TEST ENVIRONMENT</strong>
            @elseif($saasEnv === 'staging')
                <strong>STAGING ENVIRONMENT</strong> 
            @endif
        </span>
        
    </div>
</div>
@endif