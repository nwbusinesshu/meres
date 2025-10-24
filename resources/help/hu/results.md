---
view: results
title: Eredmények
role: all
visible_to: [employee, manager, ceo, admin]
related: [/home]
actions:
  - { label: "Előző lezárt időszak", trigger: "navigate-previous", permission: "all" }
  - { label: "Következő lezárt időszak", trigger: "navigate-next", permission: "all" }
  - { label: "Vissza az összesített eredményekhez", trigger: "back-to-admin", permission: "admin" }
keywords: [eredmények, saját eredmények, my results, performance, teljesítmény, idősoros, time series, grafikon, chart, kompetencia bontás, competency breakdown, bónusz, bonus, málusz, malus, értékelési komponensek, önértékelés, kollégák, beosztottak, vezetők, CEO rangsor, trend, fejlődés, progress, telemetria, trust score]


<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

## Technikai részletek (AI számára)

**Controller:** `ResultsController`  

**Főbb metódusok:**
- `index(Request $request, ?int $assessmentId = null)` - Display individual user results with charts and historical trends
- `calculateCompetencyScores(int $assessmentId, int $userId)` - Calculate average scores per competency for bar chart
- `getLocalizedCompetencyName()` - Get competency names in current locale

**Routes:**
- `results.index` - GET /results/{assessmentId?} (normal users)

**Permissions:** 
- Middleware: `auth:UserType::NORMAL`, `org`
- Available to employees, managers, and CEOs
- Admins can view any user's results by passing `as` parameter
- Users can only view their own results (filtered by session uid)

**Key Database Tables:**
- `assessments` - Assessment periods (only closed ones shown)
- `users` - User data
- `user_result_snapshot` - Cached results (org_snapshot JSON)
- `user_bonus_malus` - Bonus/Malus levels per user per assessment
- `assessment_bonuses` - Calculated bonus amounts (visible if employees_see_bonuses enabled)
- `competency_submit` - Individual competency scores for bar chart
- `user_competency_submit` - Telemetry data for admin view (trust scores, flags)

**JavaScript Files:**
- Inline JS in blade file - Chart.js initialization for time-series line chart and competency bar chart
- CircularProgressBar for pie chart (current assessment display)

**Translation Keys:**
- `lang/hu/results.php` - Hungarian translations
- `lang/en/results.php` - English translations
- `lang/hu/global.php` - Global bonus-malus level translations

**Key Features:**
- **Period Navigation:** Navigate between closed assessments with prev/next arrows
- **Cached Results:** Uses `user_result_snapshot.org_snapshot` for fast loading
- **Component Display:** Shows 5 components (self, colleagues, direct_reports, managers, ceo_rank)
- **Missing Components:** Dynamically hides unavailable components with yellow "Hiányzó" badges
- **Time-Series Chart:** Line chart showing total score and component scores across all closed assessments
- **Competency Breakdown:** Horizontal bar chart showing average score per competency
- **Bonus Display:** Shows calculated bonus amount if `employees_see_bonuses` config enabled
- **AI Telemetry (Admin Only):** Shows trust scores and behavioral flags when admin views user results
- **Trend Indicators:** Up/down/stable arrows showing performance changes between periods

**Data Structure (org_snapshot JSON per user):**
```json
{
  "total": 85.5,
  "self": 90.0,
  "colleague": 82.3,
  "direct_reports": 88.5,
  "manager": 84.0,
  "ceo": 85.0,
  "bonus_malus_level": "B03",
  "change": "up",
  "components_available": 5,
  "missing_components": [],
  "complete": true
}
```

**Validations (backend):**
- Only closed assessments (`closed_at IS NOT NULL`) are displayed
- Users can only view their own results (unless admin with `as` parameter)
- Assessment must belong to current organization
- Competency scores filtered by assessment and target user

**Business Logic:**
- Results calculated and cached when assessment closes (not real-time)
- History array built from all closed assessments for trend chart
- Missing components (e.g., no direct reports for non-managers) automatically hidden
- Bonus data only visible if `show_bonus_malus` AND `employees_see_bonuses` both enabled
- AI telemetry only visible to admins viewing user results
- Chart scaling: Y-axis always 0-100 for consistency
- Competency names localized using current language setting

<!-- TECHNICAL_DETAILS_END -->
---

---

# Mi ez az oldal?

A Saját Eredményeim oldal az a hely, ahol megtekintheted saját értékelési eredményeidet minden lezárt értékelési időszakra vonatkozóan. Itt láthatod részletesen, hogy az egyes komponensekben (önértékelés, kollégák, beosztottak, vezetők, ügyvezetői rangsor) milyen pontszámot értél el, hogyan változott a teljesítményed az idő múlásával, és kompetenciánként melyik területen teljesítettél jobban vagy gyengébben.

