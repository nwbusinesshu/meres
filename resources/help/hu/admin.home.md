---
view: admin.home
title: Adminisztrátor Főoldal
role: admin
visible_to: [admin]
related: [/admin/employee/index, /admin/competency/index, /admin/results/index, /admin/settings/index, /admin/ceoranks/index]
actions:
  - { label: "Értékelési időszak indítása", trigger: "create-assessment", permission: "admin" }
  - { label: "Határidő módosítása", trigger: "modify-assessment", permission: "admin" }
  - { label: "Értékelési időszak lezárása", trigger: "close-assessment", permission: "admin" }
  - { label: "Lezárás előtt fizetés", trigger: "payment-link", permission: "admin" }
keywords: [admin főoldal, értékelési időszak, értékelés indítása, értékelés lezárása, statisztikák, alkalmazottak nyomonkövetése, határidő módosítása, értékelési állapot, önértékelés, vezetői rangsor]


<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

## Technikai részletek (AI számára)

**Controller:** `HomeController`  

**Főbb metódusok:**
- `admin()` - Loads admin dashboard with assessment statistics and employee progress tracking
- Statistics calculation for assessment completion and CEO rankings
- Employee progress tracking with competency submit counts

**Routes:**
- `admin.home` - GET (main admin dashboard)
- `admin.assessment.get` - POST (load assessment modal data)
- `admin.assessment.save` - POST (save/create assessment)
- `admin.assessment.close` - POST (close assessment period)

**Permissions:** 
- Middleware: `auth:OrgRole::ADMIN`, `org`, `check.initial.payment`
- Only organization admins can access this page
- Redirects to payment page if initial payment not completed

**Key Database Tables:**
- `assessments` - Current and historical assessment periods
- `users` - Employee data and relations
- `user_competency_submits` - Track competency assessment submissions
- `user_ceo_ranks` - CEO/Manager ranking submissions
- `user_relations` - Employee relationship network
- `payments` - Track open/unpaid invoices

**JavaScript Files:**
- Inline JS in `resources/views/js/admin/home.blade.php` - Assessment modal handling
- `public/assets/js/admin/home.js` - Dashboard interactions (if exists)

**Translation Keys:**
- `lang/hu/admin/home.php` - Hungarian translations for admin home
- `lang/en/admin/home.php` - English translations (if available)
- Key translations: assessment labels, button texts, status messages

**Key Features:**
- Real-time assessment period status display
- Employee progress tracking with color-coded tiles
- Assessment and CEO rank completion statistics
- Modal-based assessment creation and modification
- Payment gateway integration check before closing assessments

**Validations (backend):**
- Assessment due date must be at least 1 day in the future
- Cannot close assessment if open payments exist
- Assessment closure calculates bonus/malus levels automatically

**Business Logic:**
- Counts employees excluding admins for statistics
- Tracks self-assessment completion separately from peer assessments
- CEO rank requirements = CEO count + Manager count (with subordinates)
- Color-coded tiles show completion status (green = complete, red = incomplete)
- Payment check prevents premature assessment closure

<!-- TECHNICAL_DETAILS_END -->
---

# Mi ez az oldal?

Az adminisztrátor főoldal a Quarma360 irányítópultja, ahol az értékelési időszakokat kezelheted és nyomon követheted a szervezet értékelési folyamatának állapotát. Itt láthatod, hogy mely alkalmazottak töltötték ki az értékeléseket, és hogy mennyi vezetői rangsorolás készült el.

## Kiknek látható ez az oldal?

**Adminisztrátorok:** Teljes hozzáféréssel rendelkeznek. Létrehozhatnak, módosíthatnak és lezárhatnak értékelési időszakokat, valamint részletes statisztikákat láthatnak minden alkalmazott előrehaladásáról.

**Ügyvezetők (CEO):** Ez az oldal nem látható számukra. Ők a saját főoldalukat látják.

**Vezetők (Manager):** Ez az oldal nem látható számukra. Ők a saját főoldalukat látják.

**Alkalmazottak:** Ez az oldal nem látható számukra. Ők a saját főoldalukat látják.

---

## Mit tudsz itt csinálni?

### Értékelési időszak indítása
**Gomb:** "Értékelési időszak indítása"  
**Helye:** Az oldal tetején, ha nincs aktív értékelés  
**Mit csinál:** Új értékelési időszakot indíthatsz, amelyben a dolgozók értékelik egymást és magukat. Megadhatod a határidőt, ameddig az értékeléseket be kell nyújtani.  
**Korlátozások:** Egyszerre csak egy aktív értékelési időszak futhat. Nem indíthatsz újat, amíg a jelenlegi nem zárul le.

### Határidő módosítása
**Gomb:** "Határidő módosítása"  
**Helye:** Az oldal tetején, ha fut aktív értékelés  
**Mit csinál:** Módosíthatod a futó értékelési időszak határidejét, ha több időt szeretnél adni a dolgozóknak a kitöltésre.  
**Korlátozások:** A határidő csak jövőbeli dátumra állítható (legalább 1 nappal előre).

