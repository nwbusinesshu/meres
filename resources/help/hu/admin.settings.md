---
view: admin.settings.index
title: Beállítások
role: admin
visible_to: [admin]
related: [/admin/home, /admin/employee/index, /admin/bonuses, /admin/assessment/index]
actions:
  - { label: "Beállítások mentése", trigger: "form-submit", permission: "admin" }
  - { label: "Bónusz szorzók beállítása", trigger: "modal-open", permission: "admin" }
keywords: [beállítások, settings, AI, telemetria, szigorú anonim, strict anonymous, bónusz, bonus, malus, pontozási módszer, threshold, küszöbérték, fixpontos, hybrid, dynamic, suggested, fejlett intelligencia, részlegkezelés, department, kapcsolatok, relations, 2FA, OAuth, biztonsági, security, szorzók, multipliers, jutalmazás, értékelés, assessment]


<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

## Technikai részletek (AI számára)

**Controller:** `AdminSettingsController`

**Főbb metódusok:**
- `index()` - Beállítások oldal megjelenítése, összes konfiguráció betöltése
- `toggle(Request $request)` - AJAX endpoint toggle kapcsolók kezelésére (strict_anonymous_mode, ai_telemetry_enabled, show_bonus_malus, enable_bonus_calculation, employees_see_bonuses, easy_relation_setup, force_oauth_2fa, enable_multi_level)
- `save(Request $request)` - Pontozási módszer és küszöbértékek mentése (threshold_mode, normal_level_up, normal_level_down, threshold_min_abs_up, threshold_top_pct, threshold_bottom_pct, threshold_grace_points, threshold_gap_min, target_promo_rate_max_pct, target_demotion_rate_max_pct, never_below_abs_min_for_promo, use_telemetry_trust, no_forced_demotion_if_high_cohesion)

**Routes:**
- `admin.settings.index` - GET /admin/settings/index (beállítások oldal)
- `admin.settings.toggle` - POST /admin/settings/toggle (toggle kapcsolók AJAX)
- `admin.settings.save` - POST /admin/settings/thresholds (küszöbértékek mentése)
- `admin.bonuses.config.get` - POST /admin/bonuses/config/get (bónusz szorzók lekérdezése)
- `admin.bonuses.config.save` - POST /admin/bonuses/config/save (bónusz szorzók mentése)

**Permissions:** Admin role required (middleware: 'auth:admin', 'org')

**Key Database Tables:**
- `org_config` - Szervezeti beállítások tárolása (key-value párok)
- `bonus_malus_config` - Bónusz/malus szorzók tárolása (organization_id, level, multiplier)
- `assessment` - Értékelések táblája (threshold_method, normal_level_up, normal_level_down mentése)
- `organization_user` - Felhasználók szervezeti kapcsolatai (department_id, role)
- `organization_department_managers` - Részlegvezetők (multi-level esetén)

**JavaScript Files:**
- `resources/views/js/admin/settings.blade.php` - Toggle kapcsolók kezelése, SweetAlert megerősítések, AJAX hívások
- `resources/views/admin/modals/bonus-config.blade.php` - Bónusz szorzók modal és JavaScript logika

**Translation Keys:**
- `lang/hu/admin/settings.php` - Magyar fordítások
- `lang/en/admin/settings.php` - Angol fordítások
- `lang/hu/admin/bonuses.php` - Bónusz rendszer fordítások
- `lang/hu/global.php` - Globális fordítások (bonus-malus szintek)

**Key Features:**
- **Szigorú anonim mód:** Ha bekapcsolva, a user_id nem kerül tárolásra az assessment_score táblában, AI telemetria automatikusan kikapcsol
- **AI telemetria:** Viselkedési minták rögzítése, trust_score számítás, fraud detection
- **Multi-level részlegkezelés:** Egyszer bekapcsolva VISSZAVONHATATLAN, department_id használata, organization_department_managers tábla
- **Bónusz rendszer:** Hierarchikus beállítások (show_bonus_malus → enable_bonus_calculation → employees_see_bonuses)
- **Pontozási módszerek:** fixed (fix pontok), hybrid (fix alsó + dinamikus felső), dynamic (teljes percentilis alapú), suggested (AI-vezérelt)
- **15-szintű bónusz/malus rendszer:** M04-M03-M02-M01-A00-B01...B10, szorzók 0.00-10.00 tartományban

**Validations (backend):**
- `threshold_mode` validáció: csak 'fixed', 'hybrid', 'dynamic', 'suggested' értékek
- Küszöbértékek validációja: 0-100 között, integer típus
- Toggle kapcsolók validációja: boolean típus
- Kölcsönös függőségek ellenőrzése (pl. strict_anon és ai_telemetry nem lehet egyszerre aktív)
- Hierarchikus kapcsolók ellenőrzése (pl. employees_see_bonuses csak akkor engedélyezett, ha show_bonus_malus és enable_bonus_calculation is aktív)

