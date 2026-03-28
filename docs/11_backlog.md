# 11 – Produktový Backlog

Backlog je seřazený seznam všech plánovaných funkcí projektu Whisp, rozdělený
do epik a user stories. Položky jsou seřazeny od nejdůležitějších (MVP) po
volitelná rozšíření.

---

## Epiky (hlavní oblasti)

| Kód | Název epiky | Stav |
|-----|-------------|------|
| EP1 | Projektová příprava a infrastruktura | ✅ Hotovo |
| EP2 | Autentizace a správa uživatelů | ✅ Hotovo |
| EP3 | Real-time jádro (WebSocket) | ✅ Hotovo |
| EP4 | Chatová funkcionalita | ✅ Hotovo |
| EP5 | Skupinové chaty | ✅ Hotovo |
| EP6 | Systém přátel | ✅ Hotovo |
| EP7 | Notifikace a presence | ✅ Hotovo |
| EP8 | Admin panel a audit | ✅ Hotovo |
| EP9 | Bezpečnostní hardening | ✅ Hotovo |
| EP10 | Testování a CI/CD | ✅ Hotovo |
| EP11 | Docker a DevOps | ✅ Hotovo |
| EP12 | Dokumentace | ✅ Hotovo |

---

## EP1 – Projektová příprava a infrastruktura

### US1.1 Inicializace repozitáře
Jako vývojář chci mít strukturovaný Git repozitář s README.
- Vytvořit strukturu složek (backend, frontend, docs, database)
- Inicializovat Git, nastavit .gitignore
- Napsat první README

### US1.2 Docker Compose základ
Jako vývojář chci spustit všechny části aplikace jedním příkazem.
- Definovat 4 kontejnery: db, backend, websocket, frontend
- Nastavit sdílenou síť whisp_net
- Persistentní volume pro PostgreSQL data
- ENV proměnné pro DB připojení

### US1.3 Hello World proof-of-concept
Jako vývojář chci ověřit, že PHP API komunikuje s React frontendem.
- PHP endpoint vrátí JSON
- React ho zavolá a zobrazí výsledek
- Potvrdí funkčnost CORS a síťování v Docker

---

## EP2 – Autentizace a správa uživatelů

### US2.1 Databázové schéma
Jako vývojář chci mít kompletní DB schéma před implementací.
- Navrhnout 9 tabulek (roles, users, sessions, rooms, ...)
- Rozhodnutí: UUID pro users, SERIAL pro ostatní
- Soft delete pro messages (is_deleted)
- ON DELETE SET NULL pro sender_id v messages

### US2.2 Registrace
Jako návštěvník chci se zaregistrovat, abych mohl používat aplikaci.
- Validace: unikátní username/email, min. délka hesla, formát emailu
- Hashování hesla: bcrypt
- Výchozí avatar: DiceBear URL
- Vrátit JWT token + user objekt

### US2.3 Přihlášení
Jako uživatel chci se přihlásit pomocí emailu a hesla.
- Ověření password_hash
- Vytvoření session záznamu v DB
- Generování JWT (payload: sub, role, iat, exp)
- Nastavení statusu na 'online'

### US2.4 Odhlášení s invalidací
Jako uživatel chci se odhlásit a zajistit, že token přestane platit.
- UPDATE sessions SET is_active = FALSE
- Nastavení statusu na 'offline'
- Activity log: LOGOUT

### US2.5 Správa profilu
Jako uživatel chci upravit svůj profil.
- Update: username, email, bio, avatar_url
- Avatar: generovaný (DiceBear seed) nebo vlastní URL
- Validace: nový username musí být unikátní

### US2.6 Smazání účtu
Jako uživatel chci smazat svůj účet.
- Kaskádní smazání: sessions, friendships, memberships
- Zprávy zůstávají (sender_id → NULL)
- Skupiny: předání vlastnictví nebo smazání prázdné skupiny

---

## EP3 – Real-time jádro (WebSocket)

### US3.1 Ratchet WebSocket server
Jako vývojář chci mít běžící WebSocket server.
- Bootstrap v bin/server.php
- ChatSocket implementující MessageComponentInterface
- Naslouchání na portu 8080

### US3.2 JWT autentizace po připojení
Jako vývojář chci, aby se klient autentizoval po navázání WS spojení.
- Klient odešle {type: 'auth', token: '...'}
- Server ověří JWT + DB session
- Server odpoví {type: 'auth_ok', userId: '...'}

### US3.3 Multi-tab podpora
Jako uživatel chci mít otevřeno více záložek bez problémů se statusem.
- $userConnections[userId][resourceId] struktura
- Online jen při prvním připojení, Offline jen při posledním odpojení

---

## EP4 – Chatová funkcionalita

### US4.1 Odesílání zpráv
Jako uživatel chci posílat zprávy.
- REST: POST /api/messages/send → INSERT + vrátit savedMessage
- WS: broadcast {type: 'message:new'} členům místnosti

