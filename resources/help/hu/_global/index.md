---
title: "Quarma360 Súgó Központ"
version: "1.0"
last_updated: "2025-10-23"
---

# Quarma360 - 360° Értékelési Rendszer

## 🎯 A Rendszer Célja

A **Quarma360** egy **360 fokos értékelési platform**, amely segíti a szervezeteket abban, hogy átfogó képet kapjanak munkavállalóik teljesítményéről. A rendszer **többirányú visszajelzéseket** gyűjt (saját értékelés, kollégák, vezetők, beosztottak), és ezek alapján **objektív pontszámokat** és **javaslatokat** ad előléptetésekre vagy bónuszokra.

## A rendszer alapvető működése:
- Az admin a regisztráció után tudja hozzáadni a dolgozókat. Az admin felhasználó NEM vesz részt a mérésben, csak "külső irányító és megfigyelő".
- Az alkalmazottak tömegesen is importálhatóak. A rendszer kétféle működésre képes. Kisebb cégek esetén alkalmazható az egyszintes elrendezés: itt ügyvezetők (ceo-k), és alkalmazottak adhatóak a rendszerhez. De ezen belül is lehet a viszonyokban a beosztotti felettesi viszonyt beállítani. A multi-level kezelés bekpcsolásával részlegek és managerek és létrehozhatóak. Ezt akkor ajánljuk, ha a cégben nagyobb, legalább 5-10 fős elkülönülő részlegek vannak és azoknak meghatározható vezetői. A részlegek vezetői (managerek) úgy viselkednek, mint a részlegek ügyvezetői. Egy részlegnek több managere is lehet, de egy manager csak egy részleget vezethet. A viszonyok ilyenkor is beállíthatóak a részlegen kívül is, azt nem befolyásolja.
- Fizetés: a mérési időszakokért fizet a cég, bankkártyával. Regisztrációkor a megadott számú munkavállaló első mérési díját ki kell fizetni a rendszer használatához, azonban van egy 5 napos kezdő próbaidőszak, tehát addig is használható a rendszer konfigurációs felülete, viszont értékelési időszak nem indítható. Az első fizetés után az első mérés elindítható. Ha van különbözet (több alkalmazott lett regisztrálva, mint a cég létrehozásakor megadott), akkor azt rendezni kell a lezárás előtt. Egyébként fizetési kötelezettség az alkalmazottak számától függően keletkezik minden mérés elindításakor. A mérést csak akkor lehet lezárni, ha nincs nyitott tartozás. A cég kreditekben esetleg jóváírást kaphat, amit későbbi időszakokban felhasználhat.
- Az app fejlett intelligenciával (AI) felszerelt. Az AI asszisztens felügyeli a kitöltések megbízhatóságát, segít a pontszámokban, fejlesztési tervek meghatározásában, az eredmények értékelésében, sőt, akár a csapat teljesítménye alapján a küszöbszámok meghatározását is rá lehet bízni a mérések során. Az AI használata biztonságos és a rendszer úgy lett megtervezve, hogy anonim maradhasson.

---

## 👥 Felhasználói Szerepkörök

A rendszerben **4 különböző szervezeti szerepkör** létezik:

### 1. **Admin (Adminisztrátor)**
- **Legmagasabb jogosultság** a szervezeten belül
- **Mit tehet:**
  - Értékelések indítása és lezárása
  - Alkalmazottak kezelése (hozzáadás, törlés, szerkesztés)
  - Szervezeti beállítások módosítása
  - Kompetenciák és kérdések kezelése
  - Osztályok és kapcsolatok beállítása
  - Bónuszok konfigurálása
  - Vezetői rangsorolási kategóriák, lépcsők létrehozása
  - Számlázások, használati díjak kezelése (Barion, számlák)
  - Eredmények megtekintése minden alkalmazottról

### 2. **CEO (Ügyvezető/Felsővezető)**
- **Speciális vezető szerepkör**
- **Mit tehet:**
  - Saját értékelések kitöltése
  - Vezetői rangsorolásban részvétel (más vezetők értékelése)
  - Beosztottak értékelésének megtekintése (ha van joga)
  - Saját eredmények megtekintése