Az oldal célja, hogy átlátható képet kapj saját fejlődésedről, azonosítsd erősségeidet és fejlesztendő területeidet, valamint nyomon kövesd bónusz/málusz besorolásodat (ha a funkció engedélyezett a szervezetedben).

## Kiknek látható ez az oldal?

**Adminisztrátorok:** Teljes hozzáférés bármelyik dolgozó eredményeihez. Láthatják az összes komponens részletét, időbeli trendet, kompetencia bontást, bónusz adatokat, és egy speciális AI telemetria szekciót is, amely megmutatja, hogy az adott dolgozó milyen megbízhatóan értékelt másokat.

**Ügyvezetők (CEO):** Láthatják saját eredményeiket minden lezárt időszakra. Mivel ők nem kapnak felülről rangsorolást, a "Vezetők értékelése" és "Ügyvezetői rangsor" komponensek nem jelennek meg számukra (helyettük "Hiányzó" jelzés látható). Láthatják viszont a beosztottjaik által adott értékelést.

**Vezetők (Manager):** Láthatják saját eredményeiket minden lezárt időszakra. Számukra minden komponens elérhető: önértékelés, kollégák értékelése, beosztottak értékelése, vezetők értékelése és ügyvezetői rangsor.

**Alkalmazottak:** Láthatják saját eredményeiket minden lezárt időszakra. Mivel nincs beosztottjuk, a "Beosztottak értékelése" komponens nem jelenik meg (helyette "Hiányzó" jelzés látható).

---

## Mit tudsz itt csinálni?

### Értékelési időszakok közötti navigálás
**Gombok:** Bal oldali nyíl (előző időszak), Jobb oldali nyíl (következő időszak)  
**Helye:** Az oldal tetején, a dátum mindkét oldalán  
**Mit csinálnak:** Lehetővé teszik, hogy gyorsan váltogass a különböző lezárt értékelési időszakok között és megtekintsd, hogyan változott a teljesítményed az idő múlásával.  
**Korlátozások:** A gombok inaktívak (szürkék), ha nincs korábbi vagy későbbi lezárt értékelés.

### Aktuális eredmények megtekintése
**Helye:** Az oldal felső részén, színes kördiagrammal  
**Mit mutat:**
- **Összpontszám:** A teljes értékelési pontszámod 0-100 skálán, kördiagram formájában
- **Komponens pontszámok:** Az egyes értékelési komponensek részletes pontszámai:
  - **Önértékelés:** A saját magadról adott értékelésed átlaga
  - **Kollégák értékelése:** A munkatársaid által adott értékelések átlaga
  - **Beosztottak értékelése:** A közvetlen beosztottjaid által adott értékelések átlaga (csak vezetőknél jelenik meg)
  - **Vezetők értékelése:** A felettes vezetőid által adott értékelések átlaga (nem ügyvezetőknél)
  - **Ügyvezetői rangsor:** Az ügyvezetői rangsorban elfoglalt helyezésed pontértéke (nem ügyvezetőknél)
- **Bónusz/Málusz szint:** Az aktuális bónusz/málusz kategóriád (pl. B03, A00, M02) - csak akkor látható, ha engedélyezve van
- **Változás:** Nyíl jelzi, hogy az előző időszakhoz képest javult (↑), romlott (↓) vagy változatlan (→) a teljesítményed

**Hiányzó komponensek:** Ha valamelyik komponens nem áll rendelkezésre (például nincs beosztottad), akkor sárga "Hiányzó" jelvény jelenik meg a komponens neve mellett.

### Teljesítmény trendjének követése (Idősoros grafikon)
**Helye:** Az aktuális eredmények alatt, vonaldiagram formájában  
**Mit mutat:** Az összes lezárt értékelési időszakot ábrázolja időrendben, megmutatva:
- **Összpontszám alakulása:** Vastag kék vonal mutatja a teljes pontszámod változását
- **Komponensek alakulása:** Külön színű vonalak mutatják az egyes komponensek (önértékelés, kollégák, beosztottak, vezetők) pontszámainak alakulását

**Használat:** Görgess a kurzort a vonalak fölé, hogy pontos értékeket láss minden egyes időszakra. A grafikon segít azonosítani hosszú távú trendeket és szezonális változásokat a teljesítményedben.

### Kompetencia szerinti bontás megtekintése (Oszlopdiagram)
**Helye:** Az idősoros grafikon alatt, vízszintes oszlopdiagram formájában  
**Mit mutat:** Az aktuális értékelési időszakban elért átlagos pontszámodat minden egyes kompetenciára lebontva. Ez megmutatja, hogy mely területeken vagy erősebb vagy gyengébb.

**Használat:** Azonosítsd azokat a kompetenciákat, ahol alacsonyabb pontszámot értél el, és tervezd meg a fejlődésedet ezeken a területeken.

