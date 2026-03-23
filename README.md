# Whisp – realtime chat (React + PHP + PostgreSQL + WebSockets)

Whisp je chatovací aplikace se správou uživatelů, přátel, soukromých zpráv i skupinových místností a realtime notifikacemi přes WebSocket (Ratchet).

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

## Testy
- Frontend unit testy: `cd frontend && npm test`
- Backend validator smoke test: `php backend/tests/validator_smoke_test.php`
- API smoke test nad běžícím stackem: `./tests/api_smoke_test.sh`

## Struktura repozitáře (high-level)
- `frontend/` – React klient
- `backend/` – PHP API + Ratchet WS server + init DB
- `docs/` – projektová dokumentace (hybrid: maturita + engineering)
- `docker-compose.yml` – lokální prostředí

## Bezpečnostní poznámka
Projekt používá JWT uložené v konfiguraci prostředí a WebSocket autentizaci přes úvodní `auth` zprávu po navázání spojení. Přehled bezpečnostních pravidel a otevřených bodů dalšího rozvoje je popsán v [`docs/09_security_model.md`](docs/09_security_model.md) a [`docs/issue.md`](docs/issue.md).

