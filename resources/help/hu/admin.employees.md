---
view: admin.employees
title: Alkalmazottak kezelése
role: admin
visible_to: [admin]
related: [admin.home, admin.competency, admin.results, admin.bonuses]
actions:
  - { label: "Új alkalmazott felvétele", trigger: "click-new-employee", permission: "admin" }
  - { label: "Keresés", trigger: "search-input", permission: "admin" }
  - { label: "Adatok módosítása", trigger: "click-edit", permission: "admin" }
  - { label: "Viszonylista", trigger: "click-relations", permission: "admin" }
  - { label: "Kompetencialista", trigger: "click-competencies", permission: "admin" }
  - { label: "Besorolás", trigger: "click-bonusmalus", permission: "admin" }
  - { label: "Jelszó visszaállítás", trigger: "click-password-reset", permission: "admin" }
  - { label: "Fiók feloldása", trigger: "click-unlock-account", permission: "admin" }
  - { label: "Eltávolítás", trigger: "click-remove", permission: "admin" }
  - { label: "Tömeges Import", trigger: "click-mass-import", permission: "admin" }
  - { label: "Új részleg létrehozása", trigger: "click-new-department", permission: "admin" }
  - { label: "Részleg szerkesztése", trigger: "click-edit-department", permission: "admin" }
  - { label: "Tagok kezelése", trigger: "click-manage-members", permission: "admin" }
  - { label: "Részleg törlése", trigger: "click-delete-department", permission: "admin" }
  - { label: "Cégkapcsolati háló", trigger: "click-network", permission: "admin" }
keywords: [alkalmazottak, munkavállalók, dolgozók, felhasználók, részlegek, osztályok, kapcsolatok, viszonyok, kompetenciák, besorolás, bónusz, málusz, jelszó, fiók, zárolás, feloldás, import, tömeges, csv, excel, hálózat, network, CEO, manager, vezető, beosztott, kolléga, felettes]

<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

**Controller:** `AdminEmployeeController`  
**Főbb metódusok:**
- `index()` - Oldal betöltése
- `getEmployee()` - Felhasználó adatainak lekérése
- `saveEmployee()` - Felhasználó mentése (új és módosítás)
- `removeEmployee()` - Felhasználó törlése
- `getEmployeeRelations()` - Kapcsolatok lekérése
- `saveEmployeeRelations()` - Kapcsolatok mentése
- `getEmployeeCompetencies()` - Kompetenciák lekérése
- `saveEmployeeCompetencies()` - Kompetenciák mentése
- `getBonusMalus()` - Bónusz szint lekérése
- `setBonusMalus()` - Bónusz szint beállítása
- `passwordReset()` - Jelszó visszaállítás email küldése
- `unlockAccount()` - Fiók feloldása
- `storeDepartment()` - Új részleg létrehozása
- `updateDepartment()` - Részleg módosítása
- `getDepartmentMembers()` - Részleg tagjainak lekérése
- `saveDepartmentMembers()` - Részleg tagjainak mentése
- `deleteDepartment()` - Részleg törlése
- `getNetworkData()` - Hálózati adatok lekérése

<!-- TECHNICAL_DETAILS_START - This section is for AI use only, not visible to end users -->

## Technikai részletek (AI számára)

**Controller:** `AdminEmployeeController`  

**Főbb metódusok:**
- `index()` - Oldal betöltése, felhasználók listázása
- `getEmployee()` - Felhasználó adatainak lekérése
- `saveEmployee()` - Felhasználó mentése (új és módosítás)
- `removeEmployee()` - Felhasználó törlése
- `getAllEmployee()` - Összes felhasználó lekérése (AJAX)
- `getEmployeeRelations()` - Kapcsolatok lekérése
- `saveEmployeeRelations()` - Kapcsolatok mentése
- `getEmployeeCompetencies()` - Kompetenciák lekérése
- `saveEmployeeCompetencies()` - Kompetenciák mentése
- `getBonusMalus()` - Bónusz szint lekérése
- `setBonusMalus()` - Bónusz szint beállítása
- `passwordReset()` - Jelszó visszaállítás email küldése
- `unlockAccount()` - Fiók feloldása (sikertelen login törlése)
- `storeDepartment()` - Új részleg létrehozása
- `getDepartment()` - Részleg adatok lekérése
- `updateDepartment()` - Részleg módosítása
- `getDepartmentMembers()` - Részleg tagjainak lekérése
- `getEligibleForDepartment()` - Részlegbe adható felhasználók
- `saveDepartmentMembers()` - Részleg tagjainak mentése
- `deleteDepartment()` - Részleg törlése
- `getNetworkData()` - Hálózati adatok lekérése (vizualizációhoz)
- `getEligibleManagers()` - Elérhető managerek lekérése