**Business Logic:**
- **Cascading toggles:** show_bonus_malus kikapcsolása automatikusan kikapcsolja az enable_bonus_calculation és employees_see_bonuses kapcsolókat is
- **Exclusive settings:** szigorú anonim mód és AI telemetria egymást kizáró beállítások
- **Irreversible actions:** enable_multi_level bekapcsolása visszavonhatatlan
- **Suggested mode requirements:** csak akkor elérhető, ha van legalább egy lezárt értékelés ÉS az AI telemetria be van kapcsolva
- **Threshold storage:** assessment táblában az assessment lezáráskor kerülnek mentésre a használt küszöbértékek (org_snapshot JSON-ben)

<!-- TECHNICAL_DETAILS_END -->

---

# Mi ez az oldal?

A Beállítások oldal a Quarma360 rendszer központi irányítópultja, ahol az adminisztrátorok minden fontos szervezeti paramétert kezelhetnek. Innen vezérelheted az adatvédelmi beállításokat, a mesterséges intelligencia funkciókat, a jutalmazási rendszert és a teljesítményértékelés pontozási módszereit. A beállítások azonnal életbe lépnek és hatással vannak a jövőbeli értékelésekre - a már lezárt értékelések adatai nem változnak meg.

## Kiknek látható ez az oldal?

**Adminisztrátorok:** Teljes hozzáférés az összes beállításhoz, minden funkció szerkeszthető és kapcsolható.

---

## Mit tudsz itt csinálni?

### AI & Adatvédelmi Beállítások

#### Szigorú anonim mód

**Kapcsoló:** Bal oldali első csempe  
**Helye:** "AI & adatvédelem" szekció tetején

**Mit csinál:**
- Bekapcsolt állapotban a rendszer NEM tárolja el, hogy melyik felhasználó töltötte ki az értékelést
- Az adatbázisban még analitikai célból sem lesz látható a kitöltő személye
- **AUTOMATIKUSAN KIKAPCSOLJA** az AI telemetriát is (mivel az AI-nak szüksége van a viselkedési adatokra)

**Mit NEM csinál:**
- Nem törli a korábbi értékelésekből a már tárolt user_id adatokat
- Nem teszi láthatóvá a korábbi anonim kitöltések készítőit
- Nem vonatkozik az eredmények megjelenítésére (az eredmények mindig név szerint láthatók)

**Mikor kapcsold BE:**
- Ha jogszabályi kötelezettség előírja a szigorú anonimitást
- Ha rendkívül bizalmas HR folyamatokról van szó
- Ha a dolgozók nem bíznak a rendszer anonimitásában

**Mikor kapcsold KI:**
- Ha szeretnéd használni az AI telemetriát és fraud detection funkciókat
- Ha szükséged van viselkedési minták elemzésére
- Ha fontos a csalások és visszaélések felismerése

**Figyelem:** Ha bekapcsolod, a korábbi mérések adatai hiányosak lesznek, és viselkedési minták, csalások nem lesznek felismerve.

---

#### NWB THEMIS AI Engine (AI Telemetria)

**Kapcsoló:** Bal oldali második csempe  
**Helye:** "AI & adatvédelem" szekció

**Mit csinál:**
- Rögzíti és elemzi a kitöltések viselkedési mintáit (kitöltési idő, válaszváltoztatások, szünetidők)
- Kiszámítja a kitöltések megbízhatósági pontszámát (trust_score)
- Súlyozza a kitöltéseket megbízhatóságuk alapján
- Felismeri és kiszűri a gyanús, csaló kitöltéseket
- Megtanulja az egyéni alkalmazottak viselkedési mintáit

**Mit NEM csinál:**
- Nem dolgozza fel a felhasználók személyes adatait (név, email, stb.)
- Nem olvassa el a szöveges válaszokat vagy kommenteket
- Nem automatikusan módosítja vagy törli a gyanús kitöltéseket
- Nem helyettesíti az emberi döntéshozatalt

**Mikor kapcsold BE:**
- Ha objektív és megbízható értékelési eredményeket szeretnél
- Ha csökkenteni akarod a visszaélések lehetőségét
- Ha szeretnél javulni a hosszútávú értékelési eredményeken

**Mikor kapcsold KI:**
- Ha a szigorú anonim módot használod (ez esetben automatikusan kikapcsol)
- Ha jogszabály tiltja a viselkedési adatok rögzítését
- Ha tesztelési fázisban vagy és nem akarsz AI beavatkozást

**Fontos:** A szigorú anonim mód és az AI telemetria egymást kizárják - ha az egyiket bekapcsolod, a másik automatikusan kikapcsol.

---

### Programbeállítások

#### Többszintű részlegkezelés (Multi-level)

**Kapcsoló:** Programbeállítások első csempe  
**Helye:** "Programbeállítások" szekció

**Mit csinál:**
- Engedélyezi a részlegek (departments) létrehozását és kezelését
- Lehetővé teszi a dolgozók részlegekbe sorolását
- Létrehozza a részlegvezető (Manager) szerepkört
- A vezetők csak a saját részlegük tagjait látják és értékelik
- Több vezetőt is ki lehet jelölni egy részleghez

**Mit NEM csinál:**
- Nem hoz létre automatikusan részlegeket
- Nem sorol be automatikusan dolgozókat részlegekbe
- Nem változtatja meg a meglévő szervezeti felépítést
- Nem korlátozza az adminok és CEO-k láthatóságát

