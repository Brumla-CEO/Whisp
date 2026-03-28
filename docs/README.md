# Dokumentace projektu Whisp

Tato složka obsahuje kompletní projektovou dokumentaci aplikace **Whisp – Live Chat Application**.
Dokumentace je strukturována tak, aby pokryla všechny fáze SDLC (Software Development Life Cycle)
a zároveň sloužila jako technická reference pro vývojáře.

---

## Obsah dokumentace

| Soubor | Popis |
|--------|-------|
| [01_project_overview.md](01_project_overview.md) | Přehled projektu, cíle, technologický stack, MVP |
| [02_requirements.md](02_requirements.md) | Funkční a nefunkční požadavky (FR + NFR) |
| [03_architecture.md](03_architecture.md) | Systémová architektura, komponenty, runtime toky |
| [04_backend_design.md](04_backend_design.md) | Backend design: Router, Controllers, Models, Middleware |
| [05_frontend_design.md](05_frontend_design.md) | Frontend design: React komponenty, AuthContext, WebSocket |
| [06_database_design.md](06_database_design.md) | Databázový návrh: 9 tabulek, ER vztahy, indexy |
| [07_api_specification.md](07_api_specification.md) | Kompletní REST API specifikace (25+ endpointů) |
| [08_realtime_architecture.md](08_realtime_architecture.md) | WebSocket architektura, event model, presence logika |
| [09_security_model.md](09_security_model.md) | Bezpečnostní model, JWT, CORS, Rate Limiting, technický dluh |
| [10_methodology.md](10_methodology.md) | Metodika vývoje: SDLC, Scrum, sprinty, Git workflow |
| [11_backlog.md](11_backlog.md) | Produktový backlog — epiky a user stories |
| [12_sprint_progress.md](12_sprint_progress.md) | Průběh sprintů, časová osa, výstupy |
| [13_dev_guide.md](13_dev_guide.md) | Developer Guide — spuštění, debugging, přidání endpointu |
| [14_user_guide.md](14_user_guide.md) | User Guide — použití aplikace pro koncového uživatele |
| [15_deployment.md](15_deployment.md) | Deployment guide — Docker, produkční nasazení |

---

## Jak číst dokumentaci

- Pro **obhajobu a hodnocení** začněte od `01_project_overview.md` → `02_requirements.md` → `10_methodology.md`
- Pro **technické pochopení** architektury čtěte `03_architecture.md` → `04_backend_design.md` → `08_realtime_architecture.md`
- Pro **spuštění a vývoj** přejděte přímo na `13_dev_guide.md`
- Pro **použití aplikace** viz `14_user_guide.md`

---

## Rychlý přehled projektu

- **Technologie:** PHP 8.2 + React 19 + PostgreSQL 15 + WebSocket (Ratchet)
- **Nasazení:** Docker Compose (4 kontejnery)
- **Testy:** 49 PHPUnit + 5 Vitest
- **CI/CD:** GitHub Actions
- **Autor:** Bruno Vašíček, I4C, SPŠE Ostrava, 2025/2026