**Import Controller:** `AdminEmployeeImportController`  

**Főbb metódusok:**
- `downloadTemplate(string $type)` - Sablon letöltése (legacy/multilevel)
- `validateImport(Request $request)` - Import előnézet validálása
- `start(Request $request)` - Import folyamat indítása
- `status(int $jobId)` - Import státusz lekérése
- `downloadReport(int $jobId)` - Import jelentés letöltése CSV-ben
- `checkActiveImport()` - Aktív import ellenőrzése

**Routes:**
- `admin.employee.index` - GET (oldal megjelenítés)
- `admin.employee.get` - POST (felhasználó adatok)
- `admin.employee.save` - POST (felhasználó mentés)
- `admin.employee.remove` - POST (felhasználó törlés)
- `admin.employee.all` - POST (összes felhasználó AJAX)
- `admin.employee.relations` - POST (kapcsolatok lekérés)
- `admin.employee.relations.save` - POST (kapcsolatok mentés)
- `admin.employee.competencies` - POST (kompetenciák lekérés)
- `admin.employee.competencies.save` - POST (kompetenciák mentés)
- `admin.employee.bonusmalus.get` - POST (bónusz szint lekérés)
- `admin.employee.bonusmalus.set` - POST (bónusz szint beállítás)
- `admin.employee.password-reset` - POST (jelszó reset)
- `admin.employee.unlock-account` - POST (fiók feloldás)
- `admin.employee.department.store` - POST (részleg létrehozás)
- `admin.employee.department.get` - POST (részleg adatok)
- `admin.employee.department.update` - POST (részleg módosítás)
- `admin.employee.department.members` - POST (részleg tagok)
- `admin.employee.department.eligible` - POST (elérhető tagok)
- `admin.employee.department.members.save` - POST (tagok mentés)
- `admin.employee.department.delete` - POST (részleg törlés)
- `admin.employee.network` - POST (hálózati adatok)
- `admin.employee.get-eligible-managers` - POST (elérhető managerek)
- `admin.employee.import.*` - Import route-ok

**Permissions:** `OrgRole::ADMIN` middleware + `org` middleware + `check.initial.payment` middleware

**Key Database Tables:**
- `user` - Felhasználók alapadatai
- `organization_user` - Szervezet-felhasználó kapcsolat (role, position, department_id)
- `organization_departments` - Részlegek
- `organization_department_managers` - Részlegvezetők (multi-manager support)
- `user_relation` - Felhasználók közötti kapcsolatok (colleague, subordinate, superior)
- `user_competency` - Felhasználó-kompetencia hozzárendelések
- `user_bonus_malus` - Bónusz/málusz szintek havi bontásban
- `user_import_jobs` - Import feladatok
- `user_import_results` - Import eredmények soronként
- `competency_groups` - Kompetencia csoportok
- `competency_group_users` - Csoport-felhasználó hozzárendelések

**JavaScript Files:**
- `resources/views/js/admin/employees.blade.php` - Fő oldal logika
- `resources/views/admin/modals/employee.blade.php` - Felhasználó modal
- `resources/views/admin/modals/relations.blade.php` - Kapcsolatok modal
- `resources/views/admin/modals/user-competencies.blade.php` - Kompetenciák modal
- `resources/views/admin/modals/bonusmalus.blade.php` - Bónusz/málusz modal
- `resources/views/admin/modals/department.blade.php` - Részleg modal
- `resources/views/admin/modals/departmentuser.blade.php` - Részleg tagok modal
- `resources/views/admin/modals/network.blade.php` - Hálózati vizualizáció modal
- `resources/views/admin/modals/employee-import.blade.php` - Import modal