**KRITIKUS: VISSZAVONHATATLAN BEÁLLÍTÁS**

Ha egyszer bekapcsolod, **nem kapcsolható vissza**. Mielőtt bekapcsolod:
1. Győződj meg róla, hogy tényleg szükséged van részlegekre
2. Tervezd meg a részlegstruktúrát
3. Olvasd el a dokumentációt a többszintű kezelésről
4. Konzultálj a vezetőséggel

**Mikor kapcsold BE:**
- Ha a szervezet több önálló részlegre oszlik
- Ha a vezetők csak saját csapatukat akarják irányítani
- Ha 50+ főnél nagyobb a szervezet

**Mikor NE kapcsold be:**
- Ha kis létszámú a cég (<20 fő)
- Ha lapos a szervezeti hierarchia
- Ha nem vagy biztos benne, hogy szükséges

---

#### Jutalmazási bónuszrendszer megjelenítése

**Kapcsoló:** Programbeállítások második csempe  
**Helye:** "Programbeállítások" szekció

**Mit csinál:**
- Megjeleníti a Bonus/Malus kategóriákat (M04-M03-M02-M01-A00-B01...B10) az alkalmazott listában
- Láthatóvá teszi a dolgozók aktuális besorolását
- Engedélyezi a bónusz szorzók konfigurálását
- Aktiválja a bónusz számítási lehetőséget

**Mit NEM csinál:**
- Nem számítja ki automatikusan a bónuszokat (ehhez külön kapcsolót kell bekapcsolni)
- Nem jeleníti meg a bónusz összegeket a dolgozóknak (ehhez külön kapcsoló kell)
- Nem változtatja meg a teljesítményértékelés számítását

**Mikor kapcsold BE:**
- Ha használni szeretnéd a 15-szintű bónusz/malus rendszert
- Ha pénzügyi jutalmazást akarsz kapcsolni a teljesítményhez
- Ha átlátható kategorizálást akarsz létrehozni

**Mikor kapcsold KI:**
- Ha nem használsz pénzügyi jutalmakat
- Ha csak a teljesítményértékelést szeretnéd, bónusz nélkül
- Ha tesztelési fázisban vagy

**Hierarchikus kapcsoló:** Ha ezt kikapcsolod, az "Engedélyezi a bónusz számítást" és "Alkalmazottak látják a bónuszokat" is automatikusan kikapcsol.

---

#### Bónusz számítás engedélyezése nettó bér alapján

**Kapcsoló + Gomb:** Bal oldali csempe kapcsolóval, mellette "Bónusz szorzók beállítása" gomb  
**Helye:** "Bónuszok / Fizetések" szekció

**Mit csinál:**
- Engedélyezi a bónuszok automatikus számítását a dolgozók nettó bére alapján
- Aktiválja a képletet: (havi nettó bér / 40) × szorzó
- Hozzáférhetővé teszi a "Bónuszok" menüpontot a navigációban
- Lehetővé teszi a dolgozók nettó bérének rögzítését
- Kiszámolja minden értékelés lezárásakor a bónusz összegeket

**Mit NEM csinál:**
- Nem fizet ki automatikusan semmit
- Nem exportál könyvelési adatokat
- Nem küldi el a bónuszokat a dolgozóknak

**Függőség:** Csak akkor engedélyezhető, ha a "Jutalmazási bónuszrendszer megjelenítése" BE van kapcsolva.

**"Bónusz szorzók beállítása" gomb:**
- Megnyit egy részletes panelt a 15 bónusz/malus szint szorzóinak beállítására
- Minden szinthez (M04...B10) külön-külön állítható a szorzó (0.00-10.00 között)
- Vizuális csúszka segítségével könnyedén módosítható
- Gyors gombok: 0x, 1x, 5x, 10x
- "Alapértelmezések visszaállítása" gomb: magyar default értékeket állítja vissza

**Mikor kapcsold BE:**
- Ha a teljesítményhez pénzügyi jutalmakat akarsz kapcsolni
- Ha átlátható és automatikus bónusz számítást akarsz
- Ha negyedéves kifizetési rendszert használsz

**Mikor kapcsold KI:**
- Ha nincs bónuszrendszered
- Ha nem akarod a nettó bért a rendszerben tárolni
- Ha külső könyvelési rendszerrel kezeled a bónuszokat

---

#### Alkalmazottak látják a bónusz összegeket

**Kapcsoló:** Jobb oldali csempe  
**Helye:** "Bónuszok / Fizetések" szekció

**Mit csinál:**
- A dolgozók az eredmények oldalon látják a saját bónusz/malus kategóriájukat
- Megjelenik a kiszámított bónusz összeg
- Látható a formula (havi nettó bér / 40 × szorzó)
- Átlátható a jutalmazás módja

**Mit NEM csinál:**
- Nem teszi láthatóvá más dolgozók bónuszait
- Nem mutat történeti adatokat
- Nem engedélyezi a dolgozóknak a szerkesztést

**Függőség:** Csak akkor engedélyezhető, ha MINDKETTŐ be van kapcsolva:
1. "Jutalmazási bónuszrendszer megjelenítése"
2. "Bónusz számítás engedélyezése"

