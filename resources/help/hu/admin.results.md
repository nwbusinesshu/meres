---
view: admin.results
title: Eredmények
role: admin
visible_to: [admin]
related: [/admin/home, /admin/employee/index, /admin/settings/index, /admin/ceoranks/index,]
actions:
  - { label: "Előző lezárt időszak", trigger: "navigate-previous", permission: "admin" }
  - { label: "Következő lezárt időszak", trigger: "navigate-next", permission: "admin" }
  - { label: "Alkalmazott eredményeinek megnyitása", trigger: "open-user-results", permission: "admin" }
keywords: [eredmények, results, értékelés, assessment, összesítő, statistics, bónusz málusz, bonus malus, CEO rangsor, teljesítmény, performance, küszöb, threshold, AI összefoglaló, lezárt időszak, closed period, statisztikák, komponensek, telemetria, trust index]

<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

## Technikai részletek (AI számára)

**Controller:** `AdminResultsController`  

**Főbb metódusok:**
- `index(Request $request, ?int $assessmentId = null)` - Main results page with assessment period navigation and user statistics display

**Routes:**
- `admin.results.index` - GET with optional assessmentId parameter

**Permissions:** 
- Middleware: `auth`, `OrgRole::ADMIN`, `org`
- Only organization administrators can view aggregated results
- Filters out superadmins and org admins from user list

**Key Database Tables:**
- `assessments` - Assessment periods (only closed ones shown)
- `users` - Employee data
- `user_result_snapshot` - Cached results for performance (JSON column with org_snapshot)
- `organization_user` - Organization membership and roles
- `user_bonus_malus` - Bonus/Malus levels per assessment
- `assessment_bonuses` - Calculated bonuses per user per assessment

**JavaScript Files:**
- Inline JS in blade file - CircularProgressBar initialization for pie charts
- No separate JS file

**Translation Keys:**
- `lang/hu/admin/results.php` - Hungarian translations
- `lang/en/admin/results.php` - English translations
- `lang/hu/results.php` - Shared results translations
- `lang/hu/global.php` - Global bonus-malus level translations

**Key Features:**
- **Period Navigation:** Navigate between closed assessment periods using prev/next arrows
- **Threshold Display:** Shows upper/lower score limits and calculation method for each period
- **AI Summary Display:** For "suggested" threshold mode, displays AI-generated summary in Hungarian
- **Cached Results:** Uses `user_result_snapshot.org_snapshot` JSON for 100x-1000x faster loading
- **Component Visibility:** Dynamically shows/hides missing components (self, colleagues, direct_reports, managers, ceo_rank)
- **CEO Badge:** Visual indicator for CEO role employees
- **Bonus/Malus Display:** Shows bonus/malus level if `show_bonus_malus` config enabled
- **Click-through Access:** Each employee tile links to individual detailed results page

**Data Structure (org_snapshot JSON):**
```json
{
  "total": 85.5,
  "selfTotal": 90.0,
  "colleagueTotal": 82.3,
  "directReportsTotal": 88.5,
  "managersTotal": 84.0,
  "ceoRankTotal": 85.0,
  "bonus_malus_level": "B03",
  "change": "up",
  "components_available": 5,
  "missing_components": [],
  "is_ceo": false,
  "complete": true
}
```

**Validations (backend):**
- Only closed assessments (`closed_at IS NOT NULL`) are displayed
- Users without cached results are filtered out
- Assessment must belong to current organization

**Business Logic:**
- Results are calculated and cached when assessment is closed (not real-time)
- Navigation arrows disabled when no previous/next closed period exists
- AI summary only displayed when `threshold_method === 'suggested'` and summary exists in `suggested_decision` JSON
- Missing components automatically hidden from display
- CEO badge shown when `is_ceo === true` in snapshot
- Thresholds displayed: `normal_level_up`, `normal_level_down`, `threshold_method`

<!-- TECHNICAL_DETAILS_END -->
---

# Mi ez az oldal?

Az Eredmények az adminisztrátorok számára készült központi oldal, ahol áttekintheted az összes lezárt értékelési időszak eredményeit. Itt egy helyen láthatod, hogy minden alkalmazott milyen pontszámot ért el az egyes értékelési komponensekben, milyen bónusz/málusz szintet kapott, és hogyan változott a teljesítménye az előző időszakhoz képest.

