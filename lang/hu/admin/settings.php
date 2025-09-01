<?php
return [
    'settings' => [
        'menu' => 'Beállítások',
        'title' => 'Beállítások',
        'section_ai_privacy' => 'AI & adatvédelem',

        'strict' => [
            'title' => 'Szigorú anonim mód',
            'meta_html' => 'Szigorú anonim módban az adatbázisban még analitikai célból sem kerül tárolásra az értékelést beküldő felhasználó azonosítója. Figyelem: a szigorú anonim mód bekapcsolása az AI telemetriát is kikapcsolja. Az adatrögzítési mód bármikor visszaállítható, de korábbi mérések adatai hiányosak lesznek. A viselkedési minták és esetleges csalások nem lesznek felismerve és kiszűrve.',
        ],

        'ai' => [
            'title' => 'NWB AI intelligencia',
            'meta_html' => 'A kitöltéseket AI alapú telemetria szolgáltatással mérjük a háttérben, viselkedési minták alapján súlyozzuk a kitöltéseket és igyekszünk kiszűrni a csalásokat. A felhasználók személyes adatait a modell nem dolgozza fel, kizárólag anonim viselkedési és tartalmi minták alapján határozza meg a kitöltés megbízhatóságát. A funkció hosszútávon javítja a mérési eredményeket és megtanulja az egyes alkalmazottak viselkedését.',
        ],

        // JS üzenetek – UGYANITT, nem gyökérszinten
        'confirm'        => 'Figyelem!',
        'warn_strict_on' => 'Szigorú anonim mód bekapcsolásával az AI telemetria automatikusan kikapcsol, amennyiben be volt kapcsolva. A beállítás bármikor megváltoztatható, de az idősoros adatokban hibát okozhat.',
        'warn_ai_on'     => 'Bekapcsolod az AI telemetriát. Mérési időszakokban a felhasználók viselkedési mintáit anonim módon AI eszközökkel vizsgáljuk és a pontszámításnál az eredményeket súlyozzuk.',
        'warn_ai_off'    => 'Kikapcsolod az AI telemetriát. A rendszer nem fogja szűrni a csalásokat (pl. lepontozás, felülpontozás, hanyag kitöltés).',
        'saved'          => 'Beállítás elmentve',
        'error'          => 'Hiba történt',

        // ha „igen/nem” is kell ide, rakhatod ide lokálisan:
        'yes'            => 'Igen',
        'no'             => 'Mégsem',
    ],
];
