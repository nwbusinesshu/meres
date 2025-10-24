---
view: admin.bonuses
title: Bónuszok
role: admin
visible_to: [admin]
related: [/admin/results/index, /admin/settings/index, /admin/employees/index]
actions:
  - { label: "Előző időszak", trigger: "navigate-previous", permission: "admin" }
  - { label: "Következő időszak", trigger: "navigate-next", permission: "admin" }
keywords: [bónusz, bonus, malus, jutalom, fizetés, nettó bér, wage, pénznem, currency, kifizetés, payment, értékelés, assessment, időszak, period, kifizetve, paid, unpaid] 


<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

## Technikai részletek (AI számára)

**Controller:** `AdminBonusesController`  

**Főbb metódusok:**
- `index(Request $request, $assessmentId = null)` - Bónusz oldal megjelenítése értékelési időszak navigációval
- `togglePayment(Request $request)` - Bónusz kifizetési státusz váltása (AJAX)

**Routes:**
- `admin.bonuses.index` - GET with optional assessmentId parameter
- `admin.bonuses.payment.toggle` - POST (AJAX)

**Permissions:** 
- Middleware: `auth`, `OrgRole::ADMIN`, `org`
- Requires BOTH settings enabled: `show_bonus_malus` AND `enable_bonus_calculation`

**Key Database Tables:**
- `assessments` - Assessment periods (only closed ones shown with bonuses)
- `assessment_bonuses` - Calculated bonuses per user per assessment (bonus_amount, is_paid, paid_at)
- `user_wage` - User net wages and currencies
- `user_bonus_malus` - Monthly bonus/malus levels per user

**JavaScript Files:**
- `resources/views/js/admin/bonuses.blade.php` - Payment toggle handling with AJAX

**Translation Keys:**
- `lang/hu/admin/bonuses.php` - Hungarian translations
- `lang/en/admin/bonuses.php` - English translations
- `lang/hu/global.php` - Global bonus-malus level translations (M04-B10)

**Key Features:**
- **Period Navigation:** Browse between closed assessment periods using previous/next arrows
- **Summary Statistics:** Total bonus amount, paid count, unpaid count for selected period
- **Bonus Display:** View all calculated bonuses with employee details
- **Payment Tracking:** Toggle payment status per bonus, tracks paid_at timestamp
- **Cached Results:** Bonuses calculated once on assessment close via `BonusCalculationService`

**Business Logic:**
- Only shows closed assessments with calculated bonuses
- Bonuses are calculated by `BonusCalculationService` when assessment closes
- Only employees with `net_wage > 0` receive bonus calculations
- Formula: `bonus_amount = net_wage × multiplier`
- Multipliers are configured in Settings page, not here
- Page is inaccessible if parent features are disabled in settings

<!-- TECHNICAL_DETAILS_END -->
---


# Mi ez az oldal?

A Bónuszok oldal lehetővé teszi az adminisztrátorok számára az értékelési időszakok alapján kiszámított bónusz és málusz összegek megtekintését és a kifizetési státuszok nyomon követését. Az oldal automatikusan megjeleníti minden lezárt értékelési időszak bónuszadatait.

## Kiknek látható ez az oldal?

**Adminisztrátorok:** Teljes hozzáférés - megtekintheti az összes dolgozó bónuszadatait, módosíthatja a kifizetési státuszokat, és navigálhat az értékelési időszakok között.

**Ügyvezetők, vezetők, alkalmazottak:** Ez az oldal nem érhető el számukra. Az alkalmazottak saját bónuszukat az Eredmények oldalon tekinthetik meg, ha az "Alkalmazottak látják a bónuszokat" beállítás be van kapcsolva a Beállítások oldalon.


## Mit tudsz itt csinálni?

### Értékelési időszakok közötti navigáció
**Gombok:** "Előző időszak" (balra nyíl), "Következő időszak" (jobbra nyíl)  
**Helye:** Az oldal tetején, az időszak kijelző mellett  
**Mit csinál:** Lehetővé teszi a lezárt értékelési időszakok közötti váltást. Az aktuálisan kiválasztott időszak dátuma megjelenik középen, naptár ikonnal.  
**Korlátozások:** Csak olyan időszakok elérhetők, amelyekhez már lezárt értékelés tartozik. Ha nincs előző vagy következő időszak, a megfelelő nyíl szürkén jelenik meg és nem kattintható.

