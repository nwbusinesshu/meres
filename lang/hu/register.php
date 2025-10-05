<?php

return [

    // Flow titles
    'flow_title' => 'Regisztráljon egy admin felhasználót!',
    'flow_subtitle' => 'Az admin hozzáfér mindhez: munkavállalók, értékelések, beállítások. Adja meg az admin nevét és e-mail címét.',

    // Step titles
    'step1_title' => 'Admin felhasználó',
    'step2_title' => 'Cég és számlázási adatok',
    'step3_title' => 'Alapbeállítások',
    'step4_title' => 'Összegzés',

    // Step 1 - Admin user
    'admin' => [
        'name' => 'Név',
        'email' => 'E-mail',
        'employee_limit' => 'Munkavállalók száma',
        'employee_limit_placeholder' => 'pl. 50',
    ],

    // Step 2 - Company and billing
    'company' => [
        'name' => 'Cégnév',
        'country' => 'Ország',
        'postal_code' => 'Irányítószám',
        'region' => 'Megye/Régió',
        'city' => 'Város',
        'street' => 'Közterület',
        'house_number' => 'Házszám',
        'phone' => 'Telefonszám',
        'phone_placeholder' => '+36…',
        'tax_number' => 'Adószám',
        'tax_number_placeholder' => 'pl. 12345678-1-12',
        'eu_vat' => 'EU VAT',
        'eu_vat_placeholder' => 'pl. DE123456789',
    ],

    // Countries
    'countries' => [
        'HU' => 'Magyarország (HU)',
        'AT' => 'Ausztria (AT)',
        'BE' => 'Belgium (BE)',
        'BG' => 'Bulgária (BG)',
        'HR' => 'Horvátország (HR)',
        'CY' => 'Ciprus (CY)',
        'CZ' => 'Csehország (CZ)',
        'DK' => 'Dánia (DK)',
        'EE' => 'Észtország (EE)',
        'FI' => 'Finnország (FI)',
        'FR' => 'Franciaország (FR)',
        'DE' => 'Németország (DE)',
        'GR' => 'Görögország (GR)',
        'IE' => 'Írország (IE)',
        'IT' => 'Olaszország (IT)',
        'LV' => 'Lettország (LV)',
        'LT' => 'Litvánia (LT)',
        'LU' => 'Luxemburg (LU)',
        'MT' => 'Málta (MT)',
        'NL' => 'Hollandia (NL)',
        'PL' => 'Lengyelország (PL)',
        'PT' => 'Portugália (PT)',
        'RO' => 'Románia (RO)',
        'SK' => 'Szlovákia (SK)',
        'SI' => 'Szlovénia (SI)',
        'ES' => 'Spanyolország (ES)',
        'SE' => 'Svédország (SE)',
    ],

    // Step 3 - Settings
    'settings' => [
        'ai_telemetry_title' => 'AI telemetria',
        'ai_telemetry_description' => 'Telemetria és AI segédfunkciók bekapcsolása. (Később módosítható.)',
        'multi_level_title' => 'Multi-level részlegkezelés',
        'multi_level_description' => 'Részlegek és vezetői szintek bekapcsolása. <strong>Visszavonhatatlan:</strong> később nem kapcsolható ki.',
        'bonus_malus_title' => 'Bonus/Malus megjelenítés',
        'bonus_malus_description' => 'A besorolások láthatóságának kapcsolója a felületen.',
    ],

    // Buttons
    'buttons' => [
        'next' => 'Tovább',
        'back' => 'Vissza',
        'finalize' => 'Véglegesítés',
    ],

    // Footer
    'footer' => [
        'already_have_account' => 'Már van fiókja?',
        'login' => 'Bejelentkezés',
    ],

    'validation' => [
        'required' => 'Ez a mező kötelező.',
        'invalid_email' => 'Adj meg érvényes e-mail címet.',
        'employee_limit_min' => 'A munkavállalók száma legalább 1 legyen.',
        'tax_number_required' => 'Érvényes magyar adószám szükséges.',
        'eu_vat_format_hu' => 'Érvényes EU ÁFA szám formátum szükséges (pl. HU12345678).',
        'eu_vat_required' => 'Érvényes EU ÁFA szám szükséges (pl. DE123456789).',
        'network_error' => 'Hálózati hiba. Próbáld újra.',
        'server_error' => 'Szerverhiba történt.',
    ],

    'summary' => [
        'checkbox_on' => 'Be',
        'checkbox_off' => 'Ki',
        'admin' => 'Admin',
        'company_name' => 'Cégnév',
        'employee_count' => 'Munkavállalók száma',
        'employee_unit' => 'fő',
        'billing_address' => 'Számlázási cím',
        'phone' => 'Tel',
        'tax_identification' => 'Adóazonosítás',
        'tax_number' => 'Adószám',
        'eu_vat' => 'EU ÁFA',
        'settings' => 'Beállítások',
        'ai_telemetry' => 'AI telemetria',
        'multi_level' => 'Multi-level',
        'bonus_malus' => 'Bonus/Malus',
    ],

    'errors' => [
        'tax_number_invalid' => 'Érvénytelen vagy hiányzó adószám.',
        'tax_number_exists' => 'Ezzel az adószámmal már létezik szervezet.',
        'eu_vat_invalid_format' => 'Érvénytelen EU ÁFA-szám formátum.',
        'eu_vat_exists' => 'Ezzel az EU ÁFA-számmal már létezik szervezet.',
        'eu_vat_invalid_or_missing' => 'Érvénytelen vagy hiányzó EU ÁFA-szám.',
        'email_exists' => 'Ezzel az e-mail címmel már van aktív felhasználó.',
        'email_required' => 'E-mail szükséges.',
        'email_invalid' => 'Érvénytelen e-mail cím.',
        'email_in_use' => 'Ez az e-mail cím már használatban van.',
        'recaptcha_failed' => 'Kérjük, erősítsd meg, hogy nem vagy robot.',
    ],

    'success_message' => 'Sikeres regisztráció. Be tudsz lépni OAuth-tal, vagy állítsd be a jelszavad a kiküldött emailből.',

    'steps' => [
        'step1_title' => 'Regisztráljon egy admin felhasználót!',
        'step1_subtitle' => 'Az admin mindent kezel: felhasználók, értékelések, beállítások. Adja meg az admin nevét és e-mail címét.',
        'step2_title' => 'Adja meg a céges és számlázási adatokat',
        'step2_subtitle' => 'A számlázáshoz kérjük a címadatokat és adóazonosítót. EU-s ország esetén EU ÁFA-szám szükséges.',
        'step3_title' => 'Alapbeállítások',
        'step3_subtitle' => 'Határozza meg a rendszer működését: AI telemetria, multi-level értékelés, bónusz/málusz.',
        'step4_title' => 'Ellenőrizze az adatokat',
        'step4_subtitle' => 'Véglegesítés előtt győződjön meg, hogy minden adat helyes.',
    ],
    // JavaScript step flow texts
    'flow' => [
        'step0_title' => 'Regisztráljon egy admin felhasználót!',
        'step0_subtitle' => 'Az admin mindent kezel: felhasználók, értékelések, beállítások. Adja meg az admin nevét és e-mail címét.',
        'step1_title' => 'Adja meg a céges és számlázási adatokat',
        'step1_subtitle' => 'A számlázáshoz kérjük a címadatokat és adóazonosítót. EU-s ország esetén EU ÁFA-szám szükséges.',
        'step2_title' => 'Válassza ki az alapbeállításokat',
        'step2_subtitle' => 'AI telemetria, multi-level részlegkezelés és Bonus/Malus megjelenítés. Ezek később módosíthatók (a multi-level nem).',
        'step3_title' => 'Ellenőrizze és véglegesítse',
        'step3_subtitle' => 'Nézze át az összegzést. A befejezés után e-mailben kap linket a jelszó beállításához.',
    ],

];