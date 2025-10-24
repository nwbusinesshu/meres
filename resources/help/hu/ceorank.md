---
view: ceorank
title: Vezetői rangsorolás
role: ceo|manager
visible_to: [ceo, manager]
related: [/home]
actions:
  - { label: "Rangsorolás véglegesítése", trigger: "submit-ranking", permission: "ceo|manager" }
keywords: [rangsorolás, CEO rang, alkalmazott értékelés, teljesítmény kategorizálás, húzás, elengedés, drag and drop, minimum, maximum, szintek, bónusz kategória, teljesítmény szintek, munkavállaló besorolás]

<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

## Technikai részletek (AI számára)

**Controller:** `CeoRankController`  

**Főbb metódusok:**
- `index()` - CEO rangsorolási képernyő betöltése, engedélyek ellenőrzése multi-level mód szerint
- `submitRanking()` - Rangsor mentése biztonsági ellenőrzésekkel (min/max validáció, engedélyezett felhasználók)
- `getAllowedTargets()` - Határozza meg, hogy kik rangsorolhatók (multi-level módtól és szerepkörtől függően)
- `getTranslatedName()` - Rangsor nevek fordítása a felhasználó nyelvére

**Routes:**
- `ceorank.index` - GET (rangsorolási képernyő megjelenítése)
- `ceorank.submit` - POST (rangsorolás mentése)

**Permissions:** 
- Multi-level OFF: csak CEO fér hozzá, minden normál felhasználó rangsorolható
- Multi-level ON:
  - CEO: csak managerek + részleg nélküli dolgozók rangsorolhatók (Admin és CEO szerepkör kizárva)
  - Manager: csak saját részleg(ek) beosztottjai rangsorolhatók (Manager, Admin, CEO szerepkörök kizárva)

**Key Database Tables:**
- `ceo_rank` - Rang-kategóriák tárolása (value, name, min%, max%, name_json fordításokkal)
- `organization_user` - Felhasználó-szervezet kapcsolat, department_id, role mezőkkel
- `organization_department_managers` - Manager-részleg kapcsolat multi-level módban
- `assessment_ceo_rank` - Rangsorolási eredmények tárolása assessment_id, rater_user_id, target_user_id, rank_id szerint

**JavaScript Files:**
- `resources/views/js/ceorank.blade.php` - Drag & drop logika, validáció, AJAX submission

**Translation Keys:**
- `lang/hu/ceorank.php` - Magyar fordítások
- `lang/en/ceorank.php` - Angol fordítások (ha van)

**Key Features:**
- Drag & drop interfész alkalmazottak kategóriákba sorolásához
- Real-time min/max constraint validáció (frontend és backend is)
- Mobile eszközök tiltása (kijelző méret alapján)
- Multi-language támogatás name_json mezőn keresztül
- Biztonsági ellenőrzések: csak engedélyezett felhasználók rangsorolhatók

**Validations (backend):**
- Assessment futása kötelező
- Biztonsági ellenőrzés: csak engedélyezett target_user_id-k menthetők
- Min/Max százalékos korlátozások betartása
- Minden alkalmazott pontosan egy kategóriába kell kerüljön

**Business Logic:**
- Multi-level mód esetén a CEO nem rangsorolja a beosztottakat közvetlenül, csak a managereket és részleg nélküli dolgozókat
- Manager csak saját részlegének beosztottjait rangsorolja
- A rangsorolás bónusz-malus rendszer alapja a bonus_malus komponensben
- Rang értékek (value) 0-100 skálán, magasabb = jobb teljesítmény

<!-- TECHNICAL_DETAILS_END -->

---


# Mi ez az oldal?

Az alkalmazottak teljesítmény szerinti kategorizálására szolgáló felület, ahol húzd-és-ejtsd módszerrel rendezheted a munkatársakat előre meghatározott teljesítmény szintekre. A rangsorolás a bónusz-malus rendszer alapját képezi.

## Kiknek látható ez az oldal?

**Ügyvezetők (CEO):** Látják a teljes rangsorolási felületet. Multi-level mód esetén csak a vezetőket és részleg nélküli alkalmazottakat rangsorolják.

**Vezetők (Manager):** Látják a rangsorolási felületet, de csak a saját részlegük alkalmazottait rangsorolhatják (multi-level mód esetén).

**Adminisztrátorok:** Nem férnek hozzá ehhez az oldalhoz értékelés során. A rangsor kategóriákat adminisztrációs felületen kezelhetik.

**Alkalmazottak:** Nem férnek hozzá ehhez az oldalhoz.

---

## Mit tudsz itt csinálni?

