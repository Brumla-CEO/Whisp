# Dev guide

Tento dokument popisuje vývojové prostředí a doporučený workflow.

## Požadavky
- Docker Desktop / Docker Engine
- Node.js (pokud budeš spouštět FE mimo Docker)
- PHP 8.x (pokud budeš spouštět BE mimo Docker)
- PostgreSQL klient (volitelné)

## Spuštění přes Docker (doporučeno)
```bash
docker compose up --build
```

### Co se stane
- `db` nastartuje Postgres a při prvním startu provede `backend/init.sql`
- `backend` spustí PHP built-in server na `:8000`
- `websocket` spustí Ratchet server na `:8080`
- `frontend` spustí Vite dev server na `:5173`

## Spuštění bez Dockeru (jen pro vývoj)
### DB
Nejjednodušší je nechat DB v Dockeru:
```bash
docker compose up db
```

### Backend
```bash
cd backend
composer install
php -S 0.0.0.0:8000 -t public public/index.php
```

ENV proměnné pro DB (pokud nepoužiješ compose):
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

### WebSocket
```bash
cd backend
php bin/server.php
```

### Frontend
```bash
cd frontend
npm install
npm run dev -- --host
```

## Debugging
- API logy: container `whisp_backend`
- WS logy: container `whisp_websocket`
- DB: `psql` na portu 5432

## Databáze (přístup)
- host: `localhost`
- port: `5432`
- db: `whisp_db`
- user: `whisp_user`
- password: `whisp_password`