**Mikor kapcsold BE:**
- Ha átláthatóságot akarsz teremteni a jutalmazásban
- Ha motiválni akarod a dolgozókat konkrét összegekkel
- Ha csökkenteni akarod a bérezéssel kapcsolatos kérdéseket

**Mikor kapcsold KI:**
- Ha a bónuszokat bizalmasan kezeled
- Ha nem akarod, hogy az alkalmazottak összehasonlítsák egymás bónuszait
- Ha még teszteled a bónuszrendszert

---

#### Kapcsolatok egyszerűsített beállítása

**Kapcsoló:** Bal oldali csempe  
**Helye:** "Kapcsolatok" szekció

**Mit csinál:**
- Automatikusan beállítja a kapcsolatokat kétirányúan
- **Alárendelt → Kolléga:** Ha X beosztottként értékeli Y-t, akkor Y automatikusan kollégaként értékeli X-et
- **Kolléga → Kolléga:** Ha X kollégaként értékeli Y-t, akkor Y is kollégaként értékeli X-et
- Csökkenti az adminisztratív terheket
- Kevesebb hibalehetőség a kapcsolatok beállításánál

**Mit NEM csinál:**
- Nem állítja be automatikusan a vezető-beosztott kapcsolatokat
- Nem módosítja a már meglévő, helyesen beállított kapcsolatokat
- Nem old fel kapcsolat-ütközéseket automatikusan

**Mikor kapcsold BE:**
- Ha sok dolgozód van és sok idő menne a manuális beállítással
- Ha szeretnéd csökkenteni a hibák számát
- Ha a legtöbb kapcsolat reciprok jellegű (kölcsönös)

**Mikor kapcsold KI:**
- Ha aszimmetrikus kapcsolatokat használsz (pl. X értékeli Y-t, de Y nem értékeli X-et)
- Ha teljes kontrollra van szükséged a kapcsolatok felett
- Ha komplexebb szervezeti dinamikád van

**Fontos:** Ütközés esetén a rendszer figyelmeztetést ad, és lehetőséget biztosít a javításra.

---

#### 2FA kényszerítés OAuth bejelentkezésnél

**Kapcsoló:** Jobb oldali csempe  
**Helye:** "Biztonsági beállítások" szekció

**Mit csinál:**
- Google és Microsoft OAuth bejelentkezéseknél is kötelezővé teszi a kétfaktoros azonosítást
- Email ellenőrző kódot küld minden belépésnél
- Megerősítési lépést ad a bejelentkezési folyamathoz
- Maximális biztonságot nyújt

**Mit NEM csinál:**
- Nem módosítja a hagyományos email+jelszó belépés 2FA beállításait
- Nem küld SMS kódot (csak email kódot)
- Nem befolyásolja a superadmin belépéseket

**Alapértelmezett állapot: KIKAPCSOLVA**

**Mikor kapcsold BE:**
- Ha rendkívül bizalmas adatokat kezeltek (pl. egészségügy, pénzügy)
- Ha magas biztonsági szintet követel meg a jogszabály
- Ha aggódsz az OAuth fiókok biztonságáért

**Mikor kapcsold KI (alapértelmezett):**
- Ha a Google/Microsoft már biztosít erős hitelesítést (ez általában elegendő)
- Ha nem akarsz további súrlódást a bejelentkezési folyamatba
- Ha a dolgozók panaszkodnak a túl sok biztonsági lépésre

**Ajánlott:** Csak nagyon bizalmas adatokat kezelő szervezeteknek kapcsold be.

---

## Pontozási módszerek (Threshold beállítások)

### Módszer kiválasztása

**Helye:** "Módszertani beállítások" szekció tetején  
**Négy választható mód:** Fixpontos, Hybrid, Dynamic, Fejlett intelligencia

**Módválasztó kapcsolók:**
- Egyszerre csak egy választható
- A korábbi, lezárt értékeléseket NEM befolyásolja
- Az új értékeléseknél azonnal életbe lép

---

### 1. FIXPONTOS MÓD (Fixed)

**Mit csinál:**
Két állandó pontszámot használ: aki a **felső határ fölé** kerül, előrelép; aki az **alsó határ alá** esik, visszajelzést vagy visszaminősítést kap.

**Beállítható értékek:**
- **Felső határ (előléptetés):** 0-100 pont között (alapértelmezett: 85)
- **Alsó határ (lefokozás):** 0-100 pont között (alapértelmezett: 70)

**Példa:**
- Ha felső határ = 85 → aki 85 pont feletti teljesítményt ér el, előrelép
- Ha alsó határ = 70 → aki 70 pont alatti teljesítményt ér el, fejlesztési tervet kap
- Aki 70-85 között teljesít → szinten marad

**Előnyök (Pro):**
- ✅ Könnyen kommunikálható és érthető a dolgozók számára
- ✅ Stabil mérce: mindig ugyanazok a határok
- ✅ Jó alapbeállítás kisebb, standardizált csapatoknál
- ✅ Nincs szükség AI-ra vagy komplex számításokra
- ✅ Előre tudják a dolgozók, mi a célszám

