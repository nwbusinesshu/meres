<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | SECURITY: Restrict CORS to specific domains only.
    | Use CORS_ALLOWED_ORIGINS in .env as a comma-separated list of domains.
    | Example: CORS_ALLOWED_ORIGINS="https://yourdomain.com,https://app.yourdomain.com"
    |
    */
    'allowed_origins' => array_filter(
        array_map(
            'trim',
            explode(',', env('CORS_ALLOWED_ORIGINS', env('APP_URL', 'http://localhost')))
        ),
        function($origin) {
            return !empty($origin);
        }
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];