### Bónusz összegzés megtekintése
**Helye:** Időszak választó alatt, három dobozban megjelenítve  
**Mit mutat:** Az aktuális időszakra vonatkozóan:
- **Teljes összeg:** Az összes kiszámított bónusz és málusz összege HUF-ban
- **Kifizetve:** Hány bónusz van már kifizetettként megjelölve
- **Nincs kifizetve:** Hány bónusz vár még kifizetésre

### Bónusz lista áttekintése
**Helye:** Az összegzés alatt, táblázatos formában  
**Mit mutat:** Minden dolgozó esetében látható:
- **Alkalmazott:** A dolgozó neve
- **Részleg:** A dolgozó részlegének neve (ha a többszintű részlegkezelés be van kapcsolva)
- **Pozíció:** A dolgozó beosztása
- **Szint:** Az értékelés alapján kapott bónusz/málusz szint (M04-tól B10-ig, színkóddal)
- **Nettó bér:** A dolgozó nettó fizetése a megadott pénznemben
- **Szorzó:** Az adott szinthez tartozó szorzó érték (a Beállítások oldalon konfigurálandó)
- **Bónusz összeg:** A számított végösszeg (nettó bér × szorzó)
- **Fizetési státusz:** Kapcsoló, amellyel jelölheted, hogy a bónusz ki van-e fizetve

**Korlátozások:** Csak azok a dolgozók jelennek meg, akiknek van megadott nettó bére. Ha egy dolgozónak nincs bér adata, nem kap bónusz számítást.

### Kifizetési státusz módosítása
**Elem:** Kapcsoló (toggle) minden sor végén  
**Helye:** Bónusz lista táblázat "Fizetési státusz" oszlopában  
**Mit csinál:** Ha a kapcsolót bekapcsolod, a bónusz kifizetettként lesz megjelölve, és az oldal automatikusan újratöltődik a frissített statisztikákkal. Ha kikapcsolod, a státusz visszaáll ki nem fizetett állapotra.  
**Korlátozások:** Nincs korlátozás, bármikor módosítható a státusz.

---

## Lépések: Bónusz kifizetések nyomon követése

### 1. Időszak kiválasztása
Használd az oldal tetején lévő balra/jobbra nyilakat az értékelési időszakok közötti navigáláshoz. Az aktuális időszak dátuma középen jelenik meg.

### 2. Bónuszok áttekintése
Nézd át a bónusz listát, ellenőrizd minden dolgozónál:
- A számított bónusz összegeket
- A hozzájuk rendelt szinteket
- Az alkalmazott szorzókat

### 3. Kifizetés jelölése
Amikor egy bónuszt kifizetsz, kapcsold be a kapcsolót az adott sor végén. Az oldal automatikusan újratöltődik és frissíti a statisztikákat.

**Fontos:** A kapcsoló csak a fizetési státuszt jelöli, nem végez tényleges pénzügyi tranzakciót. Ez egy adminisztrációs eszköz a kifizetések nyilvántartására.

---

## Korlátozások és Feltételek

### ❌ Az oldal nem érhető el, ha:
- A "Bónusz/Málusz megjelenítés" beállítás ki van kapcsolva a Beállítások oldalon
- A "Bónusz számítás bekapcsolása" beállítás ki van kapcsolva a Beállítások oldalon
- Mindkét beállításnak be kell lennie kapcsolva, különben átirányítás történik a főoldalra

### ⚠️ Figyelem:
- **Bónusz számítás automatikus:** A bónuszok az értékelés lezárásakor automatikusan kiszámításra kerülnek, utólag nem lehet újraszámolni
- **Nettó bér szükséges:** Csak azok a dolgozók kapnak bónusz számítást, akiknek van megadott nettó bére
- **Szorzók módosítása:** A bónusz szorzókat a Beállítások oldalon lehet beállítani

---

## Hibaüzenetek

### "A bónusz funkció nincs engedélyezve. Kapcsold be a Beállításokban."
**Mikor jelenik meg:** Ha a bónusz rendszer szükséges beállításai nincsenek bekapcsolva.  
**Megoldás:**
1. Menj a Beállítások oldalra
2. Kapcsold be a "Bónusz/Málusz megjelenítés" kapcsolót
3. Kapcsold be a "Bónusz számítás bekapcsolása" kapcsolót
4. Térj vissza a Bónuszok oldalra

