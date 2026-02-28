# Backend Documentation — Whisp

## 1. Úvod

Backend aplikace Whisp je REST API implementované v objektově orientovaném PHP bez použití externího frameworku.  
Zajišťuje:

- autentizaci uživatelů pomocí session tokenů,
- autorizaci pomocí RBAC (role-based access control),
- aplikační logiku (rooms, membership, messaging, friendships, notifications),
- persistenci dat do PostgreSQL,
- komunikaci s WebSocket serverem pro real-time distribuci událostí.

Backend je navržen jako vícevrstvá (layered) aplikace s jasně oddělenými odpovědnostmi.

---

## 2. Technologie

- PHP (OOP)
- PostgreSQL
- PDO (prepared statements)
- UUID pro identifikaci uživatelů
- Docker (kontejnerizované prostředí)
- WebSocket server (oddělená služba)

---

## 3. Architektonický přístup

Backend používá vrstvenou architekturu:

Request → Router → Middleware → Controller → Service → Repository → Database → Response

### Hlavní principy:
- Separation of Concerns
- Single Responsibility Principle
- Database jako zdroj pravdy
- REST pro persistenci
- WebSocket pro real-time distribuci

---

## 4. Struktura backendu

### public/
- `index.php` — vstupní bod aplikace
- inicializace aplikace
- předání requestu routeru

### src/

#### Router
- Mapuje HTTP metodu + URI na konkrétní controller
- Zajišťuje směrování requestu

#### Middleware
- CORS hlavičky
- autentizace (ověření session tokenu)
- autorizace podle role

#### Controllers
- HTTP vrstva
- přijímá JSON payload
- validuje vstupy
- volá Service vrstvu
- vrací JSON odpovědi

#### Services
- Obsahuje business logiku
- Řídí transakce
- Rozhoduje o oprávnění operací
- Emituje události do WebSocket vrstvy

#### Repositories
- Přímý přístup k databázi
- PDO prepared statements
- CRUD operace

---

## 5. Lifecycle HTTP requestu

1. Klient (React SPA) odešle HTTP request.
2. `index.php` předá request Routeru.
3. Router vybere odpovídající endpoint.
4. Middleware:
   - nastaví CORS
   - ověří session token
   - ověří oprávnění (RBAC)
5. Controller:
   - validuje vstup
   - volá Service
6. Service:
   - provede aplikační logiku
   - zavolá Repository
7. Repository:
   - provede SQL dotaz
8. Controller vrátí JSON odpověď.

---

## 6. Autentizace (Session-based)

Autentizace je založena na tabulce `sessions`.

### Login proces:
1. Ověření emailu a hesla (`password_verify`)
2. Vytvoření kryptograficky bezpečného tokenu
3. Uložení do tabulky:
   - user_id
   - token
   - expires_at
   - is_active = true

### Autorizovaný request:
- Token je zaslán v hlavičce nebo cookie
- Backend provede:
  SELECT * FROM sessions WHERE token=? AND is_active=true AND expires_at > NOW()

### Logout:
- UPDATE sessions SET is_active=false WHERE token=?

---

## 7. Autorizace (RBAC)

Tabulka `roles` obsahuje role:
- admin
- user

Tabulka `users` obsahuje `role_id`.

RBAC se kontroluje v middleware nebo service vrstvě.

Admin-only operace:
- změna role
- přístup k activity_logs
- správa uživatelů

---

## 8. Databázová vrstva

Používá se PDO s prepared statements.

Důležité tabulky:

- users (UUID PK)
- roles
- sessions
- rooms
- room_memberships (PK: room_id + user_id)
- messages
- friendships
- activity_logs
- notifications

### Integrita:
- Foreign keys
- ON DELETE CASCADE / SET NULL
- Unikátní constraint (username, email, friendships)

---

## 9. Messaging Flow

### Odeslání zprávy:
1. Ověření membership v `room_memberships`
2. INSERT do `messages`
3. Backend notifikuje WebSocket server
4. WS broadcast do klientů

### Reply:
- `reply_to_id` FK na `messages.id`

### Edit:
- `is_edited = true`
- `edited_at = NOW()`

### Delete (soft delete):
- `is_deleted = true`

---

## 10. Notifikace

Tabulka `notifications` obsahuje:
- user_id
- room_id
- type
- content
- is_read

Backend vytváří notifikace při relevantních událostech.

---

## 11. Friendships

Tabulka `friendships`:
- requester_id
- addressee_id
- status (pending / accepted / rejected)

UNIQUE(requester_id, addressee_id) brání duplicitám.

---

## 12. Activity Logs

Tabulka `activity_logs` zaznamenává:
- user_id
- action
- timestamp
- ip_address

Používá se pro admin dohled.

---

## 13. Real-time komunikace

Backend:
- ukládá data do DB
- následně informuje WebSocket server

WS server:
- drží persistentní spojení
- distribuuje eventy:
  - message:new
  - message:edited
  - notification:new
  - user:online
  - user:offline

---

## 14. Bezpečnost

- Hesla hashována pomocí password_hash
- Prepared statements (ochrana proti SQL injection)
- Session validace
- RBAC
- CORS kontrola
- Oddělená databázová vrstva

---

## 15. Error handling

Standardní HTTP kódy:
- 200 OK
- 201 Created
- 400 Bad Request
- 401 Unauthorized
- 403 Forbidden
- 404 Not Found
- 500 Internal Server Error

Odpovědi jsou ve formátu JSON.

---

## 16. Docker prostředí

Backend běží jako samostatný kontejner.

Komunikace:
Frontend → Backend (HTTP)
Backend → PostgreSQL (interní síť)
Backend → WebSocket server

DB není vystavena veřejně.

---

## 17. Shrnutí

Backend Whisp:

- je vrstvený REST API systém
- používá session-based autentizaci
- implementuje RBAC
- ukládá data do PostgreSQL
- využívá WebSocket pro real-time události
- je kontejnerizován pomocí Dockeru

Architektura je modulární, rozšiřitelná a připravená pro další vývoj.