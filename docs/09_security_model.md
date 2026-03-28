# 09 – Bezpečnostní model

Bezpečnost byla od začátku vnímána jako průřezový požadavek, nikoli jako vrstva
přidaná na konci. Základním principem je: **nikdy nedůvěřovat vstupu od klienta**.

---

## Přehled bezpečnostních opatření

| Oblast | Implementace | Kde v kódu |
|--------|-------------|------------|
| Hashování hesel | bcrypt (`password_hash(PASSWORD_DEFAULT)`) | AuthController, User model |
| SQL Injection | PDO prepared statements ve 100 % dotazů | Všechny *Model.php soubory |
| CORS | Dynamický whitelist z ENV proměnné | CorsMiddleware.php |
| Autentizace | JWT podpis + serverová DB session validace | AuthMiddleware.php, JWTService.php |
| Autorizace (RBAC) | Role admin/user — kontrola v každém endpointu | Všechny Controllers |
| Rate Limiting | Per-IP a per-endpoint | RateLimitMiddleware.php |
| Validace vstupů | Dedikované Validator třídy per doméně | src/Validators/*.php |
| Error handling | Žádné detaily chyb v odpovědích | index.php global handler |
| Admin ochrana | Admini nejsou vyhledatelní ani přidatelní | Friend.php |
| Poslední admin | Nelze smazat jediného administrátora | User.php, AdminController |
| UUID klíče | Uživatelé nejsou iterovatelní přes ID | init.sql |

---

## Autentizace — JWT + DB Sessions

### JWT (JSON Web Token)
Token se skládá ze tří Base64url zakódovaných části:
```
HEADER.PAYLOAD.SIGNATURE

Header: {"alg": "HS256", "typ": "JWT"}
Payload: {"sub": "<uuid>", "role": "user", "iat": 1700000000, "exp": 1700086400}
Signature: HMAC-SHA256(header + "." + payload, JWT_SECRET)
```

Server ověřuje podpis pomocí `JWT_SECRET`. Pokud klient token jakkoli změní,
podpis se neshoduje a server token odmítne.

### Double validation (AuthMiddleware)
Každý chráněný požadavek prochází dvěma kontrolami:
1. **Kryptografické ověření** — JWT podpis musí být platný
2. **Databázová validace** — token musí existovat jako aktivní session:
   `SELECT id FROM sessions WHERE token=? AND is_active=TRUE AND expires_at>NOW()`

Kombinace umožňuje okamžitou invalidaci tokenů při odhlášení nebo smazání účtu,
přestože JWT by kryptograficky zůstal platný do expirace.

---

## CORS (Cross-Origin Resource Sharing)

CorsMiddleware čte povolené domény z ENV proměnné `CORS_ALLOWED_ORIGINS`:
```
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
```

- Požadavky z nepovolených domén dostávají HTTP 403
- OPTIONS preflight požadavky jsou zpracovány s HTTP 204
- `Access-Control-Allow-Credentials: true` umožňuje odesílání cookies

---

## Rate Limiting

Ochrana před brute-force útoky a spamem:

| Endpoint | Limit | Okno | Chrání před |
|----------|-------|------|-------------|
| POST /api/login | 10 pokusů | 60 s | Brute-force hesel |
| POST /api/register | 5 pokusů | 300 s | Spam účtů |
| POST /api/friends/add | 20 požadavků | 60 s | Friend request spam |
| POST /api/messages/send | 120 zpráv | 60 s | Message flooding |

Implementace ukládá timestampy do temp souborů. Viz technický dluh.

---

## SQL Injection ochrana

Veškerý přístup k databázi používá PDO prepared statements:
```php
$stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

Uživatelský vstup je vždy předán jako parametr, nikdy interpolován do SQL řetězce.
Databáze sama zajistí bezpečné zacházení s hodnotami.

---

## RBAC (Role-Based Access Control)

Systém dvou rolí: `admin` a `user`.

Každý endpoint explicitně kontroluje roli:
```php
// Příklad kontroly v AdminController:
private function checkAdmin() {
    $currentUser = AuthMiddleware::check();
    if ($currentUser->role !== 'admin') {
        // vrátí HTTP 403
    }
}
```

Speciální pravidla:
- Admin nemůže být přidán jako přítel (není vyhledatelný)
- Nelze smazat posledního administrátora systému
- Admin nemůže smazat sám sebe

---

## Technický dluh (vědomé kompromisy)

### TD-01: Sessions v databázi (porušení stateless REST)
REST API by mělo být stateless. Tabulka sessions tento princip porušuje.
Kompromis byl přijat záměrně — bez ní by nebylo možné token okamžitě invalidovat.

**Doporučení:** Redis blacklist s TTL — klíč je hash tokenu, automaticky vyprší.

### TD-02: Dlouhá expirace JWT bez refresh tokenů
Token platí 24 hodin. Kompromitovaný token je zneužitelný celý den.

**Doporučení:** Sliding session — access_token (5–15 min) + refresh_token (7 dní).

### TD-03: JWT_SECRET v docker-compose.yml
Secret je součástí repozitáře. V produkci je to bezpečnostní riziko.

**Doporučení:** AWS Secrets Manager, HashiCorp Vault nebo alespoň .env soubor mimo repozitář.

### TD-04: Souborový Rate Limiting
RateLimitMiddleware ukládá data do `/tmp`. Nefunguje při horizontálním škálování.

**Doporučení:** Redis — `INCR` + `EXPIRE` — atomická operace sdílená napříč instancemi.

---

## Chybové odpovědi — information disclosure

Globální exception handler v `index.php` zachytí všechny neošetřené výjimky:
```php
set_exception_handler(function (Throwable $e): void {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    ApiResponse::error('internal_error', 'Interní chyba serveru.', 500);
});
```

Uživatel dostane generickou zprávu. Stack trace, SQL dotazy ani interní detaily
nikdy neproniknou do HTTP odpovědi.
