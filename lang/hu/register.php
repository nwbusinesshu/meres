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
        'valid_tax_number_required' => 'Érvényes adószám szükséges.',
        'valid_eu_vat_required' => 'Érvényes EU VAT szám szükséges.',
        'valid_email_required' => 'Adj meg érvényes e-mail címet.',
        'network_error' => 'Hálózati hiba. Próbáld újra.',
        'unknown_error' => 'Ismeretlen hiba.',
    ],

    // JavaScript summary labels
    'summary' => [
        'admin' => 'Admin',
        'company_name' => 'Cégnév',
        'billing_address' => 'Számlázási cím',
        'phone_label' => 'Tel:',
        'tax_identification' => 'Adóazonosítás',
        'tax_number_label' => 'Adószám:',
        'eu_vat_label' => 'EU ÁFA:',
        'settings_label' => 'Beállítások',
        'ai_telemetry_label' => 'AI telemetria:',
        'multi_level_label' => 'Multi-level:',
        'bonus_malus_label' => 'Bonus/Malus:',
        'enabled' => 'Be',
        'disabled' => 'Ki',
        'not_provided' => '—',
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