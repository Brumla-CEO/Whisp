# Project overview

## Shrnutí
Whisp je chat aplikace zaměřená na:
- registraci/přihlášení uživatelů,
- správu profilu (username/email/bio/avatar),
- systém přátel (vyhledání, žádosti, akceptace, odebrání),
- přímé konverzace (DM) a skupiny,
- realtime aktualizace (zprávy, notifikace, změny profilu a skupin).

Projekt používá jednoduchou, čitelnou architekturu: **REST API** pro CRUD operace a **WebSocket** pro realtime.

## Technologický stack
- **Frontend:** React + Vite, komponentový UI, stav přes Context
- **Backend:** PHP 8, vlastní Router + Controllers + Models, PDO pro DB
- **Realtime:** Ratchet (cboden/ratchet)
- **Auth:** JWT (firebase/php-jwt) + server‑side sessions tabulka
- **DB:** PostgreSQL

## Funkční rozsah (MVP)
1. Auth (register/login/logout/me)
2. Profil uživatele (update + delete)
3. Přátelé (search/add/accept/reject/remove, list)
4. Chat (rooms list, open DM, group create/update, message history, send/edit/delete)
5. Notifikace (unread + mark read)
6. Admin (dashboard, users/rooms/logs + detail)

## Ne-funkční cíle (NFR)
- Bezpečný přístup k API (JWT + kontrola session)
- Realtime odezva pro chat
- Udržitelnost kódu (oddělení Controller/Model, opakovatelné patterns)
- Nasaditelnost přes Docker Compose pro školní prostředí
