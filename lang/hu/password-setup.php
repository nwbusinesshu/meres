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

    'title' => 'Új jelszó beállítása',
    'email' => 'E-mail',
    'new_password' => 'Új jelszó',
    'confirm_password' => 'Jelszó megerősítése',
    'submit' => 'Jelszó beállítása',
    'separator' => '— vagy —',
    'login_google' => 'Belépés Google-fiókkal',
    'login_microsoft' => 'Belépés Microsofttal',

    /*
    |--------------------------------------------------------------------------
    | Password Requirements
    |--------------------------------------------------------------------------
    */

    'requirements' => [
        'title' => 'A jelszónak tartalmaznia kell:',
        'min_length' => 'Legalább 12 karakter',
        'has_letter' => 'Legalább egy betű',
        'has_number' => 'Legalább egy szám',
        'not_common' => 'Ne legyen gyakori jelszó',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    'match_success' => '✓ A jelszavak megegyeznek',
    'match_fail' => '✗ A jelszavak nem egyeznek',
    'validation_alert' => 'Kérjük, teljesítsd az összes jelszó követelményt!',

    // Token validation
    'token_expired_or_invalid' => 'A link/token lejárt vagy nem létezik. Kérelem nem teljesíthető.',
    'link_invalid_or_expired' => 'A jelszó beállító link érvénytelen vagy lejárt.',
    
    // User validation
    'user_account_not_active' => 'A felhasználói fiók nem aktív.',
    
    // Success
    'password_set_success' => 'Jelszó beállítva, beléptél a rendszerbe.',
    

];