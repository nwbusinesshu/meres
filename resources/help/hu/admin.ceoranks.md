---
view: admin.ceoranks
title: Vezetői rangsor
role: admin
visible_to: [admin]
related: [/admin/home, /admin/settings/index]
actions:
  - { label: "Új szint hozzáadása", trigger: "add-rank", permission: "admin" }
  - { label: "Szint módosítása", trigger: "modify-rank", permission: "admin" }
  - { label: "Szint eltávolítása", trigger: "remove-rank", permission: "admin" }
  - { label: "Fordítási nyelvek kiválasztása", trigger: "open-language-modal", permission: "admin" }
  - { label: "Szint mentése", trigger: "save-rank", permission: "admin" }
  - { label: "AI Fordítás", trigger: "ai-translate", permission: "admin" }
keywords: [CEO rang konfiguráció, rangsor beállítás, teljesítmény szintek, értékelési kategóriák, bónusz szintek, min max százalék, rang fordítás, többnyelvű rangok, AI fordítás, teljesítmény kategóriák adminisztráció]

<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

## Technikai részletek (AI számára)

**Controller:** `AdminCeoRanksController`  

**Főbb metódusok:**
- `index()` - Rang lista megjelenítése fordításokkal
- `getCeoRank()` - Egy rang adatainak lekérdezése szerkesztéshez
- `saveCeoRank()` - Rang létrehozása vagy módosítása (beleértve fordításokat)
- `removeCeoRank()` - Rang soft-delete eltávolítás
- `getCeoRankTranslations()` - Rang fordítások lekérdezése
- `translateCeoRankName()` - AI fordítás generálása OpenAI API-val
- `getTranslationLanguages()` - Kiválasztott fordítási nyelvek lekérdezése
- `saveTranslationLanguages()` - Fordítási nyelvek mentése

**Routes:**
- `admin.ceoranks` - GET (rang lista megjelenítése)
- `admin.ceoranks.get` - GET (egy rang adatainak lekérdezése)
- `admin.ceoranks.save` - POST (rang mentése)
- `admin.ceoranks.remove` - POST (rang eltávolítása)
- `admin.ceoranks.translations.get` - POST (fordítások lekérdezése)
- `admin.ceoranks.translate-name` - POST (AI fordítás generálása)
- `admin.ceoranks.languages.get` - GET (fordítási nyelvek lekérdezése)
- `admin.ceoranks.languages.save` - POST (fordítási nyelvek mentése)

**Permissions:** 
- Csak ADMIN szerepkörrel érhető el
- Futó értékelés alatt nem érhető el (constructor abort(403))

**Key Database Tables:**
- `ceo_rank` - Rang kategóriák (id, organization_id, name, value, min%, max%, name_json, original_language, removed_at)
- `organization_config` - Fordítási nyelvek tárolása (key: translation_languages, value: JSON array)

**JavaScript Files:**
- `resources/views/js/admin/ceoranks.blade.php` - Alapvető CRUD műveletek
- `resources/views/admin/modals/ceorank.blade.php` - Teljes modal logika (fordítás kezelés, AI fordítás, nyelvváltás)

**Translation Keys:**
- `lang/hu/admin/ceoranks.php` - Magyar fordítások
- `lang/en/admin/ceoranks.php` - Angol fordítások (ha van)

**Key Features:**
- CRUD műveletek rang kategóriákra
- Multi-language támogatás name_json mezőn keresztül
- AI-powered fordítás OpenAI GPT-4o-mini-vel
- Language carousel több nyelv egyidejű kezeléséhez
- Fallback jelzés hiányzó fordításokhoz (piros szöveg + "!")
- Min/max százalékos korlátozások opcionális beállítása
- Futó értékelés alatt tiltott a módosítás

**Validations (backend):**
- name: kötelező mező
- value: kötelező, numerikus, 0-100 között
- min: kötelező, numerikus, 0-100 között (0 = nincs korlát)
- max: kötelező, numerikus, 0-100 között (0 = nincs korlát)
- organization_id egyezés ellenőrzése minden műveletnél

**Business Logic:**
- value mező: magasabb érték = jobb teljesítmény (0-100 skála)
- min/max: 0 érték esetén NULL-ra konvertálódik (nincs korlát)
- Fordítások: name_json mezőben JSON formátumban, original_language követésével
- AI fordítás: csak az eredeti nyelvről fordít célnyelvekre
- Fallback: ha nincs fordítás, eredeti név jelenik meg piros színnel
- Soft delete: removed_at timestamp alapján

<!-- TECHNICAL_DETAILS_END -->

---


# Mi ez az oldal?

A teljesítmény rangsorolási kategóriák konfigurációs felülete, ahol beállíthatod azokat a szinteket, amelyekbe a CEO és vezetők az alkalmazottakat besorolják az értékelési időszak alatt. Ezek a rangok képezik a bónusz-malus rendszer alapját.

