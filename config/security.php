<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login Attempt Settings
    |--------------------------------------------------------------------------
    |
    | Configure login attempt limits and lockout behavior
    |
    */

    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 30),
        'decay_minutes' => (int) env('LOGIN_DECAY_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy (CSP)
    |--------------------------------------------------------------------------
    |
    | Configure CSP headers for XSS protection
    |
    */

    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'report_only' => env('CSP_REPORT_ONLY', false),
        'report_uri' => env('CSP_REPORT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Security
    |--------------------------------------------------------------------------
    |
    | Configure webhook rate limiting and IP whitelisting
    |
    */

    'webhook' => [
        'rate_limit' => (int) env('WEBHOOK_RATE_LIMIT', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Barion Webhook IP Whitelisting
    |--------------------------------------------------------------------------
    |
    | Configure IP address restrictions for Barion payment webhooks
    |
    */

    'barion_webhook' => [
        'ip_check_enabled' => env('BARION_WEBHOOK_IP_CHECK_ENABLED', true),
        'allow_local' => env('BARION_WEBHOOK_ALLOW_LOCAL', false),
        'ips_production' => env('BARION_WEBHOOK_IPS_PRODUCTION', ''),
        'ips_sandbox' => env('BARION_WEBHOOK_IPS_SANDBOX', ''),
    ],

];