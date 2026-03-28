# 07 – API Specifikace (REST)

## Přehled

Základní URL: `http://<host>:8000/api`

Všechny chráněné endpointy vyžadují hlavičku:
```
Authorization: Bearer <JWT_TOKEN>
```

Všechny odpovědi jsou v JSON formátu s hlavičkou `Content-Type: application/json; charset=UTF-8`.

---

## Autentizace

### POST /api/register
Registrace nového uživatele.

**Body:**
```json
{ "username": "string", "email": "string", "password": "string" }
```

**Odpovědi:**
- `201` — Registrace úspěšná:
```json
{
  "message": "Registrace úspěšná",
  "token": "<jwt>",
  "user": { "id": "<uuid>", "username": "...", "email": "...", "role": "user",
            "avatar_url": "...", "status": "online", "bio": "" }
}
```
- `400` — Neplatná data (chybějící pole, krátké heslo, neplatný email)
- `409` — Username nebo email již existuje

### POST /api/login
Přihlášení existujícího uživatele.

**Body:**
```json
{ "email": "string", "password": "string" }
```

**Odpovědi:**
- `200` — Úspěšné přihlášení:
```json
{
  "token": "<jwt>",
  "user": { "id": "<uuid>", "username": "...", "role": "admin|user", "status": "online" }
}
```
- `400` — Chybějící údaje
- `401` — Neplatné přihlašovací údaje

### POST /api/logout
Odhlášení — deaktivuje session token.

**Auth:** Bearer token  
**Odpověď:** `200 {"message": "Odhlášeno"}`

### GET /api/user/me
Vrátí data přihlášeného uživatele.

**Auth:** Bearer token  
**Odpověď:** `200 { "id", "username", "email", "role", "avatar_url", "bio", "status" }`

---

## Uživatelé

### PUT /api/users/{id}
Aktualizace profilu uživatele (jen vlastní profil).

**Auth:** Bearer token  
**Body:**
```json
{ "username": "string", "email": "string", "bio": "string|null", "avatar_url": "string|null" }
```
**Odpovědi:**
- `200` — Profil aktualizován
- `403` — Cizí profil
- `409` — Username již existuje

### DELETE /api/users/{id}
Smazání uživatelského účtu.

**Auth:** Bearer token  
**Odpovědi:**
- `200` — Účet smazán (kaskádně sessions, friendships, memberships)
- `403` — Nelze smazat posledního admina

---

## Přátelé

### GET /api/friends
Seznam přijatých přátel.

**Auth:** Bearer token  
**Odpověď:** `200 [{ "id", "username", "avatar_url", "status", "bio", "friendship_id" }]`

### GET /api/friends/search?q={query}
Vyhledávání uživatelů (bez adminů, bez existujících přátel).

**Auth:** Bearer token  
**Odpověď:** `200 [{ "id", "username", "avatar_url", "status" }]`

### POST /api/friends/add
Odeslání žádosti o přátelství.

**Body:** `{ "target_id": "<uuid>" }`  
**Odpovědi:** `200` / `400` (duplicitní žádost, sám sebe)

### GET /api/friends/requests
Seznam příchozích žádostí o přátelství.

**Odpověď:** `200 [{ "request_id", "username", "avatar_url", "created_at", "requester_id" }]`

### POST /api/friends/accept
Přijetí žádosti.

**Body:** `{ "request_id": integer }`  
**Odpověď:** `200 {"message": "Přátelství navázáno!"}`

### POST /api/friends/reject
Odmítnutí žádosti.

**Body:** `{ "request_id": integer }`

### POST /api/friends/remove
Odebrání přítele.

**Body:** `{ "friend_id": "<uuid>" }`

---

## Chat — místnosti a zprávy

### GET /api/rooms
Seznam místností uživatele (DM + skupiny) s posledními zprávami a počtem nepřečtených.

### POST /api/chat/open
Otevření nebo vytvoření DM místnosti s přítelem.

**Body:** `{ "target_id": "<uuid>" }`  
**Odpověď:** `200 { "room_id": integer }`  
**Chyba:** `403` — Nelze otevřít chat s neuživatelem nebo nekamarádem

