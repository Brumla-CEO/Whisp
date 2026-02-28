# Frontend Documentation — Whisp

## 1. Úvod

Frontend aplikace **Whisp** je webová Single Page Application (SPA) napsaná v **Reactu**.  
Běží v prohlížeči uživatele a zajišťuje:

- autentizační obrazovky (Login / Register),
- hlavní chat UI (seznam kontaktů/místností + chat okno),
- real-time aktualizace přes WebSocket,
- správu profilu (ProfileSetup),
- správu přátel a žádostí (FriendManager),
- detail profilu uživatele (UserProfileModal),
- detail skupiny a správu členů (GroupDetailsModal),
- admin rozhraní pro uživatele s rolí `admin` (AdminPanel).

Frontend komunikuje s backendem dvěma kanály:

1) **REST API (HTTP/JSON)** – pro persistenci dat (login, seznam místností, historie zpráv, CRUD akce)  
2) **WebSocket** – pro real-time události (nové zprávy, změny zpráv, notifikace, statusy uživatelů)

---

## 2. Technologie a knihovny (reálně použité)

- **React** (komponentní UI)
- **Axios** (HTTP klient)
- **Vite** (build a dev server – podle typické struktury projektu + souborů `vite.config.js`, `main.jsx`)
- CSS styly: `src/index.css` + `src/App.css`

> Poznámka: V aplikaci se nepoužívá `react-router-dom` pro routing. Navigace mezi pohledy je řešena přes React state (viz `App.jsx`).

---

## 3. Struktura projektu

### `src/main.jsx`
- Vstupní bod React aplikace.
- Renderuje `<App />` uvnitř `<AuthProvider>`.

### `src/Context/AuthContext.jsx`
- Centrální autentizační logika.
- Vytváří Axios instanci `api` s baseURL:
  - `http://{hostname}:8000/api`
- Přidává token do hlavičky pomocí request interceptoru:
  - `Authorization: Bearer <token>`
- Řeší 401 odpovědi pomocí response interceptoru:
  - smaže token, odhlásí uživatele a přesměruje na `/`

### `src/App.jsx`
- Hlavní orchestrátor UI (nejdůležitější soubor frontendu).
- Řídí:
  - přepínání Login/Register
  - přepínání Settings/Friends
  - výběr konverzace a aktivní místnosti
  - WebSocket připojení a routing WS eventů do UI
  - odlišení `admin` vs `user` UI

### `src/Components/*`
Komponenty jednotlivých částí UI:

- `Login.jsx`, `Register.jsx`
- `UserList.jsx` (seznam chatů / kontaktů / místností)
- `ChatWindow.jsx` (chat okno, odesílání a update zpráv)
- `ProfileSetup.jsx`
- `FriendManager.jsx`
- `CreateGroupModal.jsx`, `GroupDetailsModal.jsx`
- `UserProfileModal.jsx`
- `AdminPanel.jsx`

---

## 4. Autentizace (token v localStorage)

Frontend používá token uložený v:

- `localStorage["token"]`

### 4.1 Login
- Volá REST endpoint:
  - `POST /login` (payload: `email`, `password`)
- Uloží token:
  - `localStorage.setItem('token', res.data.token)`
- Uloží uživatele do stavu `user` (AuthContext)

### 4.2 Register
- Volá:
  - `POST /register` (payload: `username`, `email`, `password`)
- Pokud backend vrátí token, frontend ho uloží stejně jako při loginu.

### 4.3 Persist login (auto-check po refreshi)
Při startu aplikace AuthContext provede:

- pokud existuje token → `GET /user/me`
- nastaví `user` podle odpovědi

### 4.4 Logout
- Volá:
  - `POST /logout` (best-effort, frontend odhlásí i když request selže)
- smaže token
- nastaví `user = null`
- přesměruje na `/`

### 4.5 Řešení chyb 401 (expirace / smazaný účet)
Response interceptor v `AuthContext.jsx`:

- při `401 Unauthorized` a existujícím tokenu:
  - token smaže
  - uživatele odhlásí
  - zobrazí alert
  - provede redirect na `/`

Tím se zamezí nekonzistenci (frontend si nemyslí, že je přihlášený, když backend token neuznává).

---

## 5. REST API komunikace (používané endpointy)

Frontend volá backend přes `api` z AuthContextu.

### Základní endpointy (AuthContext)
- `POST /login`
- `POST /register`
- `POST /logout`
- `GET /user/me`

### Hlavní aplikace (App.jsx)
- `GET /notifications` (initial sync)
- `GET /friends/requests` (počítadlo žádostí)
- `POST /chat/open` (otevření DM a získání `room_id`)
- `GET /rooms` (synchronizace dat o skupinách při WS update)

### Komponenty (Components)
Podle reálných volání v komponentách:

**Admin**
- `GET /admin/dashboard`
- `GET /admin/users`
- `GET /admin/rooms`
- `GET /admin/logs`
- `POST /admin/users/delete`
- `POST /admin/rooms/delete`
- `POST /admin/create-admin`

**Chat**
- `POST /messages/send`
- `POST /messages/update`
- `POST /messages/delete`
- `POST /chat/mark-read`

