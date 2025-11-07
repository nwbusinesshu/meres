<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Password Setup Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used on the password setup page
    | where users create their initial password or reset their password.
    |
    */
    'title' => 'Set New Password',
    'email' => 'Email',
    'new_password' => 'New Password',
    'confirm_password' => 'Confirm Password',
    'submit' => 'Set Password',
    'separator' => '— or —',
    'login_google' => 'Sign in with Google',
    'login_microsoft' => 'Sign in with Microsoft',
    /*
    |--------------------------------------------------------------------------
    | Password Requirements
    |--------------------------------------------------------------------------
    */
    'requirements' => [
        'title' => 'Password must contain:',
        'min_length' => 'At least 12 characters',
        'has_letter' => 'At least one letter',
        'has_number' => 'At least one number',
        'not_common' => 'Must not be a common password',
    ],
    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */
    'match_success' => '✓ Passwords match',
    'match_fail' => '✗ Passwords do not match',
    'validation_alert' => 'Please meet all password requirements!',
    // Token validation
    'token_expired_or_invalid' => 'The link/token has expired or does not exist. Request cannot be completed.',
    'link_invalid_or_expired' => 'The password setup link is invalid or has expired.',
    
    // User validation
    'user_account_not_active' => 'The user account is not active.',
    
    // Success
    'password_set_success' => 'Password set, you have been logged into the system.',
    
];