# 04 – Backend Design (PHP 8.2)

## Přehled

Backend je napsán v PHP 8.2 s využitím objektového programování a vzoru MVC.
Skládá se ze dvou vstupních bodů — REST API a WebSocket server — které sdílejí
stejnou codebase, databázové připojení i ENV konfiguraci.

---

## Vstupní body

### REST API: `backend/public/index.php`
```php
// Nastaví globální exception handler
// Zavolá CorsMiddleware::handle()
// Vytvoří Router a zavolá handleRequest()
```

### WebSocket server: `backend/bin/server.php`
```php
// Bootstrap Ratchet IoServer
// Obalí ChatSocket do WsServer → HttpServer → IoServer
// Naslouchá na portu 8080
```

---

## Router (`backend/src/Router.php`)

Router implementuje URL dispatch pomocí `switch` na URI:
- statické endpointy: `case '/api/login':` → `(new AuthController())->login()`
- dynamické endpointy pro users: prefix `/api/users/` + extrakce ID

Před dispatchem aplikuje rate limity pro POST požadavky.

```
Endpointy:
/api/login, /api/register, /api/logout, /api/user/me
/api/users/{id} (DELETE, PUT)
/api/friends (GET, add, accept, reject, remove, requests, search)
/api/rooms, /api/chat/open
/api/messages (history, send, update, delete)
/api/groups (create, members, add-member, leave, update, kick)
/api/notifications, /api/chat/mark-read
/api/admin/* (dashboard, users, rooms, logs, create-admin)
```

---

## Controllers

Controllers jsou tenká orchestrační vrstva. Zodpovídají za:
1. Zavolání AuthMiddleware (ověření JWT)
2. Parsování vstupů (json_decode, $_GET)
3. Zavolání příslušného Validatoru
4. Zavolání Modelu
5. Vrácení ApiResponse

### AuthController
- `login()` — ověření hesla, vytvoření session, generování JWT
- `register()` — validace, unikátnost, bcrypt hash, vytvoření uživatele
- `logout()` — deaktivace session tokenu
- `me()` — vrácení dat přihlášeného uživatele

### ChatController
- `getRooms()` — seznam DM a skupinových místností uživatele
- `openDm()` — otevření/vytvoření DM s přítelem
- `sendMessage()` — odeslání a uložení zprávy
- `getHistory()` — načtení historie zpráv místnosti
- `updateMessage()` — úprava vlastní zprávy
- `deleteMessage()` — soft delete vlastní zprávy
- `createGroup()`, `getGroupMembers()`, `addGroupMember()`, `leaveGroup()`, `updateGroup()`, `kickMember()`

### FriendController
- `search()` — vyhledávání dostupných uživatelů (bez adminů, bez existujících přátel)
- `add()` — odeslání žádosti o přátelství
- `index()` — seznam přijatých přátel
- `requests()` — seznam příchozích žádostí
- `accept()`, `reject()`, `remove()`

### UserController
- `update()` — aktualizace profilu (jen vlastní)
- `delete()` — smazání účtu

### NotificationController
- `getUnread()` — nepřečtené notifikace uživatele
- `markRead()` — označení notifikací místnosti jako přečtené

### AdminController
- `getDashboardStats()` — statistiky platformy
- `getUsers()`, `getUserDetails()`, `deleteUser()`
- `getRooms()`, `getRoomDetails()`, `getRoomHistory()`, `deleteRoom()`
- `getLogs()` — audit logy
- `createAdmin()` — vytvoření nového admin účtu

---

## Models (PDO)

Modely zapouzdřují veškerou databázovou logiku.

### User.php
- `create()`, `findById()`, `findByEmail()`, `findByUsername()`, `findAll()`
- `update()`, `delete()` — transakční smazání s kaskádou
- `updateStatus()`, `logActivity()`, `countAdmins()`
- `getRoleNameById()`, `getRoleIdByName()`, `findRoleNameByUserId()`

