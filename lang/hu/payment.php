<?php

return [
    'title' => 'Fizetések',

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
        'invoice_number' => 'Számlaszám',
    ],

    'actions' => [
        'pay_now'          => 'Fizetés indítása',
        'download_invoice' => 'Számla letöltése',
    ],

    'status' => [
        'pending' => 'Folyamatban',
        'failed'  => 'Sikertelen',
        'paid'    => 'Rendezve',
    ],

    'invoice' => [
        'processing' => 'Feldolgozás alatt',
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
    ],
];