### Értékelési időszak lezárása
**Gomb:** "Értékelési időszak lezárása"  
**Helye:** Az oldal tetején, ha fut aktív értékelés  
**Mit csinál:** Lezárja az aktív értékelési időszakot. Ezután a rendszer kiszámítja a bónusz-málusz szinteket, és az eredmények elérhetővé válnak.  
**Korlátozások:** Nem zárhatsz le értékelést, ha van rendezetlen számla. Először a számlát kell kiegyenlíteni.

### Fizetési oldal megnyitása
**Gomb:** "Lezárás előtt fizetés"  
**Helye:** Az oldal tetején, ha van rendezetlen számla  
**Mit csinál:** Átirányít a számlázási oldalra, ahol kiegyenlítheted a függő számlákat.  
**Korlátozások:** Nincs korlátozás, bármikor elérhető, ha van nyitott számla.

---

## Lépések: Értékelési időszak indítása

### 1. Kattints az "Értékelési időszak indítása" gombra
Ha nincs aktív értékelés, az oldalon megjelenik egy kártyán az "Értékelési időszak indítása" gomb.

**Gomb:** "Értékelési időszak indítása"  
**Helye:** Az oldal tetején, az értékelési állapot területen

### 2. Állítsd be a határidőt
A megnyíló ablakban válaszd ki, hogy meddig tartsanak az értékelések.

**Mezők:**
- **Határidő** - Válaszd ki a dátumot, ameddig az értékeléseket be kell nyújtani. Minimum 1 nappal a mai naptól kell lennie.

**Fontos:** Az értékelési időszak alatt nem módosíthatod a rendszer más beállításait (például küszöbértékeket vagy kompetenciákat).

### 3. Mentsd el az értékelési időszakot
Kattints a "Mentés" gombra, és erősítsd meg a műveletet.

---

## Lépések: Értékelési időszak lezárása

### 1. Ellenőrizd a statisztikákat
Mielőtt lezárnád az értékelést, nézd meg az oldal közepi statisztikákat:
- **Eddig kitöltött értékelések:** Hány értékelés készült el az összes szükségesből
- **Eddigi vezetői rangsorolások:** Hány vezetői rangsor készült el az összes szükségesből

### 2. Kattints a "Értékelési időszak lezárása" gombra
Ha minden rendben van (vagy elfogadod, hogy nem minden értékelés készült el), kattints a lezárás gombra.

**Gomb:** "Értékelési időszak lezárása"  
**Helye:** Az oldal tetején, az értékelési állapot területen

**Fontos:** Ha van rendezetlen számla, előbb a "Lezárás előtt fizetés" gombra kell kattintanod, és ki kell egyenlítened a számlát.

### 3. Erősítsd meg a lezárást
A rendszer megerősítést kér. Ha biztos vagy benne, kattints az "Igen" gombra.

**Figyelem:** A lezárás után a rendszer automatikusan kiszámítja a bónusz-málusz szinteket és a negyedéves bónuszokat.

---

## Statisztikák értelmezése

### Értékelési állapot kártyák (fent)
Az oldal tetején két nagy kártya mutatja az aktuális értékelési állapotot:

**Értékelési időszak fut:**
- Zöld kártya: Fut az értékelés, látható a kezdés és a határidő dátuma
- Szürke kártya: Nincs aktív értékelés

### Statisztikai kártyák (középen)
Két statisztikai kártya mutatja a kitöltések állapotát:

**Eddig kitöltött értékelések:**
- Zöld: Minden értékelés elkészült
- Kék: Még vannak hiányzó értékelések
- Számok: "Elkészült / Összes szükséges"

**Eddigi vezetői rangsorolások:**
- Zöld: Minden rangsorolás elkészült
- Kék: Még vannak hiányzó rangsorolások
- Számok: "Elkészült / Összes szükséges"

### Alkalmazottak részletesen (lent)
Minden alkalmazotthoz külön kártya mutatja a részletes előrehaladást:

**Zöld kártya:** Az alkalmazott befejezte az összes értékelést és az önértékelést is
**Piros kártya:** Az alkalmazottnak még vannak hiányzó értékelései

**Egy kártyán látható információk:**
- **Értékelendő személyek:** Hány kollégát kellene értékelnie
- **Értékelt személyek:** Hány kollégát értékelt már
- **Önértékelés:** Pipa = elkészült, X = még nem készült el

---

## Korlátozások és Feltételek

### ❌ Nem indíthatsz új értékelést, ha:
- Már fut egy aktív értékelési időszak
- Nem vagy adminisztrátor jogosultsággal
- A kezdeti számlát még nem egyenlítetted ki

### ❌ Nem zárhatsz le értékelést, ha:
- Van rendezetlen (fizetetlen) számla a rendszerben
- Először a "Lezárás előtt fizetés" gombbal menj a számlázási oldalra