**Translation Keys:**
- `lang/hu/admin/employees.php` - Magyar fordítások
- `lang/en/admin/employees.php` - Angol fordítások

**Key Features:**
- Multi-level department system: Többszintű részlegrendszer több vezetővel részlegenként
- Automatic relation creation: Automatikus kapcsolatok részlegek alapján (manager-employee)
- Easy Relation Setup: Egyszerűsített kapcsolat létrehozás (minden új kapcsolat kolléga)
- Competency groups: Kompetencia csoportok automatikus hozzárendelése
- Mass import: Tömeges import max 500 sor Excel-ből, valós idejű progress
- Account lockout: Fiók zárolás 5 sikertelen bejelentkezés után
- Password reset email: Jelszó visszaállító email küldés
- Network visualization: Cytoscape.js alapú kapcsolati háló vizualizáció
- Rater count indicators: Értékelők száma színkóddal (red/yellow/green)
- Employee limit tracking: Munkavállaló limit követés az első fizetési időszakra
- Bonus/Malus system: 15 szintes bónusz/málusz rendszer (M04 - B10)
- Config driven visibility: Beállítások alapján láthatóság (enable_multi_level, show_bonus_malus, easy_relation_setup)

**Validations (backend):**
- Email uniqueness: `User::where('email', $email)->whereNull('removed_at')->exists()`
- Required fields: name, email, type
- Valid types: OrgRole::ADMIN, CEO, MANAGER, EMPLOYEE
- Department name required: when creating/updating department
- At least 1 manager required: when creating department
- Import max 500 rows
- Import file max 5 MB
- Import format: .xlsx only

**Business Logic:**
- Easy Relation Setup ON: All new relations default to "colleague", changeable later
- Easy Relation Setup OFF: Must specify relation type (colleague/subordinate/superior) immediately
- Department managers: Cannot be deleted while manager role active
- Auto relations: Department-based relations (manager-employee) cannot be deleted manually
- Competency groups: Group competencies cannot be deleted individually, only by removing user from group
- Mixed competencies: If competency is both group and manual, only manual assignment can be deleted
- Relation conflicts: System detects contradictions (A says B is subordinate, B says A is colleague) and asks for confirmation
- Employee limit: First assessment period has fixed employee count, unlimited after first closure

<!-- TECHNICAL_DETAILS_END -->
---

# Mi ez az oldal?

Az **Alkalmazottak kezelése** oldal a szervezet munkavállalóinak teljes körű adminisztrációját teszi lehetővé. Itt tudsz új alkalmazottakat felvenni, meglévők adatait módosítani, kapcsolatokat beállítani (ki kinek a beosztottja, kollégája vagy felettese), kompetenciákat hozzárendelni, valamint részlegeket (osztályokat) létrehozni és kezelni.

Az oldal megjelenése a szervezet beállításaitól függ:
- **Többszintű nézet**: Részlegek szerint csoportosított munkatársak, ahol a vezetők és tagok külön-külön láthatók
- **Táblázatos nézet**: Egyszerű lista nézet minden munkavállalóval egy táblázatban

---

## Kiknek látható ez az oldal?

**Adminisztrátorok:** Teljes hozzáférés minden funkcióhoz - új munkavállalók felvétele, adatok módosítása, kapcsolatok és kompetenciák beállítása, részlegek kezelése, tömeges import.

**Ügyvezetők (CEO):** Ez az oldal nem látható számukra.

**Vezetők (Manager):** Ez az oldal nem látható számukra.

**Alkalmazottak:** Ez az oldal nem látható számukra.

---

## Mit tudsz itt csinálni?

### 🆕 Új alkalmazott felvétele
**Gomb:** "Új alkalmazott felvétele" (oldal tetején)  
**Mit csinál:** Új munkavállaló regisztrálása a rendszerben  
**Megadható adatok:**
- Név (kötelező)
- Email cím (kötelező, egyedi)
- Típus (Admin, CEO, Manager, Employee)
- Beosztás
- Részleg (ha többszintű rendszer aktív)
- Havi nettó bér (bónusz számításhoz)

**Fontos:** Ha elérted a munkavállaló limitet az első mérés lezárása előtt, figyelmeztetést kapsz.

