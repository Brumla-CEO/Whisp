# Whisp – Live Chat Application

**Maturitní práce | Bruno Vašíček | I4C | SPŠE Ostrava | 2025/2026**
**Vedoucí práce:** Ing. Anna Golembiovská

Whisp je plnohodnotná webová chatovací aplikace s real-time komunikací. Projekt byl navržen, implementován, otestován a nasazen jako součást maturitní práce v oboru Informační technologie (18-20-M/01).

---

## Technologický stack

| Vrstva | Technologie | Verze |
|--------|------------|-------|
| Frontend | React, Vite, Axios | React 19, Vite 7 |
| Backend REST API | PHP OOP, vlastní Router + MVC | PHP 8.2 |
| Real-time server | Ratchet (cboden/ratchet) | 0.4.4 |
| Autentizace | JWT (firebase/php-jwt) + DB sessions | ^7.0 |
| Databáze | PostgreSQL | 15 |
| Infrastruktura | Docker Compose (4 kontejnery) | - |
| Testování backend | PHPUnit | 10 — 49 testů |
| Testování frontend | Vitest + React Testing Library | 4.x — 5 testů |
| CI/CD | GitHub Actions | - |

---

## Rychlý start

### Požadavky
- Docker 24.0+
- Docker Compose 2.0+
- Git (libovolná verze)

PHP, Node.js ani PostgreSQL není nutné instalovat lokálně — vše běží v kontejnerech.

### Spuštění

```bash
# 1. Klonovat repozitář
git clone <repository-url>
cd whisp

# 2. Spustit celý stack (první build trvá 3–5 minut)
docker compose up --build

# 3. Inicializovat admin účet (jen jednou po prvním spuštění)
docker exec -it whisp_backend php public/install_admin.php
```

### Výchozí admin přihlašovací údaje

| Pole | Hodnota |
|------|---------|
| Email | a@a.a |
| Heslo | a |

> Toto jsou pouze vývojové údaje. Před jakýmkoli veřejným nasazením je změňte!

---

## Dostupné adresy po spuštění

| Služba | URL | Popis |
|--------|-----|-------|
| Frontend | http://localhost:5173 | React SPA aplikace |
| REST API | http://localhost:8000 | PHP backend |
| WebSocket | ws://localhost:8080 | Ratchet real-time server |
| PostgreSQL | localhost:5432 | Databáze |

---

## Spuštění testů

```bash
# Backend – PHPUnit (49 unit testů)
docker exec -it whisp_backend ./vendor/bin/phpunit --testdox

# Frontend – Vitest (5 unit testů)
cd frontend && npm test
```

Výsledek: Tests: 49, Assertions: 59, OK

---

## Struktura projektu

```
Whisp/
├── README.md
├── docker-compose.yml
├── backend/
│   ├── public/
│   │   ├── index.php               ← entry point REST API
│   │   └── install_admin.php       ← jednorázová instalace admina
│   ├── bin/
│   │   └── server.php              ← entry point WebSocket serveru
│   ├── src/
│   │   ├── Config/Database.php     ← PDO singleton
│   │   ├── Controllers/            ← HTTP logika
│   │   ├── Models/                 ← databázová logika přes PDO
│   │   ├── Middleware/             ← Auth, CORS, RateLimit
│   │   ├── Services/JWTService.php ← JWT wrapper
│   │   ├── Validators/             ← vstupní validace
│   │   ├── Sockets/ChatSocket.php  ← Ratchet handler
│   │   └── Http/ApiResponse.php    ← standardizovaný JSON
│   ├── tests/Unit/                 ← PHPUnit testy
│   ├── Router.php
│   ├── composer.json
│   └── phpunit.xml
├── frontend/
│   ├── src/
│   │   ├── main.jsx
│   │   ├── App.jsx
│   │   ├── Context/AuthContext.jsx
│   │   ├── Components/
│   │   └── tests/
│   ├── vite.config.js
│   └── package.json
├── database/
│   └── init.sql
├── .github/workflows/ci.yml
└── docs/
```

---

## Hlavní funkce

- Autentizace — registrace, přihlášení, odhlášení s invalidací JWT session
- Správa profilu — avatar (DiceBear nebo vlastní URL), bio, username
- Systém přátel — vyhledávání, žádosti, přijetí/odmítnutí, odebrání
- Soukromý DM chat — automatické vytvoření místnosti
- Skupinový chat — vytvoření, správa členů, kick, leave, předání vlastnictví
- Real-time zprávy — odesílání, úprava, soft delete, reply s citací
- Presence tracking — notifikace jen pokud nejsi v aktivní místnosti
- Online/Offline status — sledováno přes WebSocket
- Admin panel — statistiky, správa uživatelů a místností, audit logy
- Bezpečnost — bcrypt, PDO prepared statements, CORS, RBAC, Rate Limiting

---

## CI/CD pipeline

Každý push na GitHub automaticky spouští: setup PHP 8.2 + Node 20, instalace závislostí, PHPUnit (49 testů), Vitest (5 testů).
Konfigurace: `.github/workflows/ci.yml`

---

## Docker architektura

| Kontejner | Port | Popis |
|-----------|------|-------|
| whisp_db | 5432 | PostgreSQL 15 |
| whisp_backend | 8000 | REST API |
| whisp_websocket | 8080 | WebSocket server |
| whisp_frontend | 5173 | React + Vite |

---

## Dokumentace

Kompletní dokumentace: [`docs/README.md`](docs/README.md)

---

## Autor

Bruno Vašíček — I4C — SPŠE Ostrava — 2025/2026
Vedoucí: Ing. Anna Golembiovská
