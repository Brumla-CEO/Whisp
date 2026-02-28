# Whisp – realtime chat (React + PHP + PostgreSQL + WebSockets)

Whisp je chatovací aplikace se správou uživatelů, přátel, DM i skupinových místností a realtime notifikacemi přes WebSocket (Ratchet).

- **Frontend:** React (Vite)
- **Backend API:** PHP 8 (vlastní router, PDO)
- **Realtime:** Ratchet WebSocket server
- **DB:** PostgreSQL (init skript v `backend/init.sql`)
- **Docker:** `docker-compose.yml` (DB + API + WS + FE)

## Rychlý start (Docker)

```bash
docker compose up --build
```

### Porty
- Frontend: `http://localhost:5173`
- Backend API: `http://localhost:8000`
- WebSocket: `ws://localhost:8080`
- PostgreSQL: `localhost:5432` (uživatel `whisp_user`, DB `whisp_db`)

## Dokumentace
Rozcestník: [`docs/README.md`](docs/README.md)

## Struktura repozitáře (high-level)
- `frontend/` – React klient
- `backend/` – PHP API + Ratchet WS server + init DB
- `docs/` – projektová dokumentace (hybrid: maturita + engineering)
- `docker-compose.yml` – lokální prostředí

## Bezpečnostní poznámka
Projekt aktuálně používá:
- JWT podpis klíčem uloženým přímo ve zdrojáku (`backend/src/Services/JWTService.php`)
- token posílá do WS serveru přes query string (`ws://.../?token=...`)

Viz **`docs/issue.md`** pro bezpečnostní a architektonický audit a návrhy oprav.