**Hátrányok (Contra):**
- ❌ Nem követi a csapat szintjének ingadozását
- ❌ Ha a mezőny szintje eltolódik, a fix számok elavulhatnak
- ❌ Nem veszi figyelembe a relatív teljesítményt
- ❌ Inflációs hatás: ha mindenki javul, mindenki előrelép

**Mikor használd:**
- Kis létszámú (10-30 fős) szervezeteknél
- Gyártásban, erősen standardizált folyamatoknál
- Ha állandó, objektív mércét akarsz
- Ha egyszerűséget és átláthatóságot értékelsz
- Ha nincs jelentős fluktuáció a csapat szintjében

**Mikor NE használd:**
- Nagy, változó teljesítményű csapatoknál
- Ha a csapat szintje gyorsan változik
- Ha relatív teljesítményt akarsz mérni (ki jobb másoknál)

---

### 2. HYBRID MÓD

**Mit csinál:**
Kombinálja a fix alsó határt a dinamikus felső határral. Az **alsó határ fix** (pl. 70 pont), de a **felső határ dinamikus**: a csapat felső X%-a lép elő, DE legalább Y pontot kell elérni.

**Beállítható értékek:**
- **Alsó határ (lefokozás):** 0-100 pont (alapértelmezett: 70)
- **Minimum abszolút pont az előléptetéshez:** 0-100 pont (alapértelmezett: 80)
- **Felső percentilis (%):** 1-50% (alapértelmezett: 15%)

**Példa:**
- Alsó határ = 70 pont → aki ez alá esik, lefokozás
- Min. abszolút pont = 80 → még ha top 15%-ban is vagy, de 80 pont alatt, nem lépsz elő
- Felső percentilis = 15% → a csapat legjobb 15%-a lép elő (ha elérik a 80 pontot)

**Működés:**
A rendszer kiszámolja, hogy ki tartozik a felső 15%-ba a pontszámok alapján. Akik benne vannak ÉS elérték a 80 pontot → előlépnek. Akik a felső 15%-ban vannak, de 80 pont alatt → nem lépnek elő.

**Előnyök (Pro):**
- ✅ Véd a túlzott lefokozás ellen (fix alsó határ)
- ✅ Követi a csapat teljesítményének változását (dinamikus felső határ)
- ✅ Minimális minőséget biztosít az előléptetéshez (abszolút minimum)
- ✅ Jó kompromisszum a fix és dinamikus módok között

**Hátrányok (Contra):**
- ❌ Bonyolultabb kommunikálni, mint a fix módot
- ❌ A dolgozók nehezebben számolják ki az esélyeiket
- ❌ Két paraméter beállítása szükséges

**Mikor használd:**
- Közepes méretű (30-100 fős) szervezeteknél
- Ha szeretnél védelmet a tömeges lefokozás ellen
- Ha fontos a minimális minőség biztosítása
- Ha változó teljesítményű a csapat, de alapszintet akarsz biztosítani

**Mikor NE használd:**
- Ha túl komplex a dolgozóknak
- Ha nagyon homogén a csapat (mindenki hasonló szinten van)

---

### 3. DYNAMIC MÓD

**Mit csinál:**
Teljes mértékben percentilis alapú. A csapat **alsó X%-a** lefokozást kap, a **felső Y%-a** előrelép. Nincs fix ponthatár - minden relatív a csapathoz képest.

**Beállítható értékek:**
- **Alsó percentilis (%):** 1-50% (alapértelmezett: 20%)
- **Felső percentilis (%):** 1-50% (alapértelmezett: 15%)

**Példa:**
- Alsó percentilis = 20% → a csapat leggyengébb 20%-a lefokozást kap
- Felső percentilis = 15% → a csapat legjobb 15%-a előrelép
- A középső 65% → szinten marad

**Működés:**
Ha 100 dolgozó van:
- Az alsó 20 fő (20. helytől lefelé) → lefokozás
- A felső 15 fő (1-15. helyezettek) → előlépés
- A középső 65 fő (16-80. helyezettek) → szinten maradás

**Előnyök (Pro):**
- ✅ Mindig van előléptetés és lefokozás (nem mindeki maradhat szinten)
- ✅ Relatív teljesítményt mér (ki a jobb másoknál)
- ✅ Ösztönzi a versenyhelyzetet
- ✅ Automatikusan alkalmazkodik a csapat szintjéhez
- ✅ Nincs szükség fix pontszámok beállítására

**Hátrányok (Contra):**
- ❌ Ha mindenki kiválóan teljesít, a gyengébb is lefokozást kaphat
- ❌ Ha mindenki gyengén teljesít, a jobbik felső kategóriába kerülhet
- ❌ Versenyhelyzetet teremt a dolgozók között (nem mindig kívánatos)
- ❌ Nem vesz figyelembe abszolút teljesítményt

**Mikor használd:**
- Nagy létszámú (100+ fős) szervezeteknél
- Értékesítési csapatoknál, ahol a relatív teljesítmény számít
- Ha szeretnél garantált differenciálást
- Ha fontos a versenyhelyzet ösztönzése

