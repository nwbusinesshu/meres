<?php

return [
    'title' => 'Számlázás',

    'sections' => [
        'open'    => 'Nyitott tartozások',
        'settled' => 'Korábban rendezettek',
    ],

    'columns' => [
        'created_date'   => 'Dátum',
        'due_date'       => 'Fizetési határidő',
        'amount'         => 'Összeg',
        'status'         => 'Státusz',
        'actions'        => 'Művelet',
        'issue_date'     => 'Kiállítás dátuma',
        'payment_date'   => 'Fizetés dátuma',
        'invoice_number' => 'Számla szám',
    ],

    'actions' => [
        'pay_now'          => 'Fizetés indítása',
        'download_invoice' => 'Számla letöltése',
    ],

    'status' => [
        'initial' => 'Belépő',
        'pending' => 'Folyamatban',
        'failed'  => 'Sikertelen',
        'paid'    => 'Rendezve',
    ],

    'invoice' => [
        'processing' => 'Feldolgozás alatt',
        'downloading' => 'Számla letöltése folyamatban',
    ],

    'empty' => [
        'open'    => 'Nincs nyitott tartozás.',
        'settled' => 'Nincs korábban rendezett tétel.',
    ],

    'swal' => [
        'paid_title'   => 'Sikeres fizetés',
        'paid_text'    => 'A fizetés rendezve, a számla kiállítva.',
        'failed_title' => 'Sikertelen fizetés',
        'failed_text'  => 'A fizetés nem jött létre.',

        'start_unknown_title' => 'Ismeretlen válasz',
        'start_unknown_text'  => 'A fizetés indítása nem egyértelmű választ adott.',
        'start_fail_title'    => 'Nem sikerült indítani',
        'start_fail_text'     => 'A fizetés indítása sikertelen.',

        // NEW: Blocked payment notification
        'payment_blocked_title' => 'Fizetés folyamatban vagy megszakítva',
        'payment_blocked_text' => 'A fizetés nem fejeződött be. Kérjük, várjon 10 percet, mielőtt újra próbálkozna.',

        // NEW: Redirect notification
        'redirecting_title' => 'Átirányítás...',
        'redirecting_text' => 'Azonnal átirányítjuk a Barion fizetési oldalára.',
        // NEW: Initial payment blocking modal
'initial_payment_required_title' => 'Belépési díj rendezése szükséges',
'initial_payment_required_text' => 'A rendszer használatához először rendezze a belépési díjat. Kérjük, indítsa el a fizetést az alábbi gombbal.',
'understand' => 'Értem',
    ],
];