### Bónusz összeg megtekintése (ha engedélyezett)
**Helye:** Az oldal alján, kiemelten  
**Mit mutat:** Az aktuális értékelési időszakra vonatkozó kalkulált bónusz összegét nettóban (Ft), ha a munkáltató engedélyezte az alkalmazottaknak a bónusz összegek megtekintését.  
**Mikor látható:** Csak akkor jelenik meg, ha:
1. A "Bónusz/Málusz rendszer" be van kapcsolva a szervezetnél
2. A "Bónusz számítás engedélyezve" be van kapcsolva
3. Az "Alkalmazottak láthatják a bónuszokat" be van kapcsolva

**Fontos:** Ha ez a szekció nem látszik, akkor a szervezeted úgy döntött, hogy a bónusz összegeket nem jelenítik meg az alkalmazottaknak közvetlenül.

### Vissza az összesített eredményekhez (csak adminoknak)
**Gomb:** "Vissza az összesített eredményekhez"  
**Helye:** Az oldal tetején (csak akkor jelenik meg, ha admin vagy és egy másik dolgozó eredményeit nézed)  
**Mit csinál:** Visszanavigál az adminisztrátori összesített eredmények oldalra, ahol az összes dolgozó eredményeit láthatod egyszerre.

---

## Lépések: Eredmények áttekintése és elemzése

### 1. Válaszd ki a megfelelő időszakot
Használd a bal és jobb oldali nyilakat a különböző lezárt értékelési időszakok közötti navigáláshoz.

### 2. Nézd meg az összpontszámodat
A kördiagram gyors vizuális áttekintést ad arról, hogyan teljesítettél az adott időszakban.

### 3. Elemezd a komponens pontszámokat
Görgess le a részletes pontszámokhoz. Nézd meg, melyik komponensben értél el magas vagy alacsony pontszámot:
- **Magas önértékelés, alacsony kollégák értékelése:** Lehet, hogy túlbecsülöd magad, vagy a kollégák nem látják ugyanazokat az erősségeket
- **Alacsony önértékelés, magas mások értékelése:** Lehet, hogy alulbecsülöd magad
- **Minden komponens hasonló:** Konzisztens teljesítmény, jó önismeret

### 4. Kövesd a trendet az idősoros grafikonon
Nézd meg, hogyan változott a teljesítményed az idő múlásával:
- **Emelkedő trend:** Folyamatosan javulsz, gratulálunk!
- **Csökkenő trend:** Érdemes azonosítani az okokat és fejlesztési tervet készíteni
- **Stabil trend:** Következetes teljesítmény, de lehet, hogy továbblépési lehetőségek vannak

### 5. Azonosítsd a fejlesztendő kompetenciákat
A kompetencia szerinti bontás oszlopdiagramon nézd meg, mely területeken értél el alacsonyabb pontszámot. Ezeket érdemes priorizálni a következő időszakban.

### 6. Tervezd meg a fejlődésedet
Az eredmények alapján készíts egy személyes fejlesztési tervet:
- Mely kompetenciákon kell dolgoznod?
- Milyen képzéseken vehetnél részt?
- Kitől kérhetsz mentorálást vagy segítséget?

---

## Korlátozások és Feltételek

### ❌ Nem tekintheted meg, ha:
- Még nem zártak le egyetlen értékelési időszakot sem a szervezetedben
- Nem vagy bejelentkezve
- Nincs aktív szervezeted kiválasztva

### ⚠️ Figyelem:
- **Csak lezárt értékelések láthatók:** Az éppen futó értékelési időszak eredményei még nem jelennek meg, mivel azok csak a lezárás után kerülnek kiszámításra
- **Hiányzó komponensek normálisak:** Ha valamelyik komponens "Hiányzó" jelzést mutat, az azért van, mert az adott értékelési típus nem releváns a szerepkörödre (pl. nincs beosztottad, vagy te magad vagy az ügyvezető)
- **Gyorsítótárazott adatok:** Az eredmények a lezáráskor kerülnek elmentésre és rögzítésre, így a későbbi változtatások (például kompetencia súlyok módosítása) nem befolyásolják a már lezárt időszakok eredményeit
- **Bónusz láthatóság:** A bónusz összegek megjelenítése teljes mértékben a szervezet adminisztrátorának döntésén múlik. Ha nem látod a bónusz adatokat, az nem jelenti azt, hogy ne kapnál bónuszt, csak azt, hogy a szervezet ezt az információt nem jeleníti meg közvetlenül

---

## Hibaüzenetek

