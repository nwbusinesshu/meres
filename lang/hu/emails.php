<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Language Lines - Hungarian
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in email templates
    |
    */

    // Password Setup Email
    'password_setup' => [
        'subject' => 'Jelszó beállítása – :org_name',
        'title' => 'Jelszó beállítás',
        'greeting' => 'Üdvözlünk a **:org_name** szervezet 360 értékelési rendszerében!',
        'invitation' => 'Meghívást kaptál a rendszerbe a **:email** e-mail címmel.',
        'action_text' => 'Az első belépéshez kattints az alábbi gombra és állíts be jelszót:',
        'button' => 'Jelszó beállítása',
        'expires' => 'Ez a link **:expires_at** időpontig érvényes.',
        'ignore' => 'Ha nem te kezdeményezted, hagyd figyelmen kívül ezt a levelet.',
        'salutation' => 'Üdv,',
    ],

    // Password Reset Email
    'password_reset' => [
        'subject' => 'Jelszó visszaállítása – :org_name',
        'title' => 'Jelszó visszaállítás',
        'intro' => 'A(z) **:org_name** rendszerében jelszó visszaállítást kértünk a **:email** fiókhoz.',
        'action_text' => 'Kattints az alábbi gombra az új jelszó beállításához:',
        'button' => 'Jelszó visszaállítása',
        'expires' => 'Ez a link **:expires_at** időpontig érvényes.',
        'warning' => 'Ha nem te kérted, azonnal értesítsd a céges admin kapcsolattartód.',
        'salutation' => 'Üdv,',
    ],

    // Email Verification Code
    'verification_code' => [
        'subject' => 'Bejelentkezési ellenőrző kód',
        'greeting' => 'Kedves :user_name!',
        'intro' => 'A bejelentkezéshez szükséges ellenőrző kódod:',
        'code_label' => 'Ellenőrző kód:',
        'expires' => 'Ez a kód 10 percig érvényes.',
        'warning' => 'Ha nem te próbáltál meg bejelentkezni, kérjük, hagyd figyelmen kívül ezt az emailt.',
        'salutation' => 'Üdvözlettel,',
        'team' => 'A Quarma360 csapata',
    ],

    // Common elements
    'footer' => [
        'copyright' => '© :year :app_name. Minden jog fenntartva.',
    ],
];