## Kiknek látható ez az oldal?

**Adminisztrátorok:** Teljes hozzáférés a rangsor kategóriák létrehozásához, szerkesztéséhez, fordításához és törléséhez.

**Ügyvezetők (CEO):** Nem férnek hozzá - ők a rangsorolási felületen használják ezeket a kategóriákat alkalmazottak besorolására.

**Vezetők (Manager):** Nem férnek hozzá - ők is a rangsorolási felületen használják ezeket a kategóriákat.

**Alkalmazottak:** Nem férnek hozzá.

---

## Mit tudsz itt csinálni?

### Új rang kategória létrehozása
**Gomb:** "Új szint hozzáadása"  
**Helye:** Oldal tetején, bal szélen  
**Mit csinál:** Megnyit egy űrlapot, ahol új teljesítmény kategóriát hozhatsz létre név, érték, és opcionális minimum-maximum korlátokkal.  
**Korlátozások:** Nem használható futó értékelés alatt.

### Rang kategória módosítása
**Gomb:** Ceruza ikon (narancssárga)  
**Helye:** Minden rang kártya jobb alsó sarkában  
**Mit csinál:** Megnyitja a rang szerkesztési űrlapot, ahol módosíthatod a név, érték, és korlát beállításokat. Több nyelvre is lefordíthatod a nevet.  
**Korlátozások:** Nem használható futó értékelés alatt.

### Rang kategória eltávolítása
**Gomb:** Kuka ikon (piros)  
**Helye:** Minden rang kártya jobb alsó sarkában  
**Mit csinál:** Megerősítés után eltávolítja a rang kategóriát a rendszerből (soft-delete).  
**Korlátozások:** 
- Nem használható futó értékelés alatt
- Az eltávolított rang nem jelenik meg a rangsorolási felületen

### Fordítási nyelvek kiválasztása
**Gomb:** "Fordítási nyelvek kiválasztása" (nyelv ikon)  
**Helye:** Oldal tetején, jobb szélen  
**Mit csinál:** Megnyit egy párbeszédablakot, ahol kiválaszthatod, milyen nyelvekre szeretnéd lefordítani a rang neveket.  
**Korlátozások:** Legalább egy nyelvet ki kell választani.

### AI fordítás használata
**Gomb:** "AI Fordítás" (robot ikon)  
**Helye:** Rang szerkesztési ablak alján, mentés gomb mellett  
**Mit csinál:** Automatikusan lefordítja a rang nevét az eredeti nyelvről a kiválasztott célnyelvekre mesterséges intelligencia segítségével.  
**Korlátozások:** 
- Csak akkor aktív, ha az eredeti nyelven már kitöltötted a nevet
- Legalább két nyelvet kell kiválasztani (eredeti + minimum 1 célnyelv)
- Csak az eredeti nyelv fülön érhető el

---

## Lépések: Új rang kategória létrehozása

### 1. Új szint hozzáadása gomb

Kattints az oldal tetején található "Új szint hozzáadása" gombra.

**Gomb:** "Új szint hozzáadása"  
**Helye:** Oldal tetején, bal szélen

Megnyílik a rang beállítási ablak.

### 2. Alapadatok kitöltése

**Mezők:**
- **Érték** - A rang numerikus értéke 0-100 között (magasabb = jobb teljesítmény)
- **Megnevezés** - A rang neve az aktuális nyelven (pl. "Kiválóan teljesített")

**Fontos:** Az Érték és Megnevezés kitöltése kötelező.

### 3. Alkalmazottak száma korlátozás (opcionális)

Ha szeretnéd korlátozni, hány alkalmazott kerülhet ebbe a kategóriába:

**Mezők:**
- **Minimum (%)** - Pipáld be, majd add meg a minimális százalékot
- **Maximum (%)** - Pipáld be, majd add meg a maximális százalékot

**Példa:** Ha 20% minimum és 30% maximum, akkor az alkalmazottak 20-30%-ának kell ebbe a kategóriába kerülnie.

**Tipp:** Ha nem pipálod be, akkor nincs korlát az adott irányban.

### 4. Fordítások hozzáadása (opcionális, de ajánlott)

Ha többnyelvű környezetben dolgozol:

1. **Nyelv fülre kattintás** - Válts át a kívánt nyelvre a felső nyelv választóval
2. **Fordítás begépelése** - Írd be a rang nevét az adott nyelven
3. **Vagy használd az AI Fordítást** - Kattints az "AI Fordítás" gombra az automatikus fordításhoz

**Fontos:** Ha egy nyelven nem adsz meg fordítást, az eredeti név jelenik meg piros színnel (!).

### 5. Mentés