### "Nincsenek lezárt értékelések"
**Mikor jelenik meg:** Ha még nem zártál le egyetlen értékelést sem.  
**Megoldás:** Először hozz létre és zárj le egy értékelést az Értékelések oldalon. A bónuszok automatikusan kiszámításra kerülnek a lezáráskor.

### "Nincsenek kifizetendő bónuszok a mérési időszakban!"
**Mikor jelenik meg:** Ha egy lezárt időszakban egyetlen dolgozónak sincs bónusz adata (pl. mert senkinél nincs nettó bér beállítva).  
**Megoldás:** Ellenőrizd, hogy az Alkalmazottak oldalon minden érintett dolgozó esetében be van-e állítva a nettó bér és a pénznem.

### "Nem sikerült módosítani a státuszt"
**Mikor jelenik meg:** Ha a kifizetési státusz váltása sikertelen.  
**Megoldás:**
1. Frissítsd az oldalt
2. Próbáld újra bekapcsolni vagy kikapcsolni a kapcsolót
3. Ha a probléma fennáll, próbálj meg kilépni és újra bejelentkezni

---

## GYIK (Gyakran Ismételt Kérdések)

**1. Mikor számolódnak ki a bónuszok?**  
A bónuszok automatikusan kiszámításra kerülnek az értékelési időszak lezárásakor. Utólag nem lehet újraszámolni őket, tehát fontos, hogy a nettó bérek és szorzók helyesen legyenek beállítva a lezárás előtt.

**2. Miért nem látok bónuszokat egy dolgozónál?**  
Ha egy dolgozó nem jelenik meg a bónusz listában, valószínűleg azért van, mert nincs megadva a nettó bére. Menj az Alkalmazottak oldalra, keresd meg a dolgozót, és add meg a nettó bér adatokat.

**3. Módosíthatom egy már lezárt időszak bónuszait?**  
A bónusz összegek nem módosíthatók, mert azok a lezáráskor kerülnek kiszámításra és rögzítésre. Viszont a kifizetési státuszt bármikor módosíthatod a kapcsoló segítségével.

**4. Mit jelentenek a szintek (M04, A00, B10 stb.)?**  
Ezek a teljesítményszintek jelölik, amelyeket a dolgozók az értékelés során kaptak. M04-M01 a málusz szintek (gyenge teljesítmény), A00 az alapszint (átlagos teljesítmény), B01-B10 pedig a bónusz szintek (kiemelkedő teljesítmény).

**5. Hogyan állíthatom be vagy módosíthatom a szorzókat?**  
A bónusz szorzókat a Beállítások oldalon találod. Keresd a "Bónusz szorzók beállítása" gombot, és ott konfigurálhatod az összes szint szorzóját.

**6. Lehet különböző pénznemben kifizetni a bónuszokat?**  
Igen, minden dolgozónál egyénileg beállítható a pénznem (HUF, EUR, USD stb.). A rendszer mindenhol megjeleníti a megfelelő pénznemet az összegek mellett.

**7. Hogyan tudom nyomon követni, hogy mely bónuszok lettek kifizetve?**  
A táblázat "Fizetési státusz" oszlopában láthatod minden bónusznál, hogy kifizetve van-e vagy sem. Az oldal tetején lévő összegzésben pedig összesítve látod a kifizetett és ki nem fizetett bónuszok számát.

**8. Az alkalmazottak látják a saját bónuszukat?**  
Ez attól függ, hogy be van-e kapcsolva az "Alkalmazottak látják a bónuszokat" beállítás a Beállítások oldalon. Ha be van kapcsolva, a dolgozók az Eredmények oldalon megtekinthetik a saját bónusz adataikat.

**9. Mi történik, ha módosítom a szorzókat a Beállítások oldalon?**  
A módosított szorzók csak a jövőbeli értékelésekre vonatkoznak. A már lezárt időszakok bónuszai változatlanok maradnak.

---

## Kapcsolódó oldalak

- **[Beállítások](/admin/settings/index)**: Konfiguráld a bónusz szorzókat és kapcsold be/ki a bónusz funkciókat
- **[Eredmények](/admin/results/index)**: Megtekintheted az értékelési eredményeket és az egyes dolgozók teljesítményszintjeit
- **[Alkalmazottak](/admin/employees/index)**: Beállíthatod a dolgozók nettó bérét és pénznemét a bónusz számításokhoz