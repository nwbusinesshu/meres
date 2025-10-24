---
view: admin.competencies
title: Kompetenciák Kezelése
role: admin
visible_to: [admin]
related: [/admin/employees/index, /admin/assessment/index, /admin/results/index]
actions:
  - { label: "Kompetencia létrehozása", trigger: "create-competency", permission: "admin" }
  - { label: "Csoport létrehozása", trigger: "create-competency-group", permission: "admin" }
  - { label: "Fordítási nyelvek kiválasztása", trigger: "open-language-modal", permission: "admin" }
  - { label: "Kompetencia módosítása", trigger: "modify-competency", permission: "admin" }
  - { label: "Kompetencia törlése", trigger: "remove-competency", permission: "admin" }
  - { label: "Kérdés hozzáadása", trigger: "create-question", permission: "admin" }
  - { label: "Csoport módosítása", trigger: "modify-competency-group", permission: "admin" }
  - { label: "Csoport törlése", trigger: "remove-competency-group", permission: "admin" }
  - { label: "Felhasználók hozzárendelése", trigger: "assign-group-users", permission: "admin" }
keywords: [kompetencia, competency, értékelés, assessment, kérdések, questions, csoport, group, fordítás, translation, AI, alkalmazottak, employees, önértékelés, self-assessment, Likert-skála, rating scale]


<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

## Technikai részletek (AI számára)

**Controller:** `AdminCompetencyController`  

**Főbb metódusok:**
- `index()` - Oldal megjelenítése kompetenciákkal és csoportokkal
- `saveCompetency()` - Kompetencia létrehozása/módosítása fordításokkal
- `removeCompetency()` - Kompetencia törlése
- `saveCompetencyQuestion()` - Kérdés létrehozása/módosítása fordításokkal
- `removeCompetencyQuestion()` - Kérdés törlése
- `translateCompetencyName()` - AI fordítás kompetencia névhez
- `translateCompetencyQuestion()` - AI fordítás kérdésekhez
- `saveCompetencyGroup()` - Kompetencia csoport létrehozása/módosítása
- `removeCompetencyGroup()` - Kompetencia csoport törlése
- `saveCompetencyGroupUsers()` - Felhasználók hozzárendelése csoporthoz

**Routes:**
- `admin.competency.index` - GET (oldal betöltése)
- `admin.competency.save` - POST (kompetencia mentése)
- `admin.competency.remove` - POST (kompetencia törlése)
- `admin.competency.question.save` - POST (kérdés mentése)
- `admin.competency.question.remove` - POST (kérdés törlése)
- `admin.competency.translate-name` - POST (AI fordítás név)
- `admin.competency.translate-question` - POST (AI fordítás kérdés)
- `admin.competency-group.save` - POST (csoport mentése)
- `admin.competency-group.remove` - POST (csoport törlése)
- `admin.competency-group.users.save` - POST (felhasználók mentése)

**Permissions:** Admin middleware, organization context required

**Key Database Tables:**
- `competency` - Kompetenciák tárolása (organization_id, name, name_json, description, description_json, original_language)
- `competency_question` - Értékelési kérdések (competency_id, question, question_json, question_self, min_label, max_label, max_value)
- `competency_groups` - Kompetencia csoportok (organization_id, name, competency_ids JSON, assigned_users JSON)
- `user_competency` - Felhasználói kompetencia hozzárendelések
- `user_competency_sources` - Kompetencia forrás nyomon követése (manual/group)

**JavaScript Files:**
- `resources/views/admin/modals/competency.blade.php` - Kompetencia modal kezelés
- `resources/views/admin/modals/competencyq.blade.php` - Kérdés modal kezelés
- `resources/views/admin/modals/competency-group.blade.php` - Csoport modal kezelés
- `resources/views/admin/modals/language-select.blade.php` - Nyelv kiválasztó modal

**Translation Keys:**
- `lang/hu/admin/competencies.php` - Magyar fordítások
- `lang/en/admin/competencies.php` - Angol fordítások

**Key Features:**
- Multi-language support with JSON storage (name_json, description_json)
- AI-powered translation using AiTranslationService (OpenAI GPT-4o-mini)
- Competency groups with automatic user competency synchronization
- Fallback display for missing translations with visual indicators
- Global competencies (read-only) vs organizational competencies (editable)
- Likert-scale questions with customizable labels and scales
- Two question types: for rating others and for self-assessment

**Validations (backend):**
- Competency name required, max 255 characters
- Question fields required for all questions
- Group name required when creating groups
- Users must belong to organization when assigned to groups
- Cannot modify global competencies (organization_id = null)

