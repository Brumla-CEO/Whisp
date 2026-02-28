# issue.md – audit chyb a technického dluhu

Tento dokument je **technický audit** projektu Whisp. Každý bod obsahuje:
- **Evidence** (konkrétní soubory)
- **Problém**
- **Riziko**
- **Doporučené řešení**
- **Priorita** (P0 kritické → P2 zlepšení)

---

## 1) Hardcoded JWT secret (P0)

**Evidence**
- `backend/src/Services/JWTService.php`

**Problém**
Secret klíč pro podpis JWT je uložený ve zdrojáku.

**Riziko**
- únik repa = kompromitace všech tokenů
- nelze rozumně rotovat klíč bez redeploy a invalidace

**Doporučené řešení**
- přesun do ENV (`JWT_SECRET`)
- v Dockeru definovat v `docker-compose.yml` env
- zavést rotaci (support více klíčů po dobu migrace)

---

## 2) WebSocket autentizace přes query param token (P0)

**Evidence**
- `frontend/src/App.jsx` (`ws://...:8080?token=...`)
- `backend/src/Sockets/ChatSocket.php` (čte query param `token`)

**Problém**
Token je součástí URL.

**Riziko**
- token se může propsat do access logů / proxy logů / historických záznamů
- token se může omylem sdílet (např. screenshot konzole)

**Doporučené řešení**
- použít `Sec-WebSocket-Protocol` pro předání tokenu
- nebo připojit bez tokenu a poslat první message `{type:"auth", token:"..."}`

---

## 3) Nekonzistentní CORS (P0)

**Evidence**
- `backend/public/index.php`:
  - `Access-Control-Allow-Origin: *`
- `backend/src/Router.php`:
  - `Access-Control-Allow-Origin: <HTTP_ORIGIN>`
  - `Access-Control-Allow-Credentials: true`

**Problém**
Dvě vrstvy nastavují CORS rozdílně. Navíc kombinace wildcard + credentials je špatně.

**Riziko**
- otevření API pro nechtěné origins
- obtížné debugování („někdy to funguje, někdy ne“)

**Doporučené řešení**
- mít CORS v jednom místě (např. middleware)
- explicitní allowlist (např. `http://localhost:5173`)
- v produkci restrikce na domény

---

## 4) Sessions: chybí kontrola expirace (P0)

**Evidence**
- `backend/src/Middleware/AuthMiddleware.php`:
  - kontroluje pouze `token` + `is_active`
- DB: `sessions.expires_at` existuje

**Problém**
I po expiraci JWT může zůstat session aktivní (nebo naopak) – logika není sjednocená.

**Riziko**
- nekonzistentní auth
- potenciálně „věčné“ sessions (pokud exp v JWT nebude vynucen)

**Doporučené řešení**
- v middleware ověřit i `expires_at > now()`
- pravidelný cleanup job (cron) pro expired sessions
- zvážit refresh token pattern

---

## 5) SQL v controllerech (P1)

**Evidence**
- `backend/src/Controllers/AuthController.php`
- `backend/src/Controllers/FriendController.php`
- `backend/src/Controllers/AdminController.php`

**Problém**
Controllers jsou mix HTTP logiky a DB logiky.

**Riziko**
- tight coupling vrstev
- horší testovatelnost
- duplicita query patterns

**Doporučené řešení**
- přesunout SQL do Model/Repository vrstvy
- v controlleru řešit jen validaci + orchestraci

---

## 6) Validace vstupů je minimální (P1)

**Evidence**
- většina controllerů používá `isset($data->field)` bez formátové validace

**Problém**
Chybí centrální validační vrstva (např. DTO/Validator).

**Riziko**
- nekonzistentní chování endpointů
- snadné DoS přes velké payloady (např. message content)
- nekvalitní data v DB

**Doporučené řešení**
- zavést jednoduchý Validator utility
- validovat: email format, length constraints, UUID format, numeric IDs
- limitovat `content` velikost

---

## 7) Chybí rate limiting (P1)

**Evidence**
- žádný middleware pro rate limit

**Riziko**
- brute force login
- spam messages/search
- DoS

**Doporučené řešení**
- jednoduchý per-IP/per-user limit (v DB nebo in-memory pro dev)
- v produkci ideálně reverse proxy limit (Nginx) + aplikace (defense in depth)

---

## 8) Error kontrakt není sjednocený (P2)

**Evidence**
- různé struktury: někde `{"message":...}`, jinde `{"message":..., "data":...}`

**Riziko**
- FE musí dělat výjimky
- horší debuggování

**Doporučené řešení**
- sjednotit strukturu:
  - success: `{data, meta}`
  - error: `{error:{message, code, details}}`

---

## 9) WS server: in-memory state bez persistence (P2)

**Evidence**
- `ChatSocket` drží `userConnections` a `connMeta` v paměti

**Riziko**
- restart WS = ztráta presence
- škálování na více instancí složité

**Doporučené řešení**
- pro produkci Redis presence + pub/sub
- pro školní projekt stačí zdokumentovat jako limit

---

## 10) Multi-step operace bez transakcí (P2)

**Evidence**
- operace typu „create group + memberships“ (v modelu) typicky vyžadují více kroků

**Riziko**
- částečný zápis při chybě
- nekonzistentní data

**Doporučené řešení**
- použít `BEGIN/COMMIT/ROLLBACK` pro multi-step zápisy

---

## 11) Friendships – symetrie dvojic (P2)

**Evidence**
- DB má UNIQUE(requester_id, addressee_id), ale neřeší A-B vs B-A.

**Riziko**
- duplikace vztahů (request v opačném směru)

**Doporučené řešení**
- normalizovat ukládání (menší UUID vždy jako left)
- nebo přidat constraint, který hlídá symetrii

---

## 12) Docker “misuse” pro vývoj (P2)

**Evidence**
- `docker-compose.yml` staví FE/BE/WS přes Dockerfile (OK), ale pro vývoj to někdy komplikuje debug.

**Riziko**
- složitější onboarding bez dev guide
- horší hot reload při špatném nastavení volumes

**Doporučené řešení**
- dokumentace (dev guide) + případně „dev“ compose profil
- v prod by se stejně buildoval image bez bind mountů

---

## Doporučený plán oprav (roadmap)
- **P0 (security):** JWT secret -> ENV, WS auth bez query tokenu, sjednocení CORS, session expiry check
- **P1 (stabilita):** validace vstupů, rate limiting, refactor SQL z controllerů
- **P2 (quality):** transakce, sjednocení error kontraktu, optimalizace indexů, škálování WS