### "Még nincsenek megjeleníthető eredmények!"
**Mikor jelenik meg:** Amikor még egyetlen értékelési időszakot sem zártak le a szervezetedben, vagy nem volt résztvevője egy lezárt értékelésnek sem.  
**Megoldás:**
1. Várj, amíg az adminisztrátor elindítja és lezárja az első értékelési időszakot
2. Győződj meg róla, hogy a következő értékelési időszakban kitöltöd az értékeléseket
3. Ha már volt lezárt értékelés, de még mindig ezt az üzenetet látod, jelezd az adminisztrátornak

### Hiányzó komponensek (sárga jelvények)
**Mikor jelenik meg:** Amikor egy értékelési komponens nem áll rendelkezésre a szerepkörödre.  
**Példák:**
- **"Beosztottak értékelése - Hiányzó":** Nincs közvetlen beosztottad, ezért ez a komponens nem releváns
- **"Vezetők értékelése - Hiányzó":** Ügyvezető vagy, így nincs felettes vezetőd
- **"Ügyvezetői rangsor - Hiányzó":** Ügyvezető vagy, így te rangsorolod a többieket, téged nem rangsorolnak

**Megoldás:** Ez nem hiba, hanem a rendszer normális működése. A hiányzó komponensek automatikusan ki vannak szűrve az összpontszám számításából.

---

## GYIK (Gyakran Ismételt Kérdések)

**1. Miért nem jelenik meg a legutóbbi értékelési időszak eredménye?**  
Az eredmények csak akkor jelennek meg, amikor az adminisztrátor lezárta az értékelési időszakot. A lezárás után néhány percen belül láthatóvá válnak az eredmények.

**2. Miért látok "Hiányzó" feliratot egyes komponenseknél?**  
Egyes értékelési komponensek nem relevánsak minden szerepkörre. Például ha nincs beosztottad, akkor nem kapsz "Beosztottak értékelése" pontszámot. Ez teljesen normális és nem befolyásolja negatívan az összpontszámodat.

**3. Hogyan számolódik ki az összpontszámom?**  
Az összpontszám súlyozott átlaga az elérhető komponenseknek. Minden komponensnek van egy súlya (például Önértékelés: 20%, Kollégák: 30%, stb.), és az összpontszám ezen súlyok alapján kerül kiszámításra. A hiányzó komponensek súlyai automatikusan újraosztódnak az elérhető komponensek között.

**4. Miért nem látom a bónusz összegeket?**  
A bónusz összegek megjelenítése a szervezet adminisztrátorának döntésén múlik. Ha a "Bónusz/Málusz rendszer" vagy az "Alkalmazottak láthatják a bónuszokat" funkció nincs engedélyezve, akkor nem látod a bónusz összegeket az eredmények oldalon.

**5. Mit jelent a nyíl az összpontszámom mellett?**  
A nyíl mutatja, hogy az előző értékelési időszakhoz képest javult (↑), romlott (↓) vagy változatlan maradt (→) a teljesítményed. Ez gyors vizuális visszajelzés arról, hogy jó irányba haladsz-e.

**6. Minek van jelentősége a kompetencia szerinti bontásnak?**  
A kompetencia szerinti bontás megmutatja, hogy mely területeken vagy erősebb vagy gyengébb. Ez segít célzott fejlesztési tervet készíteni és priorizálni a tanulási lehetőségeket.

**7. Hogyan javíthatom az eredményeimet?**  
Az eredmények javításához:
- Nézd meg a kompetencia bontást és azonosítsd a gyenge területeket
- Beszélj a vezetőddel és kérj visszajelzést
- Vegyél részt képzéseken és fejlesztési programokon
- Kérj mentorálást tapasztaltabb kollégáktól
- A következő értékelési időszakban koncentrálj a fejlesztendő kompetenciákra

**8. Miért térnek el az egyes komponensek pontszámai?**  
Az eltérések normálisak és több okból előfordulhatnak:
- Különböző emberek különböző szempontból értékelnek (például a kollégák mást látnak, mint a vezetők)
- Az önértékelés gyakran eltér mások értékelésétől (túl- vagy alulbecslés)
- Egyes területeken jobban teljesítesz, mint másokban

**9. Mennyi ideig maradnak meg az eredmények?**  
Az eredmények korlátlan ideig megmaradnak az adatbázisban és bármikor visszanézheted az összes korábbi értékelési időszak eredményeit.

**10. Miért nem tudom szerkeszteni az eredményeimet?**  
Az eredmények automatikusan számolódnak ki a lezárt értékelések alapján és nem szerkeszthetők. Ez biztosítja az adatok integritását és megbízhatóságát. Ha úgy érzed, hogy valami hiba történt az értékelés során, beszélj az adminisztrátorral.

---

## Kapcsolódó oldalak

- **[Főoldal](/home)**: Értékelési feladatok áttekintése, határidők megtekintése, kitöltendő értékelések elérése