**Business Logic:**
- Competencies can be standalone or part of groups
- Groups automatically sync competencies to assigned users
- Removing group removes group-sourced competencies from users (unless manually added)
- Translations stored in JSON with original_language tracking
- Missing translations shown with red italic text and (!) indicator
- AI translations preserve context for HR assessment terminology

<!-- TECHNICAL_DETAILS_END -->

---

# Mi ez az oldal?

A Kompetenciák oldal lehetővé teszi a szervezet által használt kompetenciák teljes körű kezelését. Itt hozhatod létre azokat az értékelési szempontokat (kompetenciákat), amelyek alapján az alkalmazottak teljesítményét méred az értékelések során. Minden kompetencia tartalmaz egy vagy több kérdést, amelyeket az értékelés során a kollégák, vezetők és az alkalmazott maga válaszol meg.

Az oldal három fő részből áll: egyéni szervezeti kompetenciák (amit te hozhatsz létre és szerkeszthetsz), kompetencia csoportok (összefogott kompetenciák egyszerűbb kezeléshez), és globális kompetenciák (előre definiált, csak olvasható kompetenciák).

## Kiknek látható ez az oldal?

**Adminisztrátorok:** Teljes hozzáférés - létrehozhatnak, módosíthatnak és törölhetnek kompetenciákat, csoportokat kezelhetnek, nyelvi beállításokat végezhetnek és AI fordítást használhatnak.

---

## Mit tudsz itt csinálni?

### Kompetencia létrehozása
**Gomb:** "Kompetencia létrehozása"  
**Helye:** Jobb felső sarokban, keresés mező mellett  
**Mit csinál:** Új kompetenciát hozhatsz létre a szervezeted számára. A kompetencia egy értékelési szempont (pl. "Csapatmunka", "Kommunikáció"), amihez kérdéseket rendelhetsz.  
**Korlátozások:** A kompetencia nevét kötelező megadni (maximum 255 karakter). Opcionálisan hozzáadhatsz leírást is.

### Csoport létrehozása
**Gomb:** "Csoport létrehozása"  
**Helye:** Második sorban bal oldalon  
**Mit csinál:** Kompetencia csoportot hozhatsz létre, amely több kompetenciát fog össze. Ha hozzárendelsz felhasználókat a csoporthoz, automatikusan megkapják az összes kompetenciát a csoportból.  
**Korlátozások:** A csoport nevét kötelező megadni, és legalább egy kompetenciát ki kell választani.

### Fordítási nyelvek kiválasztása
**Gomb:** "Fordítási nyelvek kiválasztása"  
**Helye:** Második sorban jobb oldalon  
**Mit csinál:** Kiválaszthatod, hogy mely nyelvekre szeretnéd lefordítani a kompetenciákat és kérdéseket. A kiválasztott nyelvek minden új és meglévő kompetenciánál megjelennek fordítási lehetőségként.  
**Korlátozások:** Legalább egy nyelvet (az eredeti nyelvet) kötelező kiválasztani.

### Kompetencia szerkesztése
**Gomb:** "Kompetencia módosítása" (ceruza ikon)  
**Helye:** Minden kompetencia jobb oldalán  
**Mit csinál:** Módosíthatod a kompetencia nevét, leírását és a fordításokat. Itt tudod szerkeszteni a meglévő kérdéseket is, vagy újakat hozzáadni.  
**Korlátozások:** Globális kompetenciákat (kék háttérrel jelölt) nem szerkeszthetsz, csak olvashatod őket.

### Kompetencia törlése
**Gomb:** "Kompetencia törlése" (kuka ikon)  
**Helye:** Minden szervezeti kompetencia jobb oldalán  
**Mit csinál:** Véglegesen törli a kompetenciát és az összes hozzá tartozó kérdést. A kompetencia el fog tűnni a jövőbeli értékelésekből.  
**Korlátozások:** Globális kompetenciákat nem törölhetsz. Ha a kompetencia már használatban van értékelésekben, az ott marad, de új értékelésekben nem lesz elérhető.

### Kérdés hozzáadása
**Gomb:** "Kérdés hozzáadása" (plusz ikon)  
**Helye:** Kompetencia kibontása után, a kérdések listája alatt  
**Mit csinál:** Új értékelési kérdést adhatsz hozzá a kompetenciához. Minden kérdéshez megadhatod a szöveget mások értékeléséhez, az önértékeléshez, valamint a skála végpontjainak megnevezését.  
**Korlátozások:** Minden mező kitöltése kötelező (kérdés szövege, önértékelési szöveg, minimum és maximum megnevezés, skála értéke).