### 🔍 Keresés
**Helye:** Keresőmező az oldal tetején  
**Mit csinál:** Valós idejű szűrés név vagy email alapján a munkatársak listájában

### 👤 Adatok módosítása
**Gomb:** Narancssárga ceruza ikon <i class="fa fa-user-gear"></i> (minden munkatárs soránál)  
**Mit csinál:** Munkavállaló adatainak szerkesztése (név, típus, beosztás, részleg, bér)  
**Korlátozások:**
- Részlegvezetők és részlegtagok típusa bizonyos esetekben zárolva van
- Email cím nem módosítható

### 🔗 Viszonylista kezelése
**Gomb:** Kék hálózat ikon <i class="fa fa-network-wired"></i>  
**Mit csinál:** Munkatársak közötti kapcsolatok beállítása  
**Kapcsolat típusok:**
- **Kolléga:** Azonos szinten dolgoznak, kölcsönösen értékelik egymást
- **Beosztott:** Te vagy a felettese, értékel téged vezetőként
- **Felettes:** Ő a felettesed, te értékeled őt

**Működés:** A rendszer beállításától függ, hogy automatikusan "kolléga" kapcsolatként jönnek-e létre az új viszonyok, vagy már a hozzáadáskor kiválasztható a típus.

**Fontos:** Részlegből származó automatikus kapcsolatok (vezető-tag) nem törölhetők.

### 🏅 Kompetencialista kezelése
**Gomb:** Zöld medál ikon <i class="fa fa-medal"></i>  
**Mit csinál:** Értékelési szempontok (kompetenciák) hozzárendelése a munkavállalóhoz  

**Két típusú kompetencia:**
- **Csoport kompetenciák:** Ha egy munkatársat hozzárendelsz egy kompetencia csoporthoz (pl. "Értékesítők"), automatikusan megkapja az összes csoportbeli kompetenciát. Ezek nem törölhetők egyedileg.
- **Manuális kompetenciák:** Egyedileg hozzárendelt kompetenciák, szabadon törölhetők.

**Vegyes eset:** Ha ugyanaz a kompetencia csoportból és manuálisan is hozzá van rendelve, akkor csak a manuális hozzárendelés törölhető - a csoport kompetencia megmarad.

### 📊 Besorolás módosítása
**Gomb:** Kék rétegek ikon <i class="fa fa-layer-group"></i>  
**Megjelenés:** Csak akkor látható, ha a bónusz/málusz rendszer be van kapcsolva  
**Mit csinál:** Munkavállaló jelenlegi bónusz/málusz szintjének kézi módosítása (1-15 szint között)

### 🔑 Jelszó visszaállítás
**Gomb:** Szürke kulcs ikon <i class="fa fa-key"></i>  
**Mit csinál:** Jelszóbeállító email küldése a munkatársnak  
**Megjelenés:** Csak akkor látható, ha a fiók nincs zárolva

### 🔓 Fiók feloldása
**Gomb:** Piros lakat ikon <i class="fa fa-lock"></i>  
**Mit csinál:** Túl sok sikertelen bejelentkezés miatti zárolás feloldása  
**Megjelenés:** Csak akkor látható, ha a fiók zárolva van

### 🗑️ Alkalmazott eltávolítása
**Gomb:** Piros kuka ikon <i class="fa fa-trash-alt"></i>  
**Mit csinál:** Munkavállaló eltávolítása a szervezetből  
**Korlátozás:** Részlegvezetők nem törölhetők, amíg vezetői szerepben vannak

### 📦 Tömeges import
**Gomb:** "Tömeges Import" (az alkalmazott szerkesztő ablak alján)  
**Mit csinál:** Excel fájlból több munkavállaló egyidejű importálása (maximum 500 sor)  

**Folyamat:**
1. **Sablon letöltése** - Válaszd ki a megfelelő sablont (alapértelmezett vagy többszintű)
2. **Excel kitöltése** - Add meg a munkavállalók adatait (név, email, típus, beosztás, részleg, bér)
3. **Fájl feltöltése** - Húzd be a fájlt vagy tallózással válaszd ki
4. **Előnézet** - Ellenőrizd a hibákat és figyelmeztetéseket
5. **Import indítása** - Választhatod, hogy küldjön-e jelszóbeállító emaileket
6. **Folyamat követése** - Valós idejű státusz a feldolgozás közben
7. **Jelentés** - Letölthető CSV jelentés a sikeres és sikertelen importokról

