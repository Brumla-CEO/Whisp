# High-level analýza — Live Chat 

Tento dokument navazuje na [Úvod](../intro/introduction.md) a soustředí se na technický pohled na aplikaci.  
Cílem je shrnout klíčové požadavky, hlavní funkcionality, architekturu a návrh systému.  

---

## Hlavní funkcionality
Základními povinnými funkcemi aplikace budou:  

- **Registrace a přihlášení uživatelů** – pomocí bcryptu pro hashování hesel a JWT tokenů pro autentizaci.  
- **Uživatelské profily** – možnost upravit profilovou fotografii a krátké bio.  
- **Soukromé i skupinové chaty** – komunikace mezi dvěma uživateli i v rámci vícečlenných místností.  
- **Realtime komunikace** – implementovaná přes WebSocket server v PHP (Ratchet).  
- **Ukládání historie zpráv** – ve vybrané databázi (PostgreSQL/MySQL) provozované v Dockeru.  
- **Administrátorské rozhraní** – umožňující správu uživatelů, activity log a blokování účtů.  

Nad rámec těchto funkcí lze jako možné rozšíření uvažovat o podpoře emoji, push notifikacích nebo tmavém režimu UI.  

---

## Architektura a technologický stack
Architektura aplikace je navržena jako **vrstvený systém** s jasným oddělením zodpovědností:  

- **Frontend (React + TailwindCSS)** – zajišťuje uživatelské rozhraní, komunikaci s API a WebSocket serverem.  
- **Backend (OOP PHP)** – implementuje aplikační logiku, REST API a autentizaci. Kód je organizován do vrstev:  
  - `global` - veřejný root 
  - `config` - pripojení k databázi,klíče pro JWT 
  - `controllers` – zpracování HTTP požadavků a WebSocket eventů,  
  - `services` – aplikační logika,  
  - `repositories` – databázový přístup (PDO),  
  - `modules` – datové entity (User, Room, Message, ActivityLog),  
  - `middleware` – autentizace, validace vstupů.  
  - `websocket` - websocket server 
- **WebSocket server (Ratchet v PHP)** – zajišťuje realtime komunikaci.  
- **Databáze (PostgreSQL/MySQL)** – běží v Docker kontejneru, ukládá uživatele, zprávy, místnosti a activity log.  

Komunikace probíhá kombinací REST API (autentizace, CRUD operace) a WebSocketů (zprávy, notifikace, status online).  

---

## Autentizace a autorizace
Bezpečnost aplikace je založena na:  
- **bcrypt hashování hesel**,  
- **JWT tokenu s platností 1 hodiny**, který je uložený v **HttpOnly cookies** (ochrana proti XSS),  
- **roli uživatele a administrátora**, kontrolované přes middleware.  

Admin má rozšířená oprávnění pro správu systému, avšak **nemá přístup k obsahu soukromých zpráv** – vidí pouze metadata konverzací (účastníky, čas, místnosti).  

---
## WebSocket řešení
Realtime komunikace je implementována pomocí knihovny **Ratchet** (PHP). WebSocket server běží jako samostatný proces v Dockeru a stará se o:  
- předávání zpráv mezi uživateli a místnostmi,  
- indikaci online/offline stavu,  
- rozesílání notifikací.  

Při připojení k WebSocketu je vždy ověřován platný JWT token.  

---

## Databázový návrh
Databáze obsahuje klíčové entity:  


- **users** – informace o uživatelích (jméno, email, profil), ukládá se i heslo (bcrypt hash), role a status účtu (aktivní/blokovaný).  
- **roles** – seznam rolí (např. uživatel, administrátor, moderátor).  
- **rooms** – definice chatovacích místností (název, typ, vlastník).  
- **room_memberships** – vazba uživatelů na místnosti (kdo je členem jaké místnosti).  
- **messages** – zprávy posílané uživateli, s odkazem na místnost a časem vytvoření, případně editace nebo smazání.  
- **activity_logs** – loguje uživatelské akce (login, logout, změna profilu, vytváření místností apod.).  
- **notifications** – upozornění pro uživatele (např. nová zpráva, zmínka, systémové notifikace), stav přečtení.  
- **sessions** – sledování aktivních relací uživatelů (JWT token, začátek a konec session, stav). 

Soukromý chat je řešen jako místnost se dvěma uživateli.  

---

## Bezpečnostní opatření
Projekt počítá s několika bezpečnostními kroky:  

- HTTPS komunikace (TLS certifikát při nasazení).  
- Hashování hesel pomocí bcrypt.  
- Ukládání JWT tokenů v HttpOnly cookies se Secure a SameSite nastavením.  
- Input validace na backendu (ochrana proti SQLi, XSS).  
- Prepared statements v repositories.  
- Middleware kontrola oprávnění (role).  
- Omezení přístupu administrátora jen na metadata soukromých zpráv.  
- Doporučený rate limiting jako ochrana proti brute-force útokům.  

---

## Testování
Testování bude probíhat ve dvou rovinách:  
1. **Manuální testy** – ověřující uživatelské scénáře (registrace, login, chat, admin funkce).  
2. **Unit testy** – zaměřené na logiku služeb (services), např. generování a ověřování JWT, validaci vstupů nebo logiku blokování uživatele.  

Výsledky testů budou dokumentovány.  

---

## Nasazení a provoz
Aplikace bude provozována pomocí **Dockeru**:  
- kontejnery pro backend, frontend, WebSocket server a databázi,  
- volitelně reverzní proxy (nginx) pro HTTPS a routing.  

Lokální vývoj proběhne přes `docker-compose`. Produkční nasazení bude možné na VPS či cloud serveru s HTTPS certifikátem (Let's Encrypt).  

Logování bude realizováno do souborů a databáze, včetně základní rotace logů.  

---

## Plán vývoje (SDLC)
Vývoj proběhne v následujících fázích:  

1. **Analýza požadavků** – definice use-case scénářů a uživatelských očekávání.  
2. **Návrh** – ER diagram databáze, architektonický diagram, API specifikace.  
3. **Implementace** – backend (PHP API a WebSocket), frontend (React UI).  
4. **Testování** – unit testy, manuální funkční testy, bezpečnostní kontrola.  
5. **Nasazení** – Docker, nginx, HTTPS.  
6. **Údržba a rozšíření** – implementace volitelných funkcí.  

Každá fáze bude probíhat v iteracích (Scrum sprinty).  

---

## Výstupy práce
Výsledkem projektu budou:  
- funkční aplikace (frontend + backend + databáze),  
- dokumentace pokrývající všechny fáze SDLC,  
- testovací scénáře a výsledky,  
- prezentace k obhajobě projektu.  

---

## Shrnutí
Projekt Live Chat představuje moderní webovou aplikaci kombinující **React**, **TailwindCSS**, **PHP (OOP)**, **WebSockety (Ratchet)** a **Docker**. Klade důraz na bezpečnost, oddělení rolí, udržovatelnost a dokumentaci. Aplikace je připravena na **týmový vývoj**, využívá standardní workflow (Git, PR, code review) a reflektuje principy používané v profesionálních IT firmách.  