**Mikor NE használd:**
- Kis csapatoknál (10-20 fő) - túl durva a kategorizálás
- Együttműködést igénylő munkakörökben
- Ha a dolgozók között nem akarsz versenyt

---

### 4. FEJLETT INTELLIGENCIA MÓD (Suggested / AI)

**Elérhető, ha:**
- ✅ Van legalább **egy lezárt értékelés** a szervezetnél
- ✅ Az **AI telemetria be van kapcsolva**

**Mit csinál:**
A NWB THEMIS AI motor elemzi a csapat múltbeli teljesítményét, a jelenlegi eredményeket, a megbízhatósági pontszámokat és javaslatot tesz arra, kiket kell előléptetni vagy lefokozni. Az AI figyelembe veszi:
- A korábbi értékelések eredményeit
- A dolgozók teljesítményének trendjeit
- A kitöltések megbízhatósági pontszámát (trust_score)
- A csapat kohézióját (mennyire homogén a teljesítmény)
- Az előléptetési és lefokozási ráta célokat

**Beállítható értékek:**
- **Max. előléptetési ráta (%):** 0-100% (alapértelmezett: 20%) - legfeljebb a csapat hány százaléka léphet elő
- **Max. lefokozási ráta (%):** 0-100% (alapértelmezett: 10%) - legfeljebb a csapat hány százaléka eshet vissza
- **Előléptetés abszolút minimum (pontszám):** 0-100 vagy üres (alapértelmezett: üres) - bármilyen gyengén teljesít is a csapat, az AI soha nem teheti az előléptetés határát ennél alacsonyabb pontra
- **Telemetria alapú súlyozás (checkbox):** Be/Ki (alapértelmezett: Be) - az AI figyelembe veszi-e a kitöltések megbízhatósági pontszámát
- **Magas kohézió esetén nincs kényszerített lefokozás (checkbox):** Be/Ki (alapértelmezett: Ki) - ha a csapat szorosan együtt teljesít, az AI nem fog erőltetni lefokozást

**Működés:**
Az AI megvizsgálja az összes dolgozó teljesítményét és javaslatot tesz:
- "Ezt a 18%-ot javasoljuk előléptetni (max. 20% volt a cél)"
- "Ezt a 7%-ot javasoljuk lefokozni (max. 10% volt a cél)"
- Figyelembe veszi a trust_score-okat: gyanús kitöltéseket alacsonyabb súllyal számol
- Ha magas a kohézió (mindenki hasonló szinten van), nem kényszerít lefokozást

**Előnyök (Pro):**
- ✅ Tanul a korábbi értékelésekből
- ✅ Kiszűri a csalásokat és gyanús kitöltéseket
- ✅ Objektív döntéstámogatás
- ✅ Figyelembe veszi a trendeket és a csapat dinamikáját
- ✅ Véd a tömeges előléptetés/lefokozás ellen
- ✅ Rugalmas és adaptív

**Hátrányok (Contra):**
- ❌ Nehezebb megérteni és kommunikálni
- ❌ A dolgozók nem látják előre a határokat
- ❌ Szükség van AI telemetriára
- ❌ Legalább egy lezárt értékelés kell hozzá
- ❌ "Black box" érzést kelthet (nem átlátható minden döntés)

**Mikor használd:**
- Ha van elegendő történeti adatod (legalább 1-2 lezárt értékelés)
- Nagy szervezeteknél (100+ fő)
- Ha objektív, adatvezérelt döntéseket akarsz
- Ha fontos a csalások kiszűrése
- Ha szeretnél hosszútávú tanulást és optimalizálást

**Mikor NE használd:**
- Ha nincs még egy lezárt értékelésed sem
- Ha az AI telemetria ki van kapcsolva
- Ha a dolgozók nem bíznak az AI döntéseiben
- Ha 100%-os átláthatóságra van szükség

**Speciális beállítások magyarázata:**

**Max. előléptetési ráta:**
Megakadályozza, hogy az AI "túl sok" embert engedjen egyszerre előléptetni. Példa: ha 20%-ra állítod, akkor egy 100 fős csapatnál maximum 20 fő léphet elő, még ha többen is kiérdemelnék.

**Max. lefokozási ráta:**
Megakadályozza a tömeges lefokozást. Ha 10%-ra állítod, akkor maximum 10 fő eshet vissza egy 100 fős csapatból, még ha többen is gyengén teljesítettek.

**Előléptetés abszolút minimum:**
Biztonsági határ. Példa: ha 75 pontra állítod, akkor még ha a csapat gyengén is teljesít, az AI soha nem teszi az előléptetés határát 75 pont alá.

**Telemetria alapú súlyozás:**
Ha be van kapcsolva, az AI kevesebb súllyal számol azokat a kitöltéseket, amelyek gyanúsak vagy megbízhatatlanok. Ha ki van kapcsolva, minden kitöltést egyformán kezel.

**Magas kohézió esetén nincs kényszerített lefokozás:**
Ha a csapat egységesen teljesít (mindenki hasonló szinten van), az AI nem fog erőltetni lefokozást. Példa: ha mindenki 70-75 pont között van, akkor nem lesz olyan, hogy "valakinek muszáj visszaesni".

---