### AI fordítás használata
**Gomb:** "AI Fordítás"  
**Helye:** Kompetencia vagy kérdés szerkesztési ablakban, mentés gomb mellett  
**Mit csinál:** Automatikusan lefordítja a kompetencia nevét, leírását vagy a kérdés összes mezőjét a kiválasztott célnyelvekre mesterséges intelligencia segítségével.  
**Korlátozások:** Először ki kell töltened az eredeti nyelvi verziókat, és legalább két nyelvnek kiválasztva kell lennie.

### Csoport szerkesztése
**Gomb:** "Csoport módosítása" (ceruza ikon)  
**Helye:** Minden kompetencia csoport jobb oldalán a kibontás után  
**Mit csinál:** Módosíthatod a csoport nevét és a benne lévő kompetenciákat. Ha megváltoztatod a kompetenciákat, a hozzárendelt felhasználók kompetenciái automatikusan frissülnek.  
**Korlátozások:** A csoport nevét kötelező megadni.

### Felhasználók hozzárendelése csoporthoz
**Gomb:** "Felhasználók hozzárendelése" (felhasználó plusz ikon)  
**Helye:** Kompetencia csoport kibontása után, a gombok között  
**Mit csinál:** Kiválaszthatod, hogy mely alkalmazottak kapják meg automatikusan a csoport összes kompetenciáját. A hozzárendelt alkalmazottak a saját kompetenciáik között látni fogják ezeket.  
**Korlátozások:** Egy alkalmazott csak egy csoporthoz tartozhat. Ha másik csoporthoz próbálod rendelni, hibaüzenetet kapsz.

---

## Lépések: Új kompetencia létrehozása fordításokkal

### 1. Nyelvek kiválasztása
Először válaszd ki, mely nyelveken szeretnéd a kompetenciákat megjeleníteni.

**Gomb:** "Fordítási nyelvek kiválasztása"  
**Helye:** Oldal tetején, második sorban jobbra

**Lépések:**
1. Kattints a gombra
2. A bal oldali listából válaszd ki a kívánt nyelveket a "Hozzáadás" gombbal
3. Kattints a "Nyelvek mentése" gombra

**Fontos:** Az eredetileg kiválasztott nyelv (általában magyar) az alapértelmezett nyelv lesz.

### 2. Kompetencia létrehozása
Hozz létre egy új kompetenciát az alapvető adatokkal.

**Gomb:** "Kompetencia létrehozása"  
**Helye:** Oldal tetején jobbra

**Mezők:**
- **Név** - A kompetencia neve az eredeti nyelven (kötelező, pl. "Csapatmunka")
- **Leírás** - Részletesebb magyarázat a kompetenciáról (opcionális)

### 3. Fordítások hozzáadása
Addd meg a fordításokat a kiválasztott nyelvekre.

**Helye:** A modal ablakban, nyelvi fülekkel navigálhatsz

**Opciók:**
- **Manuális fordítás:** Kattints a nyelvi fülekre és töltsd ki a mezőket
- **AI fordítás:** Kattints az "AI Fordítás" gombra automatikus fordításhoz

**Fontos:** Ha hiányoznak fordítások, sárga felkiáltójel jelenik meg a kompetencia neve mellett.

### 4. Kérdések hozzáadása
Adj hozzá értékelési kérdéseket a kompetenciához.

**Lépések:**
1. Kattints a "Kérdés hozzáadása" gombra a kompetencia alatt
2. Töltsd ki az összes mezőt az eredeti nyelven
3. Ha szeretnéd, fordítsd le az AI fordítás gombbal
4. Kattints a "Kérdés mentése" gombra

**Mezők:**
- **Kérdés** - Amit mások válaszolnak meg (pl. "Mennyire működik jól együtt a csapattal?")
- **Kérdés önértékeléshez** - Amit a személy magáról válaszol meg (pl. "Mennyire működöl jól együtt a csapattal?")
- **Minimum megnevezése** - A skála alsó végpontjának neve (pl. "Egyáltalán nem")
- **Maximum megnevezése** - A skála felső végpontjának neve (pl. "Teljes mértékben")
- **Skála** - Hány fokozatú legyen a skála (általában 5 vagy 7)

### 5. Mentés és ellenőrzés
Mentsd el a változtatásokat és ellenőrizd.

**Lépések:**
1. Kattints a "Kompetencia mentése" gombra
2. Erősítsd meg a műveletet
3. Bontsd ki a kompetenciát a névére kattintva
4. Ellenőrizd, hogy minden kérdés és fordítás helyesen jelenik-e meg

