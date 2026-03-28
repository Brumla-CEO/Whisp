# 03 – Architektura systému

## Přehled

Whisp je postavena jako **třívrstvá architektura** (Three-Tier Architecture):

- **Klientská vrstva** — React 19 SPA běžící v prohlížeči uživatele
- **Serverová vrstva** — PHP 8.2 REST API + Ratchet WebSocket server
- **Datová vrstva** — PostgreSQL 15

Klíčové architektonické rozhodnutí: REST API a WebSocket server jsou dvě části téže
serverové vrstvy — sdílejí databázi, aplikační logiku i ENV konfiguraci. Přestože běží
jako samostatné procesy na různých portech (8000 a 8080), nejde o čtyřvrstvou architekturu.

---

## Komponenty a jejich role

### Frontend (React 19 SPA)
- Entry point: `frontend/src/main.jsx`
- Root komponenta: `frontend/src/App.jsx` (WebSocket logika + Event Bus)
- Globální stav: `frontend/src/Context/AuthContext.jsx` (user, api, login, logout)
- Komunikuje přes HTTP REST s backendem na portu 8000
- Komunikuje přes WebSocket s Ratchet serverem na portu 8080

### Backend REST API (PHP 8.2)
- Entry point: `backend/public/index.php`
- Router: `backend/src/Router.php` — URL dispatch na controller metody
- Controllers: přijímají HTTP požadavky, orchestrují model a vracejí odpovědi
- Models: zapouzdřují veškerou SQL logiku přes PDO
- Middleware: CORS, Auth (JWT), RateLimit — průřezové funkce
- Services: JWTService — generování a dekódování tokenů
- Validators: validace vstupních dat per doméně
- HTTP: ApiResponse — standardizovaný JSON formát

### WebSocket Server (Ratchet)
- Entry point: `backend/bin/server.php`
- Handler: `backend/src/Sockets/ChatSocket.php`
- Implementuje Ratchet `MessageComponentInterface`
- Drží v paměti aktivní připojení a jejich metadata
- Komunikuje s PostgreSQL přes stejné PDO připojení jako REST API

### PostgreSQL 15
- Schéma: `backend/init.sql` (9 tabulek)
- Inicializováno automaticky při prvním startu Docker kontejneru
- Data persistována v Docker volume `db_data`

---

## Diagram architektury

```
Prohlížeč uživatele
       |
       |--- HTTP (port 8000) ---------> PHP REST API
       |                                     |
       |--- WebSocket (port 8080) ----> Ratchet WS Server
                                             |
                                    PostgreSQL 15 (port 5432)
```

Všechny 4 komponenty běží ve společné Docker síti `whisp_net`.
Kontejnery komunikují mezi sebou pomocí jmen služeb (např. `db:5432` místo IP adresy).

---

## MVC na backendu

Backend striktně dodržuje vzor MVC (Model-View-Controller).
V kontextu REST API je View vrstvou JSON odpověď.

```
HTTP požadavek
    ↓
index.php (entry point, global error handler, CORS)
    ↓
Router.php (URL dispatch)
    ↓
Middleware stack (CorsMiddleware → RateLimitMiddleware → AuthMiddleware)
    ↓
Controller (validace vstupů, orchestrace)
    ↓
Model (SQL dotazy přes PDO)
    ↓
ApiResponse::success() nebo ApiResponse::error()
```

### Middleware stack (v tomto pořadí)
1. **CorsMiddleware** — ověří, zda požadavek přichází z povolené domény (ENV whitelist)
2. **RateLimitMiddleware** — zkontroluje per-IP limity pro daný endpoint
3. **AuthMiddleware** — dekóduje JWT, ověří aktivní session v DB

---

## Tok HTTP požadavku (příklad: odeslání zprávy)

```
React klient
    |
    | POST /api/messages/send + Bearer JWT
    ↓
CorsMiddleware → ověření domény
    ↓
RateLimitMiddleware → 120 zpráv / 60 sekund
    ↓
AuthMiddleware → JWT decode + DB session check
    ↓
ChatController::sendMessage()
    ↓
Chat::canAccessRoom() → ověření přístupu uživatele k místnosti
    ↓
Chat::sendMessage() → INSERT INTO messages RETURNING id
    ↓
ApiResponse::success({message, data: savedMessage})
    |
    ↓
React klient (HTTP odpověď 200)
```

---

## Tok WebSocket zprávy (příklad: broadcast nové zprávy)

```
React klient (odesílatel)
    |
    | WS: {type: 'message:new', roomId: 5, message: {...}}
    ↓
ChatSocket::onMessage()
    ↓
validateSocketToken() → ověření autentizace spojení
    ↓
getRoomMembers(roomId) → SELECT z room_memberships
    ↓
Pro každého člena místnosti:
    - Je online? Je v aktivní místnosti?
    - ANO → sendToUser({type: 'message:new', ...})
    - NE  → createNotification() + sendToUser({type: 'notification', ...})
```

---

## Docker Compose architektura

```yaml
services:
  db:           postgres:15-alpine    port 5432
  backend:      php:8.2-alpine        port 8000  (depends_on: db)
  websocket:    php:8.2-alpine        port 8080  (depends_on: db, backend)
  frontend:     node:20-alpine        port 5173  (depends_on: backend, websocket)

volumes:
  db_data: (persistentní data PostgreSQL)

networks:
  whisp_net: bridge
```

Pořadí spouštění je vynuceno direktivou `depends_on`:
`db` → `backend` → `websocket` → `frontend`

---

## Event Bus na frontendu

Pro komunikaci mezi React komponentami bez přímé hierarchie (prop drilling)
používá aplikace nativní mechanismus `window.dispatchEvent` a `window.addEventListener`.

```
App.jsx (WS onmessage handler)
    |
    |-- CustomEvent('chat-update')      --> ChatWindow.jsx
    |-- CustomEvent('friend-status-change') --> UserList.jsx
    |-- CustomEvent('app-notify')       --> AppAlerts.jsx (toast)
    |-- CustomEvent('friend-removed')   --> App.jsx (zavřít DM)
```

Výhoda: komponenty se mohou přihlásit k libovolnému eventu bez nutnosti
předávat callback funkce přes celý komponentový strom.

---

## Proměnné prostředí

| Proměnná | Hodnota (dev) | Popis |
|----------|--------------|-------|
| DB_HOST | db | Hostname databáze v Docker síti |
| DB_NAME | whisp_db | Název databáze |
| DB_USER | whisp_user | Uživatel PostgreSQL |
| DB_PASS | whisp_password | Heslo PostgreSQL |
| JWT_SECRET | change_this_... | Tajný klíč pro podepisování JWT |
| JWT_TTL_SECONDS | 86400 | Platnost tokenu (24 hodin) |
| CORS_ALLOWED_ORIGINS | http://localhost:5173 | Povolené domény |