**Friends**
- `GET /friends`
- `GET /friends/requests`
- `POST /friends/add`
- `POST /friends/accept`
- `POST /friends/reject`
- `POST /friends/remove`

**Groups**
- `POST /groups/create`
- `POST /groups/add-member`
- `POST /groups/kick`
- `POST /groups/leave`
- `POST /groups/update`

---

## 6. WebSocket komunikace (real-time vrstva)

WebSocket připojení se vytváří v `App.jsx` pouze když:

- uživatel je přihlášen (`user` existuje),
- `user.role !== 'admin'`,
- existuje token v localStorage,
- není už existující otevřené / otevírající se WS spojení.

### 6.1 URL připojení
Frontend vytváří URL:

- `ws://{window.location.hostname}:8080?token={token}`

Token se tedy předává jako query parameter.

### 6.2 Životní cyklus připojení
- `onopen`: nastaví `socket` do stavu a vypíše log
- `onmessage`: parsuje JSON a předá `handleWebSocketMessage()`
- `onclose`: vynuluje socket state + ref
- při logoutu nebo ztrátě `user` se socket zavírá

---

## 7. Zpracování WebSocket eventů (App.jsx)

Frontend zpracovává WS zprávy podle `data.type`:

### 7.1 Eventy pro chat
Pokud je typ:
- `message:new`
- `message_update`
- `message_delete`

Frontend:
- dispatchne `window` event:
  - `chat-update` s `detail: data`

Chat UI (např. `ChatWindow`) může na tento event reagovat a refreshnout zprávy.

### 7.2 Notifikace
Pokud je typ `notification`:
- přidá `roomId` nebo `from` do `unreadIds`
- dispatchne `chat-update`

### 7.3 Status uživatelů
Pokud je typ `user_status`:
- dispatchne `friend-status-change` s detailem

### 7.4 Změny přátel / kontaktů / skupin
- `friend_update`:
  - dispatch `friend-status-change` (refresh)
  - pokud `action === 'request_received'` zvýší `friendRequestCount`
- `contact_update` nebo `group_update`:
  - dispatch refresh
  - u `group_update` navíc provede `GET /rooms` a synchronizuje jméno/avatara aktivní skupiny

### 7.5 Speciální události
- `kicked_from_group`:
  - pokud je otevřená aktivní místnost, vyčistí view a upozorní uživatele
- `contact_deleted`:
  - refresh kontaktů
  - pokud je smazaný uživatel otevřený, vyčistí chat a upozorní

---

## 8. Řízení UI bez routeru (state-based navigation)

Frontend nepoužívá knihovnu na routing.  
Místo toho se UI větví podle hodnot v React state:

- pokud `loading` → loading screen
- pokud `!user` → auth view (Login/Register)
- pokud `user.role === 'admin'` → `<AdminPanel />`
- jinak main chat layout

Dále se přepíná:
- `showSettings` (ProfileSetup view)
- `showFriends` (FriendManager overlay)
- `selectedChatUser` + `activeRoomId` (ChatWindow vs welcome screen)
- modály:
  - `viewingProfile` → `UserProfileModal`
  - `viewingGroup` → `GroupDetailsModal`

Tento přístup je vhodný pro menší SPA bez URL navigace, kde je cílem jednoduchost.

---

## 9. Stav a synchronizace dat

Frontend udržuje několik klíčových stavů:

- `user` (AuthContext)
- `selectedChatUser` + `activeRoomId` (aktuální chat)
- `unreadIds` (místnosti/uživatelé s unread notifikací)
- `friendRequestCount` (badge v headeru)
- `socket` (WebSocket instance)

Synchronizace probíhá:
- inicializačně přes REST (`/notifications`, `/friends/requests`)
- průběžně přes WebSocket eventy + lokální `CustomEvent` dispatch na `window`

---

## 10. Error handling a UX chování

- Kritické auth chyby (401) řeší centrálně `AuthContext` interceptor:
  - odhlášení + redirect + alert (jednorázově přes `useRef` lock)
- U některých operací (např. otevření DM) se ošetřuje i 404:
  - pokud backend vrátí `404` → UI upozorní, že uživatel neexistuje a refreshne seznam kontaktů

---

## 11. Bezpečnostní poznámky (frontend pohled)

- Token je uložen v `localStorage`.
  - Výhoda: jednoduché použití s Axios interceptorem
  - Nevýhoda: vyšší riziko při XSS (proto je důležité minimalizovat XSS v UI)
- Autorizace dat není řešena frontendem.
  - Frontend pouze zobrazí UI, ale **backend musí rozhodnout**, kdo má přístup k datům.
- WebSocket připojení posílá token jako query parameter.
  - Ověření tokenu musí dělat WS server (nebo backend, pokud WS deleguje auth).

---

## 12. Shrnutí

Frontend Whisp je React SPA, které:

- používá `AuthContext` jako centrální bod autentizace,
- komunikuje s backendem přes Axios (`http://{hostname}:8000/api`),
- používá token uložený v localStorage a posílá ho jako `Bearer` hlavičku,
- používá WebSocket (`ws://{hostname}:8080?token=...`) pro real-time události,
- nevyužívá router knihovnu – UI se řídí stavem v `App.jsx`,
- implementuje uživatelské i admin rozhraní podle `user.role`.