<?php

return [
    'settings' => [
        // Menü és főcímek
        'menu' => 'Beállítások',
        'title' => 'Beállítások',
        'section_ai_privacy' => 'AI & adatvédelem',
        'section_program'    => 'Programbeállítások',

        // Szigorú anon
        'strict' => [
            'title' => 'Szigorú anonim mód',
            'meta_html' => 'Szigorú anonim módban az adatbázisban még analitikai célból sem kerül tárolásra az értékelést beküldő felhasználó azonosítója. Figyelem: a szigorú anonim mód bekapcsolása az AI telemetriát is kikapcsolja. Az adatrögzítési mód bármikor visszaállítható, de korábbi mérések adatai hiányosak lesznek. A viselkedési minták és esetleges csalások nem lesznek felismerve és kiszűrve.',
        ],

        // AI telemetria
        'ai' => [
            'title' => 'NWB THEMIS AI Engine',
            'meta_html' => 'NWB Fejlett intelligencia - viselkedési minták alapján súlyozzuk a kitöltéseket és igyekszünk kiszűrni a csalásokat. A felhasználók személyes adatait a modell nem dolgozza fel, kizárólag anonim viselkedési és tartalmi minták alapján határozza meg a kitöltés megbízhatóságát. A funkció hosszútávon javítja a mérési eredményeket és megtanulja az egyes alkalmazottak viselkedését.',
        ],

        // Multi-level részlegkezelés
        'multi_level' => [
            'title' => 'Multi-level részlegkezelés',
            'description' => 'A részlegvezetői (manager) szint bekapcsolása után a felhasználók részleg(ek)be sorolhatók, és a vezetők a saját részlegük beosztottait rangsorolhatják.<br><strong>Visszavonhatatlan:</strong> a bekapcsolás után nem lehet kikapcsolni.',
            'enabled_alert' => 'A Többszintű részlegkezelés <strong>be van kapcsolva</strong>, és <u>nem kapcsolható ki</u>.',
        ],

        // Bonus/Malus megjelenítés
        'bonus_malus' => [
            'title' => 'Jutalmazási bónuszrendszer',
            'description' => 'A Bonus/Malus besorolások megjelenítésének ki- és bekapcsolása a felhasználói felületen. Kikapcsolás esetén a számítások továbbra is működnek, de a kategóriák nem jelennek meg az alkalmazott listában és a kapcsolódó szerkesztési lehetőségek sem lesznek elérhetők.',
        ],

        // Kapcsolatok egyszerűsített beállítása
        'easy_relations' => [
            'title' => 'Kapcsolatok egyszerűsített beállítása',
            'description' => 'Ha be van kapcsolva, a kapcsolatok kétirányúan állítódnak be automatikusan.<br><strong>Alárendelt → Kolléga:</strong> Ha X beosztottként értékeli Y-t, akkor Y automatikusan kollégaként értékeli X-et.<br><strong>Kolléga → Kolléga:</strong> Ha X kollégaként értékeli Y-t, akkor Y is kollégaként értékeli X-et.<br>Ütközés esetén a rendszer figyelmeztetést ad, és lehetőséget biztosít a javításra.',
        ],

        // 2FA kényszerítés OAuth bejelentkezésnél
        'oauth_2fa' => [
            'title' => '2FA kényszerítés OAuth bejelentkezésnél',
            'description' => 'Ha be van kapcsolva, a Google és Microsoft OAuth bejelentkezéseknél is kötelező a kétfaktoros azonosítás (email ellenőrző kód).<br><strong>Kikapcsolva (alapértelmezett):</strong> OAuth felhasználók közvetlenül bejelentkeznek 2FA nélkül, mivel a Google/Microsoft már biztosít erős hitelesítést.<br><strong>Bekapcsolva:</strong> Minden felhasználónak email kóddal kell megerősítenie a bejelentkezést, függetlenül a belépési módtól.<br><em>Ajánlott csak nagyon bizalmas adatokat kezelő szervezeteknek.</em>',
        ],

        // Scoring rész alcím
        'scoring_subtitle' => 'Módszertani beállítások',

        // Módválasztó doboz
        'mode' => [
            'title' => 'Pontozási módszer',
            'meta'  => 'Válaszd ki, hogyan határozzuk meg az értékelések ponthatárait. A korábbi, lezárt értékelési időszakokat nem befolyásolja.',
            'options' => [
                'fixed'     => 'Fixpontos',
                'hybrid'    => 'Hybrid',
                'dynamic'   => 'Dynamic',
                'suggested' => 'Fejlett intelligencia',
            ],
        ],

        // FIXED panel
        'fixed' => [
            'title' => 'Fixpontos (alsó és felső ponthatár)',
            'meta_html' => 'Két állandó határt használunk: aki a <strong>felső határ fölé</strong> kerül, előrelép; aki az <strong>alsó határ alá</strong> esik, visszajelzést vagy visszaminősítést kap.',
            'description_html' => 'Egyszerű és átlátható módszer. Előre megadsz egy alsó és egy felső határt: aki a felső fölé teljesít, előrelép; aki az alsó alá esik, fejlesztési tervet kap.',
            'pros' => [
                'Könnyen kommunikálható és érthető.',
                'Stabil mérce: mindig ugyanazok a határok.',
                'Jó alapbeállítás kisebb, standardizált csapatoknál.',
            ],
            'cons' => [
                'Nem követi a csapat szintjének ingadozását.',
                'Ha a mezőny szintje eltolódik, a fix számok elavulhatnak.',
            ],
            'when' => 'Használd akkor, ha állandó mércét akarsz (pl. gyártás, erősen standard folyamatok).',
            'fields' => [
                'normal_level_up'   => 'Felső határ',
                'normal_level_down' => 'Alsó határ',
            ],
        ],

        // HYBRID panel
        'hybrid' => [
            'title' => 'Hybrid (alsó fix + felső top%)',
            'meta'  => 'Előrelép az, aki átlép egy fix pontszámot ÉS benne van a legjobb X%-ban.',
            'description_html' => 'Az alsó határ fix pontszám marad (pl. 70), de az előléptetést kiegészítjük a csapat felső X%-ával (pl. top 20%). Ha valaki jól teljesített, de a csapat is erős, csak a "legjobb húszba" kerülők lépnek előre.',
            'pros' => [
                'Megőrzi a szelektivitást erős csapatoknál.',
                'Biztos alsó határ van a gyenge teljesítés szűrésére.',
                'Jó középút a fix és dinamikus módszer között.',
            ],
            'cons' => [
                'Bonyolultabb kommunikálni, mint a fixet.',
                'Ha a csapat egyenletesen teljesít, sok ember esik ki az előléptetésből.',
            ],
            'when' => 'Akkor ideális, ha nagy létszámú csapatod van, de szeretnél biztos "alsó biztosítékot" (pl. 500+ fő).',
            'fields' => [
                'normal_level_down' => 'Alsó határ (fix)',
                'threshold_min_abs_up' => 'Előléptetés abszolút min.',
                'threshold_top_pct' => 'Felső határ (top %)',
            ],
        ],

        // DYNAMIC panel
        'dynamic' => [
            'title' => 'Dynamic (alsó bottom% + felső top%)',
            'meta'  => 'Az alsó és felső határ is a csapat aktuális teljesítményéhez igazodik.',
            'description_html' => 'A legdinamikusabb módszer: minden mérés után újraszámoljuk, hogy ki tartozik a legjobb X%-ba (előrelép) és ki a leggyengébb Y%-ba (visszajelzés). Ez biztosítja, hogy mindig van előrelépő és mindig van visszaesésre jelölt – a határok "mozognak" a csapat szintjével.',
            'pros' => [
                'Mindig van előrelépő és figyelmeztető jel, függetlenül a csapat szintjétől.',
                'Folyamatosan ösztönöz a versenyhelyzetre.',
                'Jól követi a csapat változásait (pl. szezonális ingadozás).',
            ],
            'cons' => [
                'Ha a csapat összességében gyengül, a határok is lejjebb mennek.',
                'Inflálja a pontokat: a csapat nem feltétlenül lesz jobb, csak a rangsorolás marad.',
            ],
            'when' => 'Nagy, dinamikus csapatoknál, ahol fontos a rangsorolás, de a csapat szintje gyorsan változik.',
            'fields' => [
                'threshold_bottom_pct' => 'Alsó határ (bottom %)',
                'threshold_top_pct' => 'Felső határ (top %)',
            ],
        ],

        // SUGGESTED panel
        'suggested' => [
            'title' => 'Fejlett intelligencia (AI által javasolt döntés)',
            'meta' => 'Az AI elemzi a csapat teljesítményét, szórását, előzményeit, és javaslatot tesz az előléptetésre/lefokozásra.',
            'description_html' => 'Az AI átveszi a döntéshozatalt: figyelembe veszi a csapat szórását, előzményeit, egyéni teljesítményt, és meghatározza a küszöböket ÉS konkrét személyeket javasol előléptetésre vagy lefokozásra. Te csak elfogadod vagy módosítod a javaslatot. Az AI tanul minden zárt ciklusból, így egyre pontosabbá válik. Bekapcsolásához szükséges: legalább 1 lezárt mérés + AI telemetria bekapcsolva.',
            'pros' => [
                'Teljes automatizáció: az AI megteszi a nehéz döntéseket.',
                'Figyelembe veszi a kontextust (szórás, előzmények, stb.).',
                'Folyamatosan tanul és fejlődik minden ciklusból.',
            ],
            'cons' => [
                'A döntések nehezen átláthatók (black box).',
                'Erős bizalom kell az AI-ban, mert az ember csak validál.',
                'Ha rossz adatokat tanul, rossz javaslatokat ad.',
            ],
            'when' => 'Nagy, összetett szervezeteknél, ahol szeretnéd automatizálni az előléptetési döntéseket, és már van elég adat.',
            'advanced_settings' => 'Haladó beállítások',
            'fields' => [
                'target_promo_rate_max_pct' => 'Max. előléptetési ráta (%): Az AI legfeljebb a csapat hány százalékát engedheti feljebb egy mérésben. Megakadályozza, hogy az AI „túl sok" embert engedjen egyszerre előléptetni, így megmarad a szűrő szerepe.',
                'target_demotion_rate_max_pct' => 'Max. lefokozási ráta (%): Az AI legfeljebb a csapat hány százalékát ejtheti vissza. Nem fordulhat elő, hogy egy rossz mérés miatt hirtelen a fél csapat visszaesik.',
                'never_below_abs_min_for_promo' => 'Előléptetés absz. minimum (0–100, üres = nincs): Bármilyen gyengén is teljesít a csapat, az AI soha nem teheti az előléptetés határát ennél alacsonyabb pontra.',
                'use_telemetry_trust' => 'Telemetria alapú súlyozás: Az AI figyelembe veszi a kitöltések megbízhatósági pontszámát.',
                'no_forced_demotion_if_high_cohesion' => 'Magas kohézió esetén nincs kényszerített lefokozás: Ha a csapat szorosan együtt teljesít, az AI nem fog erőltetni lefokozást.',
            ],
        ],

        // Gombok
        'buttons' => [
            'save_settings' => 'Beállítások mentése',
        ],

        // JavaScript üzenetek
        'confirm' => 'Megerősítés szükséges',
        'warn_strict_on' => 'Biztosan bekapcsolod a szigorú anonim módot? Ez az AI telemetriát is kikapcsolja.',
        'warn_ai_on' => 'Biztosan bekapcsolod az AI telemetriát?',
        'warn_ai_off' => 'Biztosan kikapcsolod az AI telemetriát? A viselkedési minták elemzése leáll.',
        'warn_multi_on' => 'Biztosan bekapcsolod a Többszintű részlegkezelést? A döntés végleges, később nem kapcsolható ki. Mielőtt bekapcsolod, tájékozódj a következményeiről a dokumentációban!',
        'warn_bonus_malus_off' => 'Biztosan elrejted a Bonus/Malus kategóriákat? A besorolások továbbra is számolódnak, de nem lesznek láthatók a felhasználói felületen.',
        'warn_bonus_malus_on' => 'Biztosan megjeleníted a Bonus/Malus kategóriákat a felhasználói felületen?',
        'warn_easy_relation_off' => 'Biztosan kikapcsolod az egyszerűsített kapcsolatbeállítást? Ezután a kapcsolatokat manuálisan kell beállítani mindkét irányban.',
        'warn_easy_relation_on' => 'Biztosan bekapcsolod az egyszerűsített kapcsolatbeállítást? A kapcsolatok automatikusan kétirányúan állítódnak be.',
        'warn_force_oauth_2fa_on' => 'Biztosan bekapcsolod a 2FA kényszerítést OAuth belépéseknél? A Google és Microsoft bejelentkezéseknél is email ellenőrző kódot kell majd megadni.',
        'warn_force_oauth_2fa_off' => 'Biztosan kikapcsolod a 2FA kényszerítést OAuth belépéseknél? A Google és Microsoft bejelentkezések 2FA nélkül történnek majd.',
        'saved' => 'Beállítások elmentve!',
        'error' => 'Hiba',
    

'api_subtitle' => 'API kapcsolat',
'api_title' => 'API kulcs',
'api_description' => 'Az API kulcs segítségével harmadik féltől származó rendszerek (például ERP, HR szoftverek) kapcsolódhatnak a Quarma360 rendszerhez és exportálhatják a szervezet adatait.',
'api_important' => 'Fontos',
'api_important_text' => 'Az API kulcs csak egyszer jelenik meg teljes egészében. Utána csak az utolsó 8 karaktert láthatja.',

// Status messages
'api_loading' => 'Betöltés...',
'api_no_key' => 'Még nincs létrehozott API kulcs.',
'api_key_last_chars' => 'API kulcs (utolsó 8 karakter):',

// Badges
'api_badge_active' => 'Aktív',
'api_badge_revoked' => 'Visszavonva',

// Metadata labels
'api_meta_name' => 'Név',
'api_meta_created' => 'Létrehozva',
'api_meta_created_by' => 'Létrehozta',
'api_meta_last_used' => 'Utolsó használat',
'api_meta_requests_24h' => 'Hívások (24h)',
'api_meta_never_used' => 'Még nem használt',

// Buttons
'api_btn_generate' => 'Új API kulcs létrehozása',
'api_btn_revoke' => 'Kulcs visszavonása',
'api_btn_copy' => 'Másolás',
'api_btn_copied' => 'Másolva!',

// Modal - Generate
'api_modal_generate_title' => 'Új API kulcs létrehozása',
'api_modal_generate_name_label' => 'Kulcs neve:',
'api_modal_generate_name_placeholder' => 'pl. ERP integráció',
'api_modal_generate_name_help' => 'Adj egy beszédes nevet a kulcsnak, hogy könnyebben azonosíthasd.',
'api_modal_generate_confirm' => 'Létrehozás',

// Modal - Display Key (one-time)
'api_modal_display_title' => '🔑 Új API kulcs létrehozva',
'api_modal_display_warning' => '⚠️ Figyelem!',
'api_modal_display_warning_text' => 'Ez az API kulcs csak most jelenik meg egyszer. Másold ki és tárold biztonságosan, mert később nem érhető el újra!',
'api_modal_display_key_label' => 'API kulcs:',
'api_modal_display_usage_hint' => 'A kulcs használatához minden API híváshoz add hozzá a következő headert:',
'api_modal_display_close' => 'Rendben, elmentettem',

// Modal - Revoke
'api_modal_revoke_title' => 'API kulcs visszavonása',
'api_modal_revoke_text' => 'Biztosan vissza akarod vonni ezt az API kulcsot? Ez a művelet nem visszavonható!',
'api_modal_revoke_confirm' => 'Igen, visszavonás',

// Validation messages
'api_validation_name_required' => 'Add meg a kulcs nevét!',
'api_validation_name_too_short' => 'A név legalább 3 karakter hosszú legyen!',
'api_validation_name_invalid' => 'Az API kulcs neve csak betűket, számokat, szóközöket, kötőjeleket és aláhúzásjeleket tartalmazhat.',

// Success messages
'api_generate_success' => 'API kulcs sikeresen létrehozva!',
'api_revoke_success' => 'API kulcs sikeresen visszavonva!',
'api_copy_success' => 'API kulcs vágólapra másolva!',

// Error messages
'api_generate_error' => 'Hiba történt az API kulcs létrehozása során.',
'api_revoke_error' => 'Hiba történt az API kulcs visszavonása során.',
'api_load_error' => 'Hiba történt az API kulcsok betöltése során.',
'api_already_exists' => 'Már létezik aktív API kulcs. Először vissza kell vonni a meglévőt.',
'api_not_found' => 'Az API kulcs nem található.',
'api_already_revoked' => 'Az API kulcs már vissza lett vonva.',
'api_revoke_failed' => 'Nem sikerült visszavonni az API kulcsot.',

// Loading states
'api_generating' => 'API kulcs létrehozása...',
'api_revoking' => 'API kulcs visszavonása...',
],

];