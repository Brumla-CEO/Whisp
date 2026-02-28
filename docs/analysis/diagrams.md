# 📊 Diagrams — Whisp (Live Chat Application)

## Úvod

Tento dokument obsahuje soubor architektonických, datových a behaviorálních diagramů systému **Whisp – Live Chat Application**.

Cílem dokumentu je vizuálně znázornit:

- celkový kontext systému,
- architekturu a rozdělení jednotlivých vrstev,
- způsob komunikace mezi frontendem, backendem, databází a WebSocket serverem,
- tok dat při klíčových operacích (autentizace, odesílání zpráv),
- strukturu databázového modelu,
- způsob nasazení aplikace v kontejnerizovaném prostředí.

Diagramy slouží jako doplněk k textové dokumentaci návrhu systému a poskytují přehled o:

- odpovědnostech jednotlivých komponent,
- komunikačních protokolech,
- závislostech mezi částmi systému,
- logice zpracování požadavků.

Použité diagramy vycházejí z principů:
- C4 modelu (Context a Container úroveň),
- UML (Sequence, State),
- ER modelování databází,
- návrhu distribuovaných webových aplikací.

Tento dokument se zaměřuje na vysokoúrovňový i středně detailní pohled na architekturu systému a popisuje, jak jednotlivé části spolupracují při běžném provozu aplikace.

---

## 1️⃣ System Context Diagram (C4 – Level 1)

### Účel
System Context diagram zobrazuje systém **Whisp** jako jeden celek (black-box) a jeho interakci s externími aktéry a službami.  
Cílem je rychle odpovědět na otázky: **kdo systém používá** a **na co je systém napojen**.

### Diagram

```mermaid
flowchart LR
    User[Uživatel<br>(Webový prohlížeč)]
    Admin[Administrátor<br>(Webový prohlížeč)]
    Whisp[Whisp<br>Live Chat Application]
    DB[(PostgreSQL Database)]
    WS[WebSocket Server<br>(Real-time messaging)]

    User -->|HTTPS / REST| Whisp
    Admin -->|HTTPS / REST| Whisp
    Whisp -->|SQL (transakce, dotazy)| DB
    Whisp <--> |WebSocket (eventy, broadcast)| WS

    ---

## 2️⃣ Container Diagram (C4 – Level 2)

### Účel

Container diagram rozděluje systém Whisp na hlavní běžící části (containers) a popisuje:

- jejich odpovědnosti,
- způsob komunikace,
- použité protokoly,
- tok dat mezi nimi.

V tomto kontextu "container" znamená samostatně běžící aplikační část (např. frontend, backend, databáze).

---

### Diagram

```mermaid
flowchart TB

    Browser[Browser<br>React SPA]

    Backend[PHP Backend API<br>Business Logic + Auth]

    Database[(PostgreSQL<br>Relational DB)]

    WebSocket[WebSocket Server<br>Real-time Messaging]

    Browser -->|HTTPS / REST (JSON)| Backend
    Backend -->|SQL (PDO, prepared statements)| Database
    Browser <--> |WebSocket Protocol| WebSocket
    Backend -->|Event Notification| WebSocket

    ---

## 3️⃣ Backend Component Diagram (vnitřní architektura)

### Účel

Tato část rozepisuje **vnitřní strukturu backendu (PHP API)** a popisuje, jak spolu jednotlivé komponenty komunikují při zpracování požadavku.

Cílem je ukázat:
- jak je request směrován (routing),
- kde probíhá validace a autorizace,
- kde je business logika,
- jak se přistupuje k databázi,
- jak se generuje odpověď.

---

### Diagram