**Fontos:** Ha valahol piros (!) jel látható, az azt jelenti, hogy hiányzik a fordítás abban a nyelvben.

---

## Lépések: Kompetencia csoport létrehozása és alkalmazottak hozzárendelése

### 1. Csoport létrehozása
Hozz létre egy új csoportot kompetenciák összefogásához.

**Gomb:** "Csoport létrehozása"  
**Helye:** Oldal tetején, második sorban balra

**Mezők:**
- **Csoport neve** - Adj meg egy beszédes nevet (pl. "Vezetői kompetenciák")

### 2. Kompetenciák hozzáadása
Válaszd ki, mely kompetenciák legyenek a csoportban.

**Gomb:** "Kompetenciák hozzáadása"  
**Helye:** A csoport modal ablakban, a lista alatt

**Lépések:**
1. Kattints a gombra
2. Pipáld ki a kívánt kompetenciákat
3. Kattints a "Kiválasztás" gombra

**Fontos:** Legalább egy kompetenciát ki kell választani.

### 3. Csoport mentése
Mentsd el az új csoportot.

**Gomb:** "Csoport mentése"  
**Helye:** A modal ablak alján

**Lépések:**
1. Ellenőrizd, hogy minden kompetencia benne van-e
2. Kattints a "Csoport mentése" gombra
3. Erősítsd meg a műveletet

### 4. Alkalmazottak hozzárendelése
Rendeld hozzá a csoportot alkalmazottakhoz.

**Gomb:** "Felhasználók hozzárendelése" (felhasználó plusz ikon)  
**Helye:** A csoport kibontása után, a gombok között

**Lépések:**
1. Bontsd ki a csoportot a nevére kattintva
2. Kattints a "Felhasználók hozzárendelése" gombra
3. Pipáld ki a kívánt alkalmazottakat
4. Kattints a "Mentés" gombra

**Fontos:** A hozzárendelt alkalmazottak automatikusan megkapják a csoport összes kompetenciáját. Ha később módosítod a csoportot (kompetenciát adsz hozzá vagy törölsz), az alkalmazottak kompetenciái automatikusan frissülnek.

---

## Korlátozások és Feltételek

### ❌ Nem végezhető el, ha:
- **Globális kompetencia szerkesztése:** A kék háttérrel jelölt globális kompetenciák csak olvashatók, nem szerkeszthetők és nem törölhetők.
- **Üres név mentése:** Kompetencia vagy csoport neve nélkül nem lehet menteni.
- **Kompetencia nélküli csoport:** Csoporthoz legalább egy kompetenciát hozzá kell rendelni.
- **Átfedő csoport hozzárendelés:** Egy alkalmazott nem tartozhat egyszerre több csoporthoz.

### ⚠️ Figyelem:
- **Fordítás hiánya:** Ha egy fordítás hiányzik, piros (!) jel jelenik meg, és piros, dőlt betűvel látod az eredeti szöveget.
- **AI fordítás:** Az AI fordítás hasznos segédeszköz, de mindig ellenőrizd a fordítás helyességét mentés előtt.
- **Csoport törlése:** Csoport törlésekor a hozzárendelt alkalmazottaktól csak a csoportból származó kompetenciák törlődnek. Ha ugyanazt a kompetenciát manuálisan is hozzáadták, az megmarad.
- **Kérdések törlése:** Ha törölsz egy kérdést, az a múltbeli értékelésekben is el fog tűnni.

---

## Hibaüzenetek

### "A név megadása kötelező"
**Mikor jelenik meg:** Amikor név megadása nélkül próbálsz kompetenciát vagy csoportot menteni.  
**Megoldás:** Töltsd ki a név mezőt és próbáld újra.

### "Válasszon ki legalább egy kompetenciát"
**Mikor jelenik meg:** Amikor kompetencia nélkül próbálsz csoportot menteni.  
**Megoldás:** Kattints a "Kompetenciák hozzáadása" gombra és válassz ki legalább egy kompetenciát.

### "A felhasználók már hozzá vannak rendelve másik csoporthoz"
**Mikor jelenik meg:** Amikor olyan alkalmazottakat próbálsz csoporthoz rendelni, akik már egy másik csoporthoz tartoznak.  
**Megoldás:** Először távolítsd el az alkalmazottakat a másik csoportból, vagy válassz más alkalmazottakat.