### 3. **Manager (Vezető/Osztályvezető)**
- **Középvezető szerepkör**
- **Mit tehet:**
  - Osztálytagok értékelése
  - Saját értékelések kitöltése
  - Vezetői rangsorolásban részvétel (vezetők értékelése)
  - Beosztottak eredményeinek megtekintése
  - Saját eredmények megtekintése

### 4. **Employee (Alkalmazott)**
- **Alapvető szervezeti tag**
- **Mit tehet:**
  - Értékelések kitöltése (kollégákról, vezetőkről, önmagáról)
  - Saját eredmények megtekintése
  - Saját bónusz/malusz szintek követése

---

## 📋 Értékelési Folyamat

### **1. Értékelés Indítása** (Admin)
1. Az admin a **Főoldalon**  megnyomja az **"Értékelési időszak indítása"** gombot
2. Beállítja a **határidőt** (meddig kell kitölteni)
3. A rendszer automatikusan:
   - Rögzíti az aktuális szervezeti struktúrát (osztályok, kapcsolatok)
   - Meghatározza, ki kit értékelhet
   - Értesítéseket küld az érintetteknek

### **2. Értékelések Kitöltése** (Minden felhasználó)
- Minden munkavállaló kap egy **listát azokról, akiket értékelnie kell**:
  - **Saját maga** (self assessment)
  - **Kollégák** (peer review)
  - **Vezető(k)** (upward feedback - ha van)
  - **Beosztottak** (downward feedback - csak vezetők esetén)

- **Értékelési kérdések:**
  - A rendszer **kompetenciákat** kérdez (pl. kommunikáció, csapatmunka, problémamegoldás)
  - Minden kompetenciához több **kérdés** tartozik
  - **Egy meghatározott skálán** kell értékelni, hoszzú választ igénylő kérdés nincs. Minden pontokban kerül kiszámításra egzakt módon.

### **3. Vezetői Rangsorolás** (CEO és Manager)
- **Csak CEO-k és managerek** tölthetik ki
- A vezetőknek **a hozzájuk tartozó dolgozókat kell kategóriákba sorolniuk**.
- 0-100 pont közötti lépcsők vannak, mindenki a kategóriájának megfelelő pontszámot kap.
- Pl. van kiválóan teljesített, átlagosan, átlag alatt, értékelhetetlen stb.


### **4. Értékelés Lezárása** (Admin)
- Az admin lezárja az értékelést a határidő után, az nem záródik le automatikusan a határidő lejártakor, az csak egy jelzés.
- A rendszer **kiszámítja a végső pontszámokat**
- Ha be van kapcsolva, akkor a bónuszrendszerrel lépteti a kategóriákat le/fel, illetve ha van megadott bér és be van kapcsolva a funkció akkor a bónuszokat is kiszámolja.

---

## 🧮 Pontszámítás - Hogyan Jönnek Létre Az Eredmények

A végső pontszám **5 komponensből áll**, mindegyik **0-100 pontos**:

### **1. Saját Értékelés (Self) - Súly: 0.5x**
- Az alkalmazott **saját magát** értékeli
- **Nincs fallback**: ha valaki nem tölti ki, ez a komponens hiányzik

### **2. Kollégák Értékelése (Colleagues) - Súly: 1.0x**
- **Peers, munkatársak** értékelése (akikkel ugyanolyan szinten dolgozik)
- **Trust súly**: ha valaki megbízhatatlanul tölt ki (gyorsan, véletlenszerűen), kisebb súlyt kap
- **Fallback**: ha nincs kollégától értékelés, a CEO rank-ből számol

### **3. Beosztottak Értékelése (Direct Reports) - Súly: 1.0x**
- **Csak vezetőknél** van (manager, CEO)
- A beosztottak **felfelé értékelik** a vezetőjüket
- **ÚJ funkció 2025-ben bevezetve!**

