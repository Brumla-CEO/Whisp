                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         # Whisp -- Progress (říjen--únor)

Tento dokument popisuje postup vývoje aplikace Whisp od října do konce
února. Text je sestaven na základě reálně implementovaných částí systému
(backend router, REST API, WebSocket server, React frontend, Docker, DB
struktura).

Nejedná se o výpis commitů, ale o logickou rekonstrukci vývoje aplikace
podle skutečné architektury.

------------------------------------------------------------------------

# ŘÍJEN -- Návrh architektury a základ projektu

## Analýza cíle projektu

Cílem bylo vytvořit real-time chatovací aplikaci s těmito vlastnostmi:

-   autentizace uživatelů
-   správa přátel
-   privátní i skupinové konverzace
-   real-time zprávy přes WebSocket
-   administrační rozhraní
-   oddělený backend a frontend
-   kontejnerizace pomocí Docker

Bylo rozhodnuto kombinovat REST API a WebSocket komunikaci.

## Backend architektura

Navržena vlastní Router implementace místo frameworku. Oddělení logiky
do controllerů: AuthController, UserController, FriendController,
ChatController, AdminController, NotificationController.

Zvolena JSON komunikace a prefix /api/...

## Databázový návrh

Navrženy entity: users, friendships, rooms, messages, groups,
group_members, notifications.

Inicializace databáze přes init.sql v Docker prostředí.

------------------------------------------------------------------------

# LISTOPAD -- Autentizace a uživatelská vrstva

Implementováno:

-   POST /api/register
-   POST /api/login
-   POST /api/logout
-   GET /api/user/me

Zavedena práce s tokenem a kontrola autorizace.

Dále CRUD operace uživatelů:

-   GET /api/users
-   PUT /api/users/{id}
-   DELETE /api/users/{id}

Implementována správa přátel:

-   vyhledávání
-   žádosti
-   přijetí/odmítnutí
-   odstranění přátel

------------------------------------------------------------------------

# PROSINEC -- Chat a skupiny

Implementován chat systém:

-   GET /api/rooms
-   POST /api/chat/open
-   GET /api/messages/history
-   POST /api/messages/send
-   POST /api/messages/update
-   POST /api/messages/delete

Zavedeno ukládání historie zpráv do databáze.

Implementovány skupiny:

-   vytvoření
-   přidání člena
-   opuštění
-   kick
-   update skupiny

------------------------------------------------------------------------

# LEDEN -- WebSocket real-time vrstva

Implementováno persistentní WebSocket spojení.

Princip: - token předán jako query parametr - ověření při navázání
spojení - správa aktivních uživatelů

Lifecycle:

-   onopen
-   onmessage (JSON.parse a switch podle type)
-   onerror
-   onclose

Zabráněno duplicitnímu připojení stejného uživatele.

------------------------------------------------------------------------

# ÚNOR -- Stabilizace a Admin část

Implementováno:

-   GET /api/notifications
-   POST /api/chat/mark-read

Admin sekce:

-   dashboard
-   seznam uživatelů
-   mazání uživatelů
-   logy
-   historie chatu
-   detail místností
-   vytvoření admina

Frontend řízen React state (bez routing knihovny). Rozdělení UI podle
stavu: loading, login, hlavní aplikace.

------------------------------------------------------------------------

# Stav na konci února

Projekt obsahuje:

-   vlastní REST router
-   plnou autentizaci
-   správu přátel
-   privátní a skupinový chat
-   WebSocket real-time komunikaci
-   notifikace
-   admin rozhraní
-   Docker prostředí

Architektura je konzistentní a plně funkční.
