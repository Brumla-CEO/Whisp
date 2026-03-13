# 📌 Backlog projektu – Whisp (Live Chat Application)

Tento dokument obsahuje kompletní backlog projektu Whisp, rozdělený do epik, sprintů a user stories.  
Struktura odpovídá agilnímu řízení (Scrum), trunk-based developmentu a profesionálním sw standardům.

---

# 🏛 Epiky (hlavní oblasti projektu)

| Kód | Název epiky |
|-----|-------------|
| EP1 | Projektová příprava & infrastruktura |
| EP2 | Autentizace & uživatelé |
| EP3 | Realtime jádro (WebSocket) |
| EP4 | Chatová funkcionalita |
| EP5 | Admin & Auditing |
| EP6 | Frontend (SPA) |
| EP7 | Testování & kvalita |
| EP8 | Docker & DevOps |
| EP9 | Dokumentace & procesy |
| EP10 | Prezentace & obhajoba |

---

# 🧭 Sprint plán (10 sprintů)

## 🟢 Sprint 1 – Dev environment & repo setup
**Cíl:** Spustit PHP, React i Postgres v Dockeru + Hello World FE↔BE  
**Epiky:** EP1

| User Story | Popis |
|------------|-------|
| US1.1 | Inicializace repa + struktura složek |
| US1.2 | Výběr a konfigurace IDE |
| US1.3 | Docker Compose (PHP + Postgres) |
| US1.4 | Hello World endpoint + React fetch |

---

## 🟡 Sprint 2 – DB návrh + User CRUD
**Cíl:** Postavit databázi + základ REST pro users  
**Epiky:** EP2

| US | Popis |
|----|--------|
| US2.1 | Návrh DB (tabulky + vztahy) |
| US2.2 | SQL schema + migrations |
| US2.3 | User model + repository + REST endpoints |
| US2.4 | FE registrace + seznam uživatelů |

---

## 🟠 Sprint 3 – Auth MVP (hash, JWT)
**Cíl:** Registrace + login + JWT ochrana API  
**Epiky:** EP2

| US | Popis |
|----|-------|
| US3.1 | Hashování hesel |
| US3.2 | JWT generování + middleware |
| US3.3 | Frontend login flow |
| US3.4 | Refresh token (poznámky, volitelně) |

---

## 🔵 Sprint 4 – WebSocket server & handshake
**Cíl:** WS server který ověří uživatele  
**Epiky:** EP3

| US | Popis |
|----|-------|
| US4.1 | Spuštění WebSocket serveru |
| US4.2 | JWT ověřovací handshake |
| US4.3 | Echo + broadcast test |
| US4.4 | Logování WS sessions |

---

## 🟣 Sprint 5 – Základ chatu (rooms + messages)
**Cíl:** Ukládat a číst zprávy z DB, chat UI  
**Epiky:** EP4

| US | Popis |
|----|-------|
| US5.1 | CRUD rooms |
| US5.2 | Message persistence |
| US5.3 | Frontend chat komponenta |
| US5.4 | Online / typing status |

---

## 🔴 Sprint 6 – Admin & Activity log
**Cíl:** Audit + blokace uživatelů  
**Epiky:** EP5

| US | Popis |
|----|-------|
| US6.1 | Activity logs |
| US6.2 | Admin REST API |
| US6.3 | Admin UI |
| US6.4 | Privacy pravidla definována v docs |

---

## 🟤 Sprint 7 – Security Hardening
**Cíl:** Zabezpečit BE i FE  
**Epiky:** EP5, EP7

| US | Popis |
|----|-------|
| US7.1 | Prepared statements |
| US7.2 | Input validation + XSS ochrana |
| US7.3 | Rate limit (návrh + základní implementace) |
| US7.4 | Security checklist & test report |

---

## ⚫ Sprint 8 – Testování + CI
**Cíl:** Unit testy + GitHub pipeline  
**Epiky:** EP7

| US | Popis |
|----|-------|
| US8.1 | PHPUnit testy služeb |
| US8.2 | React Testing Library testy |
| US8.3 | GitHub Actions pipeline |


---

## 🟤 Sprint 9 – Docker 
**Cíl:** Docker deploy guide  
**Epiky:** EP8

| US | Popis |
|----|-------|
| US9.1 | Production Dockerfiles |
| US9.2 | Deployment guide |


---

## 🟩 Sprint 10 – Finalizace & obhajoba
**Cíl:** Maturita připravená ✔  
**Epiky:** EP9 & EP10

| US | Popis |
|----|-------|
| US10.1 | Final bugfix regression |
| US10.2 | Developers Guide |
| US10.3 | Prezentace + demo |
| US10.4 | Postmortem – „Co jsem se naučil“ |

