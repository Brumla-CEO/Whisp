# Whisp – Dokumentace integrace

## 1. Přehled

Tento dokument popisuje způsob integrace frontend aplikace s backendem projektu **Whisp**.

Aplikace používá dva komunikační mechanismy:

- **REST API (HTTP, JSON)** – pro CRUD operace
- **WebSocket (real-time komunikace)** – pro živou komunikaci (chat, notifikace)

Backend používá vlastní Router implementaci.  
WebSocket připojení je vytvářeno ve `App.jsx`.  
Frontend nepoužívá routing knihovnu – UI je řízeno React stavem.

---

# 2. REST API

## 2.1 Základní konfigurace

Všechny endpointy mají prefix:

/api/...


- Komunikace probíhá ve formátu **JSON**
- Server rozlišuje HTTP metody: `GET`, `POST`, `PUT`, `DELETE`
- Odpovědi mají `Content-Type: application/json`

---

## 2.2 Autentizace

Autentizace probíhá pomocí tokenu.

### Přihlášení

POST /api/login

POST /api/login


### Registrace

POST /api/register


### Odhlášení

POST /api/logout


### Aktuální uživatel

GET /api/user/me


Token je vyžadován pro chráněné endpointy.

---

## 2.3 Users


GET /api/users
PUT /api/users/{id}
DELETE /api/users/{id}


---

## 2.4 Friends


GET /api/friends
GET /api/friends/search
POST /api/friends/add
POST /api/friends/accept
POST /api/friends/reject
GET /api/friends/requests
POST /api/friends/remove


---

## 2.5 Chat & Messages


GET /api/rooms
POST /api/chat/open
GET /api/messages/history
POST /api/messages/send
POST /api/messages/update
POST /api/messages/delete


---

## 2.6 Groups


POST /api/groups/create
GET /api/groups/members
POST /api/groups/add-member
POST /api/groups/leave
POST /api/groups/update
POST /api/groups/kick


---

## 2.7 Notifications


GET /api/notifications
POST /api/chat/mark-read


---

## 2.8 Admin


GET /api/admin/dashboard
GET /api/admin/users
POST /api/admin/users/delete
GET /api/admin/rooms
POST /api/admin/rooms/delete
GET /api/admin/logs
GET /api/admin/users/detail
GET /api/admin/chat/history
POST /api/admin/create-admin
GET /api/admin/rooms/detail


---

# 3. WebSocket komunikace

WebSocket připojení je vytvářeno ve `App.jsx`.

## 3.1 Navázání spojení

Frontend:

1. Získá URL WebSocket serveru
2. Předá token jako query parametr
3. Naváže spojení

Princip:


ws://server?token=USER_TOKEN


Server při navázání spojení token ověří.

---

## 3.2 Podmínky vytvoření spojení

Spojení se vytváří pouze pokud:

- uživatel existuje
- uživatel není admin
- existuje platný token
- uživatel není již aktivně připojen

---

## 3.3 Životní cyklus připojení

WebSocket používá standardní lifecycle:

### onopen
- spojení je úspěšně navázáno
- zapisuje se log

### onmessage
- přijde zpráva
- provede se `JSON.parse`
- podle hodnoty `type` se provede konkrétní logika

### onerror
- zalogování chyby

### onclose
- uzavření spojení
- vyčištění stavu aplikace

---

## 3.4 Formát zpráv

Komunikace probíhá ve formátu JSON.

Frontend zpracovává zprávy podle datového typu (`type`).

Každý typ zprávy vyvolává jinou aktualizaci React state.

---

# 4. Řízení UI

Frontend nepoužívá knihovnu pro routing.

Navigace je řízena pomocí React state.

Princip:

- pokud `loading` → zobrazí se loading obrazovka
- pokud uživatel není přihlášen → login obrazovka
- pokud je přihlášen → hlavní aplikace

UI je tedy řízeno stavem aplikace, nikoliv URL routerem.

---

# 5. Typický scénář komunikace (Chat)

1. Uživatel se přihlásí:

POST /api/login


2. Backend vrátí token.

3. Frontend:
- uloží token
- naváže WebSocket spojení

4. Otevření chatu:

POST /api/chat/open


5. Načtení historie:

GET /api/messages/history


6. Odeslání zprávy:

POST /api/messages/send


7. Nové zprávy jsou doručovány přes WebSocket.

---

# 6. Chybové stavy

Možné chyby:

- neplatný nebo chybějící token
- uživatel neexistuje
- neoprávněný přístup
- selhání WebSocket spojení
- chyba při parsování JSON

Frontend reaguje změnou stavu aplikace nebo logováním chyby.

---

# 7. Požadavky na integraci

Pro úspěšnou integraci je nutné:

1. Implementovat volání REST endpointů dle specifikace.
2. Správně zpracovávat JSON odpovědi.
3. Ukládat a předávat autentizační token.
4. Navázat WebSocket připojení s tokenem jako query parametr.
5. Zpracovávat zprávy podle jejich `type`.
6. Řídit UI podle React state.

---

# 8. Shrnutí

Projekt Whisp používá:

- REST API pro aplikační operace
- WebSocket pro real-time komunikaci
- Stavově řízené UI bez routing knihovny

Integrace vyžaduje správnou práci s tokenem, JSON komunikaci a správné řízení WebSocket lifecycle.
