<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cookie Consent Version
    |--------------------------------------------------------------------------
    |
    | This version is used to track changes in cookie policy.
    | When you update your cookie policy, increment this version to
    | force users to re-consent.
    |
    */
    'version' => env('COOKIE_CONSENT_VERSION', '1.0'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Age (in days)
    |--------------------------------------------------------------------------
    |
    | How long the consent is valid. After this period, users will be
    | asked to consent again.
    |
    */
    'max_age_days' => env('COOKIE_CONSENT_MAX_AGE', 365),

    /*
    |--------------------------------------------------------------------------
    | Cookie Categories
    |--------------------------------------------------------------------------
    |
    | Define the different types of cookies your application uses.
    | Each category should have a name, description, and whether it's required.
    |
    */
    'categories' => [
        'necessary' => [
            'name' => 'Szükséges sütik',
            'description' => 'Ezek a sütik elengedhetetlenek a weboldal megfelelő működéséhez és nem kapcsolhatók ki.',
            'required' => true,
            'cookies' => [
                'session cookie' => 'Munkamenet azonosító a bejelentkezéshez',
                'CSRF token' => 'Biztonsági token a támadások ellen',
                'auth cookie' => 'Bejelentkezési állapot megőrzése',
                'cookie_consent' => 'Süti beállítások tárolása',
            ],
        ],
        'analytics' => [
            'name' => 'Analitikai sütik',
            'description' => 'Ezek a sütik segítenek megérteni, hogyan használják a látogatók a weboldalt. Névtelen statisztikák készítéséhez használjuk.',
            'required' => false,
            'cookies' => [
                'telemetry' => 'Felhasználói viselkedés nyomon követése',
                'usage_stats' => 'Oldal használati statisztikák',
                'performance_data' => 'Oldal teljesítmény mérése',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Banner Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the appearance and behavior of the cookie consent banner.
    |
    */
    'banner' => [
        'position' => env('COOKIE_BANNER_POSITION', 'bottom'), // 'top' or 'bottom'
        'animation' => env('COOKIE_BANNER_ANIMATION', 'slide'), // 'slide', 'fade', 'none'
        'auto_hide' => env('COOKIE_BANNER_AUTO_HIDE', false),
        'auto_hide_delay' => env('COOKIE_BANNER_AUTO_HIDE_DELAY', 5000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance Settings
    |--------------------------------------------------------------------------
    |
    | Configure GDPR compliance settings.
    |
    */
    'compliance' => [
        'require_consent_for_analytics' => env('COOKIE_REQUIRE_ANALYTICS_CONSENT', true),
        'log_all_consents' => env('COOKIE_LOG_ALL_CONSENTS', true),
        'data_retention_days' => env('COOKIE_DATA_RETENTION_DAYS', 1095), // 3 years
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookie Settings
    |--------------------------------------------------------------------------
    |
    | Default cookie settings for the consent cookie itself.
    |
    */
    'cookie' => [
        'name' => env('COOKIE_CONSENT_NAME', 'cookie_consent'),
        'lifetime' => env('COOKIE_CONSENT_LIFETIME', 365), // days
        'secure' => env('COOKIE_CONSENT_SECURE', true),
        'http_only' => env('COOKIE_CONSENT_HTTP_ONLY', false), // false so JS can read it
        'same_site' => env('COOKIE_CONSENT_SAME_SITE', 'lax'),
    ],
];