### **4. Vezetők Értékelése (Managers) - Súly: 1.0x**
- A **felettes vezető(k)** értékelése
- Fontos komponens az előléptetéshez

### **5. CEO Rangsorolás (CEO Rank) - Súly: 1.0x**
- **CEO-k és managerek** rangsorolják egymást
- Stratégiai szempontok alapján

### **Végső Pontszám Képlete:**
```
Végső Pontszám = (Self × 0.5 + Colleagues × 1.0 + DirectReports × 1.0 + Managers × 1.0 + CEORank × 1.0) 
                 / (rendelkezésre álló komponensek súlyának összege)
```

**Példa:**
- Self: 85 (súly: 0.5)
- Colleagues: 78 (súly: 1.0)
- Managers: 92 (súly: 1.0)
- CEO Rank: 80 (súly: 1.0)
- Direct Reports: nincs (nem vezető)
```
Végső = (85×0.5 + 78×1.0 + 92×1.0 + 80×1.0) / (0.5 + 1.0 + 1.0 + 1.0) 
      = (42.5 + 78 + 92 + 80) / 3.5
      = 292.5 / 3.5
      = 83.6 pont
```

---

## 📊 Küszöbértékek és Előléptetés

A rendszer **két küszöbértéket** használ:

### **Promotion Threshold (Előléptetési Küszöb)**
- Alapértelmezett: **90 pont**
- Ha valaki **ezen felül** teljesít → **előléptetés javasolva** (bónusz szint +1)

### **Demotion Threshold (Demóciós Küszöb)**
- Alapértelmezett: **75 pont**
- Ha valaki **ezen alul** teljesít → **demóció javasolva** (bónusz szint -1)

**Megjegyzés:** Az admin beállíthatja ezeket a küszöböket a szervezet igényei szerint.

---

## 💰 Bónusz/Malusz Rendszer (Multi-Level)

Ha a szervezetben be van kapcsolva a **multi-level funkció**:

### **Bónusz Szintek (0-10)**
- Minden alkalmazottnak van egy **bónusz szintje** (ha a funkció be van kapcsolva)
- a bónusz szintek hasonlítanak a biztosításoknál megszokott Bonus-Malus kategóriákhoz, M04...M01-A00-B01...B10 kategóriák vannak. Minden kategóriához egyedi szorzó állítható be. A rendszer úgy kalkulál, hogy B10 kategóriában, negyedéves kifizetésekkel egy havi extra bér szedhető össze. A rendszer jutalmaz, nem büntet. Konkrét 0 értéket csak az M04 vesz fel a beállítások szerint, tehát ez jelenti a "negyedéves bónusz" teljes elmaradását.
- **Magasabb szint = magasabb bónusz/fizetés**
- Az értékelések alapján **évente változhat** a szint

### **Hogyan Működik:**
3. Ha valaki **előlép** (jó teljesítmény) → szint +1
4. Ha valaki **lefele lép** (gyenge teljesítmény) → szint -1


## 🏢 Szervezeti Struktúra

### **Osztályok (Departments)**
- Adminok **osztályokat hozhatnak létre** (pl. IT, Marketing, Pénzügy)
- Minden osztályhoz tartoznak **manager(ek)** és dolgozók.
- A managerek **a saját osztályukban** lévőket értékelhetik, de értékelhetnek másokat is.