```mermaid
flowchart LR
    Client[Client (React SPA)]
    Router[Router]
    Middleware[Middleware<br>(Auth / RBAC / CORS)]
    Controller[Controller]
    Service[Service Layer<br>(Business Logic)]
    Repository[Repository / DAO<br>(DB Access)]
    DB[(PostgreSQL)]
    WSNotify[WebSocket Notifier<br>(Event dispatch)]

    Client -->|HTTP REST (JSON)| Router
    Router --> Middleware
    Middleware -->|OK| Controller
    Controller --> Service
    Service --> Repository
    Repository -->|SQL| DB
    Service -->|event: new_message| WSNotify
    Controller -->|JSON Response| Client

    ---

## 4️⃣ Sequence Diagram — Login (Autentizace end-to-end)

### Účel

Tento sekvenční diagram popisuje kompletní tok přihlášení uživatele od odeslání formuláře ve frontendu až po získání autentizačního tokenu/session.

Cílem je ukázat:
- kdo s kým komunikuje,
- jaké kroky provádí backend,
- kde probíhá ověření hesla a práce s databází,
- co se vrací klientovi a jak je session/token dále používán.

---

### Diagram

```mermaid
sequenceDiagram
    autonumber
    participant U as User
    participant FE as Frontend (React SPA)
    participant API as Backend (PHP API)
    participant DB as PostgreSQL

    U->>FE: Vyplní email + heslo a odešle formulář
    FE->>API: POST /api/login (JSON: credentials)
    API->>API: Validace vstupu (formát, required)
    API->>DB: SELECT uživatel dle emailu
    DB-->>API: User record (password_hash, role, status)
    API->>API: password_verify(plain, password_hash)
    alt Přihlašovací údaje OK
        API->>API: Vytvoření tokenu/session (např. JWT / session_id)
        API-->>FE: 200 OK (token / session info)
        FE->>FE: Uložení tokenu (cookie/local storage dle návrhu)
        FE-->>U: Přesměrování do aplikace / načtení chatů
    else Chyba přihlášení
        API-->>FE: 401 Unauthorized (error message)
        FE-->>U: Zobrazení chyby
    end

    ---

## 5️⃣ Sequence Diagram — Odeslání zprávy (REST + WebSocket)

### Účel

Tento diagram popisuje, jak se v systému Whisp odesílá zpráva tak, aby:

- byla **trvale uložena** (persistována) do databáze,
- a zároveň byla **okamžitě doručena** ostatním uživatelům v reálném čase.

Záměrně kombinujeme dva komunikační kanály:
- **REST API** (pro persistenci a autoritativní validaci),
- **WebSocket** (pro real-time distribuci).

---

### Diagram

