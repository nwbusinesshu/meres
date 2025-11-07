<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Language Lines - English
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in email templates
    |
    */

    // Password Setup Email
    'password_setup' => [
        'subject' => 'Set Your Password – :org_name',
        'title' => 'Password Setup',
        'greeting' => 'Welcome to **:org_name**\'s 360 evaluation system!',
        'invitation' => 'You have been invited to the system with the email address **:email**.',
        'action_text' => 'To log in for the first time, click the button below and set your password:',
        'button' => 'Set Password',
        'expires' => 'This link is valid until **:expires_at**.',
        'ignore' => 'If you did not initiate this, please ignore this email.',
        'salutation' => 'Best regards,',
    ],

    // Password Reset Email
    'password_reset' => [
        'subject' => 'Password Reset – :org_name',
        'title' => 'Password Reset',
        'intro' => 'A password reset was requested for the **:email** account in the **:org_name** system.',
        'action_text' => 'Click the button below to set a new password:',
        'button' => 'Reset Password',
        'expires' => 'This link is valid until **:expires_at**.',
        'warning' => 'If you did not request this, please contact your company admin immediately.',
        'salutation' => 'Best regards,',
    ],

    // Email Verification Code
    'verification_code' => [
        'subject' => 'Login Verification Code',
        'greeting' => 'Dear :user_name!',
        'intro' => 'Your verification code for logging in:',
        'code_label' => 'Verification code:',
        'expires' => 'This code is valid for 10 minutes.',
        'warning' => 'If you did not attempt to log in, please ignore this email.',
        'salutation' => 'Best regards,',
        'team' => 'The Quarma360 Team',
    ],

    // Common elements
    'footer' => [
        'copyright' => '© :year :app_name. All rights reserved.',
    ],
];