## Bónusz szorzók konfigurálása

### Bónusz/Malus rendszer áttekintése

**15-szintű rendszer:** M04, M03, M02, M01, A00, B01, B02, B03, B04, B05, B06, B07, B08, B09, B10

**Kategóriák:**
- **Malus szintek (M04-M01):** Gyenge teljesítmény, csökkentett vagy nulla bónusz
- **Neutrális szint (A00):** Átlagos teljesítmény, 1.00x szorzó (alapbér szerinti bónusz)
- **Bónusz szintek (B01-B10):** Kiváló teljesítmény, növekvő szorzók

**Bónusz számítás formulája:**
```
Negyedéves bónusz = (Havi nettó bér / 40) × Szorzó
```

**Példa:**
- Havi nettó bér: 400 000 Ft
- Bónusz/malus szint: B05 (szorzó: 4.25)
- Számítás: (400 000 / 40) × 4.25 = 10 000 × 4.25 = **42 500 Ft** negyedéves bónusz

### Szorzók beállítása

**"Bónusz szorzók beállítása" gomb megnyomása után:**

**Panel elemei:**
- **15 külön szint szorzója** egyenként állítható
- **Vizuális csúszka (slider):** 0.00-10.00 tartományban
- **Gyors gombok:** 0x, 1x, 5x, 10x - egy kattintással beállítható értékek
- **Színkódolás:** 
  - Malus szintek: sárga háttér
  - Neutrális szint: szürke háttér
  - Bónusz szintek: zöld háttér

**Alapértelmezett magyar szorzók:**
- M04: 0.00x (nincs bónusz)
- M03: 0.40x
- M02: 0.70x
- M01: 0.90x
- A00: 1.00x (alapszorzó)
- B01: 1.50x
- B02: 2.00x
- B03: 2.75x
- B04: 3.50x
- B05: 4.25x
- B06: 5.25x
- B07: 6.25x
- B08: 7.25x
- B09: 8.25x
- B10: 10.00x (maximum)

**"Alapértelmezések visszaállítása" gomb:**
Visszaállítja a fenti magyar default értékeket minden szintnél.

**Mentés:**
A "Mentés" gombra kattintva a szorzók azonnal életbe lépnek, és az összes jövőbeli bónusz számításnál ezeket az értékeket használja a rendszer.

---

## Beállítások mentése

### Pontozási módszer és küszöbértékek mentése

**Gomb:** "Beállítások mentése"  
**Helye:** Minden pontozási mód panel alján

**Lépések:**
1. Válaszd ki a pontozási módot (Fixpontos, Hybrid, Dynamic, vagy Fejlett intelligencia)
2. Állítsd be a kiválasztott módhoz tartozó paramétereket
3. Kattints a "Beállítások mentése" gombra
4. A rendszer elmenti a beállításokat
5. Megerősítő üzenet jelenik meg: "Beállítások elmentve!"

**Fontos:** A változások azonnal életbe lépnek. A következő új értékelés már az új beállításokat használja. A korábbi, lezárt értékelések NEM változnak meg.

---

## Korlátozások és Feltételek

### ❌ Nem végezhető el, ha:

**Fejlett intelligencia mód kiválasztása:**
- Nincs legalább egy lezárt értékelés → "Nem választható, mert nincs még lezárt mérés."
- Az AI telemetria ki van kapcsolva → "Nem választható, mert az AI telemetria le van tiltva."

**AI telemetria bekapcsolása:**
- A szigorú anonim mód be van kapcsolva → "A Szigorú anonimizálás be van kapcsolva, így az AI telemetria nem engedélyezhető."

**Bónusz számítás engedélyezése:**
- A bónusz/malus megjelenítés ki van kapcsolva → "A Bonus/Malus megjelenítés ki van kapcsolva."

**Alkalmazottak látják a bónuszokat:**
- A bónusz/malus megjelenítés VAGY a bónusz számítás ki van kapcsolva → "A szülő beállítások (Bonus/Malus megjelenítés és Bónusz számítás) ki vannak kapcsolva."

**Többszintű részlegkezelés kikapcsolása:**
- Egyszer bekapcsolva VISSZAVONHATATLAN → Nem kapcsolható ki soha

### ⚠️ Figyelem:

- **Szigorú anonim mód bekapcsolása:** Automatikusan kikapcsolja az AI telemetriát. Az oldal újratöltődik.
- **Többszintű részlegkezelés bekapcsolása:** VISSZAVONHATATLAN döntés. Mielőtt bekapcsolod, olvasd el a dokumentációt és konzultálj a vezetőséggel.
- **Bónusz/malus megjelenítés kikapcsolása:** Automatikusan kikapcsolja a bónusz számítást és az alkalmazottak bónusz láthatóságát is.
- **Bónusz számítás kikapcsolása:** Automatikusan kikapcsolja az alkalmazottak bónusz láthatóságát.
- **Pontozási módszer változtatása:** A korábbi, lezárt értékeléseket nem befolyásolja, csak az újakat.

---

## Gyakran Ismételt Kérdések (GYIK)

### Mikor használjam a szigorú anonim módot?

