# 13 – Developer Guide

Tento dokument je praktická příručka pro každého, kdo chce projekt spustit,
vyvíjet nebo rozšiřovat.

---

## Systémové požadavky

| Nástroj | Minimální verze | Proč |
|---------|----------------|------|
| Docker | 24.0+ | Kontejnerizace všech služeb |
| Docker Compose | 2.0+ | Orchestrace multi-container stacku |
| Git | libovolná | Správa verzí |

PHP, Node.js ani PostgreSQL **není nutné instalovat lokálně**.

---

## Spuštění přes Docker (doporučeno)

```bash
# 1. Klonovat repozitář
git clone <repository-url>
cd whisp

# 2. Spustit celý stack (první build trvá 3–5 minut)
docker compose up --build

# 3. Inicializovat admin účet (jen jednou)
docker exec -it whisp_backend php public/install_admin.php
```

Po prvním buildu jsou Docker image v cache — každé další spuštění je rychlejší:
```bash
docker compose up
```

### Výchozí přihlášení (admin)
- Email: `a@a.a`
- Heslo: `a`

---

## Dostupné adresy

| Služba | URL |
|--------|-----|
| Frontend | http://localhost:5173 |
| REST API | http://localhost:8000 |
| WebSocket | ws://localhost:8080 |
| PostgreSQL | localhost:5432 |

---

## Užitečné Docker příkazy

```bash
# Zastavit všechny služby
docker compose down

# Zastavit a smazat data databáze (volume db_data)
docker compose down -v

# Sledovat logy konkrétní služby v reálném čase
docker compose logs -f backend
docker compose logs -f websocket
docker compose logs -f frontend

# Vstoupit do shellu kontejneru
docker exec -it whisp_backend bash
docker exec -it whisp_frontend sh

# Přímý přístup k PostgreSQL
docker exec -it whisp_db psql -U whisp_user -d whisp_db
```

---

## Spuštění testů

```bash
# Backend – PHPUnit (49 testů)
docker exec -it whisp_backend ./vendor/bin/phpunit --testdox

# Frontend – Vitest (5 testů)
cd frontend && npm test

# Spuštění jen konkrétního test souboru
docker exec -it whisp_backend ./vendor/bin/phpunit --testdox tests/Unit/Validators/AuthValidatorTest.php
```

Očekávaný výsledek:
```
Tests: 49, Assertions: 59, OK (0 failures)
```

---

## Spuštění bez Dockeru (pro vývoj)

### Databáze (nechat v Dockeru)
```bash
docker compose up db
```

### Backend REST API
```bash
cd backend
composer install
export DB_HOST=localhost DB_NAME=whisp_db DB_USER=whisp_user DB_PASS=whisp_password
export JWT_SECRET=development-secret-min-32-chars JWT_TTL_SECONDS=86400
export CORS_ALLOWED_ORIGINS=http://localhost:5173
php -S 0.0.0.0:8000 -t public public/index.php
```

### WebSocket server
```bash
cd backend
php bin/server.php
```

### Frontend
```bash
cd frontend
npm install
npm run dev
```

---

## Jak přidat nový REST endpoint

Každý nový endpoint prochází 5 kroky:

### Krok 1: Validator
V příslušném `src/Validators/*.php` přidat validační metodu:
```php
public static function validateNovyEndpoint(?object $data): ?string {
    if (!$data || empty($data->required_field)) {
        return 'Chybí required_field';
    }
    return null; // null = validace OK
}
```

### Krok 2: Model
V příslušném `src/Models/*.php` přidat metodu s SQL logikou:
```php
public function novaMetoda(string $param): array|false {
    $stmt = $this->conn->prepare("SELECT ... WHERE id = ?");
    $stmt->execute([$param]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### Krok 3: Controller
V příslušném `src/Controllers/*.php` přidat public metodu:
```php
public function novyEndpoint(): void {
    $currentUser = AuthMiddleware::check();
    $data = json_decode(file_get_contents("php://input"));

    $validationError = MujValidator::validateNovyEndpoint($data);
    if ($validationError !== null) {
        ApiResponse::error('validation_error', $validationError, 400);
        return;
    }

    $result = $this->model->novaMetoda($data->required_field);
    ApiResponse::success($result);
}
```

### Krok 4: Router
V `src/Router.php` přidat case do switch:
```php
case '/api/novy-endpoint':
    if ($method === 'POST') {
        (new MujController())->novyEndpoint();
        return;
    }
    break;
```

### Krok 5: Test
Spustit stávající testy a přidat nový test pro validator:
```bash
docker exec -it whisp_backend ./vendor/bin/phpunit --testdox
```

---

## Git workflow

```bash
# Začít práci na nové funkci
git checkout main && git pull origin main
git checkout -b feat/nazev-funkce

# Průběžné commity
git add .
git commit -m "feat: popis změny"

# Push a Pull Request
git push origin feat/nazev-funkce
# → GitHub → Create Pull Request → CI pipeline projde → Merge

# Cleanup
git checkout main && git pull
git branch -d feat/nazev-funkce
```

### Konvence commitů
```
feat: přidání nové funkce
fix: oprava chyby
docs: změna dokumentace
test: přidání nebo úprava testů
refactor: refaktoring bez změny funkčnosti
chore: údržba (gitignore, závislosti, ...)
```

---

## Databáze — přímý přístup

```bash
# Připojení k PostgreSQL v kontejneru
docker exec -it whisp_db psql -U whisp_user -d whisp_db

# Užitečné příkazy v psql:
\dt                    # seznam tabulek
\d users               # struktura tabulky users
SELECT * FROM users;   # výpis všech uživatelů
SELECT * FROM sessions WHERE is_active = TRUE;  # aktivní sessions
```

---

## ENV proměnné

Všechny citlivé hodnoty jsou definovány v `docker-compose.yml`:

| Proměnná | Popis |
|----------|-------|
| DB_HOST | Hostname databáze (`db` v Docker síti) |
| DB_NAME | Název databáze |
| DB_USER | Uživatel PostgreSQL |
| DB_PASS | Heslo PostgreSQL |
| JWT_SECRET | Tajný klíč pro JWT podpis — **změňte v produkci!** |
| JWT_TTL_SECONDS | Platnost JWT tokenu (86400 = 24 hodin) |
| CORS_ALLOWED_ORIGINS | Čárkou oddělené povolené domény |

---

## Časté problémy

### Port je obsazen
```bash
# Zjistit, co používá port 8000
lsof -i :8000  # macOS/Linux
netstat -ano | findstr :8000  # Windows
```

### Databáze se neinicializuje
```bash
# Smazat volume a znovu spustit
docker compose down -v
docker compose up --build
```

### WebSocket se nepřipojí
Zkontrolujte, zda kontejner `whisp_websocket` běží:
```bash
docker compose ps
docker compose logs websocket
```
