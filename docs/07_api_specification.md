# API specifikace
## HTTP API (REST)

Základní URL: `http://<host>:8000/api`

### Přehled endpointů

| Metoda | Cesta | Handler |
|---|---|---|
| GET | `/api/admin/chat/history` | `AdminController::getRoomHistory` |
| POST | `/api/admin/create-admin` | `AdminController::createAdmin` |
| GET | `/api/admin/dashboard` | `AdminController::getDashboardStats` |
| GET | `/api/admin/logs` | `AdminController::getLogs` |
| GET | `/api/admin/rooms` | `AdminController::getRooms` |
| POST | `/api/admin/rooms/delete` | `AdminController::deleteRoom` |
| GET | `/api/admin/rooms/detail` | `AdminController::getRoomDetails` |
| GET | `/api/admin/users` | `AdminController::getUsers` |
| POST | `/api/admin/users/delete` | `AdminController::deleteUser` |
| GET | `/api/admin/users/detail` | `AdminController::getUserDetails` |
| POST | `/api/chat/mark-read` | `NotificationController::markRead` |
| POST | `/api/chat/open` | `ChatController::openDm` |
| GET | `/api/friends` | `FriendController::index` |
| POST | `/api/friends/accept` | `FriendController::accept` |
| POST | `/api/friends/add` | `FriendController::add` |
| POST | `/api/friends/reject` | `FriendController::reject` |
| POST | `/api/friends/remove` | `FriendController::remove` |
| GET | `/api/friends/requests` | `FriendController::requests` |
| GET | `/api/friends/search` | `FriendController::search` |
| POST | `/api/groups/add-member` | `ChatController::addGroupMember` |
| POST | `/api/groups/create` | `ChatController::createGroup` |
| POST | `/api/groups/kick` | `ChatController::kickMember` |
| POST | `/api/groups/leave` | `ChatController::leaveGroup` |
| GET | `/api/groups/members` | `ChatController::getGroupMembers` |
| POST | `/api/groups/update` | `ChatController::updateGroup` |
| POST | `/api/login` | `AuthController::login` |
| POST | `/api/logout` | `AuthController::logout` |
| POST | `/api/messages/delete` | `ChatController::deleteMessage` |
| GET | `/api/messages/history` | `ChatController::getHistory` |
| POST | `/api/messages/send` | `ChatController::sendMessage` |
| POST | `/api/messages/update` | `ChatController::updateMessage` |
| GET | `/api/notifications` | `NotificationController::getUnread` |
| POST | `/api/register` | `AuthController::register` |
| GET | `/api/rooms` | `ChatController::getRooms` |
| GET | `/api/user/me` | `AuthController::me` |
| GET | `/api/users` | `UserController::index` |


---

## Autentizace

### POST /api/register
**Body:**
```json
{
  "username": "string",
  "email": "string",
  "password": "string"
}
```
**Responses:**
- `201` Registrace úspěšná:
```json
{
  "message": "Registrace úspěšná",
  "token": "<jwt>",
  "user": {"id":"<uuid>","username":"...","email":"...","role":"user","avatar_url":"...","status":"online","bio":""}
}
```
- `400` Neplatná data
- `409` username/email existuje

### POST /api/login
**Body:**
```json
{
  "email": "string",
  "password": "string"
}
```
**Responses:**
- `200`:
```json
{
  "token": "<jwt>",
  "user": {"id":"<uuid>","username":"...","email":"...","role":"admin|user","avatar_url":null,"status":"online","bio":null}
}
```
- `400` chybí údaje
- `401` špatné přihlášení

### GET /api/user/me
**Auth:** Bearer token

**Response 200:**
```json
{
  "id":"<uuid>",
  "username":"...",
  "email":"...",
  "role":"admin|user",
  "avatar_url":null,
  "bio":null,
  "status":"online|offline"
}
```

### POST /api/logout
**Auth:** Bearer token

