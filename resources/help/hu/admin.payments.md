---
view: admin.payments
title: Fizetések
role: admin
related: [admin.home, admin.employees, admin.results]
actions:
  - { label: "Nyitott tartozás fizetése", href: "#", trigger: "click-pay-button" }
  - { label: "Számla letöltése", href: "#", trigger: "click-invoice-button" }
---

# Mi ez az oldal?

A **Fizetések** oldal az értékelési mérések utáni számlázás és fizetések kezelésére szolgál. Minden elindított mérési időszak után automatikusan létrejön egy fizetési tétel, amit ezen az oldalon tudsz rendezni, kizárólag bankkártyás fizetéssel.

Az oldal két fő részre oszlik:
- **Nyitott tartozások**: Még ki nem fizetett vagy sikertelen fizetések
- **Korábban rendezettek**: Már kifizetett tételek a letölthető számlákkal

---

## Mit tudsz itt csinálni?

### 🔴 Nyitott tartozások kezelése
- **Fizetés indítása**: Átirányítás a Barion fizetési felületre
- **Státusz ellenőrzése**: Folyamatban lévő vagy sikertelen fizetések áttekintése
- **Fizetési határidő követése**: Látod, meddig kell rendezned a tételt, ez a határidő mindig az aktuálisan futó mérési időszak véghatárideje. Ha azt megváltoztatod, a fizetési határidő is módosul.

### ✅ Rendezett tételek kezelése
- **Számla letöltése**: PDF formátumban töltheted le a kiállított számlát
- **Fizetési előzmények**: Korábbi fizetések áttekintése
- **Számlaszám és dátum**: Minden fizetéshez tartozó adatok elérése

---

## Lépések: Fizetés végrehajtása

### 1. Nyitott tartozás azonosítása
- Keresd meg a kifizetendő tételt a **"Nyitott tartozások"** táblázatban
- Ellenőrizd az összeget és a fizetési határidőt

### 2. Fizetés indítása
- Kattints a **"Fizetés indítása"** gombra a tétel mellett
- A rendszer átirányít a Barion biztonságos fizetési oldalára
- ⚠️ **Fontos**: Ne indíts új fizetést, ha már folyamatban van egy! Sikertelen fizetés esetén a rendszer automatikusan "Sikertelen" státuszra állítja a fizetést és megpróbálhatod újra.

### 3. Fizetés teljesítése a Barion felületen
- Válaszd ki a számodra megfelelő fizetési módot, amit a Barion felkínál.
- Kövesd a Barion, esetleg a bankod utasításait.
- A sikeres fizetés után automatikusan visszairányít az alkalmazásba, ahol már látod is, hogy sikeres volt a fizetés.

### 4. Számla ellenőrzése
- Sikeres fizetés után a tétel átkerül a **"Korábban rendezettek"** közé
- A számla PDF automatikusan kiállításra kerül (általában néhány másodperc)
- Töltsd le a számlát a **"Számla letöltése"** gombbal

---

## Mezők magyarázata

### Nyitott tartozások táblázat

| Mező | Jelentés |
|------|----------|
| **Dátum** | Mikor jött létre a fizetési kötelezettség |
| **Fizetési határidő** | Meddig kell kiegyenlíteni a tételt |
| **Összeg** | Fizetendő összeg (forintban) |
| **Státusz** | **Folyamatban**: Barion fizetés folyamatban<br>**Sikertelen**: A fizetés nem jött létre |
| **Művelet** | "Fizetés indítása" gomb |

### Rendezett tételek táblázat

| Mező | Jelentés |
|------|----------|
| **Kiállítás dátuma** | Mikor állították ki a számlát |
| **Fizetés dátuma** | Mikor történt meg a sikeres fizetés |
| **Számlaszám** | A számla egyedi azonosítója |
| **Összeg** | Kifizetett összeg |
| **Művelet** | "Számla letöltése" gomb (PDF) |

---

## Gyakori hibák és megoldásuk