Kattints a "Szint mentése" gombra az ablak alján.

**Gomb:** "Szint mentése"  
**Helye:** Rang beállítási ablak alján, bal szélen

Megerősítés után a rang mentésre kerül és megjelenik a listában.

---

## Lépések: Rang kategória módosítása

### 1. Módosítás indítása

Kattints a módosítani kívánt rang kártyán a ceruza ikonra.

**Gomb:** Ceruza ikon (narancssárga)  
**Helye:** Rang kártya jobb alsó sarkában

Megnyílik a rang szerkesztési ablak a meglévő adatokkal.

### 2. Alapadatok módosítása

Az Érték, Megnevezés, vagy min/max korlátok módosítása:

**Fontos:** Az Érték és min/max százalékok csak az eredeti nyelven módosíthatók (ahol először létrehoztad a rangot).

**Mezők:**
- **Érték** - Módosítható az eredeti nyelven
- **Megnevezés** - Módosítható bármely nyelven
- **Min/Max százalékok** - Módosíthatók az eredeti nyelven

### 3. Fordítások szerkesztése

Többnyelvű módban:

1. **Nyelvváltás** - Használd a felső nyelv választót a kívánt nyelvre váltáshoz
2. **Fordítás módosítása** - Írd át a megnevezést
3. **Hiányzó fordítások pótlása** - Azoknál a nyelveknél, ahol piros csillag látható

**Tipp:** A nyelv fülek mellett:
- **Zöld pipa** - Fordítás rendben
- **Piros csillag** - Hiányzik a fordítás
- **(Eredeti)** felirat - Az eredeti nyelv

### 4. AI fordítás használata (opcionális)

Ha több nyelvre egyszerre szeretnéd lefordítani:

1. Válts át az eredeti nyelvre (ahol létrehoztad a rangot)
2. Győződj meg róla, hogy a Megnevezés ki van töltve
3. Kattints az "AI Fordítás" gombra
4. Várd meg, amíg a fordítások elkészülnek
5. Ellenőrizd és módosítsd a fordításokat, ha szükséges

**Fontos:** Az AI fordítás felülírja a meglévő fordításokat a célnyelveken.

### 5. Mentés

Kattints a "Szint mentése" gombra a módosítások mentéséhez.

**Gomb:** "Szint mentése"  
**Helye:** Rang beállítási ablak alján

---

## Lépések: Fordítási nyelvek kiválasztása

### 1. Nyelvválasztó megnyitása

Kattints a "Fordítási nyelvek kiválasztása" gombra.

**Gomb:** "Fordítási nyelvek kiválasztása" (nyelv ikon)  
**Helye:** Oldal tetején, jobb szélen

Megnyílik a nyelv kiválasztási ablak.

### 2. Nyelvek kiválasztása

Pipáld be azokat a nyelveket, amelyekre fordítani szeretnéd a rang neveket:

**Elérhető nyelvek:**
- Magyar
- English (Angol)
- Deutsch (Német)
- Română (Román)
- További európai nyelvek...

**Tipp:** Válaszd ki a szervezetedben használt összes nyelvet.

### 3. Nyelvek mentése

Kattints a mentés gombra a nyelvek alkalmazásához.

**Eredmény:** Ezután minden rang szerkesztésekor ezek a nyelvek lesznek elérhetők fordításra.

---

## Korlátozások és Feltételek

### ❌ Nem végezhető el, ha:
- Futó értékelési időszak van (egyik funkció sem érhető el)
- Nem vagy Admin szerepkörben
- Az organization_id nem egyezik a session-ben tárolt szervezet azonosítóval

### ⚠️ Figyelem:
- A rangok törlése csak soft-delete, az adatbázisban megmaradnak (removed_at timestamp)
- Futó értékelés alatt a rang beállítások zároltak - előtte végezd el a módosításokat
- Az Érték mező magasabb = jobb teljesítmény (0-100 skála)
- A min/max korlátok százalékban értendők az összes rangsorolandó alkalmazott számához képest
- AI fordítás OpenAI API-t használ, internetkapcsolat szükséges
- Fordítások name_json mezőben JSON formátumban tárolódnak
- Ha nincs fordítás egy nyelvre, az eredeti név jelenik meg piros színnel és (!) jellel

---

## Hibaüzenetek

### "Futó értékelés alatt nem módosítható"
**Mikor jelenik meg:** Ha próbálod elérni az oldalt aktív értékelési időszak alatt.  
**Megoldás:**
1. Várd meg az értékelési időszak lezárását
2. Az értékelés lezárása után végezd el a módosításokat
3. Kérdés esetén lépj kapcsolatba a rendszergazdával