### GET /api/messages/history?room_id={id}
Historie zpráv místnosti.

**Odpověď:**
```json
[{
  "id": 1, "room_id": 5, "sender_id": "<uuid>",
  "content": "Ahoj!", "reply_to_id": null,
  "is_edited": false, "is_deleted": false,
  "created_at": "2026-03-01T10:30:00",
  "username": "Bruno", "avatar_url": "..."
}]
```

### POST /api/messages/send
Odeslání zprávy.

**Body:** `{ "room_id": integer, "content": "string", "reply_to_id": integer|null }`  
**Odpověď:** `200 { "message": "Odesláno", "data": { ...message row... } }`  
**Chyba:** `403` — Přístup do místnosti odepřen

### POST /api/messages/update
Úprava vlastní zprávy.

**Body:** `{ "message_id": integer, "content": "string" }`

### POST /api/messages/delete
Soft delete vlastní zprávy (is_deleted = TRUE).

**Body:** `{ "message_id": integer }`

---

## Skupiny

### POST /api/groups/create
Vytvoření skupinové místnosti.

**Body:** `{ "name": "string", "members": ["<uuid>", "<uuid>"] }`  
Minimálně 2 přátelé v poli members (celkem 3 s zakladatelem).  
**Odpověď:** `200 { "message": "Skupina vytvořena", "room_id": integer }`

### GET /api/groups/members?room_id={id}
Seznam členů skupiny s rolemi.

### POST /api/groups/add-member
**Body:** `{ "room_id": integer, "user_id": "<uuid>" }`

### POST /api/groups/leave
Opuštění skupiny. Pokud odchází admin, vlastnictví se předá nejdéle přihlášenému členovi.

**Body:** `{ "room_id": integer }`

### POST /api/groups/update
Aktualizace názvu a avataru skupiny (jen admin skupiny).

**Body:** `{ "room_id": integer, "name": "string", "avatar_url": "string|null" }`

### POST /api/groups/kick
Vyhození člena ze skupiny (jen admin skupiny).

**Body:** `{ "room_id": integer, "user_id": "<uuid>" }`

---

## Notifikace

### GET /api/notifications
Nepřečtené notifikace přihlášeného uživatele.

### POST /api/chat/mark-read
Označení notifikací místnosti jako přečtené.

**Body:** `{ "room_id": integer }`

---

## Admin (vyžaduje roli admin)

### GET /api/admin/dashboard
Statistiky: `{ counts: { users, online, rooms, messages }, recent_logs: [...] }`

### GET /api/admin/users
Seznam všech uživatelů s rolemi.

### GET /api/admin/users/detail?user_id={uuid}
Audit logy konkrétního uživatele.

### POST /api/admin/users/delete
**Body:** `{ "user_id": "<uuid>" }`

### GET /api/admin/rooms
Seznam všech místností s počtem členů a zpráv.

### GET /api/admin/rooms/detail?room_id={id}
Členové místnosti.

### GET /api/admin/chat/history?room_id={id}
Posledních 50 zpráv místnosti (pro admin přehled).

### POST /api/admin/rooms/delete
**Body:** `{ "room_id": integer }`

### GET /api/admin/logs
Audit logy (posledních 200).

### POST /api/admin/create-admin
Vytvoření nového admin účtu.

**Body:** `{ "username": "string", "email": "string", "password": "string" }`

---

## Chybové odpovědi

Všechny chyby mají konzistentní strukturu:

```json
{
  "message": "Lidsky čitelná chybová zpráva",
  "error": {
    "code": "snake_case_error_code",
    "message": "Stejná zpráva"
  }
}
```

### HTTP stavové kódy

| Kód | Situace |
|-----|---------|
| 200 | Úspěch |
| 201 | Vytvořeno (registrace) |
| 400 | Neplatná data (validace) |
| 401 | Neautorizovaný přístup (neplatný token) |
| 403 | Zakázaný přístup (špatná role, cizí data) |
| 404 | Zdroj nenalezen |
| 409 | Konflikt (duplicitní username/email) |
| 429 | Too Many Requests (rate limit) |
| 500 | Interní chyba serveru |
