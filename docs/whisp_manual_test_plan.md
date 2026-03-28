# Whisp – Manuální testovací plán

Tento dokument slouží jako **praktický průvodce testováním** celé aplikace Whisp.
Projdi kapitoly v pořadí, jak jsou napsány — každý krok navazuje na předchozí.

---

## Příprava před testováním

### Spuštění aplikace
```bash
docker compose up --build
docker exec -it whisp_backend php public/install_admin.php
```

### Otevři 4 okna prohlížeče
| Okno | Uživatel | Typ |
|------|---------|-----|
| Okno 1 | User A | Normální okno (Chrome) |
| Okno 2 | User B | Anonymní / jiný prohlížeč |
| Okno 3 | User C | Anonymní / třetí okno |
| Okno 4 | Admin | Anonymní / čtvrté okno |

> Každé okno musí být na `http://localhost:5173`

---

## Fáze 1 – Registrace a přihlášení

### 1.1 Registrace platných účtů

Zaregistruj tyto 4 účty — každý v jiném okně:

| Účet | Username | Email | Heslo |
|------|---------|-------|-------|
| User A | userA | a@test.cz | heslo123 |
| User B | userB | b@test.cz | heslo123 |
| User C | userC | c@test.cz | heslo123 |
| User D (volitelný) | userD | d@test.cz | heslo123 |

**Ověř po každé registraci:**
- [ ] Přihlášení proběhlo automaticky
- [ ] V hlavičce se zobrazuje správné username
- [ ] Sidebar se načetl (i když je prázdný)

---

### 1.2 Negativní testy registrace
Proveď v libovolném okně — po každém testu stránku obnoš:

- [ ] Registrace **bez username** → zobrazí chybu, neprojde
- [ ] Registrace **bez emailu** → zobrazí chybu, neprojde
- [ ] Registrace **s heslem kratším než 6 znaků** → zobrazí chybu, neprojde
- [ ] Registrace **s neplatným emailem** (např. `ahoj`) → zobrazí chybu
- [ ] Registrace **s duplicitním emailem** (použij `a@test.cz`) → zobrazí `email je obsazen`
- [ ] Registrace **s duplicitním username** (použij `userA`) → zobrazí `jméno je obsazeno`

---

### 1.3 Admin přihlášení
V okně 4 se přihlas jako admin:
- Email: `a@a.a`
- Heslo: `a`

- [ ] Přihlášení proběhlo → zobrazuje se Admin Panel (ne chat)

---

### 1.4 Login/Logout cyklus
Proveď v okně 1 (User A):

1. Odhlásit se
2. Přihlásit se znovu
3. Obnovit stránku (F5)

- [ ] Po odhlášení se zobrazí přihlašovací stránka
- [ ] Po novém přihlášení funguje aplikace
- [ ] Po F5 zůstane uživatel přihlášen (session token v localStorage)

**Negativní testy přihlášení:**
- [ ] Špatné heslo → zobrazí `Neplatný email nebo heslo`
- [ ] Neexistující email → zobrazí `Neplatný email nebo heslo`
- [ ] Prázdná pole → button nefunguje (HTML required)

---

## Fáze 2 – Profil

### 2.1 Úprava vlastního profilu
V okně 1 (User A) — klikni na ⚙️:

1. Změň **bio** na libovolný text
2. Změň **avatar** — záložka Generovaný → klikni 🎲 několikrát
3. Klikni **Uložit změny**

- [ ] Stránka se obnoví
- [ ] Nový avatar a bio se zobrazují
- [ ] Po F5 jsou změny zachovány
- [ ] V okně 2 (User B) se po refreshi vidí změny u User A v přátelích (otestovat po přidání přátel)

### 2.2 Vlastní profilový modal
- [ ] Kliknutí na vlastní avatar/jméno v headeru → profil se otevře
- [ ] V profilu **není** tlačítko „Odebrat z přátel" (je to vlastní profil)

---

## Fáze 3 – Systém přátel

