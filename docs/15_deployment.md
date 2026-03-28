# 15 – Deployment Guide

## Přehled

Whisp je aktuálně nakonfigurován pro **vývojové nasazení** přes Docker Compose.
Celý stack se spustí jedním příkazem na libovolném počítači s Dockerem.

Pro produkční nasazení jsou potřeba další kroky popsané v sekci níže.

---

## Vývojové nasazení (Docker Compose)

### Rychlý start

```bash
# Klonovat a spustit
git clone <repository-url>
cd whisp
docker compose up --build

# Inicializovat admin účet (jen jednou)
docker exec -it whisp_backend php public/install_admin.php
```

### Co se stane při spuštění

```
1. whisp_db (PostgreSQL 15)
   → Spustí databázi
   → Při prvním startu provede backend/init.sql (9 tabulek + výchozí role)
   → Data persistuje v Docker volume 'db_data'

2. whisp_backend (PHP 8.2)
   → Čeká na db (depends_on)
   → Spustí PHP built-in server na portu 8000
   → Zpracovává HTTP REST požadavky

3. whisp_websocket (PHP 8.2)
   → Čeká na db a backend (depends_on)
   → Spustí Ratchet WebSocket server na portu 8080
   → Zpracovává WS spojení

4. whisp_frontend (Node 20)
   → Čeká na backend a websocket (depends_on)
   → Spustí Vite dev server na portu 5173
   → Compiluje React aplikaci on-demand
```

### Zastavení stacku

```bash
# Zastavit bez ztráty dat
docker compose down

# Zastavit a smazat databázi (kompletní reset)
docker compose down -v

# Spustit na pozadí
docker compose up -d --build
```

---

## Docker Compose konfigurace

### Kontejnery

| Kontejner | Obraz | Port | Závislosti |
|-----------|-------|------|-----------|
| whisp_db | postgres:15-alpine | 5432 | - |
| whisp_backend | php:8.2-alpine (custom) | 8000 | db |
| whisp_websocket | php:8.2-alpine (custom) | 8080 | db, backend |
| whisp_frontend | node:20-alpine (custom) | 5173 | backend, websocket |

### Volumes

```yaml
volumes:
  db_data:   # PostgreSQL data — přežije restart kontejnerů
```

### Síť

```yaml
networks:
  whisp_net:
    driver: bridge   # Všechny kontejnery komunikují interně
```

### Porty (jen pro vývoj)
Porty 5432, 8000, 8080 jsou exponovány na localhost pro debugging.
V produkci by měly být přístupné jen interně přes síť.

---

## ENV proměnné

Veškerá konfigurace je předávána přes ENV:

```yaml
# backend + websocket kontejner
DB_HOST: db
DB_NAME: whisp_db
DB_USER: whisp_user
DB_PASS: whisp_password
JWT_SECRET: change_this_to_a_long_random_secret_for_dev
JWT_TTL_SECONDS: 86400
CORS_ALLOWED_ORIGINS: http://localhost:5173,http://127.0.0.1:5173
```

> **POZOR:** `JWT_SECRET` v docker-compose.yml je vývojová hodnota.
> V produkci používejte správce tajemství (viz níže).

---

## Produkční nasazení — checklist

Pro reálné produkční nasazení jsou potřeba tyto kroky:

### 1. Reverse proxy (Nginx)
```nginx
# Příklad konfigurace
server {
    listen 443 ssl;
    server_name whisp.example.com;

    location / {
        proxy_pass http://localhost:5173;
    }
    location /api {
        proxy_pass http://localhost:8000;
    }
    location /ws {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

### 2. TLS (HTTPS + WSS)
- Certifikát přes Let's Encrypt (certbot)
- Frontend používá `https://`, WebSocket `wss://`

### 3. Bezpečné secrets management
Místo hardcoded hodnot v docker-compose.yml:
```bash
# Docker Swarm secrets
echo "tajny_klic" | docker secret create jwt_secret -

# Nebo .env soubor mimo repozitář
JWT_SECRET=$(openssl rand -hex 32)
```

### 4. PHP produkční server
PHP built-in server není vhodný pro produkci. Alternativy:
- PHP-FPM + Nginx
- Apache + mod_php
- Caddy s PHP-FPM

### 5. Frontend build
Pro produkci místo Vite dev serveru:
```bash
cd frontend
npm run build   # vytvoří dist/ složku
# Servovat statické soubory přes Nginx
```

### 6. Databáze — produkční nastavení
- Unikátní, silné heslo (min. 32 znaků)
- Zálohy (pg_dump cronjoby)
- Connection pooling (pgBouncer) pro větší zátěž
- Read repliky pro horizontální škálování čtení

### 7. Monitoring a health checks
```yaml
# Docker Compose health check
healthcheck:
  test: ["CMD-SHELL", "pg_isready -U whisp_user -d whisp_db"]
  interval: 30s
  timeout: 10s
  retries: 3
```

---

## WebSocket škálování

Ratchet WebSocket server je single-process a drží spojení v paměti jedné instance.

**Pro horizontální škálování (více instancí):**

1. **Sticky sessions na load balanceru** — každý klient vždy směrován na stejnou instanci
2. **Redis pub/sub** — broadcast zpráv napříč instancemi
3. **Centralizovaná presence** — Redis store pro online statusy

```
Load Balancer (sticky sessions)
├── WS Instance 1 ──► Redis pub/sub ◄── WS Instance 2
└── WS Instance 3 ──────────────────────────────────┘
```

---

## CI/CD pipeline (GitHub Actions)

Pipeline v `.github/workflows/ci.yml` se spouští automaticky při každém push:

```yaml
Trigger:
  - push na main, feat/**, fix/**
  - pull_request na main

Jobs:
  backend-tests:
    - Checkout kódu
    - Setup PHP 8.2 (pdo, pdo_pgsql)
    - composer install
    - Nastavit JWT_SECRET a JWT_TTL_SECONDS
    - phpunit --testdox

  frontend-tests:
    - Checkout kódu
    - Setup Node 20
    - npm ci
    - npm test
```

Zelená pipeline → bezpečné mergovat do main.
Červená pipeline → merge je zablokován.

---

## Troubleshooting

### Databáze se nespustí
```bash
docker compose logs db
# Typická příčina: port 5432 je obsazen jiným PostgreSQL
# Řešení: změnit port v docker-compose.yml: "5433:5432"
```

### Backend vrací 500
```bash
docker compose logs backend
# Zkontrolovat ENV proměnné
docker exec -it whisp_backend env | grep DB_
```

### WebSocket se nepřipojí
```bash
docker compose logs websocket
# Ověřit, že kontejner běží
docker compose ps
```

### Smazání všech dat a čistý start
```bash
docker compose down -v
docker compose up --build
docker exec -it whisp_backend php public/install_admin.php
```
