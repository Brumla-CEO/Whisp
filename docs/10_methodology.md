# 10 – Metodika vývoje

## Přehled

Projekt byl vyvíjen podle principů **SDLC (Software Development Life Cycle)**
s agilním přístupem inspirovaným metodikou **Scrum**. Vývoj byl rozdělen do
10 iterativních sprintů, každý se stanoveným cílem a výstupy.

---

## SDLC a DevOps Infinity Loop

SDLC (Software Development Life Cycle) je strukturovaný proces vývoje softwaru,
který zahrnuje všechny fáze od analýzy požadavků po nasazení a údržbu.
V projektu Whisp byly realizovány tyto fáze:

1. **Analýza požadavků** — funkční a nefunkční požadavky, MVP definice
2. **Návrh** — databázové schéma, systémová architektura, UI wireframy
3. **Implementace** — backend, frontend, WebSocket server
4. **Testování** — unit testy (PHPUnit, Vitest), manuální testování
5. **Nasazení** — Docker Compose, GitHub Actions CI/CD
6. **Údržba** — technický dluh, refaktoring, dokumentace

DevOps Infinity Loop (symbol ležaté osmičky) znázorňuje, že tyto fáze nejsou
lineární, ale cyklické — každý sprint projde celým cyklem.

---

## Proč agilní přístup (Scrum)

Scrum byl zvolen, protože:
- Umožňuje rychlou zpětnou vazbu a reakci na změny
- Krátké iterace (sprinty) udržují projekt zaměřený na konkrétní cíle
- Backlog jako jediný zdroj práce zabraňuje scopu creep
- Je vhodný pro single-developer projekt s vyvíjejícími se požadavky

Agile neznamená "hodně dokumentace" — znamená schopnost reagovat na změnu.

---

## Sprint struktura

Každý sprint trvá 2–3 týdny a zahrnuje:

- **Sprint Goal** — konkrétní cíl, který musí být dosažen
- **Sprint Scope** — vybrané user stories z backlogu
- **Demo/Review** — ověření výstupu (screenshoty, funkční demo)
- **Retrospektiva** — co šlo dobře, co příště jinak

---

## Přehled 10 sprintů

| Sprint | Název | Hlavní výstupy |
|--------|-------|----------------|
| Sprint 1 | Dev environment & repo | Docker Compose, Hello World API |
| Sprint 2 | DB návrh + User CRUD | init.sql, User model, základní endpointy |
| Sprint 3 | Auth MVP (JWT) | Login, Register, JWT middleware |
| Sprint 4 | WebSocket server | Ratchet bootstrap, JWT auth handshake |
| Sprint 5 | Chat (rooms + messages) | Místnosti, zprávy, ChatWindow UI |
| Sprint 6 | Admin & Activity log | Admin panel, audit logy |
| Sprint 7 | Security Hardening | Rate Limiting, CORS, validace vstupů |
| Sprint 8 | Testování + CI | PHPUnit 49 testů, Vitest 5 testů, GitHub Actions |
| Sprint 9 | Docker & DevOps | Production Dockerfiles, deployment guide |
| Sprint 10 | Finalizace & obhajoba | Dokumentace, bugfix, prezentace |

---

## Správa verzí — Trunk-Based Development

Projekt používá **Trunk-Based Development** na GitHubu:
- Hlavní větev `main` je vždy ve spustitelném stavu
- Nové funkce se vyvíjejí na krátkých feature větvích (`feat/nova-funkce`)
- Feature větve se mergují zpět do `main` po průchodu CI pipeline

Workflow:
```bash
git checkout main && git pull origin main
git checkout -b feat/nova-funkce
# vývoj a commity
git push origin feat/nova-funkce
# GitHub → Pull Request → CI pipeline → merge
git checkout main && git pull
git branch -d feat/nova-funkce
```

---

## Konvence commitů (Conventional Commits)

```
feat: přidání funkce reply na zprávy
fix: oprava CORS pro OPTIONS požadavky
docs: aktualizace API specifikace
test: přidání testů pro ChatValidator
refactor: sjednocení odpovědí AdminController na ApiResponse
chore: přidání .phpunit.result.cache do .gitignore
```

---

## User Stories

Každá funkce je popsána jako user story:
> Jako <typ uživatele> chci <funkci>, abych <přínos>.

Příklady:
- Jako **uživatel** chci posílat zprávy v reálném čase, abych mohl komunikovat bez obnovení stránky.
- Jako **admin skupiny** chci vyhodit člena, abych mohl spravovat skupinu.
- Jako **administrátor** chci vidět audit logy, abych měl přehled o aktivitě na platformě.

---

## Evidence progresu

- **Git commity** — nejsilnější důkaz průběžného vývoje
- **Sprint progress dokument** (`12_sprint_progress.md`) — co bylo hotové a kdy
- **CI/CD pipeline** — zelené/červené checkmarky pro každý commit na GitHubu
- **Backlog** (`11_backlog.md`) — epiky a user stories

---

## Reflexe metodiky

Agilní přístup se osvědčil. V průběhu vývoje se ukázalo, že některé původní
nápady (například pokročilé filtrování v admin panelu) jsou méně důležité,
než jsem si myslel. Díky iterativnímu přístupu jsem mohl přeskupit priority
a zaměřit se na funkce, které přinášejí největší hodnotu.

Největší výzva bylo udržení disciplíny v dokumentaci. Je lákavé psát kód
a dokumentaci odkládat. Řešením bylo dokumentovat paralelně s vývojem,
nikoli jako poslední krok.