### 3.1 Odeslání žádosti A → B
V okně 1 (User A):

1. Klikni na 👤+ (Správce přátel)
2. Záložka 🔍 Hledat → zadej `userB`
3. Klikni **Poslat žádost**

- [ ] Tlačítko se změní na `Odesláno ✔`
- [ ] V okně 2 (User B) se v záložce 📩 Žádosti zobrazí žádost od userA
- [ ] V User B sidebaru se zobrazí notifikační badge (pokud je implementovaný)

---

### 3.2 Odmítnutí žádosti
V okně 2 (User B) → 📩 Žádosti:

1. Klikni **✕ Odmítnout** u žádosti od User A

- [ ] Žádost zmizí ze seznamu ihned
- [ ] User A **není** v přátelích User B
- [ ] Po F5 v obou oknech stav odpovídá realitě

---

### 3.3 Odeslání žádosti znovu a přijetí
V okně 1 (User A) — pošli znovu žádost User B.

V okně 2 (User B) → 📩 Žádosti:

1. Klikni **✔ Přijmout**

- [ ] User B se zobrazí v sidebaru User A (v sekci přátel)
- [ ] User A se zobrazí v sidebaru User B
- [ ] Oba uživatelé vidí vzájemný online/offline status
- [ ] Po F5 v obou oknech přátelství přetrvá

---

### 3.4 Přidání dalšího přátelství A → C a B → C
Zopakuj kroky 3.1–3.3 pro:
- A pošle žádost → C přijme
- B pošle žádost → C přijme

Výsledný stav: **A-B přátelé, A-C přátelé, B-C přátelé**

- [ ] Každý vidí správné přátele v sidebaru

---

### 3.5 Zobrazení profilu přítele
V okně 1 — klikni na avatar User B v sidebaru:

- [ ] Otevře se profilový modal User B
- [ ] Zobrazuje username, avatar, bio, online status
- [ ] **Je vidět** tlačítko „Odebrat z přátel"

---

## Fáze 4 – DM Chat

### 4.1 Otevření DM
V okně 1 (User A) — klikni na User B v sidebaru:

- [ ] Otevře se chat okno s User B
- [ ] Zobrazuje se username a online status v záhlaví
- [ ] Historie je prázdná (nová konverzace)

---

### 4.2 Posílání zpráv v reálném čase
Pošli ze okna 1 (User A) zprávu: `Ahoj User B!`

- [ ] Zpráva se zobrazí v okně 1 okamžitě
- [ ] Zpráva se zobrazí v okně 2 (User B) **bez obnovení stránky**
- [ ] User B vidí notifikaci (pokud nemá chat otevřen)

Nyní pošli odpověď z okna 2 (User B): `Čau User A!`

- [ ] Obě strany vidí kompletní konverzaci
- [ ] Zprávy mají správné časové razítko

---

### 4.3 Editace zprávy
V okně 1 (User A) — najeď na vlastní zprávu → klikni **⋮** → Upravit:

1. Změň obsah zprávy
2. Ulož

- [ ] Zpráva se aktualizuje v okně 1
- [ ] Zpráva se aktualizuje v okně 2 **bez F5**
- [ ] U zprávy se zobrazí `(upraveno)`
- [ ] **Nelze** editovat zprávu User B (tlačítko upravit se nezobrazí)

---

### 4.4 Smazání zprávy
V okně 1 (User A) — najeď na zprávu → klikni **⋮** → Smazat:

- [ ] Zpráva se nahradí `🚫 Odstraněno` v okně 1
- [ ] Stejné zobrazení v okně 2 **bez F5**
- [ ] **Nelze** smazat zprávu User B

---

### 4.5 Reply (citace)
V okně 2 (User B) — najeď na zprávu User A → klikni **↩ Odpovědět**:

- [ ] Zobrazí se banner "Odpověď..." ve vstupním poli
- [ ] Odeslaná zpráva obsahuje citaci původní zprávy
- [ ] Citace se zobrazuje u User A v okně 1

---

