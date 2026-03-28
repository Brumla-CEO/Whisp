# 01 – Přehled projektu (Project Overview)

## Úvod a motivace

Whisp je webová chatovací aplikace vytvořená jako maturitní práce v oboru Informační technologie.
Cílem projektu bylo navrhnout, implementovat, otestovat a nasadit plnohodnotnou aplikaci pro
real-time komunikaci — a celý vývojový cyklus absolvovat tak, jak to dělají profesionální vývojáři.

Jako téma jsem zvolil live chat, protože mě zajímalo, co se skrývá za aplikacemi jako Discord
nebo Messenger. Jak server ví, že přišla zpráva, aniž se stránka obnovila? Co je WebSocket a
proč se nepoužívá klasické HTTP? Tyto otázky byly přirozeným vodítkem celého projektu.

Pro vývoj jsem záměrně zvolil technologie, které jsem do té doby neznal — PHP 8.2 s OOP,
React 19, PostgreSQL, Docker a WebSocket server Ratchet. Bylo to náročnější, ale přineslo
mi to mnohem víc než projekt na bezpečném technologickém stacku.

---

## Cíl práce

Navrhnout, implementovat, otestovat a nasadit webovou chatovací aplikaci, která:
- umožňuje uživatelům registraci, přihlášení a správu profilu
- podporuje soukromé i skupinové konverzace
- doručuje zprávy v reálném čase bez obnovení stránky
- obsahuje systém přátelství a notifikací
- je zabezpečena standardními bezpečnostními praktikami
- je spustitelná jedním příkazem na libovolném počítači

---

## Technologický stack

### Frontend
- **React 19** — komponentová SPA architektura
- **Vite 7** — build nástroj a dev server
- **Axios** — HTTP klient s interceptory pro JWT
- **WebSocket API** — nativní prohlížečové API

### Backend
- **PHP 8.2** — objektové programování, vlastní Router + MVC vzor
- **firebase/php-jwt** — generování a ověřování JWT tokenů
- **cboden/ratchet** — asynchronní WebSocket server (Ratchet + ReactPHP)
- **PDO** — abstraktní vrstva pro práci s PostgreSQL

### Databáze
- **PostgreSQL 15** — relační databáze s podporou UUID a transakcí

### Infrastruktura
- **Docker Compose** — orchestrace 4 kontejnerů
- **GitHub Actions** — CI/CD pipeline

### Testování
- **PHPUnit 10** — 49 unit testů pro backend
- **Vitest 4 + React Testing Library** — 5 unit testů pro frontend

---

## Architektura na vysoké úrovni

Aplikace je postavena jako třívrstvá architektura:

```
Klientská vrstva    →    Serverová vrstva    →    Datová vrstva
React 19 SPA             PHP 8.2 REST API         PostgreSQL 15
Vite + Axios             Ratchet WebSocket
WebSocket API
```

Klíčovým architektonickým rozhodnutím je použití dvou komunikačních kanálů:
- **HTTP REST API** (port 8000) — pro všechny CRUD operace (auth, profil, přátelé, skupiny)
- **WebSocket** (port 8080) — výhradně pro real-time doručení zpráv, notifikací a stavových událostí

---

## Funkční rozsah (MVP a bonusy)

### MVP — co muselo fungovat za každou cenu
1. Registrace a přihlášení uživatelů s JWT autentizací
2. PostgreSQL databáze s kompletním schématem
3. Uživatelský profil (avatar, bio, username)
4. Real-time zprávy přes WebSocket
5. Základní React UI

### Co bylo implementováno navíc
- Systém přátel se žádostmi (send, accept, reject, remove)
- Skupinové chaty s kompletní správou členů
- Administrátorský panel s audit logy
- Soft delete zpráv (zpráva zůstane v DB s příznakem is_deleted)
- Reply / citace zpráv
- Online/Offline status přes WebSocket presence
- Presence tracking (notifikace jen pro offline/neaktivní uživatele)
- Rate Limiting na klíčových endpointech
- RBAC — role-based access control (admin / user)
- 49 PHPUnit testů + 5 Vitest testů
- GitHub Actions CI/CD pipeline

---

## Nefunkční cíle (NFR)

| Oblast | Cíl | Stav |
|--------|-----|------|
| Bezpečnost | bcrypt, JWT + DB sessions, PDO prepared statements | Splněno |
| Výkon | Real-time odezva pod 1 sekundu | Splněno |
| Portabilita | Spuštění jedním příkazem (Docker) | Splněno |
| Udržovatelnost | MVC vzor, dedikované Validatory | Splněno |
| Testovatelnost | PHPUnit + Vitest, GitHub Actions | Splněno |
| CORS ochrana | Dynamický whitelist z ENV | Splněno |
| Rate Limiting | Per-IP, per-endpoint | Splněno |

---

## Splnění zadání

Zadání maturitní práce definovalo tyto povinné funkce:

| Funkce ze zadání | Implementace |
|-----------------|-------------|
| Registrace a přihlášení uživatelů | AuthController, JWT, DB sessions |
| Úprava uživatelského profilu | UserController::update(), ProfileSetup.jsx |
| Real-time komunikace WebSockety | ChatSocket.php (Ratchet) |
| Ukládání historie zpráv | Chat::getRoomMessages(), tabulka messages |
| Soukromé i skupinové chaty | Chat::getOrCreateDmRoom(), Chat::createGroup() |
| Správa uživatelů a místností | AdminController, AdminPanel.jsx |
| Notifikace o nových zprávách | Notification model + WebSocket event |
| Administrátorská část | AdminController + AdminPanel.jsx |
| React frontend | React 19 SPA |
| PHP backend | PHP 8.2 OOP |
| PostgreSQL v Docker | PostgreSQL 15 v Docker Compose |

---

## Autor

**Bruno Vašíček**
Třída: I4C | Škola: SPŠE Ostrava | Školní rok: 2025/2026
Vedoucí práce: Ing. Anna Golembiovská
Termín odevzdání: 31. března 2026
