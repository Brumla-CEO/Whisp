# Bezpečnostní model

## Autentizace
Whisp používá kombinaci:
- JWT (stateless identita + role)
- Sessions tabulku (server-side „allow-list“ tokenů)

### JWT payload
`JWTService::generate($userId, $role)`:
- `sub`: userId (UUID)
- `role`: role name (admin/user)
- `iat`, `exp` (24h)

### Session allow-list
`AuthMiddleware::check()` kromě validního JWT vyžaduje:
- token existuje v `sessions`
- `is_active = TRUE`

To umožňuje „odhlášení“ i pro JWT (které by jinak bylo stateless).

## Autorizace
- většina endpointů vyžaduje `AuthMiddleware::check()`
- admin endpointy volají interní `checkAdmin()` (na základě role)

Doporučení:
- oddělit autorizaci do middleware a nepouštět admin kontrolu až v controlleru.

## CORS
Aktuální stav:
- `backend/public/index.php` nastavuje `Access-Control-Allow-Origin: *`
- `backend/src/Router.php` nastavuje `Access-Control-Allow-Origin: <HTTP_ORIGIN>` a `Allow-Credentials: true`

To je nekonzistentní a v kombinaci může být nebezpečné (viz issue).

## Ukládání tajemství
`JWTService` má secret hardcoded ve zdrojáku.
- v dev to „funguje“
- v produkci je to kritické riziko

Doporučení:
- secret přes `.env` / Docker env
- rotace secretu + invalidace sessions

## WebSocket token v query stringu
Frontend posílá JWT jako query param: `ws://.../?token=...`.

Rizika:
- může skončit v access logu serveru nebo proxy
- může být zachycen v nástrojích, které logují URL

Doporučení:
- použít WS subprotocol (Sec-WebSocket-Protocol)
- nebo první message `{"type":"auth","token":"..."}` po připojení

## Validace vstupu
Většina endpointů dělá kontrolu `isset($data->field)`. Chybí:
- sanitizace délky / formátu
- validace emailu
- limitování velikosti zprávy
- ochrana proti brute force loginu

## Rate limiting
Není implementovaný. Minimální návrh:
- login/register: per-IP limit
- search: per-user limit
- send message: per-room limit (anti-spam)

## Logování a audit
`activity_logs` se používá pro některé operace (např. update profilu).
Doporučení:
- logovat i bezpečnostní události (failed login, invalid token, admin actions)