### ❌ "Ehhez a tételhez már folyamatban van egy fizetés"
**Ok**: Már elindítottál egy fizetést erre a tételre, de még nem fejezted be.

**Megoldás**:
1. Ellenőrizd az email fiókodat - lehet, hogy kaptál egy Barion linket
2. Használd azt a linket a fizetés befejezéséhez
3. Ha már befejezted a fizetést, várj néhány percet, majd frissítsd az oldalt
4. Ha továbbra is probléma van, kattints újra a "Fizetés indítása" gombra

### ❌ "A számla PDF még feldolgozás alatt áll"
**Ok**: A rendszerünk még generálja a számlát (általában 10-30 másodperc).

**Megoldás**:
- Várj 30 másodpercet, majd próbáld újra letölteni
- Frissítsd az oldalt (F5)

### ❌ Sikertelen fizetés
**Ok**: A Barion fizetés megszakadt vagy elutasításra került. Bankkártyád nem lett megterhelve.

**Megoldás**:
1. Kattints újra a "Fizetés indítása" gombra
2. Próbálj meg másik fizetési módot használni
3. Ellenőrizd, hogy elegendő fedezet van-e a kártyán

### ❌ "Nem sikerült indítani a fizetést"
**Ok**: Technikai hiba a Barion kapcsolatban.

**Megoldás**:
- Várj 1-2 percet, majd próbáld újra
- Ha továbbra is hiba van, jelezd ügyfélszolgálatunkon.
---

## GYIK (Gyakran Ismételt Kérdések)

### 📌 Hogyan számolódik ki a fizetendő összeg?
Az összeg az értékelt dolgozók száma alapján kerül kiszámításra. Aktuális tarifákról tájékozódj a weboldalunkon.

### 📌 Milyen fizetési módokat tudok használni?
A Barion az alábbi módokat támogatja:
- Bankkártya (Visa, Mastercard)
- Azonnali banki átutalás
- Egyéb online fizetési módok (Barion egyenleg, Google Pay, Apple Pay stb.)

### 📌 Mikor kapom meg a számlát?
A sikeres fizetés után **automatikusan**, kb. 10-30 másodpercen belül. Ha nem jelenik meg azonnal, frissítsd az oldalt vagy várj egy percet.

### 📌 Mi van, ha rossz összeget látok?
Az összeg automatikusan számolódik a dolgozók száma alapján. Ha esetleg úgy gondolod, hogy az összeg továbbra sem megfelelő, keress minket.

### 📌 Lehet módosítani egy már kifizetett tételt?
Nem. A kifizetett tételek zároltak. Ha hibát találsz a számlán, vedd fel a kapcsolatot az ügyfélszolgálattal.

### 📌 Meddig érvényes a fizetési határidő?
Általában **7 nap** a mérés lezárásától számítva. Fontos, hogy határidőn belül rendezd, különben a rendszer blokkolhatja az új mérések indítását.

### 📌 Hol találom a korábbi számláimat?
Minden korábbi számla elérhető a **"Korábban rendezettek"** táblázatban. Kattints a "Számla letöltése" gombra bármelyik tételnél.

### 📌 Mi történik, ha nem fizetek?
Nyitott tartozással rendelkező cég nem tudja lezárni a megnyitott értékelési időszakot. Amíg az értékelési időszakot nem zárod le (ehhez rendezned kell a számlát), addig minden korábbi eredmény megtekintése, illetve a beállítások módosítása is zárolva van.

---

## Kapcsolódó oldalak

- **[Főoldal](admin.home)**: Visszatérés a kezdőlapra és új mérés indítása
- **[Alkalmazottak](admin.employees)**: Dolgozók kezelése (a fizetési összeg alapja)
- **[Eredmények](admin.results)**: A kifizetett mérések eredményeinek megtekintése

---

## Gyors segítség

⚠️ **Fontos**: Mindig ellenőrizd, hogy a helyes céges adatok szerepelnek-e a számlán. Ha hibát találsz, jelezd azonnal!

📧 **Segítségre van szükséged?** Írj nekünk: support@nwbusiness.hu