Az oldal lehetővé teszi, hogy gyorsan áttekinthesd a teljes csapat teljesítményét, azonosítsd a kiemelkedő és a fejlesztésre szoruló dolgozókat, és részletes eredményeket nézz meg egy kattintással. A különböző értékelési időszakok között könnyedén navigálhatsz, így időbeli tendenciákat is követhetsz.

## Kiknek látható ez az oldal?

**Adminisztrátorok:** Teljes hozzáférés az összes lezárt értékelési időszak eredményeihez. Láthatják az összes alkalmazott összesített és részletes statisztikáit, bónusz/málusz szintjeit, és közvetlenül megnyithatják bármelyik dolgozó részletes eredményeit.

**Ügyvezetők (CEO):** Ez az oldal nem érhető el számukra. Az ügyvezetők csak a saját és a beosztottjaik eredményeit tekinthetik meg a normál felhasználói eredmények oldalon.

**Vezetők (Manager):** Ez az oldal nem érhető el számukra. A vezetők csak a saját és a beosztottjaik eredményeit tekinthetik meg a normál felhasználói eredmények oldalon.

**Alkalmazottak:** Ez az oldal nem érhető el számukra. Az alkalmazottak csak a saját eredményeiket láthatják a normál felhasználói eredmények oldalon.

---

## Mit tudsz itt csinálni?

### Értékelési időszakok közötti navigálás
**Gombok:** Bal oldali nyíl (előző időszak), Jobb oldali nyíl (következő időszak)  
**Helye:** Az oldal tetején, a dátum mindkét oldalán  
**Mit csinálnak:** Lehetővé teszik, hogy gyorsan váltogass a különböző lezárt értékelési időszakok között. A középen látható dátum mutatja az aktuálisan megjelenített időszakot.  
**Korlátozások:** A gombok inaktívak (szürkék), ha nincs korábbi vagy későbbi lezárt értékelés.

### Küszöbértékek megtekintése
**Helye:** Az időszak navigáció alatt, színes csempék formájában  
**Mit mutatnak:**
- **Felső ponthatár:** Az az értékelési pontszám, amely felett az alkalmazott előlép a bónusz/málusz skálán
- **Alsó ponthatár:** Az az értékelési pontszám, amely alatt az alkalmazott visszalép a bónusz/málusz skálán
- **Módszer:** A küszöbértékek kiszámításának módja (FIXED, HYBRID, DYNAMIC, SUGGESTED)

**Fontos:** Ezek az értékek minden értékelési időszakra külön-külön vannak meghatározva és elmentve. Az itt látható értékek azt mutatják, hogy milyen szabályok alapján számolódtak ki a bónusz/málusz szintek az adott időszakban.

### AI összefoglaló olvasása (SUGGESTED módnál)
**Helye:** A küszöbértékek alatt, sárga háttérrel  
**Mit mutat:** Ha az értékelési időszakhoz "SUGGESTED" (AI-javasolt) küszöbszámítási módszert használtál, itt jelenik meg a mesterséges intelligencia által generált magyar nyelvű összefoglaló. Ez egy rövid elemzés arról, hogy miért javasolja az AI az adott küszöbértékeket.  
**Mikor látható:** Csak akkor jelenik meg, ha a küszöbszámítási módszer "SUGGESTED" és az AI sikeresen generált összefoglalót.

### Alkalmazottak eredményeinek áttekintése
**Helye:** Az oldal fő területe, színes csempék formájában  
**Mit mutatnak a csempék:**
- **Név és CEO jelvény:** Az alkalmazott neve, és ha ügyvezető, akkor lila "CEO" jelvény
- **Kördiagram:** Az összpontszám vizuális megjelenítése (0-100 skálán)
- **Komponens pontszámok:** A részletes pontszámok az értékelési komponensekre lebontva:
  - **Önértékelés:** Az alkalmazott saját magáról adott értékelése
  - **Kollégák:** A munkatársak által adott átlagos értékelés
  - **Beosztottak:** A közvetlen beosztottak által adott átlagos értékelés (csak vezetőknél)
  - **Vezetők:** A felettes vezetők által adott átlagos értékelés
  - **Ügyvezetők:** Az ügyvezetői rangsorban elfoglalt helyezés pontértéke