### US4.2 Historie zpráv
Jako uživatel chci vidět předchozí zprávy.
- REST: GET /api/messages/history?room_id=...
- JOIN s users pro username a avatar_url
- Filtrovat is_deleted = FALSE

### US4.3 Editace zpráv
Jako autor zprávy chci upravit obsah.
- REST: POST /api/messages/update (jen vlastní zprávy)
- WS: broadcast {type: 'message_update'}
- Nastavení is_edited = TRUE

### US4.4 Smazání zpráv (soft delete)
Jako autor zprávy chci smazat zprávu.
- REST: POST /api/messages/delete → is_deleted = TRUE
- WS: broadcast {type: 'message_delete'}
- UI: zobrazit "🚫 Odstraněno"

### US4.5 Reply (citace)
Jako uživatel chci odpovídat na konkrétní zprávy.
- Uložit reply_to_id v messages tabulce
- Frontend zobrazí citovanou zprávu nad odpovědí

---

## EP5 – Skupinové chaty

### US5.1 Vytvoření skupiny
Jako uživatel chci vytvořit skupinový chat.
- Transakce: INSERT room + INSERT room_memberships (admin + members)
- Min. 3 členové (zakladatel + 2 přátelé)

### US5.2 Správa členů
Jako admin skupiny chci přidávat/vyhazovat členy.
- add-member: INSERT room_memberships
- kick: DELETE room_memberships + WS event kicked_from_group
- leave: bezpečné opuštění s předáním vlastnictví

### US5.3 Úprava skupiny
Jako admin skupiny chci změnit název a avatar.
- UPDATE rooms SET name, avatar_url
- WS broadcast group_update

---

## EP6 – Systém přátel

### US6.1 Vyhledávání uživatelů
Jako uživatel chci najít jiné uživatele.
- Fulltext LIKE search na username
- Filtrovat: adminy, smazané uživatele, existující přátele

### US6.2 Žádosti o přátelství
Jako uživatel chci posílat a přijímat žádosti.
- send: INSERT friendships status='pending'
- accept: UPDATE status='accepted'
- reject: DELETE friendship
- WS broadcast friend_action event

---

## EP7 – Notifikace a presence

### US7.1 Presence tracking
Jako systém chci vědět, kdo má kterou místnost aktivní.
- Klient posílá presence:set_active_room při otevření/zavření místnosti
- Server ukládá do connMeta[resourceId].activeRoomId

### US7.2 Offline notifikace
Jako uživatel chci dostávat notifikace o zmeškanych zprávách.
- Notifikace vzniká jen pokud příjemce nemá místnost aktivní
- Badge v UI při příchodu WS event 'notification'
- Označení jako přečtené po vstupu do místnosti

---

## EP8 – Admin panel a audit

### US8.1 Dashboard
Jako admin chci přehled statistik platformy.
- Počty: uživatelé, online, místnosti, zprávy
- Posledních 20 záznamů audit logu

### US8.2 Správa uživatelů
Jako admin chci spravovat uživatelské účty.
- Seznam uživatelů s rolemi a statusy
- Detail aktivity (audit logy uživatele)
- Smazání účtu (s ochranou posledního admina)

---

## EP9 – Bezpečnostní hardening

### US9.1 Rate Limiting
Jako systém chci chránit API před zneužitím.
- 10 loginů / 60s, 5 registrací / 5min
- 20 friend requests / 60s, 120 zpráv / 60s

### US9.2 Input validace
Jako systém chci odmítat neplatné vstupy.
- Dedikované Validator třídy pro každou doménu
- Validace emailu, délky hesla, povinných polí

---

## EP10 – Testování a CI/CD

### US10.1 PHPUnit testy
Jako vývojář chci mít ověřenou business logiku.
- AuthValidatorTest (19 testů)
- FriendAndChatValidatorTest (18 testů)
- JWTServiceTest (11 testů)
- Celkem: 49 testů, 59 assertions

### US10.2 Vitest testy
Jako vývojář chci mít ověřenou Login komponentu.
- 5 testů pro Login.jsx (render, submit, error, network error, validation)

### US10.3 GitHub Actions CI pipeline
Jako vývojář chci, aby se testy spouštěly automaticky.
- Trigger: push/PR na main, feat/*, fix/*
- Steps: PHP 8.2 setup, Node 20 setup, composer install, npm ci, phpunit, vitest

---

## Volitelná rozšíření (Nice to Have)

| Funkce | Popis | Priorita |
|--------|-------|----------|
| Emoji reakce | Přidání emoji reakcí na zprávy | Nízká |
| Vyhledávání v historii | Fulltext search v chat historii | Nízká |
| Push notifikace | Browser push API pro offline notifikace | Nízká |
| Dark/Light mode | Přepínání barevného schématu | Nízká |
| Paginace zpráv | Načítání starší historie (scroll up) | Střední |
| Refresh tokeny | Access + refresh token flow | Střední |
| Redis Rate Limiting | Nahrazení souborového rate limiteru | Střední |