### 4.6 Persistence při refreshi
Proveď F5 v obou oknech:

- [ ] DM chat se znovu otevře správně
- [ ] Historie zpráv je zachována
- [ ] Smazané zprávy stále zobrazují `🚫 Odstraněno`
- [ ] Upravené zprávy mají stále `(upraveno)`

---

## Fáze 5 – Skupinový chat

### 5.1 Vytvoření skupiny
V okně 1 (User A) — klikni **+** v horní části sidebaru:

1. Zadej název skupiny: `Testovací skupina`
2. Zaškrtni **userB** a **userC**
3. Klikni **Vytvořit**

- [ ] Skupina se zobrazí v sidebaru User A
- [ ] Skupina se zobrazí v sidebaru User B (okno 2) bez F5
- [ ] Skupina se zobrazí v sidebaru User C (okno 3) bez F5

---

### 5.2 Skupinové zprávy
Pošli zprávy ze všech tří oken:

- Okno 1 (A): `Zdravím skupinu!`
- Okno 2 (B): `Ahoj od Béčka`
- Okno 3 (C): `Čau od Céčka`

- [ ] Všechny tři zprávy vidí všichni členové bez F5
- [ ] U každé zprávy je zobrazen odesílatel (jméno + avatar)
- [ ] Notifikace chodí pouze pokud uživatel nemá skupinu otevřenu

---

### 5.3 Detail skupiny
V okně 1 (User A) — klikni na název skupiny v záhlaví chatu:

- [ ] Otevře se GroupDetailsModal
- [ ] Zobrazuje seznam 3 členů (A jako admin, B a C jako member)
- [ ] User A vidí tlačítko **✎ Upravit**

---

### 5.4 Úprava skupiny
V detailu skupiny (User A):

1. Klikni **✎ Upravit**
2. Změň název na `Přejmenovaná skupina`
3. Ulož

- [ ] Nový název se zobrazí v detailu
- [ ] Název se aktualizuje v sidebaru User B a User C (bez F5)

---

### 5.5 Přidání člena
V detailu skupiny (User A) — klikni **+ Přidat další lidi**:

- Pokud máš User D přidaného jako přítele, přidej ho do skupiny

- [ ] User D vidí skupinu v sidebaru
- [ ] Ostatní členové vidí nového člena v detailu skupiny
- [ ] User D může posílat zprávy

---

### 5.6 Vyhození člena (kick)
V detailu skupiny (User A) — klikni **Vyhodit** u User B:

Sleduj **okno 2 (User B)**:

- [ ] User B dostane okamžité upozornění `Byli jste odebráni ze skupiny`
- [ ] Group chat se User B **okamžitě zavře** bez F5
- [ ] User B se vrátí na výchozí stav sidebaru
- [ ] Skupina zmizí ze sidebaru User B
- [ ] User B nemůže skupině psát ani po ručním refreshi

V okně 1 (User A):
- [ ] User B zmizí ze seznamu členů v detailu skupiny
- [ ] Konverzace pokračuje normálně pro A a C

---

### 5.7 Opuštění skupiny
V okně 3 (User C) — otevři detail skupiny → klikni **Opustit skupinu**:

- [ ] Skupina zmizí ze sidebaru User C
- [ ] User C nemůže do skupiny psát
- [ ] Po F5 se skupina nevrátí
- [ ] Zbývající členové vidí, že User C odešel

---

## Fáze 6 – Odebrání z přátel

### 6.1 Odebrání bez otevřeného chatu
V okně 1 (User A) — klikni na avatar User B → modal → **Odebrat z přátel**:

- [ ] User B zmizí ze sidebaru User A
- [ ] User A zmizí ze sidebaru User B (bez F5)
- [ ] Nelze otevřít DM — žádné tlačítko ani přístup

---

### 6.2 Odebrání s otevřeným DM na obou stranách

Nejdřív přidej A a B zpět jako přátele (Fáze 3.1–3.3), pak:

1. Okno 1 (A): otevři DM s User B
2. Okno 2 (B): otevři DM s User A
3. V okně 1 (A): klikni na avatar User B v záhlaví chatu → modal → **Odebrat z přátel**

**Sleduj User A (okno 1):**
- [ ] DM chat se ihned zavře
- [ ] Sidebar se vrátí do výchozího stavu
- [ ] Nelze odeslat zprávu

**Sleduj User B (okno 2):**
- [ ] DM chat se zavře **bez F5**
- [ ] Sidebar se vrátí do výchozího stavu
- [ ] Zobrazí se notifikace nebo toast o odebrání

---

## Fáze 7 – Notifikace a presence

### 7.1 Presence tracking — oba aktivní v chatu
A a B si znovu přidají přátelství a otevřou DM. Oba mají chat otevřen.

Okno 1 (A) pošle zprávu:

- [ ] Zpráva se zobrazí User B ihned
- [ ] **Žádná** červená notifikační tečka se u User B neobjeví (má chat otevřen)

---

### 7.2 Presence tracking — příjemce v jiném chatu
User B přepne na jiný chat (nebo sidebar). User A pošle zprávu.

- [ ] User B vidí **notifikační tečku** u konverzace s User A
- [ ] Po přepnutí zpět na DM s A tečka zmizí
- [ ] Po F5 tečka nezmizí (dokud se chat neotevře)

---

### 7.3 Offline notifikace
User B se odhlásí. User A pošle zprávu.
User B se znovu přihlásí.

- [ ] User B vidí notifikační tečku v sidebaru u User A
- [ ] Po otevření chatu tečka zmizí

---

## Fáze 8 – Admin panel

### 8.1 Dashboard
V okně 4 (Admin):

- [ ] Načtou se statistiky (počty uživatelů, online, místností, zpráv)
- [ ] Čísla odpovídají reálnému stavu (min. 3 uživatelé, min. 1 místnost)
- [ ] Zobrazují se poslední audit logy s akcemi (LOGIN, REGISTER, ...)

---

### 8.2 Záložka Uživatelé
- [ ] Seznam zobrazuje všechny registrované uživatele
- [ ] Každý uživatel má username, email, roli a status
- [ ] Kliknutí na detail zobrazuje audit logy uživatele

---

### 8.3 Smazání uživatele adminem
V záložce Uživatelé — smaž **User C**:

- [ ] User C zmizí ze seznamu
- [ ] Pokud byl User C přihlášen → session se zneplatní (po F5 v okně 3 → login stránka)
- [ ] Zprávy User C zůstanou v chatech jako `Smazaný uživatel`
- [ ] Admin **nemůže** smazat sebe (tlačítko pro vlastní účet nefunguje nebo chybí)
- [ ] Admin **nemůže** smazat posledního admina

---

### 8.4 Záložka Místnosti
- [ ] Zobrazují se všechny místnosti (DM i skupiny)
- [ ] Detail místnosti zobrazuje členy
- [ ] Historie místnosti zobrazuje zprávy
- [ ] Smazání místnosti ji odstraní z UI všech uživatelů

---

### 8.5 Záložka Logy
- [ ] Zobrazují se logy akcí (LOGIN, LOGOUT, UPDATE_PROFILE, DELETE_USER, ...)
- [ ] Logy odpovídají akcím provedeným během testování

---

## Fáze 9 – Hraniční a negativní testy

### 9.1 Nelze odeslat žádost sobě
V okně 1 — pokud rozhraní dovolí, zkus přidat vlastní username:

- [ ] Backend vrátí chybu `Nemůžeš přidat sám sebe`

---

### 9.2 Nelze otevřít DM s ne-přítelem
Ručně zavolej (nebo ověř chování v UI): otevření DM s uživatelem, který není přítel:

- [ ] Backend vrátí `403 chat_open_forbidden`
- [ ] UI zobrazí chybu nebo redirect

---

### 9.3 Nelze posílat zprávy po odebrání přítele
Po odebrání z přátel (Fáze 6) se pokus odeslat zprávu:

- [ ] Backend vrátí `403 message_send_forbidden`
- [ ] UI zobrazí toast nebo uzavře chat

---

### 9.4 Nelze posílat zprávy po vyhazení ze skupiny
Po kicku (Fáze 5.6) v okně User B se pokus odeslat zprávu do skupiny:

- [ ] Backend vrátí `403`
- [ ] Chat je již zavřen

---

### 9.5 Duplicitní žádost o přátelství
V okně 1 — pokud tlačítko zobrazuje `Odesláno ✔`, klikni znovu nebo zavolej endpoint ručně:

- [ ] Backend vrátí `400 friend_request_failed`
- [ ] Žádost se nevytvoří podruhé

---

### 9.6 Více záložek stejného uživatele
Otevři dvě záložky se stejným přihlášeným User A.
V záložce 1 pošli zprávu:

- [ ] Zpráva se zobrazí v záložce 2 bez F5
- [ ] Online status se nemění při zavření jedné záložky

---

## Fáze 10 – Závěrečný průchod

### 10.1 Logout / Login cyklus
Proveď logout a nový login u User A a User B:

- [ ] Po logoutu nelze přistoupit k chráněným endpointům (vrátí 401)
- [ ] Po novém loginu funguje vše jako dříve
- [ ] WebSocket se po loginu znovu naváže

---

### 10.2 Refresh u všech otevřených oken
Proveď F5 ve všech otevřených oknech:

- [ ] Žádné okno se nezasekne nebo nevyhodí HTML/PHP chybu
- [ ] Každý uživatel zůstane přihlášen
- [ ] WebSocket se znovu připojí
- [ ] Data odpovídají aktuálnímu stavu databáze

---

## Finální checklist

| Oblast | Funkce | Otestováno |
|--------|--------|-----------|
| Auth | Registrace s validními daty | ☐ |
| Auth | Registrace — validační chyby (6 scénářů) | ☐ |
| Auth | Login / Logout | ☐ |
| Auth | Persistence po F5 | ☐ |
| Profil | Úprava bio + avatar | ☐ |
| Profil | Vlastní profil bez tlačítka odebrat | ☐ |
| Přátelé | Odeslání žádosti | ☐ |
| Přátelé | Odmítnutí žádosti | ☐ |
| Přátelé | Přijetí žádosti | ☐ |
| Přátelé | Odebrání (bez otevřeného chatu) | ☐ |
| Přátelé | Odebrání (DM otevřen na obou stranách) | ☐ |
| DM Chat | Otevření konverzace | ☐ |
| DM Chat | Real-time doručení zpráv | ☐ |
| DM Chat | Editace vlastní zprávy | ☐ |
| DM Chat | Smazání vlastní zprávy (soft delete) | ☐ |
| DM Chat | Reply / citace | ☐ |
| Skupiny | Vytvoření skupiny | ☐ |
| Skupiny | Skupinové zprávy real-time | ☐ |
| Skupiny | Úprava názvu skupiny | ☐ |
| Skupiny | Kick člena (okamžité zavření u vyhazovaného) | ☐ |
| Skupiny | Opuštění skupiny | ☐ |
| Notifikace | Presence tracking (aktivní/neaktivní místnost) | ☐ |
| Notifikace | Offline notifikace (odhlášen, přihlásí se zpět) | ☐ |
| Admin | Dashboard statistiky | ☐ |
| Admin | Seznam uživatelů | ☐ |
| Admin | Smazání uživatele | ☐ |
| Admin | Ochrana posledního admina | ☐ |
| Admin | Seznam a detail místností | ☐ |
| Admin | Audit logy | ☐ |
| Edge cases | Žádost sama sobě → zamítnuta | ☐ |
| Edge cases | DM s ne-přítelem → zamítnut | ☐ |
| Edge cases | Více záložek stejného uživatele | ☐ |
| Edge cases | F5 ve všech stavech nezpůsobí pád | ☐ |

---

*Testovací plán — Whisp | Bruno Vašíček | I4C | 2025/2026*
