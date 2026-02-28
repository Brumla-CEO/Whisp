# Whisp – Setup / spuštění aplikace

Tento dokument popisuje, jak lokálně **nastavit a spustit** aplikaci **Whisp** (frontend, backend API, WebSocket server a PostgreSQL databázi) přes Docker.

---

## 0. Předpoklady

- Nainstalovaný **Docker** a **Docker Compose**
- Volné porty na stroji:
  - `5173` (frontend – Vite)
  - `8000` (backend – PHP API)
  - `8080` (WebSocket server)
  - `5432` (PostgreSQL)

---

## 1. Struktura projektu (co se spouští)

Repozitář obsahuje:

- `docker-compose.yml` – spouští všechny služby
- `backend/` – PHP backend + WebSocket server (Ratchet)
- `frontend/` – React/Vite frontend
- `backend/init.sql` – inicializace databáze (tabulky + default role)

Služby v `docker-compose.yml`:

- `db` – PostgreSQL 15
- `backend` – PHP 8.2 (REST API) na portu `8000`
- `websocket` – WebSocket server na portu `8080`
- `frontend` – Vite dev server na portu `5173`

---

## 2. Spuštění přes Docker Compose

### 2.1 Přechod do rootu projektu

Spusť příkazy v adresáři, kde je `docker-compose.yml`:

```bash
cd Whisp
```

(Je to složka obsahující `backend/`, `frontend/`, `docker-compose.yml`.)

### 2.2 Build + start

```bash
docker compose up --build
```

- Poprvé to chvíli trvá (build PHP/Node image, instalace Composer a NPM závislostí).
- Databáze se při prvním startu inicializuje skriptem `backend/init.sql`.

### 2.3 Ověření běhu

Po startu běží:

- **Frontend:** `http://localhost:5173`
- **REST API:** `http://localhost:8000/api`
- **WebSocket:** `ws://localhost:8080`
- **DB:** `localhost:5432`

---

## 3. Databáze

### 3.1 Přihlašovací údaje (z docker-compose.yml)

- Host: `localhost`
- Port: `5432`
- DB: `whisp_db`
- User: `whisp_user`
- Password: `whisp_password`

### 3.2 Co se vytvoří při prvním startu

`backend/init.sql` vytvoří tabulky pro:

- role (vloží `admin`, `user`)
- users
- sessions
- rooms
- room_memberships
- messages
- friendships
- activity_logs
- notifications

---

## 4. Backend API (PHP)

Backend je spuštěný vestavěným PHP serverem:

- URL: `http://localhost:8000`
- API prefix: `/api`

Frontend volá API dynamicky podle hostname:

- baseURL je ve frontendu nastavena na:
  - `http://${window.location.hostname}:8000/api`

---

## 5. WebSocket server

Frontend vytváří WebSocket spojení v `App.jsx` (pouze pro přihlášeného uživatele, který **není admin**).

- URL:

```text
ws://${window.location.hostname}:8080?token=USER_TOKEN
```

Token se bere z `localStorage` (`token`).

Poznámka: připojení se znovu nevytváří, pokud už existuje `OPEN` nebo `CONNECTING` socket.

---

## 6. Zastavení aplikace

### 6.1 Stop (zachová kontejnery)

```bash
docker compose stop
```

### 6.2 Down (odstraní kontejnery)

```bash
docker compose down
```

### 6.3 Down včetně databázových dat (POZOR: smaže DB)

```bash
docker compose down -v
```

---

## 7. Nejčastější problémy

### 7.1 Porty jsou obsazené

Pokud ti Docker hlásí konflikt portu (např. `5432`), zastav službu, která port používá (např. lokální PostgreSQL), nebo uprav mapování portů v `docker-compose.yml`.

### 7.2 Chyba `function gen_random_uuid() does not exist`

V `backend/init.sql` je u `users.id` použito `gen_random_uuid()`. Pokud PostgreSQL při initu spadne na chybě, bývá potřeba povolit rozšíření `pgcrypto`.

Ověření/oprava (v DB):

```sql
CREATE EXTENSION IF NOT EXISTS pgcrypto;
```

---

## 8. Rychlá kontrola, že vše funguje

1. Otevři frontend: `http://localhost:5173`
2. Zaregistruj uživatele
3. Po přihlášení by měl frontend:
   - používat REST API na `:8000`
   - navázat WebSocket na `:8080` (uvidíš log o připojení v konzoli)

---

## 9. Poznámky k vývojovému režimu

- Frontend běží jako Vite dev server (`npm run dev -- --host` v kontejneru).
- Backend běží jako PHP built-in server.
- Kód je do kontejnerů přimountovaný přes volumes, takže změny se projeví bez rebuildů (standardní dev workflow).