**Követelmények:**
- Maximum 5 MB fájlméret
- .xlsx formátum
- Maximum 500 sor
- Kötelező mezők: név, email, típus

### 🏢 Részlegek kezelése (többszintű nézet)

#### Új részleg létrehozása
**Gomb:** "Új részleg létrehozása" (részlegek szekció tetején)  
**Mit csinál:** Új osztály/részleg létrehozása vezetőkkel  
**Szükséges adatok:**
- Részleg neve (kötelező)
- Legalább 1 vezető (kötelező)

#### Részleg szerkesztése
**Gomb:** Kék ceruza ikon <i class="fa fa-pen"></i> (részleg fejlécében)  
**Mit csinál:** Részleg nevének és vezetőinek módosítása

#### Tagok kezelése
**Gomb:** Zöld emberek ikon <i class="fa fa-users"></i> (részleg fejlécében)  
**Mit csinál:** Részleghez tartozó munkatársak hozzáadása és eltávolítása  
**Műveletek:**
- Új tag hozzáadása a listából
- Egyes tagok eltávolítása (X ikon)
- Összes tag egyszeri eltávolítása

**Fontos:** Az eltávolítás után ne felejts el menteni!

#### Részleg törlése
**Gomb:** Piros kuka ikon <i class="fa fa-trash-alt"></i> (részleg fejlécében)  
**Mit csinál:** Részleg törlése - a tagok átkerülnek a "Nem besorolt felhasználók" közé  
**Megjegyzés:** A munkatársak nem törlődnek, csak a részleghez való hozzárendelésük szűnik meg

### 🕸️ Cégkapcsolati háló
**Gomb:** "Cégkapcsolati háló" (oldal tetején vagy alján)  
**Mit csinál:** A szervezet kapcsolati hálózatának interaktív vizualizációja  
**Funkciók:**
- Különböző elrendezések (Force-directed, Kör, Rács, Hierarchikus, Koncentrikus)
- Szűrés részlegek szerint
- Színkódolt kapcsolat típusok (kolléga, beosztott, felettes)
- Zoom és görgetés

---

## Lépések: Új alkalmazott felvétele

### 1. Szerkesztő ablak megnyitása
Kattints az **"Új alkalmazott felvétele"** gombra az oldal tetején.

### 2. Adatok kitöltése
Töltsd ki a kötelező mezőket:
- **Név** - Munkavállaló teljes neve
- **Email** - Egyedi email cím
- **Típus** - Admin / CEO / Manager / Employee

Opcionálisan megadható:
- **Beosztás** - Munkaköri megnevezés
- **Részleg** - Melyik részleghez tartozik (többszintű rendszerben)
- **Havi nettó bér** - Forintban (bónusz számításhoz)

**Figyelem:** Ha az email cím már létezik a rendszerben, hibaüzenetet kapsz.

### 3. Mentés és jóváhagyás
1. Kattints a **"Módosítás"** gombra
2. Erősítsd meg a műveletet a felugró ablakban
3. Sikeres mentés esetén automatikusan jelszóbeállító email érkezik az új munkatársnak

---

## Lépések: Kapcsolatok beállítása

### 1. Viszonylista megnyitása
Kattints a kék hálózat ikonra <i class="fa fa-network-wired"></i> a munkatárs soránál.

### 2. Új kapcsolat hozzáadása
1. Kattints az **"Új viszony felvétele"** gombra
2. Válassz ki egy munkatársat a listából
3. Állítsd be a kapcsolat típusát:
   - **Kolléga** - egyenrangú munkatárs
   - **Beosztott** - te vagy a felettese
   - **Felettes** - ő a felettesed

**Megjegyzés:** A rendszer beállításaitól függően a kapcsolatok automatikusan "kolléga" típusúként jönnek létre, amit később módosíthatsz.

### 3. Kapcsolat törlése
Kattints a piros kuka ikonra <i class="fa fa-trash-alt"></i> a kapcsolat mellett.

