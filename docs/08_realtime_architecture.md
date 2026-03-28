# 08 – Real-time Architektura (WebSocket + Ratchet)

## Proč WebSockety místo HTTP Pollingu

Klasická HTTP komunikace funguje na principu request-response — klient se musí vždy
zeptat jako první. Pro chat by to znamenalo neustálé dotazování serveru, zda přišla
nová zpráva. Tomuto přístupu se říká polling a je velmi neefektivní.

```
HTTP Polling (špatně):
Klient → "Jsou nové zprávy?" → Server → "Ne"
Klient → "Jsou nové zprávy?" → Server → "Ne"
Klient → "Jsou nové zprávy?" → Server → "Ano!"
→ Zbytečná zátěž, zpoždění

WebSocket (správně — RFC 6455):
Klient ──── HTTP Upgrade ────► Server
            ◄── 101 Switching Protocols ──
(trvalé obousměrné TCP spojení)
Server ──── "Nová zpráva!" ──► Klient (okamžitě, kdykoli)
```

WebSocket umožňuje plně duplexní komunikaci — server i klient mohou posílat data
kdykoli, bez nutnosti předchozí žádosti.

---

## Ratchet WebSocket Server

Server je implementovaný v `backend/src/Sockets/ChatSocket.php` pomocí knihovny Ratchet.
Ratchet je event-driven asynchronní WebSocket server postavený na ReactPHP event loop.

Třída `ChatSocket` implementuje `MessageComponentInterface` se čtyřmi metodami:
- `onOpen(ConnectionInterface $conn)` — nové spojení
- `onMessage(ConnectionInterface $from, string $msg)` — příchozí zpráva
- `onClose(ConnectionInterface $conn)` — odpojení
- `onError(ConnectionInterface $conn, Exception $e)` — chyba

---

## Datové struktury serveru (in-memory)

Server drží tři datové struktury pro správu aktivních spojení:

```php
$clients         // SplObjectStorage všech aktivních WS spojení
$userConnections // [userId][resourceId] = ConnectionInterface
$connMeta        // [resourceId] = {authenticated, userId, activeRoomId}
```

Klíčové architektonické rozhodnutí: `$userConnections[userId][resourceId]`
— jeden uživatel může mít více záložek otevřených současně.

Status `online` se mění jen při:
- PRVNÍM připojení daného userId (ne při každé záložce)
- POSLEDNÍM odpojení (ne při zavření jedné ze dvou záložek)

---

## Autentizace přes WebSocket

WebSocket handshake HTTP hlavičky sice podporuje, ale jejich přístupnost
v JavaScript WebSocket API je omezená. Proto je implementována vlastní auth flow:

```
1. Client → Server: new WebSocket('ws://host:8080')
2. Server → Client: onopen event
3. Client → Server: {"type": "auth", "token": "eyJ..."}
4. Server: ověří JWT + DB session
5. Server → Client: {"type": "auth_ok", "userId": "..."}
6. Od teď: server přijímá další zprávy od tohoto klienta
```

Neautentizovaná spojení jsou ignorována nebo uzavřena po timeoutu.

---

## Presence Tracking

Aby se zbytečně nevytvářely notifikace pro uživatele aktivně sledující danou místnost:

```
Uživatel otevře místnost:
  React → WS: {"type": "presence:set_active_room", "roomId": 5}
  Server: connMeta[resourceId].activeRoomId = 5

Nová zpráva přijde do místnosti 5:
  → userB.activeRoomId == 5 → zprávu vidí přímo, notifikace NE
  → userC.activeRoomId != 5 → INSERT INTO notifications + badge
```

---

## Notifikační logika

```
Příchozí zpráva pro místnost X:
  ↓
getRoomMembers(X) — všichni členové místnosti
  ↓
Pro každého člena:
  Je uživatel online (má aktivní WS spojení)?
    NE  → INSERT notification, neposlat WS event (offline)
    ANO → Je activeRoomId == X?
            ANO → sendToUser(message:new)   -- žádná notifikace
            NE  → sendToUser(message:new) + INSERT notification + sendToUser(notification)
```

---

## Přehled WebSocket událostí

### Klient → Server

| Event | Payload | Popis |
|-------|---------|-------|
| `auth` | `{token}` | Autentizace po připojení |
| `presence:set_active_room` | `{roomId}` | Označení aktivní místnosti |
| `message:new` | `{roomId, message}` | Broadcast nové zprávy členům |
| `message_update` | `{roomId, msgId, newContent}` | Broadcast úpravy zprávy |
| `message_delete` | `{roomId, msgId}` | Broadcast smazání zprávy |
| `friend_action` | `{targetId, action}` | Akce v přátelství |
| `group_kick` | `{roomId, kickedUserId, groupName}` | Vyhazení ze skupiny |
| `group_change` | `{roomId}` | Změna skupiny (reload pro členy) |
| `profile_change` | - | Broadcast změny profilu přátelům |
| `contact_deleted` | `{userId}` | Broadcast smazání účtu |

### Server → Klient

| Event | Payload | Kdy |
|-------|---------|-----|
| `auth_ok` | `{userId}` | Po úspěšné autentizaci |
| `message:new` | `{roomId, message}` | Nová zpráva v místnosti |
| `message_update` | `{roomId, msgId, newContent}` | Zpráva byla upravena |
| `message_delete` | `{roomId, msgId}` | Zpráva byla smazána |
| `user_status` | `{userId, status}` | Online/offline změna |
| `notification` | `{roomId, from}` | Nepřečtená zpráva (badge) |
| `friend_update` | `{action, from}` | Akce v systému přátel |
| `group_update` | `{roomId}` | Změna ve skupině |
| `kicked_from_group` | `{roomId, groupName}` | Vyhození ze skupiny |
| `contact_deleted` | `{userId}` | Uživatel byl smazán |
| `admin_user_deleted` | `{userId}` | Admin smazal účet |

---

## Online/Offline status flow

```
WS onOpen + úspěšná auth:
  → Je to první připojení tohoto userId?
      ANO → DB: status = 'online', broadcast přátelům user_status
      NE  → druhý tab, nic se nemění

WS onClose:
  → Odebrat z userConnections
  → Zbývají jiná WS připojení pro tohoto userId?
      ANO → nic se nemění
      NE  → DB: status = 'offline', broadcast přátelům user_status
```

---

## Flow odeslání zprávy (end-to-end)

```
1. Uživatel A píše zprávu a klikne Odeslat

2. React: POST /api/messages/send {room_id, content, reply_to_id}
   PHP API: INSERT INTO messages RETURNING id
   Odpověď: {data: savedMessage}

3. React: WS send {type: 'message:new', roomId, message: savedMessage}

4. ChatSocket::onMessage():
   - Ověří auth
   - getRoomMembers(roomId) → [userA, userB, userC]
   - userB je online + activeRoom == 5 → sendToUser(message:new)
   - userC je online + activeRoom != 5 → sendToUser(message:new) + notification
   - userD je offline → INSERT notification (doručí se při přihlášení)
```

---

## Technický dluh — WebSocket

Aktuální implementace má tyto omezení:

1. **Single-process** — Ratchet běží jako jeden PHP proces. Při pádu procesu
   jsou ztracena všechna aktivní spojení.

2. **In-memory state** — Datové struktury existují pouze v paměti jedné instance.
   Horizontální škálování (více instancí) není možné bez centralizovaného pub/sub.

3. **Doporučení pro produkci:**
    - Redis pub/sub pro broadcast zpráv napříč instancemi
    - Shared presence store (Redis) pro online status
    - Supervisor nebo systemd pro automatický restart při pádu
