# 05 – Frontend Design (React 19)

## Přehled

Frontend je Single Page Application (SPA) postavená v React 19 s build nástrojem Vite 7.
SPA znamená, že prohlížeč načte stránku jednou a veškerá navigace probíhá dynamicky
bez obnovení stránky. Výsledkem je plynulý zážitek podobný nativní desktopové aplikaci.

---

## Struktura komponent

```
main.jsx
└── AuthProvider (AuthContext)
    └── App.jsx (WebSocket logika + Event Bus + routing)
        ├── Login.jsx / Register.jsx       (nepřihlášený stav)
        ├── AdminPanel.jsx                 (přihlášen jako admin)
        └── Hlavní UI (přihlášen jako user)
            ├── UserList.jsx               (levý sidebar)
            │   └── CreateGroupModal.jsx   (modal pro vytvoření skupiny)
            ├── ChatWindow.jsx             (hlavní chat oblast)
            ├── ProfileSetup.jsx           (nastavení profilu)
            ├── FriendManager.jsx          (správce přátel)
            ├── UserProfileModal.jsx       (profil uživatele)
            ├── GroupDetailsModal.jsx      (detail skupiny)
            └── AppAlerts.jsx              (toast notifikace — vždy renderováno)
```

---

## AuthContext (`Context/AuthContext.jsx`)

`AuthContext` je centrální místo pro správu přihlašovacího stavu.
Díky React Context API jsou data dostupná všem komponentám bez prop drillingu.

### Co poskytuje
- `user` — profil přihlášeného uživatele (id, username, role, avatar, bio, status)
- `api` — Axios instance s automaticky přidávaným JWT tokenem
- `loading` — příznak inicializace (do ověření tokenu při načtení stránky)
- `login(email, password)` — POST /api/login, uložení tokenu, setUser
- `register(username, email, password)` — POST /api/register
- `logout()` — POST /api/logout, vyčištění localStorage, redirect na /

### Axios instance
```javascript
const api = axios.create({
    baseURL: `http://${window.location.hostname}:8000/api`
});
```

Dynamická URL zajišťuje, že aplikace funguje lokálně i v Dockeru bez přepisování.

### Request interceptor
```javascript
api.interceptors.request.use(config => {
    const token = localStorage.getItem('token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});
```

Každý API požadavek automaticky dostane JWT token bez nutnosti ho přidávat ručně.

### Response interceptor (401 handler)
Při obdržení HTTP 401 (smazaný účet, expirace session):
1. Odstraní token z localStorage
2. Nastaví user na null
3. Zobrazí alert uživateli
4. Přesměruje na login stránku

---

## App.jsx — WebSocket a routing

`App.jsx` je kořenová komponenta. Zodpovídá za:
- vytvoření WebSocket spojení po přihlášení
- správu WebSocket stavu (reconnect při odpojení)
- zpracování příchozích WS zpráv a jejich distribuci přes Event Bus
- routing mezi admin panelem, chat UI a profile setupem

### WebSocket flow
```javascript
// 1. Vytvoření spojení
const ws = new WebSocket(`ws://${hostname}:8080`);

// 2. Po otevření — odeslání auth zprávy
ws.onopen = () => {
    ws.send(JSON.stringify({ type: 'auth', token: localStorage.getItem('token') }));
};

// 3. Po auth_ok — socket je připraven
// 4. Distribuce příchozích zpráv přes window.dispatchEvent
```

---

## Komponenty — popis

### UserList.jsx (levý sidebar)
- Načítá seznam přátel a místností (`/api/friends` + `/api/rooms`)
- Merguje přátele s jejich DM historií (last_message, unread_count)
- Zobrazuje filtry: Vše / Online / Skupiny
- Vyhledávací pole pro filtrování zobrazených kontaktů
- Reaguje na WS eventy přes `friend-status-change` a `chat-update`
- Zobrazuje notification dot pro nepřečtené zprávy

### ChatWindow.jsx (hlavní chat)
- Zobrazuje historii zpráv z `/api/messages/history?room_id=...`
- Odesílá zprávy přes REST + broadcast přes WebSocket
- Podporuje editaci a soft delete vlastních zpráv
- Implementuje reply (citaci) zpráv s `reply_to_id`
- Sleduje aktivní místnost přes `presence:set_active_room` WS event
- Reaguje na živé WS eventy (`message:new`, `message_update`, `message_delete`)
- Zobrazuje jméno odesílatele ve skupinových chatech

### FriendManager.jsx (správce přátel)
- Záložka Hledat: vyhledávání uživatelů + odesílání žádostí
- Záložka Žádosti: příchozí žádosti s přijetím/odmítnutím
- Posílá WS eventy při friend actions (`friend_action`)

### GroupDetailsModal.jsx (detail skupiny)
- Zobrazuje seznam členů s online/offline indikátorem
- Admin skupiny může: upravit název/avatar, přidat člena, vyhodit člena
- Tlačítko pro opuštění skupiny
- Posílá WS eventy `group_kick` a `group_change`

### ProfileSetup.jsx (nastavení profilu)
- Formulář pro úpravu username, bio a avataru
- Avatar: generovaný (DiceBear seed) nebo vlastní URL
- Sekce smazání účtu s potvrzením zadáním username
- Po uložení posílá WS event `profile_change` + hard refresh

### AdminPanel.jsx (admin dashboard)
- Záložky: Přehled / Uživatelé / Místnosti / Logy
- HTTP polling každých 30 sekund (ne WebSocket)
- Zobrazuje statistiky, seznam uživatelů, místností a audit logy
- Akce: smazání uživatele, smazání místnosti

### AppAlerts.jsx (toast notifikace)
- Globální systém toast notifikací
- Naslouchá na `window` eventu `app-notify`
- Notifikace se zobrazují v zásobníku, automaticky mizí po timeoutu
- Podporuje typy: info, success, warning, error

---

## Event Bus

Aplikace používá nativní `window.dispatchEvent` pro komunikaci mezi komponentami.

| Event | Odesílatel | Příjemce | Kdy |
|-------|-----------|----------|-----|
| `chat-update` | App.jsx | ChatWindow.jsx | Nová/editovaná/smazaná zpráva |
| `friend-status-change` | App.jsx | UserList.jsx, FriendManager | Online/offline změna, refresh |
| `app-notify` | Kdekoli | AppAlerts.jsx | Toast notifikace |
| `friend-removed` | ChatWindow / Modal | App.jsx | Odebrání přítele |
| `friend-request-handled` | FriendManager | App.jsx | Přijetí/odmítnutí žádosti |
| `group-updated` | GroupDetailsModal | UserList.jsx | Změna skupiny |

---

## Technické volby

### Proč React Context místo Redux
Pro tento rozsah projektu je Context API dostatečné. Redux by přidával zbytečnou komplexitu.

### Proč Event Bus místo prop drilling
Mnoho komponent potřebuje reagovat na WS eventy, ale nejsou v přímé hierarchii.
Event Bus je jednoduchý a efektivní — každá komponenta se zaregistruje na eventy,
které ji zajímají, bez nutnosti předávat callbacky přes 3-4 úrovně komponent.

### Proč hard refresh po uložení profilu
Po změně username/avataru je potřeba aktualizovat AuthContext a všechny komponenty,
které zobrazují uživatelská data. Hard refresh je nejjednodušší a nejspolehlivější řešení.