```mermaid
sequenceDiagram
    autonumber
    participant U1 as User1
    participant FE1 as Frontend1 (React SPA)
    participant API as Backend (PHP API)
    participant DB as PostgreSQL
    participant WS as WebSocket Server
    participant FE2 as Frontend2 (React SPA)
    participant U2 as User2

    U1->>FE1: Napíše zprávu a odešle
    FE1->>API: POST /api/messages (token + JSON: room_id, content)
    API->>API: Auth + RBAC kontrola
    API->>API: Validace vstupu (room_id, content)
    API->>DB: INSERT message (user_id, room_id, content)
    DB-->>API: OK + message_id + timestamp
    API->>WS: Emit event "new_message" (room_id + message payload)
    WS-->>FE2: Push "new_message" (room_id + payload)
    FE2-->>U2: UI zobrazí zprávu v místnosti
    API-->>FE1: 201 Created (message payload)
    FE1-->>U1: UI potvrzení / zobrazení odeslané zprávy

    ---

## 6️⃣ Databázový model (ER diagram) + vysvětlení vztahů a komunikace

### Účel

Tato část popisuje reálný databázový model systému **Whisp** (PostgreSQL) a vysvětluje:

- jak jsou data strukturována,
- jaké jsou vztahy mezi entitami,
- jak backend databázi používá při klíčových operacích (autentizace, membership, zprávy, notifikace, admin logy).

Databáze je autoritativní zdroj pravdy: všechny kritické změny stavu (zprávy, členství, sessions, notifikace) se nejdřív uloží do DB a teprve potom se propagují do real-time vrstvy.

---

### ER Diagram (odpovídá implementaci)

```mermaid
erDiagram

    ROLES {
        int id PK
        string name
        string description
    }

    USERS {
        uuid id PK
        string username
        string email
        string password_hash
        int role_id FK
        string avatar_url
        string bio
        string status
        timestamp created_at
        timestamp updated_at
    }

    SESSIONS {
        int id PK
        uuid user_id FK
        string token
        timestamp expires_at
        boolean is_active
        timestamp created_at
    }

    ROOMS {
        int id PK
        string name
        string type
        uuid owner_id FK
        string avatar_url
        timestamp created_at
        timestamp updated_at
    }

    ROOM_MEMBERSHIPS {
        int room_id FK
        uuid user_id FK
        string role
        timestamp joined_at
        boolean is_muted
    }

    MESSAGES {
        int id PK
        int room_id FK
        uuid sender_id FK
        text content
        int reply_to_id FK
        string type
        boolean is_edited
        boolean is_deleted
        timestamp created_at
        timestamp edited_at
    }

    FRIENDSHIPS {
        int id PK
        uuid requester_id FK
        uuid addressee_id FK
        string status
        timestamp created_at
        timestamp updated_at
    }

    ACTIVITY_LOGS {
        int id PK
        uuid user_id FK
        string action
        timestamp timestamp
        inet ip_address
    }

    NOTIFICATIONS {
        int id PK
        uuid user_id FK
        int room_id FK
        string type
        text content
        boolean is_read
        timestamp created_at
    }

    ROLES ||--o{ USERS : "role_id"
    USERS ||--o{ SESSIONS : "has"
    USERS ||--o{ ROOMS : "owns (owner_id)"
    USERS ||--o{ ROOM_MEMBERSHIPS : "member"
    ROOMS ||--o{ ROOM_MEMBERSHIPS : "has members"
    USERS ||--o{ MESSAGES : "sends"
    ROOMS ||--o{ MESSAGES : "contains"
    MESSAGES ||--o{ MESSAGES : "replies to (reply_to_id)"
    USERS ||--o{ FRIENDSHIPS : "requester"
    USERS ||--o{ FRIENDSHIPS : "addressee"
    USERS ||--o{ ACTIVITY_LOGS : "produces"
    USERS ||--o{ NOTIFICATIONS : "receives"
    ROOMS ||--o{ NOTIFICATIONS : "in room"

    ---

## 7️⃣ Deployment Diagram (Docker) + síťová komunikace mezi kontejnery

### Účel

Tato část popisuje, jak je Whisp nasazen v **Docker** prostředí a jak spolu jednotlivé služby komunikují na úrovni infrastruktury.

Cílem je vysvětlit:

- jaké kontejnery běží,
- jaký mají účel,
- přes jaké porty / sítě spolu komunikují,
- jak probíhá tok požadavků od klienta až k databázi a WebSocketům.

---

### Deployment Diagram

```mermaid
flowchart LR
    Client[Client<br>Web Browser]

    subgraph DockerHost[Docker Host]
        FE[Frontend Container<br>(Static/SPA)]
        API[Backend Container<br>(PHP API)]
        WS[WebSocket Container<br>(Real-time)]
        DB[(PostgreSQL Container)]
    end

    Client -->|HTTPS / HTTP| FE
    FE -->|REST (HTTP)| API
    FE <--> |WebSocket| WS
    API -->|SQL| DB
    API -->|Event notify| WS

    ---

## 8️⃣ State Diagram — Uživatelský status (offline / online)

### Účel

Tento stavový diagram popisuje životní cyklus hodnoty `users.status`, kterou systém používá pro zobrazení přítomnosti uživatele (presence).

V systému Whisp se používají pouze dva stavy:
- `offline`
- `online`

Změna stavu se typicky děje:
- při úspěšném přihlášení (offline → online),
- při odhlášení nebo odpojení (online → offline).

---

### Diagram

```mermaid
stateDiagram-v2
    [*] --> Offline
    Offline --> Online: Login / Connect WS
    Online --> Offline: Logout / Disconnect / Timeout

    ---

## 9️⃣ Data Flow Overview — Přehled toku dat systémem

### Účel

Tato část shrnuje **hlavní datové toky** v systému Whisp napříč všemi vrstvami a vysvětluje:

- odkud data přichází,
- kde se validují a autorizují,
- kde se ukládají (persistují),
- jak se distribuují v reálném čase.

Diagram slouží jako “mapa” komunikace a doplňuje detailní sekvenční diagramy.

---

### Diagram

```mermaid
flowchart LR
    U[Uživatel<br>(Browser)]
    FE[Frontend<br>(React SPA)]
    API[Backend<br>(PHP REST API)]
    DB[(PostgreSQL)]
    WS[WebSocket Server]

    U -->|UI interakce| FE
    FE -->|REST: JSON (HTTPS)| API
    API -->|SQL: CRUD + transakce| DB
    API -->|Event notify (new_message, notification, presence)| WS
    FE <--> |WebSocket: real-time events| WS
    WS -->|Push aktualizace UI| FE