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
        'open'    => 'Hurrá! Nincs nyitott tartozás.',
        'open-info' => 'Fizetésre váró tételeid majd itt fognak megjelenni!',
        'settled' => 'Nincs korábban rendezett tétel.',
        'info-settled' => 'Számláid itt fognak megjelenni. Ne felejtsd el beállítani a pontos számlázási adatokat! Kiállított számlán már nem tudunk módosítani!',
    ],

    'billing_data' => [
        'title'              => 'Számlázási adatok',
        'button'             => 'Számlázási adatok',
        'company_name'       => 'Cégnév',
        'tax_number'         => 'Adószám',
        'tax_number_hint'    => 'Pl.: 12345678-1-23',
        'eu_vat_number'      => 'EU ÁFA szám',
        'eu_vat_hint'        => 'Opcionális, pl.: HU12345678',
        'country_code'       => 'Ország',
        'postal_code'        => 'Irányítószám',
        'city'               => 'Város',
        'region'             => 'Megye/Régió',
        'street'             => 'Utca',
        'house_number'       => 'Házszám',
        'phone'              => 'Telefonszám',
        'load_error'         => 'A számlázási adatok betöltése sikertelen.',
        'save_success'       => 'Számlázási adatok sikeresen mentve.',
        'save_error'         => 'A számlázási adatok mentése sikertelen.',
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

        // Blocked payment notification
        'payment_blocked_title' => 'Fizetés folyamatban vagy megszakítva',
        'payment_blocked_text' => 'A fizetés nem fejeződött be. Kérjük, várjon 10 percet, mielőtt újra próbálkozna.',

        // NEW: Connecting to Barion overlay
        'connecting_barion_title' => 'Kapcsolódás a fizetési rendszerhez...',
        'connecting_barion_text' => 'Kérjük, várjon, amíg csatlakozunk a Barion fizetési rendszerhez.',
        'connecting_barion_wait' => 'Ez eltarthat néhány másodpercig. Ne zárja be ezt az ablakot!',

        // Redirect notification
        'redirecting_title' => 'Átirányítás...',
        'redirecting_text' => 'Átirányítjuk a Barion fizetési oldalára.',
        
        // Initial payment blocking modal
        'initial_payment_required_title' => 'Belépési díj rendezése szükséges',
        'initial_payment_required_text' => 'A rendszer használatához először rendezze a belépési díjat. Kérjük, indítsa el a fizetést az alábbi gombbal.',
        'understand' => 'Értem',

        // ✅ NEW translations for already paid detection
        'already_paid_title' => 'Fizetés már teljesítve',
        'already_paid_text' => 'Ez a fizetés már sikeresen teljesítve lett a Barion rendszerében.',
        
        // Make sure these exist:
        'payment_blocked_title' => 'Fizetés folyamatban',
        'payment_blocked_text' => 'Ehhez a tételhez már folyamatban van egy fizetés. Kérlek, várj néhány percet, vagy használd a frissítés gombot.',
        
        'connecting_barion_title' => 'Kapcsolódás a Barion-hoz...',
        'connecting_barion_text' => 'Fizetési tranzakció indítása folyamatban',
        'connecting_barion_wait' => 'Kérjük, ne zárja be az ablakot',
        
        'redirecting_title' => 'Átirányítás...',
        'redirecting_text' => 'Átirányítás a Barion fizetési oldalra',
        
        'paid_title' => 'Sikeres fizetés',
        'paid_text' => 'A fizetés sikeresen teljesült.',
        
        'failed_title' => 'Sikertelen fizetés',
        'failed_text' => 'A fizetés nem sikerült vagy megszakadt.',
        
        'start_unknown_title' => 'Ismeretlen válasz',
        'start_unknown_text' => 'Váratlan válasz érkezett. Kérjük, próbálja újra.',
        
        'start_fail_title' => 'Fizetés indítása sikertelen',
        'start_fail_text' => 'Nem sikerült elindítani a fizetést. Kérjük, próbálja újra.',
    ],

    'trial' => [
        'active_title' => 'Próbaidőszak aktív',
        'active_message' => 'Jelenleg az :days napos próbaidőszakban vagy. A rendszer teljes funkcionalitása elérhető, kivéve az értékelési időszak indítását.',
        'days_remaining' => ':days nap van hátra',
        'hours_remaining' => ':hours óra van hátra',
        'expired_title' => 'Próbaidőszak lejárt',
        'expired_message' => 'A próbaidőszak lejárt. A rendszer használatához kérjük, rendezze a belépési díjat.',
        'pay_now' => 'Fizetés indítása most',
        'assessment_blocked' => 'Értékelési időszak nem indítható a belépési díj rendezése előtt.',
    ],

    // Update the initial payment blocking modal text
    'initial_payment_required_text' => 'A rendszer használatához először rendezze a belépési díjat. 5 napos próbaidőszak alatt hozzáadhat alkalmazottakat és beállíthatja a rendszert, de értékelést nem indíthat.',

];