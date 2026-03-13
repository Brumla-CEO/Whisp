# Databázový návrh (PostgreSQL)

Zdroj: `backend/init.sql` + ER diagram.

## Přehled tabulek
- `roles` – RBAC role (`admin`, `user`)
- `users` – uživatelé
- `sessions` – aktivní session tokeny
- `rooms` – DM / group místnosti
- `room_memberships` – členství v místnostech
- `messages` – zprávy
- `friendships` – přátelství
- `activity_logs` – audit log (admin)
- `notifications` – notifikace

## Vztahy (high-level)
- `users.role_id -> roles.id`
- `sessions.user_id -> users.id`
- `rooms.owner_id -> users.id`
- `room_memberships.room_id -> rooms.id`
- `room_memberships.user_id -> users.id`
- `messages.room_id -> rooms.id`
- `messages.sender_id -> users.id`
- `messages.reply_to_id -> messages.id`
- `friendships.requester_id -> users.id`
- `friendships.addressee_id -> users.id`
- `notifications.user_id -> users.id`
- `notifications.room_id -> rooms.id`
- `activity_logs.user_id -> users.id`

## Tabulky detailně

### roles
- `id` (PK, SERIAL)
- `name` (UNIQUE)
- `description`

### users
- `id` (PK, UUID, default `gen_random_uuid()`)
- `username` (UNIQUE)
- `email` (UNIQUE)
- `password_hash`
- `role_id` (FK roles)
- `avatar_url`, `bio`, `status`
- `created_at`, `updated_at`

Pozn.: status se používá i pro admin dashboard (`online/offline`). V produkci by se často řešilo přes presence v redis/WS.

### sessions
- `id` (SERIAL PK)
- `user_id` (FK users)
- `token` (TEXT) – JWT token uložený i v DB
- `expires_at` – datum expirace session
- `is_active` – boolean
- `created_at`

Aktuální middleware ověřuje `token` + `is_active`, nikoliv `expires_at` (viz issue).

### rooms
- `id` (SERIAL PK)
- `name`
- `type` – `'dm'` nebo `'group'`
- `owner_id` (FK users)
- `avatar_url`
- timestamps

### room_memberships
- composite PK (`room_id`, `user_id`)
- `role` – role v room (`admin`/`member`)
- `joined_at`
- `is_muted`

### messages
- `id` (SERIAL PK)
- `room_id` (FK rooms)
- `sender_id` (FK users)
- `content`
- `reply_to_id` (self FK)
- `type` (default `text`)
- `is_edited`, `is_deleted`
- timestamps

### friendships
- `requester_id`, `addressee_id` (FK users)
- `status` – pending/accepted/rejected
- UNIQUE (requester_id, addressee_id)

Pozn.: v praxi se často řeší i symetrie (A-B vs B-A). Aplikace musí hlídat, aby nevznikly duplicitní dvojice.

### activity_logs
- `action` (např. `UPDATE_PROFILE`, `DELETE_USER`)
- `details`
- `ip_address`

### notifications
- `user_id` – komu patří notifikace
- `room_id` – vázaná na room
- `type`, `content`
- `is_read`
- `created_at`

## Doporučené indexy (optimalizace)
Pro reálný provoz doporučuji:
- `sessions(token)` (unikátní) + index na `is_active`
- `room_memberships(user_id)` a `room_memberships(room_id)`
- `messages(room_id, created_at DESC)`
- `friendships(requester_id)`, `friendships(addressee_id)`, případně `friendships(status)`
- `notifications(user_id, is_read)`