**Kivétel:** Részlegből származó automatikus kapcsolatok (vezető-tag) nem törölhetők.

### 4. Mentés
1. Kattints a **"Viszonylista mentése"** gombra
2. Erősítsd meg a műveletet
3. Ha van ütközés (pl. A szerint B beosztott, de B szerint A kolléga), döntsd el, hogy felülírod-e

---

## Lépések: Kompetenciák hozzárendelése

### 1. Kompetencialista megnyitása
Kattints a zöld medál ikonra <i class="fa fa-medal"></i> a munkatárs soránál.

### 2. Kompetenciák megtekintése
A listában két szekció látható:
- **Csoportokból** - Automatikusan hozzárendelt kompetenciák (szürke kuka ikon, nem törölhető)
- **Manuálisan hozzáadva** - Egyedileg hozzáadott kompetenciák (piros kuka ikon, törölhető)

### 3. Új kompetencia hozzáadása
1. Kattints az **"Új kompetencia hozzáadása"** gombra
2. Válassz ki egy kompetenciát a listából
3. A kompetencia megjelenik a manuális listában

### 4. Kompetencia törlése
Kattints a piros kuka ikonra a kompetencia mellett.

**Megjegyzés:** Ha ugyanaz a kompetencia csoportból és manuálisan is hozzá van rendelve, csak a manuális hozzárendelés törlődik - a csoport kompetencia megmarad.

### 5. Mentés
Kattints a **"Kompetencialista mentése"** gombra és erősítsd meg.

---

## Lépések: Részleg létrehozása

### 1. Új részleg indítása
Kattints az **"Új részleg létrehozása"** gombra a részlegek szekció tetején (többszintű nézetben).

### 2. Adatok megadása
Töltsd ki a szükséges mezőket:
- **Részleg neve** (kötelező) - pl. "IT Részleg", "Értékesítés"
- **Vezetők** (legalább 1 kötelező) - válassz Manager típusú felhasználókat

**Vezetők kezelése:**
- Új vezető hozzáadása: Kattints a **"Vezető hozzáadása"** gombra
- Vezető eltávolítása: Kattints a piros X ikonra a vezető neve mellett

### 3. Mentés
1. Kattints a **"Mentés"** gombra
2. Ha minden adat helyes, a részleg létrejön
3. Ha hiányzik a név vagy nincs vezető, hibaüzenetet kapsz

---

## Lépések: Tömeges import

### 1. Import ablak megnyitása
Kattints a **"Tömeges Import"** gombra az alkalmazott szerkesztő ablak alján.

### 2. Sablon letöltése
Válaszd ki a megfelelő sablont:
- **Alapértelmezett sablon** - Név, Email, Típus, Beosztás, Bér
- **Többszintű sablon** - Plusz Részleg mező is (ha többszintű rendszer aktív)

### 3. Excel kitöltése
Töltsd ki az Excel táblázatot a munkavállalók adataival:
- **Kötelező mezők:** Név, Email, Típus
- **Típus értékek:** admin, ceo, manager, employee
- **Maximum:** 500 sor

### 4. Fájl feltöltése
Két lehetőség:
- **Drag & drop:** Húzd be a fájlt a feltöltési területre
- **Tallózás:** Kattints a "Tallózás" gombra

**Követelmények:** Maximum 5 MB, .xlsx formátum

### 5. Előnézet ellenőrzése
A rendszer megmutatja:
- ✅ **Zöld sorok** - Rendben, ezek bekerülnek
- ⚠️ **Sárga sorok** - Figyelmeztetés (pl. munkavállaló limit túllépés)
- ❌ **Piros sorok** - Hiba van, ezek NEM kerülnek be

Ha van hiba:
1. Kattints a **"Javítás és Újratöltés"** gombra
2. Javítsd ki az Excel fájlban a hibákat
3. Töltsd fel újra

### 6. Import indítása
1. Döntsd el, hogy küldjön-e jelszóbeállító emaileket (checkbox)
2. Kattints az **"Import Indítása"** gombra
3. **Fontos:** Ne zárd be az ablakot a folyamat alatt!

