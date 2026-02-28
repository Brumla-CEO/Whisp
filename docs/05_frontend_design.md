# Frontend design (React)

## Struktura
- `frontend/src/App.jsx` – hlavní orchestrátor UI (routing podle role/stavu)
- `frontend/src/Context/AuthContext.jsx` – zdroj pravdy pro user/token + axios klient + API volání
- `frontend/src/Components/*` – UI moduly:
  - Login/Register
  - FriendManager
  - ChatWindow + modal komponenty (group, profile)
  - AdminPanel

## AuthContext
`AuthContext` řeší:
- uložení tokenu do `localStorage`
- načtení uživatele přes `/api/user/me`
- axios instance s interceptory:
  - request: přidání `Authorization` headeru
  - response: při 401 logout

### Base URL
```js
baseURL: `http://${window.location.hostname}:8000/api`
```
Díky tomu funguje lokálně i v Dockeru bez přepisování hosta.

## WebSocket integrace
V `App.jsx` se po přihlášení vytváří WS spojení:

- URL: `ws://<host>:8080?token=<JWT>`
- po připojení se socket ukládá do state a předává child komponentám

Front-end odesílá eventy (viz realtime dokumentace):
- `message:new`
- `presence:set_active_room`
- `profile_change`
- `group_change`
- `group_kick`
- `friend_action`
- `contact_deleted`

A naslouchá eventům:
- `message:new`
- `notification`
- `contact_update`
- `friend_update`
- `group_update`
- `kicked_from_group`
- `contact_deleted`

## Komponenty – odpovědnosti
- `ChatWindow.jsx` – UI pro chat: historie, odeslání, edit/delete, nastavení activeRoom pro notifikace
- `FriendManager.jsx` – vyhledávání + žádosti + seznam přátel
- `CreateGroupModal.jsx` – vytvoření skupiny
- `GroupDetailsModal.jsx` – správa členů, update názvu/avataru, kick/leave
- `UserProfileModal.jsx` – detail profilu + edit
- `AdminPanel.jsx` – admin dashboard + správa uživatelů a roomů
- `ProfileSetup.jsx` – dokončení profilu (bio/avatar)

## UI/UX principy
- Token-based session – po refreshi se uživatel automaticky načte z `/api/user/me`
- Minimalizace dotazů: po realtime eventu se UI lokálně aktualizuje nebo refetchne relevantní data.

