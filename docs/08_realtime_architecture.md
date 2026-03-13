# Realtime architektura (WebSocket)

## Připojení
Frontend se připojuje na:

- `ws://<host>:8080?token=<JWT>`

Příklad (aktuální kód `frontend/src/App.jsx`):
```js
const wsUrl = `ws://${window.location.hostname}:8080?token=${token}`;
const ws = new WebSocket(wsUrl);
```

## Autentizace (server-side)
`backend/src/Sockets/ChatSocket.php`:
1. načte query param `token`
2. dekóduje JWT (`JWTService::decode`)
3. uloží mapování `userId -> ConnectionInterface`
4. drží `connMeta` (např. activeRoomId)

**Riziko:** token v query string může skončit v logu reverse proxy nebo v historii. Doporučený fix je WS subprotocol nebo první message `auth`.

## Model eventů

### Klient -> server (typy)
#### presence:set_active_room
Slouží pro potlačení notifikací, když je uživatel „uvnitř“ room.

```json
{ "type": "presence:set_active_room", "roomId": 123 }
```

#### message:new
Rebroadcast zprávy pro členy room a vytvoření notifikace pro offline/nezobrazené.

```json
{
  "type": "message:new",
  "roomId": 123,
  "message": { "id": 1, "content": "Ahoj", "sender_id": "<uuid>", "created_at": "..." }
}
```

#### message_update / message_delete
Server pouze broadcastuje payload do room.
```json
{ "type": "message_update", "roomId": 123, "message_id": 1, "content": "..." }
```

#### profile_change
Rebroadcast změn profilu do seznamu kontaktů přátel.
```json
{ "type": "profile_change" }
```

#### group_change
```json
{ "type": "group_change", "roomId": 123 }
```

#### group_kick
Zkontroluje admin právo v room a:
- broadcast group_update
- pošle cílovému uživateli `kicked_from_group`

```json
{ "type": "group_kick", "roomId": 123, "kickedUserId": "<uuid>", "groupName": "..." }
```

#### friend_action
```json
{ "type": "friend_action", "targetId": "<uuid>", "action": "accepted|rejected|removed|..." }
```

#### contact_deleted
Broadcast do přátel a roomů, že uživatel byl smazán.
```json
{ "type": "contact_deleted", "userId": "<uuid>" }
```

### Server -> klient (typy)
#### message:new
```json
{ "type": "message:new", "roomId": 123, "message": { ... } }
```

#### notification
```json
{ "type": "notification", "roomId": 123, "from": "<uuid>" }
```

#### friend_update
```json
{ "type": "friend_update", "action": "accepted|rejected|...", "from": "<uuid>" }
```

#### contact_update
```json
{ "type": "contact_update", "userId": "<uuid>" }
```

#### group_update
```json
{ "type": "group_update", "roomId": 123 }
```

#### kicked_from_group
```json
{ "type": "kicked_from_group", "roomId": 123, "groupName": "skupiny" }
```

## Notifikační logika
Server vyhodnotí, zda má příjemce aktivní room:
- pokud **ano**, notifikaci nevytváří
- pokud **ne**, vloží `notifications` a pokud je uživatel online, pošle WS event `notification`

Tím se minimalizuje „spam“ při aktivním chatu.

## Škálovatelnost
Současný broadcast používá in-memory struktury (jedna instance). Pro horizontální škálování:
- sdílení presence přes Redis
- pub/sub pro eventy
- sticky sessions na load balanceru (pokud zůstane in-memory)

