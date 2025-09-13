<?php

return [
    'settings' => [
        // Menü és főcímek
        'menu' => 'Beállítások',
        'title' => 'Beállítások',
        'section_ai_privacy' => 'AI & adatvédelem',

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
    'description_html' => 'Kombinálja a minimum elvárást és a relatív teljesítményt. Így nem elég „mezőnyhöz képest jónak” lenni, kell egy alapminőség is.',
    'pros' => [
        'Minőségbiztosítás + kiemelkedők jutalmazása egyszerre.',
        'Megakadályozza, hogy gyenge mezőnyben „automatikus” előrelépések legyenek.',
    ],
    'cons' => [
        'Összetettebb kommunikáció a csapat felé.',
        'Két paramétert kell karbantartani (minimum pont + top%).',
    ],
    'when' => 'Akkor ideális, ha fontos az alapminőség és a legjobbak kiemelése (pl. értékesítés, ügyfélszolgálat).',
    'fields' => [
        'threshold_min_abs_up' => 'Minimum pontszám (0–100)',
        'threshold_top_pct'    => 'Előrelépők felső határa (%)',
    ],
],

// DYNAMIC panel
'dynamic' => [
    'title' => 'Dynamic (relatív sávok)',
    'meta'  => 'Az alsó Y% fejlesztési tervet kap, a felső X% előrelép; a középső zóna stagnál.',
    'description_html' => 'Mindenki egymáshoz képest kerül elosztásra, a sávok a mezőnyhöz igazodnak. Akkor hasznos, ha a csapat teljesítménye együtt mozog.',
    'pros' => [
        'Automatikusan követi a csapat szintjét.',
        'Motiváló, versenyhelyzetet teremt.',
    ],
    'cons' => [
        'Mindig lesz „nyertes” és „vesztes”, még jó összteljesítmény mellett is.',
        'Túlzott verseny esetén stresszes lehet.',
    ],
    'when' => 'Versenyorientált csapatoknál (sales, startup környezet), ahol fontos a relatív kiemelkedés.',
    'fields' => [
        'threshold_bottom_pct' => 'Alsó sáv aránya (%)',
        'threshold_top_pct'    => 'Felső sáv aránya (%)',
    ],
],

// SUGGESTED panel
'suggested' => [
    'title' => 'Fejlett intelligencia',
    'meta'  => 'Az értékelési időszak zárásakor a THEMIS AI Engine egyedi, a munkavállalói csapatra szabott ponthatárokat állapít meg.',
    'description_html' => 'Az AI korábbi időszakok, aktuális eredmények és megbízhatósági minták alapján ajánl határokat. Gyors döntést támogat, vezetői kontrollal.',
    'pros' => [
        'Adatvezérelt és dinamikus – időt spórol.',
        'Segít kiszűrni torzító mintákat (pl. lepontozás).',
        'A ponthatárállítás során rengeteg, cégspecifikus adatból dolgozik.'
    ],
    'cons' => [
        'Kevésbé átlátható a dolgozók számára, magyarázatot igényelhet a ponthatárszámítás módja.',
        'Nincs kézzel fogható "ponthatár", minden mérésnél máshol lehetnek a lépcsők.',
    ],
    'when' => 'Olyan, legalább közepes méretű vállalatoknál, ahol fontos a torzítások csökkentése, vagy az a gyanúnk, hogy a kitöltés során az alanyok csalhatnak.',
],

// Gombok
'buttons' => [
    'save_mode'      => 'Mód kiválasztása',
    'save_settings'  => 'Változások mentése',
],


        // JS üzenetek – UGYANITT
        'confirm'        => 'Figyelem!',
        'warn_strict_on' => 'Szigorú anonim mód bekapcsolásával az AI telemetria automatikusan kikapcsol, amennyiben be volt kapcsolva. A beállítás bármikor megváltoztatható, de az idősoros adatokban hibát okozhat.',
        'warn_ai_on'     => 'Bekapcsolod az AI telemetriát. Mérési időszakokban a felhasználók viselkedési mintáit anonim módon AI eszközökkel vizsgáljuk és a pontszámításnál az eredményeket súlyozzuk.',
        'warn_ai_off'    => 'Kikapcsolod az AI telemetriát. A rendszer nem fogja szűrni a csalásokat (pl. lepontozás, felülpontozás, hanyag kitöltés).',
        'saved'          => 'Beállítás elmentve',
        'error'          => 'Hiba történt',

        'yes' => 'Igen',
        'no'  => 'Mégsem',
    ],
];