**Folyamat követése:**
A rendszer valós időben mutatja:
- Feldolgozott sorok száma
- Sikeres importok
- Sikertelen importok
- Új részlegek létrehozva

### 7. Import befejezése
Az import végén:
- Látod az összesítést (sikeres/sikertelen)
- **"Jelentés Letöltése"** gomb - CSV fájl a részletekkel
- **"Bezárás és Frissítés"** gomb - oldal frissítése az új munkavállalókkal

---

## Korlátozások és Feltételek

### ❌ Nem végezhető el, ha:
- **Értékelés fut:** Az oldal letiltva, amíg aktív értékelési időszak van
- **Részlegvezető törlése:** Nem törölhető, amíg részlegvezetői szerepben van
- **Email módosítás:** Létező felhasználó email címe nem módosítható
- **Kapcsolat törlése:** Részlegből származó automatikus kapcsolatok nem törölhetők
- **Csoport kompetencia törlése:** Kompetencia csoportból jövő kompetenciák nem törölhetők egyedileg

### ⚠️ Figyelem:
- **Munkavállaló limit:** Az első mérés lezárása előtt figyelmeztetést kapsz, ha túlléped a limitet
- **Import méretkorlát:** Maximum 500 sor importálható egyszerre
- **Kapcsolat ütközések:** A rendszer jelzi, ha ellentmondó kapcsolatok vannak (pl. A szerint B beosztott, de B szerint A kolléga)
- **Értékelők száma:** A rendszer jelzi, ha valakinek kevés értékelője van:
  - 🔴 Piros (kevesebb mint 3) - Nem elegendő
  - 🟡 Sárga (3-6) - Elfogadható
  - 🟢 Zöld (7 vagy több) - Ideális

---

## Hibaüzenetek

### "Email már használatban van"
**Mikor jelenik meg:** Új alkalmazott létrehozásakor, ha az email cím már létezik a rendszerben  
**Megoldás:**
1. Ellenőrizd, hogy valóban új felhasználót akarsz-e létrehozni
2. Ha a felhasználó már létezik, használd a szerkesztés funkciót
3. Ha törölt felhasználóról van szó, vedd fel a kapcsolatot az adminisztrátorral

### "A részleg neve kötelező"
**Mikor jelenik meg:** Részleg létrehozásakor/szerkesztésekor, ha a név mező üres  
**Megoldás:** Adj meg egy nevet a részlegnek (pl. "IT Osztály")

### "Legalább egy vezető megadása kötelező"
**Mikor jelenik meg:** Részleg létrehozásakor, ha nincs egyetlen vezető sem kiválasztva  
**Megoldás:** Válassz ki legalább egy Manager típusú felhasználót vezetőnek

### "Elérte a maximális munkavállalói létszámot"
**Mikor jelenik meg:** Új alkalmazott felvételekor, ha elérted a regisztrációs díjban foglalt létszámot  
**Megoldás:**
- Az első értékelés lezárása után korlátlanul hozhatsz létre új munkavállalókat
- Ha sürgős, folytathatod a regisztrációt, de további költségekre számíts

### "Fiók zárolva - túl sok sikertelen bejelentkezési kísérlet"
**Mikor jelenik meg:** Felhasználó soránál, ha túl sok sikertelen bejelentkezés történt  
**Megoldás:** Használd a "Fiók feloldása" gombot (piros lakat ikon <i class="fa fa-lock"></i>)

### "Nem sikerült menteni a változtatásokat"
**Mikor jelenik meg:** Bármilyen mentési művelet után, ha hiba történt  
**Megoldás:**
1. Frissítsd az oldalt és próbáld újra
2. Ellenőrizd az adatok helyességét
3. Ha továbbra sem működik, nézd meg a böngésző konzolt (F12 → Console fül)
4. Szükség esetén jelezd az adminisztrátornak

---

## GYIK (Gyakran Ismételt Kérdések)

### 📌 Hogyan változtatom meg egy munkatárs részlegét?
Kattints a narancssárga ceruza ikonra (Adatok módosítása), majd a "Részleg" legördülő menüben válaszd ki az új részleget. Ne felejts el menteni!

