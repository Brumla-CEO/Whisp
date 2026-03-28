# 06 – Databázový návrh (PostgreSQL 15)

## Přehled

Databáze je základem celé aplikace. Špatně navržená databáze se opravuje velmi těžce,
protože změny schématu mají dopad na veškerý kód. Proto byl návrh dokončen
ještě před zahájením implementace aplikační logiky.

Výsledné schéma tvoří **9 tabulek** v PostgreSQL 15. Schéma je definováno
v souboru `backend/init.sql` a automaticky inicializováno při prvním startu Docker kontejneru.

---

## Přehled tabulek

| Tabulka | Popis |
|---------|-------|
| `roles` | RBAC role (admin, user) |
| `users` | Uživatelé aplikace |
| `sessions` | Aktivní JWT session tokeny |
| `rooms` | Chatovací místnosti (DM i skupiny) |
| `room_memberships` | Členství uživatelů v místnostech |
| `messages` | Zprávy v místnostech |
| `friendships` | Přátelství mezi uživateli |
| `activity_logs` | Audit log akcí |
| `notifications` | Nepřečtené notifikace |

---

## Tabulky detailně

### roles
```sql
CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,   -- 'admin' nebo 'user'
    description TEXT
);
```
Výchozí data: role `admin` a `user` se vloží při inicializaci.

### users
```sql
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,   -- bcrypt
    role_id INTEGER REFERENCES roles(id),
    avatar_url TEXT,
    bio VARCHAR(200),
    status VARCHAR(20) DEFAULT 'offline',  -- 'online' nebo 'offline'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Klíčové rozhodnutí — UUID primární klíč:**
UUID generovaný funkcí `gen_random_uuid()` zabraňuje iteraci přes ID.
Útočník nemůže odhadnout počet uživatelů ani procházet profily postupným zvyšováním čísla.
UUID je delší než SERIAL (36 znaků), ale pro projekt tohoto rozsahu je nevýhoda zanedbatelná.

### sessions
```sql
CREATE TABLE sessions (
    id SERIAL PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    token TEXT NOT NULL,         -- JWT string
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Proč sessions tabulka?**
JWT je ze své podstaty stateless — server si o něm nemusí nic pamatovat.
Tabulka sessions ale umožňuje okamžitou invalidaci tokenu při odhlášení nebo smazání účtu.
Bez ní by token zůstal platný až do přirozené expirace (24 hodin) i po odhlášení.

### rooms
```sql
CREATE TABLE rooms (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100),           -- NULL pro DM, název pro skupiny
    type VARCHAR(20) NOT NULL,   -- 'dm' nebo 'group'
    owner_id UUID REFERENCES users(id) ON DELETE SET NULL,
    avatar_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### room_memberships
```sql
CREATE TABLE room_memberships (
    room_id INTEGER REFERENCES rooms(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(20) DEFAULT 'member',   -- 'admin' nebo 'member'
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (room_id, user_id)
);
```

Kompozitní primární klíč (room_id, user_id) zabraňuje duplicitním členstvím.

### messages
```sql
CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    room_id INTEGER REFERENCES rooms(id) ON DELETE CASCADE,
    sender_id UUID REFERENCES users(id) ON DELETE SET NULL,  -- NULL = smazaný uživatel
    content TEXT NOT NULL,
    reply_to_id INTEGER REFERENCES messages(id) ON DELETE SET NULL,  -- self-reference
    is_edited BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,    -- soft delete
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Soft delete zpráv:**
Zprávy se nikdy fyzicky nemažou. Při smazání se nastaví `is_deleted = TRUE` a
obsah se v UI nahradí textem "Odstraněno". Tím se zachovává kontext konverzace
a citace na smazané zprávy zůstávají funkční.

**Soft delete uživatelů (sender_id SET NULL):**
Při smazání uživatele se `sender_id` nastaví na NULL. Zprávy zůstávají v databázi
a v UI se zobrazují jako zprávy od "Smazaného uživatele".

### friendships
```sql
CREATE TABLE friendships (
    id SERIAL PRIMARY KEY,
    requester_id UUID REFERENCES users(id) ON DELETE CASCADE,
    addressee_id UUID REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL,   -- 'pending' nebo 'accepted'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (requester_id, addressee_id)
);
```

UNIQUE constraint zabraňuje duplicitním přátelstvím.

### activity_logs
```sql
CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(50) NOT NULL,   -- 'LOGIN', 'LOGOUT', 'UPDATE_PROFILE', ...
    details TEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### notifications
```sql
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    room_id INTEGER REFERENCES rooms(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,   -- 'message'
    content TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## ER vztahy

```
roles ||--o{ users : "přiřazuje roli"
users ||--o{ sessions : "vlastní session"
users ||--o{ friendships : "iniciuje přátelství"
users ||--o{ activity_logs : "generuje logy"
users ||--o{ room_memberships : "je členem"
users ||--o{ messages : "odesílá"
users ||--o{ notifications : "dostává"
rooms ||--o{ room_memberships : "sdružuje členy"
rooms ||--o{ messages : "obsahuje zprávy"
rooms ||--o{ notifications : "generuje"
messages |o--o| messages : "reply_to (self-reference)"
```

---

## Životní cyklus uživatele

```
[*] --> Registrovan    (POST /api/register)
Registrovan --> Online (POST /api/login)
Online --> Offline     (POST /api/logout nebo WS disconnect)
Offline --> Online     (POST /api/login)
Online --> Smazan      (DELETE /api/users/id)
Offline --> Smazan     (DELETE /api/users/id)
Smazan: kaskádní smazání sessions, friendships, memberships
        zprávy zůstávají (sender_id = NULL)
```

---

## Doporučené indexy pro produkci

```sql
CREATE UNIQUE INDEX idx_sessions_token ON sessions(token);
CREATE INDEX idx_sessions_active ON sessions(is_active, expires_at);
CREATE INDEX idx_memberships_user ON room_memberships(user_id);
CREATE INDEX idx_memberships_room ON room_memberships(room_id);
CREATE INDEX idx_messages_room ON messages(room_id, created_at DESC);
CREATE INDEX idx_friendships_requester ON friendships(requester_id);
CREATE INDEX idx_friendships_addressee ON friendships(addressee_id);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
```

---

## Proč PostgreSQL místo MySQL

- Nativní funkce `gen_random_uuid()` — UUID bez aplikační logiky
- Striktní typový systém — databáze odmítne nesprávný typ místo tiché konverze
- Lepší podpora pro JSON datové typy (pro případná budoucí rozšíření)
- PDO abstrakce — přechod na jinou databázi by vyžadoval jen změnu DSN řetězce