### Alkalmazottak kategorizálása
**Módszer:** Húzd-és-ejtsd (drag and drop)  
**Helye:** Bal oldali rangsor kategóriák és jobb oldali alkalmazott lista között  
**Mit csinál:** Az alkalmazottakat teljesítmény kategóriákba sorolhatod áthúzással. Minden alkalmazottnak pontosan egy kategóriába kell kerülnie.  
**Korlátozások:** 
- Minimum és maximum korlátok vannak a kategóriákhoz
- Minden alkalmazottat be kell sorolni a véglegesítés előtt
- Csak nagyobb felbontású eszközön használható (nem mobilon)

### Rangsorolás véglegesítése
**Gomb:** "Rangsorolás véglegesítése"  
**Helye:** Jobb oldali alkalmazott listában jelenik meg, amikor minden alkalmazott be van sorolva  
**Mit csinál:** Elmenti a rangsorolást az értékelési rendszerbe, ami később a bónusz-malus számítás alapja lesz.  
**Korlátozások:** 
- Minden alkalmazottnak kategóriába kell kerülnie
- Minimum és maximum korlátokat be kell tartani
- Mentés után a főoldalra irányít vissza

---

## Lépések: Rangsorolás elvégzése

### 1. Rangsor kategóriák megismerése

A bal oldalon láthatók az előre beállított teljesítmény kategóriák (rangok).

**Minden kategóriánál látható:**
- **Érték** - A rang numerikus értéke (0-100 skálán)
- **Megnevezés** - A rang neve (pl. "Kiválóan teljesített")
- **Minimum** - Minimum hány alkalmazottnak kell ebbe a kategóriába kerülnie
- **Maximum** - Maximum hány alkalmazott kerülhet ebbe a kategóriába

**Fontos:** Ha egy kategóriában piros háttér jelenik meg, az azt jelenti, hogy túllépted a maximum korlátot.

### 2. Alkalmazottak besorolása

A jobb oldalon láthatók a rangsorolandó alkalmazottak.

**Hogyan sorold be őket:**
1. Kattints egy alkalmazott nevére és tartsd lenyomva az egérgombot
2. Húzd az alkalmazottat a kívánt rangsor kategóriába
3. Engedd el az egérgombot a kategória területén

**Tipp:** Az alkalmazottak között szabadon mozgathatsz, ha meg akarod változtatni a besorolást.

### 3. Korlátozások ellenőrzése

**Minimum korlátozás:**
- Ha egy kategóriába kevesebb alkalmazott került, mint a minimum, figyelmeztetést kapsz véglegesítéskor
- A rendszer nem engedi a mentést, amíg nem teljesül minden minimum követelmény

**Maximum korlátozás:**
- Ha egy kategóriába több alkalmazottat próbálsz húzni, mint a maximum, azonnal figyelmeztetést kapsz
- Az alkalmazott nem kerül át a kategóriába

### 4. Véglegesítés

Amikor minden alkalmazott be van sorolva:

**Gomb:** "Rangsorolás véglegesítése"  
**Helye:** Jobb oldali alkalmazott lista felső részén

**Mentés folyamata:**
1. A gomb automatikusan megjelenik, amikor már nincs besorolatlan alkalmazott
2. Kattints a "Rangsorolás véglegesítése" gombra
3. Megerősítő ablak jelenik meg - kattints az "Igen" gombra
4. A rendszer elmenti a rangsorolást
5. Sikeres mentés után visszairányít a főoldalra

---

## Korlátozások és Feltételek

### ❌ Nem végezhető el, ha:
- Nincs aktív értékelési időszak
- Mobiltelefont vagy kisebb felbontású eszközt használsz
- Már véglegesítetted a rangsorolást ebben az értékelési időszakban
- Multi-level módban és te Manager vagy, de nincs hozzárendelt részleged
- Multi-level módban és te Manager vagy, de nincsenek beosztottak a részlegedben

### ⚠️ Figyelem:
- A rangsorolás véglegesítése után nem módosítható
- Minden alkalmazottat be kell sorolni - egyet sem hagyhatsz ki
- A minimum és maximum korlátokat kötelező betartani
- Nagyobb felbontású eszköz (tablet, laptop, asztali számítógép) használata kötelező
- Multi-level módban CEO csak vezetőket és részleg nélküli alkalmazottakat rangsorol
- Multi-level módban Manager csak saját részlege alkalmazottait rangsorolja

---

## Hibaüzenetek

### "Több alkalmazott nem kerülhet erre a szintre!"
**Mikor jelenik meg:** Amikor olyan kategóriába próbálsz húzni alkalmazottat, ahol már elérted a maximum létszámot.  
**Megoldás:**
1. Húzz ki egy vagy több alkalmazottat a kategóriából
2. Azután próbáld újra áthelyezni az új alkalmazottat