### "Hiányos fordítások találhatók"
**Mikor jelenik meg:** Sárga felkiáltójel jelenik meg a kompetencia neve mellett, ha nem minden kiválasztott nyelvre készült fordítás.  
**Megoldás:**
1. Kattints a kompetencia szerkesztés gombra
2. Navigálj a hiányzó nyelvi fülekre (sárga felkiáltójellel jelölve)
3. Töltsd ki a hiányzó fordításokat vagy használd az AI fordítást
4. Mentsd el a változtatásokat

### "Először töltsd ki a kompetencia nevét"
**Mikor jelenik meg:** Amikor az AI fordítást próbálod használni, de az eredeti nyelven nem adtad meg a nevet.  
**Megoldás:** Váltsd át az eredeti nyelvi fülre, töltsd ki a név mezőt, majd próbáld újra az AI fordítást.

### "Globális kompetenciák nem módosíthatók"
**Mikor jelenik meg:** Amikor globális (kék háttérrel jelölt) kompetenciát próbálsz módosítani vagy törölni.  
**Megoldás:** A globális kompetenciák csak olvashatók. Ha hasonlót szeretnél, hozz létre új szervezeti kompetenciát hasonló tartalommal.

---

## GYIK (Gyakran Ismételt Kérdések)

**1. Mi a különbség a szervezeti és globális kompetenciák között?**
A szervezeti kompetenciákat te hozod létre és szerkeszted, ezek a te szervezetedre szabottak. A globális kompetenciák előre definiált, általános értékelési szempontok, amelyeket nem tudsz módosítani, de használhatsz értékelésekhez.

**2. Hogyan működik a kompetencia csoport?**
A csoport több kompetenciát fog össze. Ha hozzárendelsz alkalmazottakat a csoporthoz, automatikusan megkapják az összes kompetenciát a csoportból. Ha később módosítod a csoport tartalmát, az alkalmazottak kompetenciái automatikusan frissülnek.

**3. Miért látok piros (!) jelet és piros betűket?**
Ez azt jelzi, hogy az adott nyelvre hiányzik a fordítás, ezért az eredeti nyelv szövege jelenik meg. A felhasználók számára ez azt jelenti, hogy az értékelés során az eredeti nyelven fogják látni azt a részt.

**4. Használhatom az AI fordítást minden nyelvre?**
Igen, de mindig ellenőrizd a fordítás helyességét. Az AI általában jó minőségű fordítást készít, de HR specifikus kifejezéseknél előfordulhatnak pontatlanságok.

**5. Mi történik, ha törlök egy kompetenciát?**
A kompetencia és az összes kérdése törlődik. A múltbeli értékelésekben már rögzített válaszok megmaradnak, de a jövőbeli értékelésekben nem lesz elérhető ez a kompetencia.

**6. Hogyan tudok új kérdést hozzáadni egy meglévő kompetenciához?**
Kattints a kompetencia nevére a kibontáshoz, majd görgess le a kérdések listájának aljára. Ott találod a "Kérdés hozzáadása" gombot (plusz ikon).

**7. Miért kell külön kérdést megadni az önértékeléshez?**
Az önértékelési kérdés megfogalmazása általában egyes szám első személyben történik ("Mennyire...én"), míg a kollégák értékeléséhez harmadik személyt használunk ("Mennyire...ő/ő"). Ez teszi személyesebbé az értékelést.

**8. Módosíthatom a kérdés skáláját később?**
Igen, de ez befolyásolhatja a korábbi értékelések összehasonlíthatóságát. Ha például 5-ös skálát 7-esre változtatsz, a régi és új értékelések nem lesznek egyszerűen összehasonlíthatók.

**9. Miért nem tudok alkalmazottat hozzárendelni a csoporthoz?**
Valószínűleg az alkalmazott már egy másik csoporthoz tartozik. Egy alkalmazott egyszerre csak egy csoportban lehet. Távolítsd el a másik csoportból, majd rendeld hozzá az újhoz.

**10. Hogyan válasszam ki a megfelelő minimum és maximum megnevezést?**
Használj világos, egyértelmű kifejezéseket. Gyakori példák: "Egyáltalán nem" - "Teljes mértékben", "Soha" - "Mindig", "Gyenge" - "Kiváló". A megnevezéseknek tükrözniük kell a skála két végpontját.

---

## Kapcsolódó oldalak

- **[Alkalmazottak](/admin/employees/index)**: Az alkalmazottak kompetenciáinak egyedi kezelése és hozzárendelése
- **[Értékelések](/admin/assessment/index)**: Az értékelési időszakok kezelése, ahol a kompetenciák használatra kerülnek
- **[Eredmények](/admin/results/index)**: Az értékelési eredmények megtekintése kompetenciánként bontva