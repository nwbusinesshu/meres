<?php

return [
    'settings' => [
        'menu' => 'Beállítások',
        'title' => 'Beállítások',
        'section_ai_privacy' => 'AI & adatvédelem',

        'strict' => [
            'title' => 'Szigorú anonim mód',
            'meta_html' => 'Szigorú anonim módban az adatbázisban nem kerül tárolásra a beküldő felhasználó azonosítója. Figyelem: a szigorú anonim mód az AI telemetriát is kikapcsolja. ',
        ],

        'ai' => [
            'title' => 'Fejlett AI telemetria',
            'meta_html' => 'A kitöltéseket AI alapú telemetria szolgáltatással mérjük a háttérben, viselkedési minták alapján súlyozzuk a kitöltéseket és igyekszünk kiszűrni a csalásokat.',
        ],
    ],
        // JS üzenetek
        "confirm" => "Biztosan módosítod?",
        "warn_strict_on" => "Szigorú anonim mód bekapcsolásábal az AI telemetria automatikusan kikapcsol. A beállítás bármikor megváltoztatható, de az idősoros adatokban hibát okozhat.",
        "warn_ai_on" => "Bekapcsolod az AI telemetriát. Mérési időszakokban a felhasználók viselkedési mintáit anonim módon AI eszközökkel vizsgáljuk és a pontszámításnál az eredményeket súlyozzuk.",
        'warn_ai_off' => 'Kikapcsolod az AI telemetriát. A rendszer nem fogja szűrni a csalásokat (pl. lepontozás, felülpontozás, hanyag kitöltés).',
        'saved' => 'Beállítás elmentve',
        'error' => 'Hiba történt',
        'common.no' => "Mégse",
        'common.yes' => "OK",
];