### ⚠️ Figyelem:
- Az értékelési időszak alatt nem módosíthatod a kompetenciákat, beállításokat vagy küszöbértékeket
- A lezárás után az eredmények véglegesek, nem módosíthatók
- A lezáráskori számítások alapján a bónuszok automatikusan kiszámításra kerülnek

---

## Hibaüzenetek

### "Nem sikerült betölteni az értékelést."
**Mikor jelenik meg:** Amikor megpróbálod módosítani egy értékelés határidejét, de a rendszer nem tudja betölteni az adatokat.  
**Megoldás:**
1. Frissítsd az oldalt (F5)
2. Próbáld újra megnyitni a módosítást
3. Ha továbbra sem működik, lépj ki és jelentkezz be újra

### "Lezárás előtt ki kell egyenlíteni a számlát"
**Mikor jelenik meg:** Amikor meg akarod nyomni a lezárás gombot, de van rendezetlen számla.  
**Megoldás:**
1. Kattints a "Lezárás előtt fizetés" gombra
2. Egyenlítsd ki a nyitott számlákat
3. Térj vissza a főoldalra és zárd le az értékelést

---

## GYIK (Gyakran Ismételt Kérdések)

**Mikor érdemes indítani egy új értékelési időszakot?**
Általában negyedévenként vagy félévente szokás értékelést indítani, hogy rendszeres visszajelzést kapj a csapatról. A határidőt úgy állítsd be, hogy legalább 2 hét álljon rendelkezésre a kitöltésre.

**Mi történik, ha valaki nem fejezi be az értékelést határidőre?**
Lezárhatod az értékelést hiányos állapotban is, de azok az alkalmazottak, akiknek hiányos az értékelésük, nem kapnak teljes képet az eredményeikről. Hiányzó komponensek esetén a bónusz-málusz számítás is pontatlanabb lesz.

**Módosíthatom a határidőt futó értékelés közben?**
Igen, a "Határidő módosítása" gombbal bármikor módosíthatod a határidőt, amíg az értékelési időszak fut. Így rugalmasan kezelheted, ha több időre van szükség.

**Mi van, ha rendezetlen számlám van lezáráskor?**
A rendszer nem engedélyezi a lezárást, amíg van nyitott számla. Kattints a "Lezárás előtt fizetés" gombra, majd egyenlítsd ki a számlát a számlázási oldalon. Ezután visszajöhetsz és lezárhatod az értékelést.

**Miért nem módosíthatom a kompetenciákat futó értékelés alatt?**
Az értékelési időszak alatt a rendszer nem engedi módosítani a kompetenciákat, küszöbértékeket és egyéb beállításokat, hogy biztosítsa a folyamat következetességét. Zárd le az aktuális értékelést, és az új beállítások a következő értékelési időszakban lépnek életbe.

**Mit jelent a zöld és piros kártya az alkalmazottak részletezésében?**
A zöld kártya azt jelenti, hogy az alkalmazott befejezte az összes értékelését (kollégák + önértékelés). A piros kártya azt jelzi, hogy még vannak hiányzó értékelések.

**Törölhetek egy már elindított értékelési időszakot?**
Nem, egy elindított értékelési időszakot nem törölhetsz, csak lezárhatod. Ha mégis meg akarod szakítani, lépj kapcsolatba a Quarma360 ügyfélszolgálatával.

**Mi történik a lezárás után?**
A lezárás után a rendszer automatikusan kiszámítja minden alkalmazott bónusz-málusz szintjét a beállított küszöbértékek alapján, majd ezek alapján kiszámítja a negyedéves bónuszokat. Az eredmények ezután megtekinthetők az Eredmények oldalon.

**Hogyan tudom ellenőrizni, ki nem töltötte ki még az értékelést?**
Az oldal alján az "Alkalmazottak részletesen" részben minden dolgozóhoz látsz egy kártyát. A piros kártyák jelzik, hogy kinek vannak még hiányzó értékelései. A kártyán látod pontosan, hogy hány értékelést kell még kitöltenie.

**Lehet egyszerre több aktív értékelési időszakom?**
Nem, a rendszer egyszerre csak egy aktív értékelési időszakot engedélyez. Zárd le az aktuális időszakot, mielőtt újat indítanál.

---

## Kapcsolódó oldalak

- **[Alkalmazottak](/admin/employee/index)**: Alkalmazottak kezelése, új dolgozók felvétele, kapcsolatok kialakítása
- **[Kompetenciák](/admin/competency/index)**: Kompetenciák és értékelési kérdések létrehozása és szerkesztése
- **[Eredmények](/admin/results/index)**: Lezárt értékelési időszakok eredményeinek megtekintése
- **[Beállítások](/admin/settings/index)**: Rendszerbeállítások és küszöbértékek módosítása
- **[Vezetői rangsor](/admin/ceoranks/index)**: Vezetői rangsorolások megtekintése és kezelése
- **[Számlázás](/admin/payments/index)**: Számlák megtekintése és kifizetése