### 📌 Mi a különbség a "kolléga", "beosztott" és "felettes" kapcsolat között?
- **Kolléga:** Azonos szinten dolgoznak, kölcsönösen értékelik egymást
- **Beosztott:** Te vagy a felettese, ő értékel téged vezetőként, de te nem értékeled őt közvetlenül
- **Felettes:** Ő a felettesed, te értékeled őt, de ő téged csak vezetői értékelés keretében

### 📌 Miért nem tudom törölni egy munkatárs kapcsolatát?
Ha a kapcsolat egy részleg alapján automatikusan jött létre (pl. részlegvezető és részlegtag), akkor azt nem lehet törölni. Csak a manuálisan létrehozott kapcsolatok törölhetők.

### 📌 Hogyan működik a kompetencia csoportok rendszere?
Ha egy munkatársat hozzárendelsz egy kompetencia csoporthoz (pl. "Értékesítők"), akkor automatikusan megkapja az összes abban a csoportban lévő kompetenciát. Ezek nem törölhetők egyedileg, csak ha eltávolítod a munkatársat a csoportból.

### 📌 Mit jelent az értékelők számának színkódja?
- 🔴 **Piros (kevesebb mint 3):** Nem elegendő értékelő, az értékelés nem lesz megbízható
- 🟡 **Sárga (3-6 értékelő):** Elfogadható, de több értékelő ajánlott
- 🟢 **Zöld (7 vagy több):** Ideális számú értékelő, megbízható eredmények

### 📌 Mi történik, ha törlök egy részleget?
A részleg törlésre kerül, de a tagok nem! A részleg minden tagja automatikusan átkerül a "Nem besorolt felhasználók" közé. A vezetők is megmaradnak a rendszerben, csak a részleghez való hozzárendelésük szűnik meg.

### 📌 Hogyan tudom feloldani egy zárolva lévő fiókot?
Ha a felhasználó neve mellett piros lakat ikon látható, kattints rá. A rendszer törli az összes sikertelen bejelentkezési kísérletet, és a felhasználó újra be tud jelentkezni.

### 📌 Milyen fájlformátumot használhatok a tömeges importhoz?
Csak .xlsx (Excel) formátumú fájlokat fogad el a rendszer. Kötelező a sablon használata, hogy a mezők formátuma megfelelő legyen!

### 📌 Mi a maximális létszám, amit egyszerre importálhatok?
Egy importálási művelet során maximum 500 sort dolgozhat fel a rendszer. Ha ennél több munkavállalót szeretnél felvenni, oszd fel több részre az importot.

### 📌 Hogyan tudom módosítani egy munkatárs bónusz szintjét?
Kattints a kék rétegek ikonra <i class="fa fa-layer-group"></i> (Besorolás). Ez a funkció csak akkor látható, ha a bónusz/málusz rendszer be van kapcsolva a beállításokban.

### 📌 Miért nem látom a "Besorolás" gombot?
A Besorolás (bónusz/málusz) funkció csak akkor látható, ha a bónusz/málusz rendszer aktív a Beállítások oldalon. Ellenőrizd az admin beállításokat!

### 📌 Mit jelent az "Easy Relation Setup"?
Ez egy beállítás, ami meghatározza, hogyan működnek az új kapcsolatok:
- **BE van kapcsolva:** Minden új kapcsolat automatikusan "kolléga" típusú lesz, amit utólag átállíthatsz
- **KI van kapcsolva:** Már az első beállításkor meg kell adni a pontos kapcsolat típust

### 📌 Hogyan használhatom a Cégkapcsolati háló funkciót?
Kattints a "Cégkapcsolati háló" gombra. Interaktív gráf jelenik meg, ahol láthatod a munkatársak közötti kapcsolatokat. Szűrhetsz részlegek szerint, választhatsz különböző elrendezéseket, és zoomolhatsz is.

---

## Kapcsolódó oldalak

- **[Főoldal](/admin/home)**: Értékelés indítása, aktív mérések kezelése
- **[Kompetenciák](/admin/competency/index)**: Értékelési szempontok és kérdések szerkesztése
- **[Eredmények](/admin/results/index)**: Értékelési eredmények megtekintése
- **[Bónuszok](/admin/bonuses/index)**: Bónusz/málusz szorzók és bérek kezelése

---