**Response 200:**
```json
{"message":"Odhlášeno"}
```

## Uživatelé

### GET /api/users
**Auth:** Bearer token

Vrací seznam uživatelů (používá se např. v admin panelu / listu uživatelů).

### PUT /api/users/{id}
**Auth:** Bearer token (musí to být vlastní id)

**Body:**
```json
{
  "username": "string",
  "email": "string",
  "bio": "string|null",
  "avatar_url": "string|null"
}
```
**Response 200:**
```json
{
  "message": "Profil aktualizován",
  "user": {"id":"<uuid>","username":"...","email":"...","bio":null,"avatar_url":null}
}
```

### DELETE /api/users/{id}
**Auth:** Bearer token (musí to být vlastní id)

Maže uživatele a kaskádově sessions, memberships, friendships atd. (dle FK).

## Přátelé

### GET /api/friends
**Auth:** Bearer token

Vrací seznam přátel (accepted).

### GET /api/friends/search?query=...
**Auth:** Bearer token

Vyhledání uživatelů podle query.

### POST /api/friends/add
**Body:**
```json
{"target_id":"<uuid>"}
```

### POST /api/friends/accept | /api/friends/reject
**Body:**
```json
{"request_id": <int>}
```

### POST /api/friends/remove
**Body:**
```json
{"friend_id":"<uuid>"}
```

### GET /api/friends/requests
Seznam incoming žádostí.

## Chat / Rooms / Messages

### GET /api/rooms
Vrací rooms uživatele (DM + group).

### POST /api/chat/open
Otevření / vytvoření DM.
**Body:**
```json
{"target_id":"<uuid>"}
```

### GET /api/messages/history?room_id=<id>
Historie zpráv pro room.

### POST /api/messages/send
**Body:**
```json
{"room_id":1,"content":"text","reply_to_id":null}
```
**Response 200:**
```json
{"message":"Odesláno","data":{...message row...}}
```

### POST /api/messages/update
**Body:**
```json
{"message_id":123,"content":"nový text"}
```

### POST /api/messages/delete
**Body:**
```json
{"message_id":123}
```

## Skupiny

### POST /api/groups/create
**Body:**
```json
{"name":"Název","members":["<uuid>","<uuid>"]}
```

### GET /api/groups/members?room_id=<id>

### POST /api/groups/add-member
**Body:**
```json
{"room_id":1,"user_id":"<uuid>"}
```

### POST /api/groups/leave
**Body:**
```json
{"room_id":1}
```

### POST /api/groups/update
**Body:**
```json
{"room_id":1,"name":"...","avatar_url":"..."}
```

### POST /api/groups/kick
**Body:**
```json
{"room_id":1,"user_id":"<uuid>"}
```

## Notifikace

### GET /api/notifications
Vrací nepřečtené notifikace.

### POST /api/chat/mark-read
**Body:**
```json
{"room_id":1}
```
Označí notifikace pro room jako přečtené.

## Admin
Všechny admin endpointy jsou chráněné interní kontrolou role (`AdminController::checkAdmin()`).

### GET /api/admin/dashboard
Statistiky: users/online/rooms/messages.

### GET /api/admin/users

### GET /api/admin/users/detail?user_id=<uuid>

### POST /api/admin/users/delete
**Body:**
```json
{"user_id":"<uuid>"}
```

### GET /api/admin/rooms

### GET /api/admin/rooms/detail?room_id=<id>

### POST /api/admin/rooms/delete
**Body:**
```json
{"room_id":1}
```

### GET /api/admin/logs

### GET /api/admin/chat/history?room_id=<id>

### POST /api/admin/create-admin
Instalační endpoint pro vytvoření admin účtu.


## Poznámky k API kontraktům
- Většina endpointů vrací `{"message": ...}` a někdy `data`.
- Error odpovědi nejsou 100% sjednocené – viz `docs/issue.md`.
- Endpointy pro update/delete messages a group actions se zrcadlí do WS eventů.