- **Hiányzó komponensek:** Ha valamelyik komponens nem áll rendelkezésre, sárga "Hiányzó" jelvény jelzi
- **Bónusz/Málusz szint:** Az alkalmazott bónusz/málusz kategóriája (pl. B03, A00, M02)

**Fontos:** Csak azok az alkalmazottak jelennek meg, akiknek van értékelhető eredménye az adott időszakban. Adminisztrátorok és szuperadminok nem szerepelnek a listában.

### Részletes eredmények megnyitása
**Hogyan:** Kattints bármelyik alkalmazott csempéjére  
**Mit csinál:** Új böngésző fülben megnyitja az adott alkalmazott részletes eredményeit, ahol láthatod:
- Idősoros teljesítmény grafikont
- Kompetencia szerinti bontást
- Részletes statisztikákat minden komponensre
- Vezetői rangsor pozíciót
- Bónusz/Málusz kalkulációkat (ha engedélyezve van)
- AI telemetria adatokat (ha engedélyezve van és admin vagy)

---

## Lépések: Értékelési eredmények áttekintése

### 1. Válaszd ki a megfelelő időszakot
Használd a bal és jobb oldali nyilakat az időszak kiválasztásához. A középen látható dátum mutatja az aktuálisan megjelenített értékelési időszakot.

### 2. Tekintsd át a küszöbértékeket
Nézd meg a színes csempéket, hogy értsd, milyen szabályok alapján lettek meghatározva a bónusz/málusz szintek:
- **Zöld csempe (↑):** Felső ponthatár - ezen felül előlépés
- **Piros csempe (↓):** Alsó ponthatár - ez alatt visszalépés
- **Kék csempe (⚙):** Számítási módszer

### 3. Olvasd el az AI összefoglalót (ha van)
Ha "SUGGESTED" módszert használtál, olvasd el a sárga csempében megjelenő AI elemzést a küszöbök indoklásáról.

### 4. Elemezd az alkalmazottak teljesítményét
Görgesd végig az alkalmazottak listáját:
- **Kördiagram:** Gyors vizuális áttekintés az összpontszámról
- **Komponens pontszámok:** Részletes bontás komponensenként
- **Hiányzó adatok:** Sárga "Hiányzó" jelvények jelzik, ha valamelyik komponens nem elérhető
- **Bónusz/Málusz szint:** Látható az aktuális jutalmazási kategória

### 5. Nyisd meg a részletes eredményeket
Kattints bármelyik alkalmazott csempéjére a részletes eredmények megtekintéséhez új fülben.

---

## Korlátozások és Feltételek

### ❌ Nem tekintheted meg, ha:
- Még nem zártál le egyetlen értékelési időszakot sem - ebben az esetben üres oldalt látsz üzenettel
- Nem vagy bejelentkezve adminisztrátorként
- Nincs aktív szervezeted kiválasztva

### ⚠️ Figyelem:
- **Csak lezárt értékelések láthatók:** Az éppen futó értékelési időszak eredményei nem jelennek meg, mivel még nem kerültek kiszámításra és rögzítésre
- **Gyorsítótárazott adatok:** Az eredmények a lezáráskor kerülnek elmentésre, így a későbbi módosítások (például kompetencia súlyok változása) nem befolyásolják a korábbi értékeléseket
- **Szűrt lista:** A listán csak azok az alkalmazottak jelennek meg, akiknek van értékelhető eredménye. Adminisztrátorok és rendszer adminok nem szerepelnek
- **Komponens láthatóság:** Ha egy alkalmazottnál egy komponens nem volt elérhető (például nincs beosztottja), akkor az a komponens nem jelenik meg a csempén

---

## Hibaüzenetek

### "Még nincsenek megjeleníthető eredmények!"
**Mikor jelenik meg:** Amikor még egyetlen értékelési időszakot sem zártál le, vagy az összes lezárt időszakban nincsenek értékelhető alkalmazottak.  
**Megoldás:**
1. Menj az adminisztrátori főoldalra
2. Indíts el egy új értékelési időszakot
3. Várj, amíg a dolgozók kitöltik az értékeléseket
4. Zárd le az értékelési időszakot
5. Az eredmények automatikusan megjelennek ezen az oldalon

