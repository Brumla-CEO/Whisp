# 🧠 High-level analýza — Live Chat

Tento dokument navazuje na [Úvod](../intro/introduction.md) a představuje **vysokoúrovňový návrh (high-level analysis)** projektu *Whisp – Live Chat Application*.  
Cílem je popsat hlavní funkcionality, architektonický přístup, bezpečnostní zásady, způsob testování a plán vývoje v rámci metodiky **SDLC** a přístupu **Agile / Scrum**.

---

## 🎯 Cíl projektu a hlavní funkcionality

Cílem projektu je navrhnout a realizovat moderní webovou aplikaci umožňující **komunikaci v reálném čase** s důrazem na bezpečnost, škálovatelnost a udržovatelnost.  
Uživatelé mohou:

- registrovat a spravovat své účty,  
- vytvářet a spravovat chatovací místnosti,  
- komunikovat v reálném čase (soukromě i skupinově),  
- upravovat profil a sledovat historii konverzací,  
- využívat administrátorské rozhraní pro správu uživatelů a aktivit.

Aplikace má být připravena pro týmovou spolupráci, následné rozšiřování a případnou integraci dalších funkcí (notifikace, reakce, tmavý/světlý režim).

---

## ⚙️ Architektura a technologický rámec

Architektura projektu je navržena jako **vícevrstvá webová aplikace** složená ze tří hlavních částí:

### Frontend
- Realizovaný jako **Single Page Application (SPA)**.  
- Zajišťuje interakci s uživatelem a komunikaci s backendem.  
- Systém používá vlastní routing a umožňuje responzivní zobrazení.  
- Data jsou načítána přes REST API a aktualizována v reálném čase pomocí WebSocketů.

### Backend
- Postaven na **objektově orientovaném PHP** s důrazem na čitelnost a rozšiřitelnost.  
- Zajišťuje aplikační logiku, správu dat, autentizaci, autorizaci a API komunikaci.  
- Odděluje jednotlivé odpovědnosti (kontrolery, služby, úložiště, middleware).  

### Databázová a komunikační vrstva
- **Databáze**: transakční relační systém (PostgreSQL).  
- **WebSocket server**: zajišťuje přenos zpráv a notifikací v reálném čase.  
- Komunikace probíhá přes **HTTP protokol (REST API)** a **WebSockety**.  

Tento návrh podporuje rozšiřování (např. přidání dalších služeb nebo modulů) a refaktoring bez narušení základní struktury.

---

## 🔐 Autentizace a autorizace

Aplikace využívá princip **Role-Based Access Control (RBAC)**, který definuje minimálně dvě role:

- **Uživatel** – základní oprávnění pro komunikaci a správu vlastního profilu,  
- **Administrátor** – rozšířená práva pro správu uživatelů, aktivit a systémových dat.

Proces autentizace zajišťuje bezpečné ověření identity uživatele.  
Hesla jsou ukládána v bezpečném formátu, který znemožňuje jejich přímé zpětné získání.  
Autorizace probíhá na úrovni aplikační logiky a kontroluje přístup k jednotlivým funkcím.

Aplikace klade důraz na prevenci typických útoků (SQL Injection, XSS, CSRF) a správné nakládání s uživatelskými údaji.

---

## 🧩 Databázový přehled

Systém bude využívat **relační SQL databázi s podporou transakcí**.  
Na této úrovni je potřeba zajistit konzistenci dat, referenční integritu a možnost efektivního vyhledávání.

Detailní návrh databázového modelu (ER diagram) bude součástí další fáze 

---

## 🔒 Bezpečnostní principy

Projekt počítá s implementací následujících opatření:

- Šifrovaná komunikace pomocí **TLS** v produkčním prostředí,  
- Bezpečné uchovávání uživatelských hesel,  
- Validace vstupů a ochrana proti typickým útokům,  
- Kontrola přístupových práv podle role uživatele,  
- Ochrana citlivých dat a auditní záznamy uživatelských aktivit.

Bezpečnostní opatření budou průběžně revidována v rámci testování a nasazení.

---

## 🧪 Testování a zajištění kvality

Testování bude probíhat v několika úrovních:

- **Manuální testy** – simulace reálného chování uživatele (registrace, login, odeslání zprávy, blokace účtu).  
- **Unit testy** – testování funkční logiky jednotlivých komponent.  
- **Continuous Integration (CI)** – automatizované spuštění testů při každé změně v repozitáři.  

Součástí procesu bude také **Quality Assurance (QA)** a případně **statická analýza kódu**.  
Cílem je zajistit stabilitu projektu a zabránit přijetí neúspěšných buildů do hlavní větve.

---

## 🚀 Nasazení a provoz

Pro vývoj a demonstrační účely bude aplikace provozována pomocí **Dockeru**.  
Backend, frontend a databáze poběží jako samostatné služby spravované přes `docker-compose`.  

Tento přístup umožní:
- snadné spuštění projektu v jakémkoli prostředí,  
- oddělení vývojového a produkčního prostředí,  
- přípravu na pozdější integraci s CI/CD procesy.  

---

## 🔄 SDLC cyklus a iterativní vývoj

Projekt se vyvíjí podle metodiky **SDLC (Software Development Life Cycle)**,  
která zahrnuje fáze:

1. **Analýza** – identifikace požadavků a cílů,  
2. **Návrh** – příprava architektury a modelů,  
3. **Implementace** – vývoj backendu, frontendu a websocket komunikace,  
4. **Testování** – validace funkčnosti a bezpečnosti,  
5. **Nasazení** – demonstrační provoz v Dockeru,  
6. **Údržba a rozšiřování** – iterativní přidávání nových funkcí.

Tyto fáze probíhají **opakovaně v krátkých iteracích (Scrum sprintech)**, což umožňuje plynulý vývoj a flexibilní reakci na nové požadavky.

---

## 📦 Výstupy projektu

- funkční webová aplikace (frontend + backend + databáze),  
- kompletní dokumentace všech fází SDLC,  
- testovací scénáře a výsledky,  
- **Developers Guide** popisující proces:
  - spuštění projektu,  
  - build,  
  - vývoj a nasazení,  
  - přidávání funkcí a práci s Gitem,  
- prezentace pro obhajobu projektu.  

---

## 🧭 Shrnutí

Projekt **Whisp – Live Chat Application** je koncipován jako moderní, bezpečná a rozšiřitelná aplikace.  
Přináší reálný pohled na proces vývoje softwaru v prostředí týmové spolupráce,  
využívá standardní metodiky (SDLC + Agile Scrum) a nástroje běžné v praxi (Git, CI, Code Review).