### "Egy vagy több szintnél nem teljesül az alkalmazottak minimum száma!"
**Mikor jelenik meg:** Véglegesítéskor, ha valamelyik kategóriában kevesebb alkalmazott van, mint a minimum követelmény.  
**Megoldás:**
1. Nézd meg, melyik kategóriának van minimum követelménye
2. Húzz át annyi alkalmazottat, amíg eléred a minimumot
3. Próbáld újra a véglegesítést

### "Kérlek a rangsorolást végezd nagyobb felbontású eszközről!"
**Mikor jelenik meg:** Ha mobiltelefont vagy kis felbontású tabletet használsz.  
**Megoldás:**
1. Válts nagyobb képernyős eszközre (laptop, asztali számítógép)
2. Ha tablettel dolgozol, próbáld fekvő tájolásban használni
3. Ha továbbra sem működik, használj asztali számítógépet

### "Nincs futó mérés."
**Mikor jelenik meg:** Ha nincs aktív értékelési időszak, vagy az már lezárult.  
**Megoldás:**
1. Ellenőrizd a főoldalon, hogy fut-e értékelési időszak
2. Ha nem, várd meg a következő értékelési periódust
3. Kérdés esetén lépj kapcsolatba az adminisztrátorral

### "Nincs hozzárendelt részleg." (Manager esetén)
**Mikor jelenik meg:** Multi-level módban, ha Manager vagy, de nincs hozzárendelt részleged.  
**Megoldás:**
1. Lépj kapcsolatba az adminisztrátorral
2. Az adminisztrátornak ki kell osztania téged egy részleghez
3. Utána próbáld újra elérni az oldalt

### "Nincs beosztott a részlegeidben." (Manager esetén)
**Mikor jelenik meg:** Multi-level módban, ha Manager vagy, de a részlegedben nincs beosztott.  
**Megoldás:**
1. Ellenőrizd a részlegd összetételét az adminisztrátorral
2. Ha valóban nincsenek beosztottak, nincs rangsorolandó alkalmazott
3. Ebben az esetben nem kell semmit tenned

---

## GYIK (Gyakran Ismételt Kérdések)

**1. Miért kell rangsorolnom az alkalmazottakat?**  
A rangsorolás alapján határozzuk meg a negyedéves bónusz-malus rendszert. Minden rang értékhez egy szorzó tartozik, ami befolyásolja az alkalmazottak bónuszát vagy malusát.

**2. Mi a különbség a minimum és maximum korlát között?**  
A minimum azt mondja meg, legalább hány alkalmazottnak kell egy kategóriába kerülnie. A maximum pedig azt, hogy maximum hányan lehetnek abban a kategóriában. Például ha egy rangnak min 10% és max 20%, akkor legalább 10% és legfeljebb 20% alkalmazottnak kell oda kerülnie.

**3. Mi történik, ha mobilról próbálom használni?**  
A mobileszközök nem támogatottak ezen az oldalon a húzd-és-ejtsd funkció miatt. Használj laptopot, asztali számítógépet vagy nagyobb tabletet.

**4. Módosíthatom a rangsorolást véglegesítés után?**  
Nem, a véglegesítés után már nem módosítható a rangsorolás. Ügyelj arra, hogy minden alkalmazott a megfelelő kategóriában legyen mentés előtt.

**5. Mit tegyek, ha elrontottam egy besorolást véglegesítés előtt?**  
Egyszerűen fogd meg az alkalmazottat és húzd át egy másik kategóriába. A besorolások szabadon módosíthatók véglegesítés előtt.

**6. Hogyan tudom, hogy minden alkalmazott be van-e sorolva?**  
Ha a jobb oldali alkalmazott listában már nem látható egy alkalmazott sem, és megjelent a "Rangsorolás véglegesítése" gomb, akkor mindenkit besoroltál.

**7. Multi-level módban kik jelennek meg a listában CEO-nak?**  
CEO esetén csak a vezetők (managerek) és a részleg nélküli alkalmazottak jelennek meg. A beosztottakat a saját vezetőjük rangsorolja.

**8. Multi-level módban kik jelennek meg a listában Managernek?**  
Manager esetén csak a saját részlege(i)ben lévő beosztottak jelennek meg rangsorolásra. Más részlegek dolgozóit nem látod.

**9. Mi történik, ha egy kategóriánál nincs minimum vagy maximum korlát?**  
Ha nincs korlát beállítva (a jelen megjelenítésben nem látható sem min, sem max érték), akkor tetszőleges számú alkalmazottat helyezhetsz abba a kategóriába.

**10. Milyen gyakran kell rangsorolást végeznem?**  
A rangsorolást minden értékelési időszakban egyszer kell elvégezned. Az értékelési időszakok gyakoriságát az adminisztrátor határozza meg (általában negyedévente).

---

## Kapcsolódó oldalak

- **[Főoldal](/home)**: Értékelési feladatok áttekintése, beleértve a rangsorolás állapotát is