### Chat.php
- `getUserRooms()` — komplexní JOIN dotaz pro sidebar
- `getOrCreateDmRoom()` — atomická operace v transakci
- `canAccessRoom()` — ověření přístupu (membership + friendship)
- `getRoomMessages()`, `sendMessage()`, `deleteMessage()`, `editMessage()`
- `createGroup()`, `getGroupMembers()`, `addGroupMember()`, `removeGroupMember()`
- `getMemberRole()`, `updateGroupInfo()`, `deleteRoom()`, `leaveGroupSafe()`

### Friend.php
- `sendRequest()` — kontrola existence + kontrola admin role cíle
- `acceptRequest()`, `rejectRequest()`, `remove()`
- `getFriends()`, `getPendingRequests()`
- `searchAvailableUsers()` — výsledky filtrované o existující přátelé
- `areFriends()`, `exists()`

### Session.php
- `create()` — INSERT nové session
- `deactivateByToken()` — UPDATE is_active = FALSE
- `findUserIdByToken()` — zpětné vyhledání uživatele

### Notification.php
- `getUnreadByUserId()`, `markAsRead()`, `hasUnreadForRoom()`
- `createMessageNotification()` — INSERT do notifications tabulky

### Admin.php
- `getDashboardStats()` — 4 COUNT dotazy + recent logs
- `getUsers()`, `getUserDetails()`, `getRooms()`, `getRoomDetails()`, `getRoomHistory()`
- `getLogs()`, `getRecentLogs()`, `createAdmin()`, `getRoleIdByName()`

---

## Middleware

### AuthMiddleware (`src/Middleware/AuthMiddleware.php`)
```
1. Přečte Authorization: Bearer <token> z hlavičky
2. JWTService::decode($token) — ověří kryptografický podpis
3. SELECT session WHERE token=? AND is_active=TRUE AND expires_at>NOW()
4. Vrátí decoded payload {sub, role, iat, exp} nebo 401
```

### CorsMiddleware (`src/Middleware/CorsMiddleware.php`)
- Čte CORS_ALLOWED_ORIGINS z ENV (čárkou oddělený seznam domén)
- Nastaví Access-Control-Allow-Origin pouze pro povolené domény
- Odpovídá na OPTIONS preflight s HTTP 204
- Nepovolené domény dostávají HTTP 403

### RateLimitMiddleware (`src/Middleware/RateLimitMiddleware.php`)
- Ukládá timestampy požadavků do temp souborů (`/tmp/whisp_rate_limits/`)
- Per-IP, per-endpoint limity:
    - `POST /api/login` — 10 pokusů / 60 sekund
    - `POST /api/register` — 5 pokusů / 300 sekund
    - `POST /api/friends/add` — 20 pokusů / 60 sekund
    - `POST /api/messages/send` — 120 zpráv / 60 sekund
- Při překročení vrátí HTTP 429 + Retry-After header

---

## Services

### JWTService (`src/Services/JWTService.php`)
```php
generate(string $userId, string $role): string
    // payload: {iat, exp, sub, role}
    // podepisuje HMAC-SHA256 s JWT_SECRET z ENV

decode(string $token): ?stdClass
    // vrátí null při neplatném tokenu nebo chybě
    // chytá všechny Throwable výjimky
```

---

## ApiResponse (`src/Http/ApiResponse.php`)

Garantuje konzistentní strukturu všech JSON odpovědí:

```json
// Úspěšná odpověď
{"token": "...", "user": {...}}

// Chybová odpověď
{
  "message": "Neplatný email nebo heslo",
  "error": {
    "code": "invalid_credentials",
    "message": "Neplatný email nebo heslo"
  }
}
```

---

## Validátory (`src/Validators/`)

| Soubor | Co validuje |
|--------|------------|
| AuthValidator | login (email, password), register (+ délka hesla, formát emailu) |
| ChatValidator | target_id, message payload, group creation, room_id |
| FriendValidator | target_id, request_id, friend_id, sanitizeSearchQuery |
| UserValidator | profileUpdate, adminCreate |
| AdminValidator | userIdFromQuery, roomIdFromQuery, userIdPayload, roomIdPayload |
| NotificationValidator | markReadPayload (room_id) |
