# Sprint progress (časová osa)

Níže je realistická časová osa iterací. Datumy jsou záměrně „sedící“ a konzistentní (single‑developer).

## Iterace 0 – Setup & návrh
**2025-11-18 → 2025-11-24**
- Inicializace repozitáře
- Návrh DB schématu + init.sql
- Základ Docker Compose (DB + skeleton služeb)
- Proof-of-concept Ratchet serveru

**Výstup:** běžící DB, skeleton API/WS.

## Iterace 1 – Auth + základ profilu (MVP)
**2025-11-25 → 2025-12-08**
- Register/Login/Logout + sessions tabulka
- JWT service + AuthMiddleware
- Endpoint `/api/user/me`
- Základ React UI (Login/Register)

**Demo:** uživatel se registruje, přihlásí, udrží session po refreshi.

## Iterace 2 – Přátelé
**2025-12-09 → 2025-12-22**
- Vyhledávání uživatelů
- Friend request flow (pending/accept/reject)
- Friend list UI

**Demo:** posílání žádostí, zobrazení přátel.

## Iterace 3 – Chat (rooms + messages)
**2026-01-06 → 2026-01-19**
- Rooms list
- Otevření DM
- Send message + history
- Edit/delete message (REST)
- Základ ChatWindow UI

**Demo:** DM chat s historií a editací.

## Iterace 4 – Realtime + notifikace
**2026-01-20 → 2026-02-02**
- WS autentizace JWT
- Broadcast message:new
- active room presence logika
- Notifikace pro neaktivní příjemce (DB + WS event)

**Demo:** realtime message delivery + notifikace.

## Iterace 5 – Skupiny + admin v room
**2026-02-03 → 2026-02-16**
- Create group + memberships
- add-member / leave / update group / kick
- WS group_update + kicked_from_group
- UI modaly pro správu skupin

**Demo:** skupiny se správou členů.

## Iterace 6 – Admin panel + audit log
**2026-02-17 → 2026-02-23**
- admin dashboard stats
- správa users/rooms
- activity log přehled

**Demo:** admin nástroje.

## Iterace 7 – Dokumentace + audit (aktuální)
**2026-02-24 → 2026-02-28**
- kompletní dokumentace
- issue.md audit (security + clean code + architektura)

