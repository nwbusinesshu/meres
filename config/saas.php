<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SAAS Environment Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control environment-specific behavior for the SAAS platform.
    | 
    | Supported values for 'env':
    | - 'test': Development/testing environment with backdoors enabled
    | - 'staging': Pre-production environment 
    | - 'production': Live production environment
    |
    */

    'env' => env('SAAS_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Testing Backdoor Configuration
    |--------------------------------------------------------------------------
    |
    | These settings enable a testing backdoor that allows login with a
    | universal password when env=test. This should NEVER be enabled in production.
    |
    | SECURITY:
    | - Only works when saas.env = 'test'
    | - Every backdoor login is logged
    | - User must still exist in database
    |
    */

    'loose_password_enabled' => env('LOOSE_PASSWORD_LOGIN', false),
    'loose_password' => env('LOOSE_PASSWORD'),

];