### **Kapcsolatok (Relations)**
A rendszer **automatikusan meghatározza**, ki kit értékelhet:
- **self** Önértékelés → saját maga
- **colleague / peer** Mellérendelő viszony → ugyanazon szinten lévő munkatársak
- **subordinate** → Beosztotti viszony (akit a manager/CEO irányít)
- **superior** → Felettesi viszony (akik felfelé értékelik a vezetőt)
- A viszonyok beállítása mindig az "értékelő" szempontjából történik: pl. ha A dolgozó viszonyát állítjuk be, és ő B-nek a főnöke, akkor A-nál a beosztotti viszonyt állítsuk be a viszonylista ablakban az Alkalmazottak oldalon.
- A beállításokban bekapcsolható a viszonylista egyszerű beállítása, ami automatikusan kezeli és letrehozza a relációk ellentétes irányíú párját. Ha ez a beállítás ki van kapcsolva, akkor mindenkinek egyedi kapcsolatok is beállíthatóak, de a rendszer nem engedi az ellentmondásokat. Az Alkalmazottak oldalon lehet nyomon követni, kit hányan értékelnek, itt minimum 3-4 fő minimum, de ajánlott legalább 5-6 fő. Ebben mindenképp legyen vezetői értékelés is.

---

## 🔧 Kompetenciák és Kérdések

### **Kompetenciák:**
- Olyan **értékelési szempontok**, mint pl.:
  - Kommunikáció
  - Csapatmunka
  - Problémamegoldás
  - Időgazdálkodás
  - Technikai tudás

### **Kérdések:**
- Minden kompetenciához **3-5 állítás** tartozik
- Például a "Kommunikáció" kompetenciánál:
  - "Világosan és érthetően fogalmaz."
  - "Aktívan hallgat a beszélgetések során."
  - "Konstruktívan ad visszajelzést."

### **Globális vs Szervezeti:**
- **Globális kompetenciák**: minden szervezet használhatja (adminok hozták létre) és rendelkezésre bocsájtjuk.
- **Szervezeti kompetenciák**: csak az adott cégnél érvényesek.
- Kompetencia csoportok: létrehozhatóak kompetencia csoportok, amelyekhez kompetenciákat és dolgozókat is rendelhetünk. Ezzel jelentősen meggyorsíthatjuk a kompetenciák beállítását, nem kell ugyanabban a munkakörben dolgozó kollégához egyesével hozzáadni minden kompetenciát, hanem pl. létrehozható az "Értékesítők" kompetenciacsoport, amihez az összes értékesítőt hozzárendelhetjük és az adott kompetenciák szerint fogjuk értékelni. A kompetenciák mérési időszakok között megváltoztathatóak.

---

## 📍 Elérhető Oldalak és Súgók

### **Admin Oldalak:**
1. **admin.home** - Admin főoldal: értékelés indítása, statisztikák értékelések közben
2. **admin.results** - Eredmények: értékelési eredmények megtekintése
3. **admin.bonuses** - Bónuszok: bónuszok, jutalmak kezelése, bérek szerint
4. **admin.employees** - Alkalmazottak: alkalmazottak, managerek, vezetők felvitele, tömeges import, relációk beállítása, kompetenciák hozzárendelése
5. **admin.competency** - Kompetenciák: kompetenciák, kompetenciacsoportok és kérdések szerkesztése
6. **admin.ceoranks** - Vezetői rangsor: vezetői rangsorolási szempontok beállítása
7. **admin.settings** - Beállítások: rendszer beállítások (küszöbök, funkciók) kezelése
8. **admin.payments** - Számlázás: fizetések kezelése, számlák, Barion integráció, számlázási adatok módosítása

### **Felhasználói Oldalak:**
9. **home** - Főoldal: értékelési feladatok elvégzése, kérdőívek, vezetői rangsor indítása
10. **assessment** - Értékelési oldal: kompetencia értékelések kitöltése
11. **results** - Eredmények: saját eredmények (esetleg bónuszok) megtekintése (korábbiak is)
12. **ceorank** - Vezetői rangsor: CEO/Manager rangsorolás kitöltése

### **Egyéb:**
13. **org.select** - Szervezetváltás (több céges felhasználóknak)

---

## 🔍 Gyakran Használt Kifejezések