Csak akkor, ha jogszabályi kötelezettség vagy nagyon bizalmas helyzet indokolja. A szigorú anonim mód kikapcsolja az AI telemetriát is, így nem lesz fraud detection és viselkedési elemzés. A legtöbb szervezetnek NEM javasolt bekapcsolni.

### Miért nem tudom bekapcsolni a Fejlett intelligencia módot?

Két feltétel kell teljesüljön:
1. Legyen legalább egy lezárt értékelésed (az AI-nak tanulnia kell a múltbeli adatokból)
2. Az AI telemetria be legyen kapcsolva

Ha valamelyik hiányzik, a "Fejlett intelligencia" opció mellett megjelenik egy ⓘ ikon a hiányzó feltétellel.

### Mi a különbség a fixpontos és a hybrid mód között?

**Fixpontos:** Mind az alsó, mind a felső határ fix pontszám. Egyszerű, de nem alkalmazkodik a csapat szintjéhez.

**Hybrid:** Az alsó határ fix, de a felső határ dinamikus (a csapat felső X%-a). Védelmet nyújt a tömeges lefokozás ellen, de követi a csapat változását.

### Megváltozhatnak a régi értékelések eredményei, ha átállítom a pontozási módot?

**NEM.** A korábbi, lezárt értékelések adatai megmaradnak. Csak az új értékelésekre lesz hatással a módszer változtatása.

### Mennyire megbízható az AI-alapú pontozás?

Az AI a múltbeli adatokból tanul, és objektív javaslatokat tesz. A trust_score alapján kiszűri a gyanús kitöltéseket. Azonban az AI csak javaslatot tesz - a végső döntés mindig az adminé vagy a CEO-é. Az AI nem helyettesíti az emberi döntéshozatalt.

### Hogyan állítsam be a bónusz szorzókat?

Nyisd meg a "Bónusz szorzók beállítása" gombot a "Bónusz számítás engedélyezése" csempe mellett. Ott egy részletes panelen állítsd be minden szinthez (M04...B10) a kívánt szorzót 0.00-10.00 között. Használd a csúszkát vagy a gyors gombokat (0x, 1x, 5x, 10x). Mentsd el a változtatásokat.

### Miért nem látják a dolgozók a bónusz összegeket?

Három beállítás kell bekapcsolt legyen hierarchikusan:
1. "Jutalmazási bónuszrendszer megjelenítése" - BE
2. "Bónusz számítás engedélyezése" - BE
3. "Alkalmazottak látják a bónuszokat" - BE

Ha bármelyik ki van kapcsolva, a dolgozók nem látják az összegeket.

### Mit tegyek, ha véletlenül bekapcsoltam a Többszintű részlegkezelést?

Sajnos semmit. Ez a beállítás VISSZAVONHATATLAN. Egyszer bekapcsolva nem lehet kikapcsolni. Ezért mindig kérj megerősítést és konzultálj a vezetőséggel, mielőtt bekapcsolod.

### Lehet egy dolgozónak több részlegvezetője is?

Igen, a Többszintű részlegkezelés támogatja, hogy egy részleghez több vezetőt is hozzárendelj. Mindegyik vezető látja és értékelheti a részleg tagjait.

### Mi az a trust_score és hogyan működik?

A trust_score (megbízhatósági pontszám) az AI telemetria által kiszámított érték 0-100 között. Az AI elemzi a kitöltési viselkedést (időzítés, válaszváltoztatások, szünetek) és megállapítja, mennyire megbízható a kitöltés. Alacsony trust_score esetén az értékelés kisebb súllyal számít a végeredményben.

### Hogyan tudom visszaállítani a bónusz szorzókat az alapértelmezésre?

A "Bónusz szorzók beállítása" panelen van egy "Alapértelmezések visszaállítása" gomb. Erre kattintva az összes szorzó visszaáll a magyar default értékekre (M04=0.00, M03=0.40, ... B10=10.00).

### Milyen gyakran frissülnek a beállítások az új értékelésekben?

A beállítások azonnal életbe lépnek mentés után. Az új értékelések létrehozásakor az aktuális beállításokat használja a rendszer, és az értékelés lezárásakor rögzíti a használt küszöbértékeket és módszert az értékelés adatai közé.

---

## Kapcsolódó oldalak

- **[Főoldal](/admin/home)**: Itt látod az aktív értékeléseket és azok állapotát. Az értékelések létrehozásakor az aktuális beállítások kerülnek használatra.
- **[Alkalmazottak](/admin/employee/index)**: A dolgozók kezelése, kapcsolatok beállítása, részlegek hozzárendelése (ha multi-level be van kapcsolva), bónusz/malus szintek megjelenítése.
- **[Bónuszok](/admin/bonuses)**: A bónusz számítások részletes kezelése, nettó bérek rögzítése, szorzók áttekintése, fizetési állapot követése.
- **[Értékelések](/admin/assessment/index)**: Az értékelések indítása, követése és lezárása. Itt érvényesülnek a beállított küszöbértékek és pontozási módszerek.
- **[Eredmények](/admin/results/index)**: Az értékelési eredmények megtekintése, elemzése. A dolgozók bónusz/malus besorolása és teljesítménymutatói.