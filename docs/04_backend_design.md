# Backend design (PHP)

## Vstupní body
- HTTP API: `backend/public/index.php`
- WebSocket server: `backend/bin/server.php`

## Routing
`backend/src/Router.php` implementuje jednoduchý router:
- podporuje statické endpointy (`switch (uri)`)
- podporuje dynamické route pro users: `/api/users/{id}` (DELETE/PUT)
- řeší CORS (společně s `public/index.php` – viz audit)

### Konvence
- JSON payload přes `php://input` (POST/PUT)
- Query parametry přes `$_GET` (typicky `room_id`)

## Controllers
Controllers jsou tenká vrstva mezi HTTP a modely. Aktuálně:
- část controllerů pracuje výhradně přes modely (ChatController)
- část controllerů obsahuje přímé SQL (AuthController, FriendController, AdminController) – viz `issue.md`

Seznam controllerů:
- `AuthController` – login/register/logout/me
- `UserController` – list users, update, delete
- `FriendController` – friendships (search/add/accept/reject/remove)
- `ChatController` – rooms, messages, groups
- `NotificationController` – unread + mark read
- `AdminController` – admin dashboard/users/rooms/logs

## Models (PDO)
- `Models/User.php` – CRUD uživatele + activity log
- `Models/Friend.php` – operace nad friendships + join na user data
- `Models/Chat.php` – rooms + memberships + messages + groups

Modely používají PDO prepared statements, což je správný základ.

## Middleware
### AuthMiddleware
Soubor: `backend/src/Middleware/AuthMiddleware.php`

Chování:
1. přečte `Authorization: Bearer <token>`
2. dekóduje JWT (`JWTService::decode`)
3. ověří existenci aktivní session v DB:
   - `SELECT id FROM sessions WHERE token = ? AND is_active = TRUE LIMIT 1`

Výstup:
- při úspěchu vrací decoded JWT payload (`sub`, `role`, `iat`, `exp`)
- při neúspěchu vrací `401` + JSON message

> Pozn.: middleware aktuálně nekontroluje `expires_at` v tabulce sessions a nedělá rotaci tokenů.

## Services
### JWTService
Soubor: `backend/src/Services/JWTService.php`

- generuje token s expirací 24h (`exp = now + 86400`)
- payload obsahuje `sub` (userId) a `role`
- decode vrací `null` při chybě

**Technický dluh:** secret je hardcoded.

## Error handling
Systém používá jednoduchý pattern:
- nastaví `http_response_code(<code>)`
- vrací `echo json_encode(["message" => "..."])`

Doporučení (v issue.md): sjednotit error kontrakt (např. `{error:{code,message,details,traceId}}`), logování a validaci vstupů.