- **Assessment**: Értékelési időszak/folyamat
- **Competency**: Kompetencia, értékelési szempont
- **Snapshot**: A szervezet pillanatfelvétele az értékelés indításakor
- **Trust Score**: Megbízhatósági pontszám (0-20), méri mennyire gondosan töltötte ki valaki
- **Threshold**: Küszöbérték (előléptetéshez/demócióhoz)
- **Level**: Bónusz/malusz szint (0-10)
- **Multiplier**: Bónusz szorzó (pl. 1.2x)
- **Relation**: Kapcsolat típusa (self, colleague, subordinate, superior)
- **Department**: Osztály, szervezeti egység
- **Rater**: Értékelő (aki kitölti az értékelést)
- **Target**: Értékelt (akiről az értékelés szól)

---

## 📱 Navigáció a Rendszerben

### **Felső sáv (navbar):**
- **Kezdőlap** 🏠 - Főoldal (mindenki látja, csak más a funkció)
- **Eredmények** 📊 - Értékelési eredmények (admin minden dolgozót lát, az alkalmazott, manager, ceo csak önmagát)
- **Bónuszok** 💰 - Bónusz kezelés (admin)
- **Konfigurácó** ⚙️ - Dropdown menü (csak az admin látja, ha nem fut értékelés):
  - Alkalmazottak
  - Kompetenciák
  - Vezetői rangsor
  - Beállítások
  - Számlázás

### **Felső Sáv (Navbar) extra infók:**
- Felhasználó neve és email
- Szervezet neve (ha több céghez is tartozik, válthat)
- Kijelentkezés gomb

A felső navbar telefonos eszközökön alsó navbarként jelenik meg, ugyanezzel a funkcionalitással, a felsp navbaron csak az extra infók maradnak.

---

## ❓ Mire Használjam Az AI Chatbot-ot?

A **súgó rendszer AI asszisztense** segít:
- **Navigációban**: "Hol tudom megváltoztatni a bónusz szorzót?"
- **Funkciók megértésében**: "Hogyan számolódik a CEO rangsorolás?"
- **Hibakeresésben**: "Miért nem látom a fizetések menüt?"
- **Lépésről lépésre útmutatásban**: "Hogyan indítsak új értékelést?"

**Használati tippek:**
- Kérdezz konkrétan: "Hogyan töltök ki egy értékelést?"
- Ha több oldalra vonatkozik a kérdés, az AI automatikusan betölt további súgót
- Minden válasznál megkapod a pontos lépéseket és gombneveket

---

## Fordítások, multilingual működés

A rendszer képes **multilingual, többnyelvű** módban működni:
- **Nyelvválasztó**: Az admin meghatározhatja a listából a szükséges nyelveket, ha pl. van több angol nyelvű kolléga, akkor a magyar mellett az angol nyelvet is választhatja.
- **Fordítások**: Ha több nyelv is ki van választva (az admin nyelve az alapértelmezett), akkor az egyedileg létrehozott kompetenciák és vezetői rangsor kategóriák egy AI fordító asszisztens segítségével egy gombnyomással létrehozhatóak MINDEN kiválasztott nyelvre.
- **Fordítások megjelenítése**: az értékelések futtatása során minden kolléga az általa kiválasztott nyelven látja nem csak az oldalt, hanem a mérési kompetenciákat is.
- **Elérhető nyelvek**: a globális kompetenciák és az alap vezetői rangsor elemei az összes támgoatott nyelven elérhetőek, azokat nem kell fordítani.


**Használati tippek:**
- Kérdezz konkrétan: "Hogyan töltök ki egy értékelést?"
- Ha több oldalra vonatkozik a kérdés, az AI automatikusan betölt további súgót
- Minden válasznál megkapod a pontos lépéseket és gombneveket

---

## 🆘 További Segítségre Van Szükséged?

Ha nem találod meg a választ a kérdésedre:
1. **Használd az AI chatbot-ot** - Kérdezz bátran!
2. **Nézd meg a konkrét oldal súgóját** - Minden oldalon van az AI chatbot mellett Súgó típusú leírás
3. **Keresd az adminisztrátort** - Ő tud technikai részletekben segíteni

---

**Verzió:** 1.0  
**Utolsó frissítés:** 2025. október 23.  
**© Quarma360 - 360° Értékelési Platform**