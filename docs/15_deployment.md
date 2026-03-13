# Deployment

Whisp je aktuálně nastavený primárně pro **vývojové nasazení** přes Docker Compose.

## Lokální „deployment“ (demo)
```bash
docker compose up --build -d
```

## Produkční poznámky (co by bylo potřeba)
Pro reálný deployment je vhodné:
1. **Reverse proxy (Nginx)** před FE/API/WS
2. **TLS** (HTTPS/WSS)
3. JWT secret přes ENV + rotace
4. Přechod z PHP built-in serveru na FPM + Nginx nebo Apache
5. Oddělené DB credentials, žádné default heslo
6. Rate limiting a audit logování
7. Monitoring (health checks, log shipping)

## WS škálování
Ratchet instance je single-process a drží connections v paměti.
Pro horizontální škálování:
- sticky sessions (L4)
- pub/sub (Redis) pro broadcast
- centralizovaná presence



## Ověření po spuštění

Po startu aplikace je vhodné provést rychlé ověření:

```bash
php backend/tests/validator_smoke_test.php
./tests/api_smoke_test.sh
```