### "A mező kitöltése kötelező"
**Mikor jelenik meg:** Ha az Érték vagy Megnevezés mező üres mentéskor.  
**Megoldás:**
1. Töltsd ki az összes kötelező mezőt (Érték, Megnevezés)
2. Próbáld újra a mentést

### "Az érték 0 és 100 között kell legyen"
**Mikor jelenik meg:** Ha az Érték mezőbe 0-nál kisebb vagy 100-nál nagyobb számot írsz.  
**Megoldás:**
1. Adj meg 0 és 100 közötti értéket
2. Próbáld újra a mentést

### "A minimum/maximum értéknek 0 és 100 között kell lennie"
**Mikor jelenik meg:** Ha a min vagy max százalék mezőbe helytelen értéket írsz.  
**Megoldás:**
1. Adj meg 0 és 100 közötti százalékértéket
2. Ha nem szeretnél korlátot, ne pipáld be a mezőt
3. Próbáld újra a mentést

### "Fordítás sikertelen"
**Mikor jelenik meg:** Ha az AI fordítás nem sikerült (pl. hálózati hiba, API probléma).  
**Megoldás:**
1. Ellenőrizd az internetkapcsolatot
2. Próbáld újra később
3. Ha továbbra sem működik, írd be manuálisan a fordításokat

### "A név megadása kötelező / Először adja meg a szint nevét"
**Mikor jelenik meg:** Ha AI fordítást próbálsz használni, de az eredeti nyelven nincs kitöltve a Megnevezés.  
**Megoldás:**
1. Töltsd ki a Megnevezés mezőt az eredeti nyelven
2. Ezután használd az AI fordítás gombot

---

## GYIK (Gyakran Ismételt Kérdések)

**1. Mi a különbség az Érték és a Megnevezés között?**  
Az Érték (0-100) a rang numerikus súlya, ami a bónusz-malus számításban használatos. A Megnevezés a rang szöveges neve, amit a felhasználók látnak (pl. "Kiválóan teljesített").

**2. Miért nem tudom módosítani a rangokat?**  
Ha futó értékelési időszak van, a rangok zároltak. Várd meg az értékelés lezárását, majd utána módosítsd a beállításokat.

**3. Mit jelent a min/max százalék?**  
A minimum azt mondja meg, az alkalmazottak legalább hány százalékának kell ebbe a kategóriába kerülnie. A maximum pedig azt, hogy legfeljebb hány százalék. Például min 10% és max 20% = legalább 10%, de maximum 20% kerülhet ebbe a rangba.

**4. Mi történik, ha nem állítok be min/max korlátot?**  
Ha nem pipálod be a min/max jelölőnégyzeteket (vagy 0-t adsz meg), akkor nincs korlátozás, tetszőleges számú alkalmazott kerülhet abba a kategóriába.

**5. Hogyan működik az AI fordítás?**  
Az AI fordítás az OpenAI GPT-4o-mini modelljét használja. Az eredeti nyelven megadott rang nevet automatikusan lefordítja a kiválasztott célnyelvekre kontextusban maradva.

**6. Módosíthatom a fordításokat AI fordítás után?**  
Igen, az AI fordítás csak javaslatot ad. Minden fordítást manuálisan módosíthatsz a különböző nyelv füleken mentés előtt.

**7. Mit jelent a piros szöveg és (!) jel a rang nevénél?**  
Ez azt jelzi, hogy az adott nyelven nincs fordítás, ezért az eredeti név jelenik meg. Adj meg fordítást a hiányzó nyelvekre.

**8. Hány rang kategóriát hozhatok létre?**  
Nincs technikai korlát, de általában 4-7 kategória ajánlott a gyakorlatban (pl. Kiválóan teljesített, Jól teljesített, Megfelelően teljesített, Fejlődésre szorul, Értékelhetetlen).

**9. Mi történik a törölt rangokkal?**  
A törölt rangok nem jelennek meg a felületen és nem használhatók új rangsorolásokban.

**10. Milyen sorrendben jelenjenek meg a rangok?**  
A rangok Érték alapján csökkenő sorrendben jelennek meg (legmagasabb érték felül). Így a legjobb teljesítményt jelző rang lesz az első.

**11. Módosíthatom a rang Értékét utólag?**  
Igen, de csak az eredeti nyelven (ahol létrehoztad). A fordítási füleken az Érték mező zárolva van.

**12. Mit tegyek, ha egy fordítási nyelvet törölni szeretnék?**  
Nyisd meg a "Fordítási nyelvek kiválasztása" ablakot, töröld a pipát a nem kívánt nyelvről, és mentsd el. A nyelv eltűnik a fordítási fülekről.

---

## Kapcsolódó oldalak

- **[Főoldal](/admin/home)**: Admin irányítópult, rendszer áttekintés
- **[Rendszer Beállítások](/admin/settings/index)**: Általános rendszer konfigurációk, beleértve a multi-level módot