### AI összefoglaló nem érhető el
**Mikor jelenik meg:** Amikor "SUGGESTED" módszert használtál, de az AI nem tudott összefoglalót generálni, vagy a generálás során hiba történt.  
**Megoldás:**
1. Ez technikai probléma, amit a rendszer nem tud automatikusan megoldani
2. Az értékelési eredmények ettől még helyesen kiszámolódtak
3. Az összefoglaló hiánya nem befolyásolja az eredmények pontosságát
4. Ha gyakran előfordul, ellenőrizd a rendszer beállításokat vagy jelezd a technikai támogatásnak

---

## GYIK (Gyakran Ismételt Kérdések)

**1. Miért nem látok egy alkalmazottat a listában?**  
Lehetséges okok: (1) Az alkalmazott adminisztrátor vagy szuperadmin, akik nem jelennek meg, (2) Az alkalmazott nem volt aktív tag a szervezetben az értékelés idején, (3) Az alkalmazott egyáltalán nem kapott értékelést egyetlen komponensben sem.

**2. Mit jelentenek a kördiagramon lévő számok?**  
A nagy szám az alkalmazott összpontszámát mutatja 0-100 skálán. Ez a súlyozott átlag az összes elérhető értékelési komponens alapján.

**3. Miért látok "Hiányzó" jelvényeket egyes alkalmazottoknál?**  
A "Hiányzó" jelvény azt jelzi, hogy az adott értékelési komponens nem állt rendelkezésre az alkalmazottnál. Például egy nem-vezető pozícióban lévő dolgozónak nincs "Beosztottak" komponense, mert nincs senki, aki őt beosztottként értékelhetné.

**4. Miért változnak a küszöbértékek értékelési időszakonként?**  
A küszöbértékek az Értékelési Beállításokban választott számítási módszertől függenek. FIXED módban mindig ugyanazok, DYNAMIC és SUGGESTED módoknál viszont időszakonként újraszámolódnak a szervezet teljesítménye alapján.

**5. Hogyan tudom összehasonlítani két időszak eredményeit?**  
A legjobb módszer, ha megnyitod egy alkalmazott részletes eredményeit (kattintás a csempére), ahol idősoros grafikon mutatja a teljesítmény változását az összes lezárt időszakon keresztül.

**6. Mit jelent a CEO jelvény?**  
A lila "CEO" jelvény jelzi, hogy az adott alkalmazott ügyvezető szerepkörrel rendelkezik a szervezetben.

**7. Módosíthatom utólag egy lezárt értékelés eredményeit?**  
Nem. A lezárt értékelések eredményei rögzítettek és nem módosíthatók. Ez biztosítja az adatok integritását és a transzparenciát. Ha hibát találsz, az következő értékelési időszakban korrigálódhat.

**8. Miért nem látok bónusz/málusz szinteket?**  
Lehetséges okok: (1) A bónusz/málusz rendszer ki van kapcsolva a Rendszer Beállításokban, (2) Az értékelés még azelőtt zárult le, hogy a funkció engedélyezésre került volna.

**9. Mit jelent az AI összefoglaló a SUGGESTED módnál?**  
Az AI összefoglaló egy mesterséges intelligencia által generált magyar nyelvű elemzés, amely megmagyarázza, hogy az AI miért javasolta az adott felső és alsó küszöbértékeket. Ez segít megérteni a döntési logikát.

**10. Mennyi időbe telik az eredmények betöltése?**  
Az oldal gyorsítótárazott adatokat használ, így akár több száz alkalmazott esetén is másodpercek alatt betöltődik. A gyorsítótár az értékelés lezárásakor jön létre automatikusan.

---

## Kapcsolódó oldalak

- **[Adminisztrátor Főoldal](/admin/home)**: Értékelési időszakok kezelése, új értékelés indítása, határidők módosítása és értékelés lezárása
- **[Alkalmazottak Kezelése](/admin/employee/index)**: Dolgozók hozzáadása, szerkesztése, kapcsolatok kezelése és bónusz/málusz szintek módosítása
- **[Rendszer Beállítások](/admin/settings/index)**: Bónusz/málusz rendszer be/kikapcsolása, küszöbszámítási módszer választása, AI telemetria beállítások
- **[Ügyvezetői Rangsor](/admin/ceoranks/index)**: Rangsor kategóriák létrehozása és kezelése az ügyvezetői értékeléshez