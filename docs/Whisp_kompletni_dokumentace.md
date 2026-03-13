

---

# Soubor: README.md

# Whisp – Kompletní technická dokumentace (A–Z)

Tento archiv obsahuje rozsáhlou technickou dokumentaci projektu **Whisp** vytvořenou na základě analýzy zdrojového kódu v archivu `Whisp_final_cleanup.zip`. Dokumentace je koncipována jako podklad pro:

- obhajobu projektu,
- provozní a vývojářskou dokumentaci,
- vysvětlení architektury,
- detailní pochopení implementace frontendu, backendu, databáze, Dockeru a realtime vrstvy.

## Obsah archivované dokumentace

1. `01_system_a_architektura.md` – účel aplikace, hlavní funkce a systémová architektura
2. `02_backend_od_A_do_Z.md` – detail backendu, routeru, controllerů, modelů, middleware a service vrstvy
3. `03_frontend_od_A_do_Z.md` – detail frontendu, contextu, komponent, lokálního stavu a toků v UI
4. `04_realtime_websockety.md` – podrobný popis WebSocket serveru, eventů a synchronizace mezi klienty
5. `05_databaze_a_sql.md` – popis databázového schématu, relací, dotazů a datových pravidel
6. `06_docker_startup_a_provoz.md` – Docker Compose, start služeb, volumes, sítě, init SQL, seed admina
7. `07_bezpecnost_validace_testovani.md` – bezpečnostní logika, validace, sessions, JWT, CORS, testování
8. `08_sdlc_agile_scrum_a_obhajoba.md` – metodika SDLC, iterativní vývoj, backlog, sprinty a doporučený způsob prezentace
9. `09_appendix_endpointy_eventy_soubory.md` – soupis endpointů, eventů a odpovědností souborů

## Jak dokumentaci používat

### Pokud se připravuješ na obhajobu
Začni pořadím:
1. `01_system_a_architektura.md`
2. `05_databaze_a_sql.md`
3. `06_docker_startup_a_provoz.md`
4. `08_sdlc_agile_scrum_a_obhajoba.md`

### Pokud chceš chápat implementaci
Začni pořadím:
1. `02_backend_od_A_do_Z.md`
2. `03_frontend_od_A_do_Z.md`
3. `04_realtime_websockety.md`
4. `07_bezpecnost_validace_testovani.md`

### Pokud chceš projekt provozovat nebo testovat
Zaměř se na:
1. `06_docker_startup_a_provoz.md`
2. `07_bezpecnost_validace_testovani.md`
3. `09_appendix_endpointy_eventy_soubory.md`

## Poznámka ke stylu

Text je záměrně velmi podrobný. Cílem není krátká anotace, ale dokument, který vysvětlí **proč** jsou jednotlivé části navržené právě takto, **jak** spolu souvisí a **co přesně** se děje při běhu systému.


---

# Soubor: 01_system_a_architektura.md

# 1. Systém jako celek a architektura

## 1.1 Co je Whisp

Whisp je webová aplikace pro realtime komunikaci mezi uživateli. Na první pohled se může jevit jako „chat“, ale technicky je to systém složený z několika vrstev, které dohromady řeší pět hlavních problémů:

1. **Identita uživatele** – registrace, přihlášení, odhlášení, role, profil.
2. **Sociální vztahy** – přátelství, žádosti o přátelství, jejich přijetí nebo odmítnutí.
3. **Komunikační prostor** – soukromé konverzace a skupinové místnosti.
4. **Realtime synchronizace** – okamžité promítnutí změn druhému klientovi bez ručního refresh.
5. **Administrace a dohled** – přehled nad uživateli, místnostmi a aktivitami v systému.

Těžiště projektu je v tom, že tyto vrstvy spolu komunikují v reálném čase a musí zůstávat konzistentní: pokud uživatel odebere přítele, nemá se změnit jen jedna tabulka v databázi, ale musí se synchronně promítnout backend pravidla, websocket eventy i stav uživatelského rozhraní.

## 1.2 Vrstvy systému

### Frontend
Frontend je napsaný v Reactu a běží ve Vite development serveru. Starost frontendu je:
- vykreslit uživatelské rozhraní,
- držet lokální UI state,
- volat REST API,
- otevírat websocket spojení,
- reagovat na realtime eventy.

Frontend neřeší trvalá data ani bezpečnostní pravidla systému. Je klientem backendu a websocketu.

### Backend REST API
Backend je napsaný v PHP a funguje jako REST API vrstva. Zajišťuje:
- autentizaci a autorizaci,
- validaci vstupů,
- přístup do databáze přes modely,
- návrat dat ve formátu JSON,
- aplikační pravidla typu „DM lze otevřít jen mezi přáteli“.

### WebSocket server
WebSocket server běží jako samostatný proces v PHP pomocí Ratchet. Jeho úkolem není nahradit backend, ale doplnit ho. Pokud by systém spoléhal jen na REST, musel by frontend vše opakovaně dotazovat pollingem. WebSocket vrstva místo toho umožňuje serveru aktivně poslat klientům změnu hned v okamžiku, kdy nastane.

### Databáze
Databází je PostgreSQL. Uchovává:
- uživatele,
- role,
- session tokeny,
- přátelství,
- chat rooms,
- členství v místnostech,
- zprávy,
- notifikace,
- logy aktivit.

### Docker infrastruktura
Všechny služby jsou izolované v kontejnerech. To řeší opakovatelnost prostředí a umožňuje celý projekt spustit jedním compose souborem.

## 1.3 Jak spolu vrstvy komunikují

### HTTP/REST komunikace
Když uživatel například odešle zprávu, frontend:
1. vezme text z inputu,
2. pošle `POST /api/messages/send`,
3. backend zprávu validuje,
4. uloží ji do databáze,
5. vrátí potvrzení s uloženou zprávou,
6. frontend okamžitě renderuje novou zprávu.

### Realtime komunikace
Současně frontend přes websocket pošle event typu `message:new`, aby ostatní klienti ve stejné room dostali zprávu okamžitě bez dalšího HTTP dotazu. Tento dvojkrok je v projektu důležitý:
- **REST** = perzistence a pravidla,
- **WebSocket** = distribuce události ostatním připojeným klientům.

## 1.4 Proč je architektura rozdělena právě takto

Toto rozdělení není náhodné. Každá vrstva má jinou odpovědnost.

### Proč frontend neřeší pravidla přístupu
Frontend je nedůvěryhodné prostředí. Uživatel může JavaScript upravit, odeslat si vlastní request, použít Postman, nebo cokoliv odchytit v DevTools. Proto pravidla typu „uživatel smí otevřít DM jen s přítelem“ musí být v backendu.

### Proč backend neřeší realtime sám v rámci HTTP
HTTP request je krátkodobý a jednorázový. Pro průběžné synchronizace stavu mezi více klienty je vhodnější samostatný websocket proces.

### Proč jsou WebSockety samostatně
Realtime vrstva má jiný životní cyklus než REST API. REST request začne, zpracuje se a skončí. WebSocket spojení zůstává otevřené, drží metadata spojení a rozesílá události. Oddělení websocket procesu proto dává smysl jak architektonicky, tak provozně.

## 1.5 Přehled implementačních rozhodnutí

### React na frontendu
React byl zvolen kvůli komponentové architektuře a snadné práci se stavem aplikace. U chatu je zásadní, aby UI okamžitě reagovalo na změny. Komponentový model Reactu je pro to vhodný.

### PHP na backendu
Backend je psaný v čistém PHP bez velkého frameworku. To snižuje režii, ale zvyšuje nárok na disciplínu v architektuře. Proto jsou v projektu ručně zavedené vrstvy jako router, middleware, modely a validační třídy.

### PostgreSQL
PostgreSQL je vhodný pro relační model aplikace. Projekt potřebuje konzistentní vazby mezi uživateli, rooms, membershipy a zprávami. Relační databáze je pro tyto vztahy přirozenou volbou.

### Docker
Docker řeší opakovatelné prostředí a snadné spuštění. U školního i vývojářského projektu je klíčové, aby bylo možné celý systém rozběhnout bez ruční instalace PostgreSQL a složité konfigurace.

## 1.6 Co se v aplikaci děje po přihlášení

Po přihlášení se spustí několik vrstev logiky současně:

1. Backend vrátí JWT token a detail uživatele.
2. Frontend token uloží do localStorage.
3. Axios interceptor začne token přidávat do každého requestu.
4. `AuthContext` drží přihlášeného uživatele v paměti.
5. `App.jsx` otevře WebSocket spojení.
6. Po `onopen` pošle přes websocket autentizační zprávu.
7. WebSocket server spojení spáruje s uživatelem a nastaví jeho status online.
8. Frontend načte unread notifikace a pending friend requesty.

To znamená, že samotné „login successful“ ve skutečnosti není jediná akce, ale vstupní bod do celého navazujícího runtime stavu aplikace.

## 1.7 Hlavní runtime toky systému

### Tok A – uživatel se zaregistruje
- frontend odešle registrační data,
- backend validuje payload,
- backend zkontroluje unikátnost username a emailu,
- vytvoří uživatele, avatar a session,
- vrátí token,
- frontend nastaví přihlášeného uživatele.

### Tok B – uživatel otevře DM
- frontend vybere přítele v seznamu,
- backend zkontroluje, zda vztah existuje,
- najde nebo vytvoří DM room,
- vrátí `room_id`,
- frontend načte historii,
- websocket je připraven přijímat nové zprávy.

### Tok C – přijde nová zpráva
- klient odešle REST request,
- backend uloží zprávu,
- klient pošle websocket event,
- ostatní klienti dostanou `message:new`,
- pokud nebyli v aktivním chatu, vytvoří se notifikace.

### Tok D – odebrání přítele
- backend smaže nebo zneplatní přátelství,
- websocket pošle druhé straně event `friend_update/unfriended`,
- frontend obou uživatelů aktualizuje seznam přátel,
- pokud byl otevřen DM chat, UI jej zavře.

## 1.8 Proč aplikace kombinuje REST a WebSockety

Kombinace REST + WebSocket není náhoda, ale vědomé rozhodnutí:

- REST je vhodný pro spolehlivé zápisy a získání dat.
- WebSocket je vhodný pro push události a synchronizaci klientů.

Použít jen REST by vedlo k neustálému polling mechanismu. Použít jen WebSocket by naopak komplikovalo perzistenci, autorizaci a debuggování. Hybridní model je zde nejpraktičtější.

## 1.9 Jaká je role administrátora

Admin není samostatná aplikace, ale zvláštní provozní režim stejného systému. Z hlediska architektury je to výhodné, protože:
- správa uživatelů a rooms využívá stejné backendové jádro,
- role se rozhoduje přes `role_id` a jméno role,
- frontend po přihlášení podle role přepne na `AdminPanel`.

Tím je zajištěno, že administrace sdílí datový model s běžnou aplikací, ale zároveň má vlastní rozhraní a privilegované endpointy.

## 1.10 Shrnutí kapitoly

Whisp je vícevrstvý systém, ve kterém jsou jednotlivé části oddělené podle odpovědnosti:
- frontend = UI a lokální stav,
- backend = pravidla a API,
- websocket = realtime synchronizace,
- databáze = trvalá data,
- Docker = provozní obal.

Právě toto rozdělení je důvod, proč aplikace není jen „soubor PHP skriptů a React komponent“, ale skutečný softwarový systém se srozumitelnou architekturou.


---

# Soubor: 02_backend_od_A_do_Z.md

# 2. Backend od A do Z

## 2.1 Úloha backendu v systému

Backend projektu Whisp představuje aplikační vrstvu mezi frontendem a databází. Jeho úkolem není jen „vracet data“, ale vynucovat pravidla systému. To je zásadní rozdíl mezi prostou CRUD aplikací a skutečným backendem s obchodní logikou.

Backend proto řeší zejména:
- autentizaci uživatele,
- autorizaci k citlivým akcím,
- validaci vstupních dat,
- práci s databází,
- jednotné JSON odpovědi,
- logování aktivit,
- koordinaci s websocket vrstvou prostřednictvím sdíleného datového modelu.

## 2.2 Front controller: `public/index.php`

Každý HTTP požadavek vstupuje do aplikace přes `backend/public/index.php`. Tento soubor je extrémně důležitý, protože představuje bootstrap celé PHP aplikace.

### Co přesně dělá

1. načte autoloader z `vendor/autoload.php`,
2. zaregistruje globální exception handler,
3. zaregistruje globální error handler,
4. aplikuje `CorsMiddleware`,
5. vytvoří instanci routeru,
6. předá řízení `Router::handleRequest()`.

### Proč je to správně

Front controller centralizuje spuštění aplikace. Kdyby jednotlivé PHP soubory byly přístupné napřímo bez centrálního vstupu, bylo by obtížnější:
- jednotně nastavovat hlavičky,
- řešit chyby,
- přidat middleware,
- držet konzistentní bootstrap.

V projektu tedy front controller zajišťuje, že každá request cesta prochází stejnou inicializační sekvencí.

## 2.3 Router: `src/Router.php`

Router je vlastní implementace routování. Není použit frameworkový router, takže logika mapování URL a HTTP metod je psaná ručně.

### Odpovědnost routeru
Router má jediný hlavní úkol: mapovat kombinaci `URI + HTTP metoda` na správný controller a metodu.

Příklad:
- `POST /api/login` -> `AuthController::login()`
- `GET /api/friends` -> `FriendController::index()`
- `POST /api/messages/send` -> `ChatController::sendMessage()`

### Další odpovědnosti routeru
V projektu router navíc:
- nastavuje `Content-Type: application/json`,
- aplikuje rate limiting na vybrané endpointy,
- obsluhuje dynamické route jako `/api/users/{id}` pro update a delete,
- vrací 404 response, pokud endpoint neexistuje.

### Proč je router takto jednoduchý
Tohle řešení je vhodné pro projekt této velikosti. Není nutné zavádět plný framework, pokud se počet endpointů stále dá udržet přehledně v jednom routeru. Zároveň je výhoda, že při obhajobě je velmi snadné vysvětlit, co se děje: každý request je přímo dohledatelný v jednom souboru.

## 2.4 HTTP response helper: `src/Http/ApiResponse.php`

ApiResponse je pomocná třída pro jednotné vracení JSON odpovědí.

### Proč tato třída vznikla
Bez centralizace by controllery vracely JSON různými způsoby:
- někde ruční `echo json_encode`,
- někde jiný status,
- někde jiný klíč pro chybu.

ApiResponse sjednocuje chování a tím snižuje chaos.

### Typická použití
- `ApiResponse::success(...)`
- `ApiResponse::error(code, message, status)`

### Praktický přínos
Frontend může očekávat předvídatelnější strukturu odpovědí a backend se lépe udržuje.

## 2.5 Konfigurační vrstva: `src/Config/Database.php`

`Database.php` zajišťuje připojení k PostgreSQL přes PDO.

### Jak funguje
- čte `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` z prostředí,
- sestaví DSN řetězec,
- vytvoří PDO instanci,
- nastaví `ERRMODE_EXCEPTION`,
- nastaví defaultní fetch mode na asociativní pole.

### Proč PDO
PDO je v tomto projektu dobrá volba, protože:
- umí parametrizované dotazy,
- je součástí standardního PHP stacku,
- nevyžaduje ORM,
- je dobře přenosné a pochopitelné.

### Proč je to oddělené v samostatné třídě
Připojení k databázi je infrastrukturní odpovědnost, ne business logika. Pokud by se DSN skládal v každém controlleru, byla by to duplicita a chaos.

## 2.6 Middleware vrstva

Whisp má tři důležité middleware komponenty:

### `CorsMiddleware`
Řeší CORS hlavičky a preflight `OPTIONS` requesty.

#### Co dělá
- nastaví `Vary: Origin`,
- ověří `Origin` proti allowlistu z `CORS_ALLOWED_ORIGINS`,
- nastaví `Access-Control-Allow-Origin` pouze pro povolené originy,
- obslouží `OPTIONS` request a vrátí 204,
- odmítne nepovolený origin pro preflight.

#### Proč je to v middleware
CORS je transportní otázka. Nepatří do controllerů ani do routeru. Proto je správně centralizován před vlastní business logikou.

### `AuthMiddleware`
Autentizuje request na základě `Authorization: Bearer ...` hlavičky.

#### Co dělá
1. načte header,
2. vytáhne token pomocí regexu,
3. ověří JWT přes `JWTService`,
4. ověří, že session token existuje v tabulce `sessions`,
5. ověří `is_active = TRUE`,
6. ověří `expires_at > NOW()`,
7. vrátí payload tokenu pro další použití.

#### Proč je kontrola i v databázi
Pouhé ověření JWT by nestačilo. JWT by samo o sobě bylo validní až do expirace, ale nebylo by možné ho zneplatnit při odhlášení. Tabulka `sessions` proto slouží jako databázová invalidace. To je velmi důležitý bezpečnostní prvek.

### `RateLimitMiddleware`
Chrání vybrané endpointy proti příliš častému volání.

#### Kde se používá
V routeru se aplikuje například na:
- login,
- register,
- přidání přítele,
- posílání zprávy.

#### Proč je to důležité
Není to jen otázka bezpečnosti, ale i stability. Login endpoint je citlivý na brute-force, message endpoint na spam.

## 2.7 Service vrstva: `src/Services/JWTService.php`

JWTService je příklad správně oddělené pomocné vrstvy.

### Co přesně dělá
- generuje JWT tokeny,
- dekóduje JWT tokeny,
- čte secret a TTL z environment proměnných,
- používá algoritmus HS256.

### Proč není JWT logika v controlleru
Generování a validace tokenů je cross-cutting concern. Kdyby byla přímo v `AuthController`, kontrolery by byly přetížené a logika by se hůře testovala. Service vrstva proto zvyšuje čistotu návrhu.

## 2.8 Validátory

Whisp má sadu validačních tříd:
- `AuthValidator`
- `FriendValidator`
- `ChatValidator`
- `NotificationValidator`
- `UserValidator`
- `AdminValidator`

### Proč vznikly
Původně bývá běžné, že kontrola vstupních dat vzniká přímo v controlleru. To ale vede k dlouhým metodám, duplicitě a slabé čitelnosti. Zavedení validátorů znamená, že controller jen deleguje otázku „jsou data validní?“ na samostatnou třídu.

### Příklad
`AuthValidator::validateLogin($data)` zkontroluje, že při loginu existuje email a heslo v očekávaném formátu. Controller pak nemusí obsahovat sérii `if (!isset(...))` podmínek.

## 2.9 Controllery – obecný princip

V projektu platí pravidlo:
- controller má orchestraci,
- model má data access,
- service má sdílenou logiku,
- validator má vstupní kontrolu,
- middleware řeší technické/bezpečnostní předpodmínky.

Controller tedy typicky provede tyto kroky:
1. získá request data,
2. validuje je,
3. ověří identitu přes middleware,
4. zavolá model,
5. vrátí JSON response.

## 2.10 `AuthController`

### Odpovědnost
Řídí login, register, logout a načtení vlastního profilu (`me`).

### `login()`
- načte JSON payload,
- validuje email a heslo,
- najde uživatele podle emailu,
- ověří heslo přes `password_verify`,
- nastaví status online,
- vygeneruje JWT,
- vytvoří session v DB,
- zaloguje aktivitu,
- vrátí token a user payload.

#### Proč je status měněn při loginu
Status online/offline je součást uživatelského modelu a používá se ve frontendu. Login je přirozený okamžik, kdy se uživatel stává online.

### `register()`
- validuje payload,
- kontroluje unikátnost username a emailu,
- generuje výchozí avatar přes DiceBear URL,
- vytvoří uživatele,
- zaloguje registraci,
- nastaví status online,
- založí session,
- vrátí token a profil.

#### Proč se po registraci uživatel rovnou přihlásí
Jde o UX rozhodnutí. Uživatel nemusí po registraci dělat další krok navíc.

### `logout()`
- načte Bearer token,
- deaktivuje session v DB,
- dohledá `user_id`,
- zapíše aktivitu LOGOUT,
- nastaví status offline,
- vrátí potvrzení.

### `me()`
- použije `AuthMiddleware::check()`,
- dohledá uživatele podle `sub`,
- načte role name,
- vrátí normalizovaný payload uživatele.

## 2.11 `FriendController`

FriendController zprostředkovává správu přátelství.

### `search()`
Vyhledává uživatele, které lze přidat jako přátele. Nevrací všechny uživatele systému bez filtru, ale pouze smysluplné kandidáty na základě vstupního dotazu a aktuálního uživatele.

### `add()`
- ověří identitu odesílatele,
- validuje `target_id`,
- blokuje pokus přidat sám sebe,
- deleguje vytvoření žádosti do modelu.

### `index()`
Vrací seznam přátel aktuálního uživatele.

### `requests()`
Vrací pending žádosti mířící na aktuálního uživatele.

### `accept()` a `reject()`
Provádí rozhodnutí nad konkrétní žádostí identifikovanou `request_id`.

### `remove()`
Odstraní přátelství mezi dvěma uživateli. Důležité je, že odstranění musí fungovat symetricky bez ohledu na to, kdo byl v původním záznamu requester a kdo addressee.

## 2.12 `ChatController`

ChatController je centrální bod pro HTTP část chatovací logiky.

### `getRooms()`
Vrací seznam room, do kterých uživatel patří. Na frontendu z těchto dat vzniká levý panel se seznamem konverzací.

### `openDm()`
- ověří identitu uživatele,
- ověří, že target je stále validní přítel,
- najde nebo vytvoří room typu `dm`,
- vrátí `room_id`.

### `getHistory()`
Vrací historii zpráv pro konkrétní room. Pro DM se navíc kontroluje, že room je stále oprávněná. Pokud se uživatelé přestanou přátelit, backend vrací 403 a frontend DM zavře.

### `sendMessage()`
- validuje payload,
- ověří oprávnění k room,
- uloží zprávu,
- vrátí perzistentní podobu zprávy.

### `updateMessage()` a `deleteMessage()`
Umožňují editovat a mazat zprávy, typicky jen jejich autorovi.

### Group operace
- `createGroup()` – vytvoří skupinu a přidá členy,
- `getGroupMembers()` – vrací členy konkrétní skupiny,
- `addGroupMember()` – přidá uživatele do skupiny,
- `leaveGroup()` – uživatel skupinu opustí,
- `updateGroup()` – změní název nebo avatar,
- `kickMember()` – admin skupiny odebere člena.

Tyto operace jsou přirozeně bohatší než DM, protože skupina má vlastnictví, role členů a správu membershipů.

## 2.13 `NotificationController`

Tento controller řeší dvě základní akce:
- načtení unread notifikací,
- označení room jako přečtené.

Jeho úloha je malá, ale důležitá pro konzistenci badge stavu v UI.

## 2.14 `UserController`

UserController se stará o:
- seznam uživatelů,
- profilové informace,
- update uživatelského profilu,
- případné mazání uživatele.

Prakticky je to „profilová vrstva“ systému.

## 2.15 `AdminController`

AdminController agreguje operace nad systémem jako celkem:
- dashboard statistiky,
- správa uživatelů,
- správa room,
- logy,
- detaily entit.

Admin controller je přirozeně širší než ostatní, protože reprezentuje provozní pohled na celý systém. Přesto je důležité, že data access neleží v controlleru samotném, ale v `Admin` modelu a dalších modelech.

## 2.16 Modelová vrstva

### `User`
Obsahuje operace nad tabulkou `users` a související helpery:
- vyhledání podle ID/emailu/username,
- vytvoření uživatele,
- update statusu,
- update profilu,
- čtení role name,
- logování aktivit.

### `Friend`
Obsahuje logiku nad tabulkou `friendships`:
- search dostupných uživatelů,
- create request,
- get friends,
- get pending requests,
- accept/reject,
- remove friendship.

### `Chat`
Je nejbohatší model systému. Obsahuje logiku nad:
- `rooms`,
- `room_memberships`,
- `messages`,
- částečně i pomocné dotazy pro notifikace a membershipy.

### `Session`
Spravuje databázovou tabulku session tokenů. Důležitá hlavně pro login/logout a invalidaci tokenů.

### `Notification`
Čte a aktualizuje notifikační záznamy.

### `Admin`
Obsahuje query-heavy admin operace jako dashboard statistiky a detailní výpisy.

## 2.17 Shrnutí backend vrstvy

Backend je v projektu centrem pravidel. Frontend může chtít cokoli, ale backend rozhoduje, co je dovoleno. To je dobře vidět zejména v těchto scénářích:
- otevření DM pouze mezi přáteli,
- zrušení přístupu do DM po odebrání z přátel,
- websocket auth pouze s platným tokenem a session,
- admin endpointy pouze pro roli admin,
- validační odmítnutí neplatných payloadů.

Díky tomu backend není jen technický prostředník, ale autoritativní zdroj pravdy o tom, co je ve Whispu platná operace.


---

# Soubor: 03_frontend_od_A_do_Z.md

# 3. Frontend od A do Z

## 3.1 Úloha frontendu

Frontend je uživatelská vrstva aplikace. Zatímco backend rozhoduje, zda je akce dovolená, frontend řeší především:
- jak uživateli zobrazit data,
- jak sbírat vstupy,
- jak držet stav rozhraní,
- jak synchronizovat REST a WebSocket události,
- jak reagovat na změny bez ručního reloadu.

Ve Whispu je frontend postavený na Reactu a Vite. React poskytuje komponentový model a stavovou logiku, Vite vývojářský server a build pipeline.

## 3.2 Vstupní bod: `main.jsx`

`main.jsx` zavádí aplikaci do DOM. Používá:
- `ReactDOM.createRoot`,
- obalení do `AuthProvider`,
- `React.StrictMode`.

StrictMode je důležitý hlavně ve vývoji, protože některé lifecycle efekty mohou být v development režimu spuštěny dvakrát. To vysvětluje některé přechodné websocket warningy během developmentu, které se v produkci typicky neobjevují.

## 3.3 `AuthContext.jsx`

AuthContext je základní vrstva identity na frontendu. Je to jedna z nejdůležitějších částí celé aplikace, protože centralizuje přihlášeného uživatele, token a Axios konfiguraci.

### Co drží
- `user` – přihlášený uživatel,
- `loading` – zda probíhá počáteční ověření,
- `api` – Axios instance,
- metody `login`, `register`, `logout`.

### Axios instance
Axios se inicializuje s `baseURL` postaveným dynamicky z hostname. To je praktické zejména v Docker prostředí, protože frontend vždy volá backend na stejném hostu, ale různém portu.

### Request interceptor
Každý request dostane automaticky Bearer token z localStorage. Díky tomu jednotlivé komponenty nemusí při každém requestu ručně řešit hlavičky.

### Response interceptor
Pokud backend vrátí 401, frontend:
- odstraní token,
- nastaví `user = null`,
- vypíše alert,
- přesměruje na root.

Tím se centralizuje chování při expiraci session nebo smazání účtu. Komponenty nemusí samostatně řešit, co dělat při `401 Unauthorized`.

### Check user při startu
Po mountu provider ověřuje, zda v localStorage existuje token. Pokud ano, zavolá `/api/user/me`. Tím se aplikace po refreshi znovu synchronizuje s backendem.

## 3.4 `App.jsx` jako orchestrátor UI

`App.jsx` je hlavní skladatel celé klientské aplikace. Není to jen vizuální wrapper, ale orchestrátor několika paralelních stavových oblastí:

- přihlášení / odhlášení,
- websocket připojení,
- aktivní chat,
- aktivní room,
- zobrazení profilu,
- zobrazení detailu skupiny,
- unread notifikace,
- pending friend request count,
- přepnutí na admin panel.

### Proč je `App.jsx` centrální
V chatu je spousta stavů, které přesahují jednu komponentu. Pokud uživatel například odebere přítele z profilu, musí se zavřít chat, refreshnout user list, zmizet notifikační badge a někdy zavřít i otevřený modal. To je cross-component efekt, a proto se jeho koordinace děje na úrovni `App.jsx`.

## 3.5 Stavové oblasti v `App.jsx`

### UI stavy
- `showSettings`
- `showFriends`
- `viewingProfile`
- `viewingGroup`
- `isLogin`

Tyto stavy říkají, který modal nebo pohled je právě aktivní.

### Chat stavy
- `selectedChatUser`
- `activeRoomId`
- `unreadIds`
- `friendRequestCount`

Tyto stavy reprezentují samotný provoz komunikační části aplikace.

### WebSocket stavy
- `socket`
- `socketRef`
- refy na `selectedChatUser`, `activeRoomId`, `viewingProfile`

Refy jsou zásadní kvůli stale closure problému. WebSocket callback běží mimo běžný React render cyklus, a proto potřebuje přístup k aktuálním hodnotám stavu i ve chvíli, kdy vznikl v dřívějším renderu.

## 3.6 Proč se ve frontendu používají refy

Tohle je jedno z nejdůležitějších architektonických rozhodnutí na frontendu.

Kdyby websocket handler používal jen běžné state hodnoty zavřené v closure, mohl by pracovat se zastaralým `selectedChatUser`. Výsledek by byl například ten, že druhému uživateli po odebrání z přátel nezmizí otevřený DM chat. Řešení je držet aktuální state i v `useRef`, aby websocket callback viděl nejnovější data bez ohledu na to, kdy byl vytvořen.

## 3.7 WebSocket napojení ve frontendu

Po přihlášení běží v `App.jsx` effect, který:
1. zkontroluje, že uživatel existuje a není admin,
2. získá token z localStorage,
3. otevře socket na `ws://<hostname>:8080`,
4. po `onopen` pošle `{ type: 'auth', token }`,
5. po `auth_ok` uloží socket do state,
6. po `auth_error` spojení zavře.

To znamená, že websocket není autentizovaný přes query parametr v URL, ale přes první zprávu po navázání spojení. To je bezpečnější a přehlednější řešení.

## 3.8 `handleWebSocketMessage`

Tato funkce je centrem realtime logiky na klientovi. Reaguje na více typů událostí:

- `message:new`
- `message_update`
- `message_delete`
- `notification`
- `user_status`
- `friend_update`
- `contact_update`
- `group_update`
- `kicked_from_group`
- `contact_deleted`

Každý event má jiný dopad na state.

### Například `notification`
Přidá room nebo user id do `unreadIds` a vyvolá `chat-update`, což vede k reloadu seznamu konverzací.

### `friend_update`
Vyvolá refresh sociálního stavu. Pokud akce znamená `unfriended`, zavře se aktivní DM přes helper `closeDirectChatWithUser`.

### `kicked_from_group`
Pokud je právě otevřena room, ze které byl uživatel odebrán, frontend okamžitě vymaže `selectedChatUser` i `activeRoomId` a vrátí UI do výchozího stavu.

## 3.9 `UserList.jsx`

Tato komponenta tvoří levý panel konverzací.

### Co načítá
Paralelně volá:
- `/friends`
- `/rooms`

Potom výsledky slučuje do jednotného seznamu položek `allItems`.

### Proč se data slučují
Backend vrací rooms a friends odděleně, ale UI potřebuje zobrazovat jeden panel. Frontend tedy spojí:
- group rooms přímo,
- DM rooms nepřímo přes odpovídajícího přítele.

Tím vznikne jeden seznam, ve kterém může mít každá položka buď `type = 'group'`, nebo `type = 'dm'`.

### Vyhledávání a filtry
UserList obsahuje lokální fulltextový filtr a přepínání karet:
- vše,
- online,
- skupiny.

Tím je dosaženo jednoduché navigace bez dalšího backend endpointu.

## 3.10 `FriendManager.jsx`

FriendManager je kombinovaná komponenta pro:
- vyhledání uživatelů,
- zobrazení pending requestů,
- přijetí nebo odmítnutí žádosti,
- otevření profilů.

### Důležité toky
Při přijetí žádosti:
- odebere request z `pendingRequests`,
- odešle `friend_action` websocket event druhé straně,
- vyvolá `friend-status-change`,
- vyvolá `friend-request-handled`, aby se přepočítala badge.

Při odmítnutí žádosti:
- odebere request z lokálního stavu,
- vyvolá synchronizační event pro přepočet počtu requestů.

To je přesně ten typ logiky, který na první pohled působí jako detail UI, ale ve skutečnosti je kritický pro konzistenci celé aplikace.

## 3.11 `ChatWindow.jsx`

ChatWindow je hlavní komponenta pro práci se zprávami.

### Načítání historie
Při změně `roomId` komponenta načte historii přes `/messages/history`. Pokud dojde k 403, znamená to, že room již není dostupná – typicky po odebrání z přátel nebo ze skupiny. Komponenta pak přes globální event zavře chat.

### Odesílání zpráv
Komponenta provádí dva kroky:
1. zavolá REST `/messages/send`,
2. po úspěšném uložení odešle websocket `message:new`.

To je vědomý hybridní design. REST ukládá data, WebSocket distribuuje event.

### Editace a mazání
Editace i mazání probíhají přes REST. Po úspěchu se přes websocket odešle `message_update` nebo `message_delete`, aby se změna promítla i na druhé straně.

### Reply a UI akce
Komponenta drží lokální stav pro odpovídání a editaci. Vizuálně tedy řeší nejen vykreslení historie, ale i dočasný input workflow.

## 3.12 `UserProfileModal.jsx`

Zobrazuje detail profilu a umožňuje odebrání z přátel. Důležité je, že tlačítko „Odebrat z přátel“ se nesmí zobrazit na vlastním profilu. To se řeší helperem `canShowUnfriendButton` (později zpřesněným přes bezpečnější podmínky a callbacky).

Při odebrání z přátel komponenta:
- volá backend endpoint `/friends/remove`,
- pošle websocket `friend_action`,
- vyvolá eventy pro refresh seznamu,
- a přes callback do `App.jsx` může okamžitě zavřít aktivní DM.

## 3.13 `GroupDetailsModal.jsx`

Slouží k zobrazení detailu skupiny a jejímu managementu. Umožňuje:
- zobrazit členy,
- opustit skupinu,
- upravit skupinu,
- odebrat člena,
- přidat člena.

Frontend tu spolupracuje s chatovou logikou i websocket vrstvou, protože group změny je třeba okamžitě propsat ostatním členům.

## 3.14 `ProfileSetup.jsx`

Umožňuje editovat vlastní profil – avatar a bio. Změna profilu je lokální z pohledu uživatele, ale zároveň se promítá do seznamu přátel a otevřených profilů. Proto se po úspěšném update často posílá websocket event `profile_change`, aby ostatní klienti věděli, že mají data obnovit.

## 3.15 `AdminPanel.jsx`

AdminPanel je samostatný režim aplikace pro administrátora.

### Co dělá
- načítá dashboard statistiky,
- zobrazuje tabulku uživatelů,
- zobrazuje tabulku room,
- zobrazuje logy,
- umožňuje mazat uživatele a room,
- umožňuje vytvořit dalšího admina.

Z frontendového pohledu je důležité, že admin panel není vynucen routováním, ale rozhodnutím v `App.jsx` podle role uživatele.

## 3.16 Helper `friendChatState.js`

Obsahuje malé, ale důležité helper funkce.

### `canShowUnfriendButton`
Řeší, zda je vhodné zobrazit tlačítko pro odebrání přítele.

### `shouldCloseDirectChatOnFriendRemoval`
Řeší, zda se aktuálně otevřený chat má zavřít po ztrátě přátelství. Je důležité, že funkce ignoruje group chaty a reaguje jen na DM s daným uživatelem.

## 3.17 Shrnutí frontend vrstvy

Frontend Whispu není jen vizuální prezentace backendu. Je to vrstva, která:
- drží složitý lokální stav,
- sjednocuje data z více endpointů,
- reaguje na websocket eventy,
- synchronizuje modální okna, aktivní chat, unread badge a friend request count,
- a zároveň zůstává tenká v tom, že obchodní pravidla nechává backendu.

Právě kombinace React state + REST + WebSocket eventů dává aplikaci dojem „živého“ systému místo statického formulářového webu.


---

# Soubor: 04_realtime_websockety.md

# 4. Realtime vrstva a WebSocket server

## 4.1 Proč je v projektu samostatný WebSocket server

REST API řeší request-response model. Klient pošle požadavek, server odpoví a spojení končí. To je vhodné pro CRUD operace a transakční logiku, ale nevhodné pro situace, kdy má server aktivně poslat informaci jinému uživateli v okamžiku, kdy se něco stane.

Proto má Whisp samostatný WebSocket server v `backend/src/Sockets/ChatSocket.php`, spouštěný skriptem `backend/bin/server.php`.

## 4.2 Startup websocket serveru

Soubor `bin/server.php`:
- načte Composer autoloader,
- vytvoří instanci `ChatSocket`,
- zabalí ji do Ratchet `WsServer` a `HttpServer`,
- poslouchá na portu 8080.

To znamená, že websocket vrstva je technicky oddělená od REST backendu, i když používá stejný kódový základ a stejnou databázi.

## 4.3 Základní odpovědnosti `ChatSocket`

`ChatSocket` je velká třída, protože v současné architektuře plní více rolí najednou. Prakticky zajišťuje:
- správu aktivních socket spojení,
- websocket autentizaci,
- mapování uživatel -> spojení,
- sledování aktivní room na klientovi,
- rozesílání realtime eventů,
- správu online/offline statusu,
- některé vedlejší notifikační a synchronizační operace.

To je funkčně správné, ale z architektonického hlediska jde o kandidáta na budoucí rozdělení do menších tříd. Zákaz redistribuce a nerozřezávání `ChatSocket` je v projektu respektován, ale z pohledu technického dluhu je dobré tento fakt přiznat.

## 4.4 Struktury držené v paměti

`ChatSocket` si za běhu drží tři zásadní datové struktury:

### `clients`
`SplObjectStorage` se všemi aktivními websocket spojeními.

### `userConnections`
Mapa `userId -> [resourceId => ConnectionInterface]`. Díky tomu může mít jeden uživatel více aktivních klientů zároveň (například více otevřených tabů).

### `connMeta`
Metadata ke každému socketu:
- zda je autentizovaný,
- k jakému uživateli patří,
- jaká room je momentálně aktivní.

## 4.5 Proč je autentizace řešena první zprávou `auth`

Dříve je běžné posílat token v query stringu websocket URL. To ale vede k několika problémům:
- token může skončit v logách,
- token je viditelný v URL,
- query string je nevhodné místo pro citlivá data.

Whisp používá bezpečnější model:
1. klient otevře socket bez tokenu v URL,
2. po `onopen` pošle JSON zprávu `type = auth`,
3. server token ověří,
4. teprve pak spojení označí za autentizované.

To je v kódu vidět jak na frontendu (`App.jsx`), tak v `ChatSocket::handleAuthMessage()`.

## 4.6 Jak probíhá websocket autentizace

### Krok 1 – otevření socketu
`onOpen()` pouze založí metadata spojení a označí jej jako neautentizované.

### Krok 2 – příjem `auth`
Klient pošle payload:
- `type: auth`
- `token: JWT`

### Krok 3 – ověření tokenu
Server použije `JWTService::decode($token)` a následně dotaz do tabulky `sessions`, aby potvrdil:
- token existuje,
- session je aktivní,
- session není expirovaná.

### Krok 4 – svázání spojení s uživatelem
Pokud je vše v pořádku:
- `connMeta[resourceId].authenticated = true`,
- `connMeta[resourceId].userId = ...`,
- connection se uloží do `userConnections[userId][resourceId]`.

### Krok 5 – potvrzení klientovi
Server pošle `auth_ok` a klient teprve pak považuje websocket za plně funkční.

## 4.7 Proč se kontroluje i tabulka `sessions`

To je velmi důležité. Pouhé ověření JWT podpisu by nestačilo. Uživatel se může odhlásit, může mu session expirovat nebo může být token neplatný z jiného důvodu. Databázová tabulka `sessions` je proto druhá bezpečnostní vrstva nad samotným JWT.

## 4.8 Event model ve websocketu

Whisp používá websockety jako event bus mezi klienty. Události lze rozdělit do skupin.

### Zprávy
- `message:new`
- `message_update`
- `message_delete`

### Sociální události
- `friend_update`
- `contact_update`
- `contact_deleted`

### Status a presence
- `user_status`
- `presence:set_active_room`

### Group management
- `group_change`
- `group_update`
- `group_kick`
- `kicked_from_group`

### Notifikace
- `notification`

## 4.9 Význam `presence:set_active_room`

Klient může serveru sdělit, že má aktivně otevřenou určitou room. To má přímý dopad na notifikační logiku. Pokud uživatel room právě sleduje, není žádoucí vytvářet mu zbytečnou offline notifikaci pro něco, co už vidí v aktivním okně.

Tento mechanismus je jednoduchý, ale účinný. Server si v `connMeta` drží `activeRoomId`, a při odeslání nové zprávy může rozhodnout, zda notifikaci vytvořit, nebo ne.

## 4.10 Jak se rozesílá nová zpráva

Scénář nové zprávy v realtime vrstvě:

1. klient A uloží zprávu přes REST,
2. klient A pošle websocket event `message:new`,
3. server zjistí členy room,
4. server rozesílá payload členům room,
5. u nepřítomných uživatelů vytvoří notifikaci,
6. klient B dostane `message:new` a UI se aktualizuje.

To je hybridní architektura – websocket sám neukládá zprávu, pouze distribuuje událost po potvrzení, že zpráva byla perzistentně uložena přes REST.

## 4.11 Online/offline statusy

Při úspěšné autentizaci websocket spojení server:
- nastaví uživatele v DB na `online`,
- rozešle `user_status` všem klientům.

Při odpojení:
- odebere konkrétní socket z `userConnections`,
- pokud uživatel nemá žádné další aktivní spojení, nastaví status `offline`,
- rozešle `user_status` s offline stavem.

To je důležité: uživatel může mít více tabů. Proto se nepracuje s modelem „jeden uživatel = jedno spojení“, ale s množinou spojení.

## 4.12 Group update a synchronizace skupin

Pokud se změní skupina – například název, avatar nebo membership – websocket vrstva rozesílá `group_update`. Frontend pak ví, že má refreshnout rooms nebo konkrétní otevřenou skupinu.

To umožňuje, aby se změna propsala všem členům skupiny bez ručního refresh. Klient si může buď přímo aktualizovat state, nebo znovu načíst rooms přes REST, což Whisp v některých scénářích dělá.

## 4.13 Odebrání člena ze skupiny

Při `group_kick` je tok následující:
- admin skupiny provede akci,
- server ověří, že je opravdu admin skupiny,
- pošle `group_update` pro refresh ostatních,
- cílovému uživateli pošle `kicked_from_group`.

Frontend cílového uživatele pak okamžitě zavře otevřenou room a vrátí UI do výchozího stavu.

## 4.14 Odebrání přítele přes websocket

Při odebrání přítele se používá event `friend_action`/`friend_update`. Smyslem je zajistit, aby se změna nepropsala jen lokálně tomu, kdo akci provedl, ale i druhé straně.

Na frontendu tato událost vyvolá:
- refresh seznamu přátel,
- případně zavření právě otevřeného DM chatu,
- případně refresh profilového stavu.

Tím se řeší problém „backend už nepovoluje zprávy, ale UI stále ukazuje otevřený chat“.

## 4.15 Kontakt smazal účet

`contact_deleted` je speciální event. Uživatel může být odstraněn administrátorem nebo smazat účet. V tom případě websocket informuje ostatní klienty a frontend musí:
- refreshnout seznam přátel,
- zavřít případný aktivní DM chat,
- zobrazit informaci uživateli.

## 4.16 Proč websocket server přímo pracuje s databází

Může se zdát, že websocket vrstva by měla být čistě transportní. V praxi ale potřebuje:
- ověřit session token,
- aktualizovat online/offline status,
- zjišťovat členy room,
- vytvořit notifikace,
- ověřit group admin roli.

Proto má websocket server vlastní PDO připojení přes `Database`. Pro projekt této velikosti je to pragmatické řešení.

## 4.17 Omezení současné websocket architektury

Tento návrh má i limity:
- stav připojení je držen v paměti jednoho procesu,
- neexistuje sdílená presence mezi více websocket instancemi,
- horizontální škálování by vyžadovalo další vrstvu (například Redis pub/sub),
- `ChatSocket` je funkčně správný, ale příliš široký z pohledu jedné odpovědnosti.

To ale neznamená, že je návrh špatný. Pro rozsah Whispu je vhodný. Jen je důležité rozumět, kde končí jeho přirozené limity.

## 4.18 Shrnutí realtime vrstvy

WebSocket server je v Whispu klíčový pro uživatelský dojem „živé aplikace“. Bez něj by uživatelé museli refreshovat seznamy nebo čekat na polling. Realtime vrstva řeší:
- okamžité doručení zpráv,
- změny statusů,
- sociální synchronizaci,
- změny skupin,
- notifikace.

Z hlediska architektury je důležité vnímat websocket ne jako náhradu REST API, ale jako doplněk pro distribuci událostí a synchronizaci klientů.


---

# Soubor: 05_databaze_a_sql.md

# 5. Databáze, SQL a datové relace

## 5.1 Proč relační databáze

Whisp používá PostgreSQL, protože datový model projektu je přirozeně relační. Systém řeší vazby typu:
- uživatel má roli,
- uživatel má session tokeny,
- room má členy,
- room má zprávy,
- uživatel může být přítelem jiného uživatele,
- uživatel má notifikace,
- aktivita uživatele se zapisuje do logů.

Takové vztahy se v relační databázi vyjadřují čistě, srozumitelně a s podporou cizích klíčů.

## 5.2 Inicializace schématu přes `init.sql`

`backend/init.sql` je seed script, který se automaticky vykoná při prvním startu PostgreSQL kontejneru nad prázdným volume. Tím vznikne základní databázové schéma systému.

Výhodou je, že vývojář nebo hodnotitel nemusí ručně vytvářet tabulky – celé schéma je součástí projektu.

## 5.3 Tabulka `roles`

### Účel
Uchovává role systému.

### Sloupce
- `id`
- `name`
- `description`

### Výchozí data
Script vkládá dvě role:
- `admin`
- `user`

### Proč není role přímo string v `users`
Role jsou oddělené do lookup tabulky, což je správnější normalizace. Umožňuje to:
- jednoznačnou správu rolí,
- cizí klíč z `users.role_id`,
- budoucí rozšíření o další role bez zásahu do struktury uživatelů.

## 5.4 Tabulka `users`

### Účel
Základní identita uživatele.

### Klíčové sloupce
- `id UUID PRIMARY KEY DEFAULT gen_random_uuid()`
- `username`
- `email`
- `password_hash`
- `role_id`
- `avatar_url`
- `bio`
- `status`
- `created_at`

### Proč UUID
UUID je vhodné pro identifikaci uživatelů, protože:
- není snadno odhadnutelné jako sekvenční integer,
- lépe se používá v distribuovaném prostředí,
- je vhodné pro veřejně přenášené ID v API.

### `status`
Status nese hodnotu online/offline. I když jde o runtime informaci, je uložena i v DB, což usnadňuje reload klienta a administrativní přehledy.

## 5.5 Tabulka `sessions`

### Účel
Databázová vrstva session invalidace JWT tokenů.

### Klíčové sloupce
- `user_id`
- `token`
- `expires_at`
- `is_active`
- `created_at`

### Proč je tabulka potřeba
Samotný JWT token by byl stateless. To je výhodné, ale současně by nešlo token předčasně zneplatnit. Tabulka `sessions` proto poskytuje možnost:
- deaktivovat token při logoutu,
- odmítat expirované session,
- držet audit session lifecycle.

## 5.6 Tabulka `rooms`

### Účel
Reprezentuje komunikační místnosti.

### Dva typy room
- `dm`
- `group`

### Sloupce
- `id`
- `name`
- `type`
- `owner_id`
- `avatar_url`
- `created_at`

### Proč jsou DM i group v jedné tabulce
Oba koncepty jsou „room“ – liší se chováním, ale sdílí spoustu společného:
- členství,
- zprávy,
- notifikace,
- historii.

Společná tabulka tedy dává smysl a snižuje duplicitu schématu.

## 5.7 Tabulka `room_memberships`

### Účel
Propojuje uživatele a room.

### Sloupce
- `room_id`
- `user_id`
- `role`
- `joined_at`

### Primární klíč
Kombinovaný `(room_id, user_id)`.

### Proč role na membershipu
U skupin nestačí vědět jen to, že uživatel je členem. Potřebujeme také vědět, zda je admin skupiny. Proto je role uložena přímo v membershipu, ne u uživatele globálně.

## 5.8 Tabulka `messages`

### Účel
Ukládá historii zpráv.

### Sloupce
- `id`
- `room_id`
- `sender_id`
- `content`
- `reply_to_id`
- `is_edited`
- `is_deleted`
- `created_at`

### Proč soft delete
Zpráva není při smazání nutně fyzicky odstraněna. Místo toho se používá `is_deleted`. To je užitečné pro:
- zachování konzistence historie,
- správu reply odkazů,
- audit a UI stav „zpráva byla odstraněna“.

### `reply_to_id`
Umožňuje navázat zprávu na jinou zprávu a tím realizovat odpovědi.

## 5.9 Tabulka `friendships`

### Účel
Ukládá vztahy mezi uživateli.

### Sloupce
- `requester_id`
- `addressee_id`
- `status`
- `created_at`

### Statusy
- `pending`
- `accepted`
- `rejected`

### Unikátnost
`UNIQUE(requester_id, addressee_id)` brání duplicitním žádostem ve stejném směru.

### Symetrie přátelství
I když tabulka ukládá request směrově, logicky je accepted friendship symetrická. To znamená, že dotazy musí umět číst přátelství oběma směry a remove musí fungovat bez ohledu na to, kdo byl requester.

## 5.10 Tabulka `activity_logs`

### Účel
Záznam provozních a bezpečnostních událostí.

### Sloupce
- `user_id`
- `action`
- `details`
- `ip_address`
- `timestamp`

### Použití
Logují se akce typu:
- login,
- register,
- logout,
- admin zásahy,
- další důležité provozní operace.

Tato tabulka je důležitá hlavně pro administraci a dohledatelnost událostí.

## 5.11 Tabulka `notifications`

### Účel
Ukládá pending notifikace, typicky o nových zprávách.

### Sloupce
- `user_id`
- `room_id`
- `type`
- `content`
- `is_read`
- `created_at`

### Proč samostatná tabulka
Notifikace nejsou jen odvozený stav z messages. Systém potřebuje explicitně vědět, co je přečtené a co ne. To se nejlépe řeší samostatnou tabulkou.

## 5.12 Vztahy mezi tabulkami

### `roles -> users`
Jeden role záznam může patřit mnoha uživatelům.

### `users -> sessions`
Jeden uživatel může mít více session tokenů v čase.

### `users -> rooms (owner_id)`
U skupin existuje vlastník nebo zakladatel room.

### `rooms <-> users` přes `room_memberships`
Mnoho uživatelů může být ve více room a room může mít více členů.

### `rooms -> messages`
Každá zpráva patří do jedné room.

### `messages -> messages` přes `reply_to_id`
Samorelace umožňuje reply vlákna.

### `users <-> users` přes `friendships`
Přátelství je binární vztah mezi dvěma uživateli.

### `users -> notifications`
Notifikace patří konkrétnímu uživateli.

## 5.13 Datové toky nad schématem

### Registrace
Vzniká nový záznam v `users`, následně session v `sessions`, a případně log v `activity_logs`.

### Login
Čte se z `users`, vzniká nebo se aktualizuje záznam v `sessions`, zapisuje se log a mění se `users.status`.

### Žádost o přátelství
Vzniká záznam ve `friendships` se statusem `pending`.

### Přijetí žádosti
Mění se `status` na `accepted`.

### Otevření DM
Zjišťuje se, zda mezi uživateli existuje accepted friendship. Následně se najde nebo vytvoří odpovídající room typu `dm` a membershipy.

### Odeslání zprávy
Vzniká záznam v `messages`. Pokud příjemce room právě nesleduje, může vzniknout i záznam v `notifications`.

## 5.14 Proč jsou některé stavy v DB a některé jen v paměti

### V databázi
- uživatelé,
- rooms,
- membershipy,
- zprávy,
- sessions,
- notifikace,
- logy.

To jsou stavy, které musí přežít restart aplikace.

### Jen v paměti websocket serveru
- aktuální socket spojení,
- mapování user -> connection,
- aktivně otevřená room klienta.

To jsou přechodné runtime informace potřebné pro realtime chování, ale ne nutně pro trvalé uložení.

## 5.15 Jaké SQL typy jsou v projektu důležité

### SELECT
Používají se pro načítání:
- uživatelů,
- friends,
- rooms,
- history,
- notifikací,
- statistik.

### INSERT
Používají se pro:
- registraci,
- session,
- friendship requests,
- room create,
- messages,
- notifications,
- logy.

### UPDATE
Používají se pro:
- status uživatele,
- editaci zpráv,
- změnu skupiny,
- mark as read,
- změnu stavu friend requestu.

### DELETE nebo logické odstranění
Používá se tam, kde dává smysl fyzicky odstranit vazbu (např. friendship), nebo naopak logicky skrýt obsah (`is_deleted` u messages).

## 5.16 Shrnutí databázové části

Databáze Whispu není navržená jako volná sada tabulek, ale jako koherentní relační model. To je zásadní pro konzistenci aplikace. Prakticky každá důležitá funkce – přihlášení, přátelství, DM, skupiny, zprávy i admin dohled – je přímo odvozena z těchto tabulek a jejich vazeb.


---

# Soubor: 06_runtime_toky_a_implikace.md

# 6. Runtime toky, use-cases a implementační logika krok za krokem

## 6.1 Jak číst runtime toky

Tato kapitola nepopisuje systém po souborech, ale po skutečných dějích. To je důležité, protože architektura se nejlépe chápe ve chvíli, kdy sledujeme konkrétní scénář od vstupu uživatele až po změnu databáze a aktualizaci UI.

Ve Whispu se prakticky všechny důležité use-case skládají ze čtyř vrstev:
1. frontendová akce uživatele,
2. backendová HTTP logika,
3. databázová změna nebo čtení,
4. případně realtime propagace přes websocket.

## 6.2 Use-case: registrace nového uživatele

### Krok 1 – uživatel vyplní formulář
Frontend komponenta `Register.jsx` sbírá `username`, `email`, `password` a předává je přes `AuthContext.register()`.

### Krok 2 – REST request
`AuthContext` zavolá:
- `POST /api/register`

Payload obsahuje registrační data. Axios interceptor zatím nepřidává token, protože uživatel ještě není přihlášený.

### Krok 3 – `AuthController::register()`
Controller:
- načte JSON z `php://input`,
- validuje payload přes `AuthValidator`,
- zkontroluje, zda už neexistuje uživatel se stejným username nebo emailem,
- připraví výchozí avatar URL,
- vytvoří uživatele přes model,
- zapíše log registrace,
- nastaví status online,
- vygeneruje JWT,
- založí session v tabulce `sessions`,
- vrátí JSON s uživatelem a tokenem.

### Krok 4 – frontend uloží token
`AuthContext.register()` uloží token do localStorage a nastaví `user` state.

### Krok 5 – `App.jsx` naváže runtime stav
Protože je uživatel přihlášený, `App.jsx` následně:
- otevře websocket,
- pošle websocket auth,
- načte notifikace,
- načte friend request count.

### Proč je tento tok navržen takto
- registrace je atomická z pohledu uživatele,
- uživatel nemusí po registraci znovu dělat login,
- backend zůstává autoritativní v tom, že rozhoduje o unikátnosti identity.

## 6.3 Use-case: login

### Frontend
`Login.jsx` předá email a heslo `AuthContext.login()`.

### Backend
`AuthController::login()`:
- načte uživatele podle emailu,
- ověří hash hesla,
- nastaví status online,
- vytvoří JWT,
- založí session,
- zapíše aktivitu loginu.

### Frontend po úspěchu
- uloží token,
- nastaví `user`,
- zobrazí hlavní aplikaci,
- `App.jsx` připojí websocket.

### Důležitá poznámka
Bez `AuthContext` by každá komponenta musela samostatně řešit token a autentizační flow. Context tuto odpovědnost centralizuje.

## 6.4 Use-case: načtení vlastního profilu po refreshi

### Situace
Uživatel refreshne stránku, ale token v localStorage zůstává.

### Tok
1. `AuthContext` při mountu zjistí existenci tokenu.
2. Zavolá `GET /api/user/me`.
3. Axios request interceptor přidá Bearer token.
4. Backend přes `AuthMiddleware` ověří JWT a session.
5. `AuthController::me()` vrátí profil.
6. Context nastaví `user` state.

### Proč je tento krok nezbytný
Frontend state po refreshi zanikne, localStorage ale ne. Je tedy potřeba znovu synchronizovat paměťovou reprezentaci uživatele s backendem.

## 6.5 Use-case: odeslání žádosti o přátelství

### Frontend
Ve `FriendManager.jsx` uživatel vyhledá jiného uživatele a klikne na přidání.

### Backend
`FriendController::add()`:
- ověří autentizaci,
- zvaliduje `target_id`,
- zablokuje přidání sebe sama,
- vytvoří request v `friendships` jako `pending`.

### Realtime část
Pokud je navázané websocket spojení, klient odešle `friend_action`. Druhá strana tak může okamžitě dostat event `request_received` a zvýšit si badge počtu žádostí.

### Proč je to rozdělené
- REST zajistí skutečný zápis do DB,
- websocket zajistí okamžité zobrazení změny na druhém klientovi.

## 6.6 Use-case: přijetí žádosti o přátelství

### Frontend
`FriendManager.jsx` po kliknutí na „Přijmout“:
- zavolá REST endpoint,
- optimisticky upraví `pendingRequests`,
- vyšle websocket event druhé straně,
- vyvolá globální event pro refresh friend listu a přepočet badge.

### Backend
`FriendController::accept()`:
- validuje `request_id`,
- změní `friendships.status` na `accepted`.

### Výsledek
Oba uživatelé se dostanou do seznamu přátel a mohou otevřít DM.

## 6.7 Use-case: odmítnutí žádosti o přátelství

### Co je zde důležité
Pouhé odstranění žádosti z tabulky nestačí. Je potřeba synchronizovat i frontend badge, jinak může zůstat svítit indikátor pending requests.

### Tok
- frontend zavolá `POST /api/friends/reject`,
- backend request označí nebo odstraní,
- frontend odebere request z lokálního seznamu,
- vyvolá `friend-request-handled`,
- `App.jsx` znovu načte `/friends/requests` a přepočítá badge.

### Proč je to správné
Lokální decrement `count - 1` je křehký. Bezpečnější je po významné akci reálně synchronizovat stav z backendu.

## 6.8 Use-case: otevření DM chatu

### Frontend
V `UserList.jsx` uživatel klikne na přítele.

### `App.jsx::handleUserSelect`
- nastaví `selectedChatUser`,
- zavolá `POST /api/chat/open` s `target_id`.

### Backend
`ChatController::openDm()`:
- ověří, že target je validní uživatel,
- ověří existenci accepted friendship,
- najde nebo vytvoří room typu `dm`,
- vrátí `room_id`.

### Frontend pokračování
Po získání `room_id` nastaví `activeRoomId` a vykreslí `ChatWindow`.

### Proč backend blokuje DM bez přátelství
Je to důležité pravidlo domény. Frontend může chtít otevřít libovolný DM, ale backend musí být autoritativní a dovolit jen to, co odpovídá stavu systému.

## 6.9 Use-case: načtení historie room

### Frontend
`ChatWindow.jsx` při změně `roomId` volá `/messages/history`.

### Backend
`ChatController::getHistory()`:
- ověří oprávnění uživatele k room,
- načte zprávy,
- vrátí historii.

### Speciální případ
Pokud uživatel přišel o oprávnění – například po odebrání z přátel nebo ze skupiny – backend vrátí 403.

### Frontend reakce
ChatWindow vyvolá event vedoucí k zavření chatu a návratu do defaultního stavu. To je důležité UX pravidlo: chat nesmí zůstávat otevřený, pokud už není legální.

## 6.10 Use-case: odeslání zprávy

### Krok 1 – uživatel napíše text
`ChatWindow` drží `newMessage` state.

### Krok 2 – REST zápis
Po submitu komponenta volá `POST /api/messages/send`.

### Krok 3 – backend uloží zprávu
`ChatController::sendMessage()` vytvoří záznam v `messages`.

### Krok 4 – websocket propagace
Po úspěšném uložení klient odešle `message:new` přes websocket.

### Krok 5 – ostatní klienti obdrží event
`ChatSocket` rozešle event ostatním členům room.

### Krok 6 – notifikace
Pokud příjemce danou room aktivně nesleduje, websocket vrstva vytvoří notifikaci do DB a frontend si zobrazí badge.

## 6.11 Use-case: editace a mazání zprávy

### Editace
- REST endpoint upraví obsah,
- websocket event `message_update` zajistí, že i druhá strana uvidí novou verzi.

### Mazání
- REST označí zprávu jako `is_deleted`,
- websocket `message_delete` zajistí promítnutí do UI všech klientů.

### Proč soft delete
Uživatelé vidí, že zpráva existovala, ale byla odstraněna. To zachovává návaznost konverzace.

## 6.12 Use-case: vytvoření skupiny

### Frontend
`CreateGroupModal.jsx` sbírá:
- název skupiny,
- vybrané členy.

### Validace na klientu
Skupina musí mít minimálně zakladatele a další dva členy. Tato validace je uživatelsky užitečná, ale není jedinou ochranou – backend si musí smysluplnost akce ověřit také.

### Backend
`ChatController::createGroup()` vytvoří room typu `group` a membershipy.

### Realtime propagace
Klient po úspěchu pošle `group_change`, aby ostatní členové viděli novou skupinu bez refresh.

## 6.13 Use-case: update skupiny

Admin skupiny může změnit:
- název,
- avatar.

Backend změnu uloží a websocket rozesílá `group_update`. Frontend reaguje dvěma způsoby:
- refreshne rooms,
- pokud je daná skupina právě otevřená, upraví její metadata i v aktivním chat view.

## 6.14 Use-case: odebrání člena ze skupiny

### Backend
- ověří, že iniciátor je admin skupiny,
- upraví membership,
- cílovému uživateli pošle `kicked_from_group`,
- ostatním členům pošle `group_update`.

### Frontend cílového uživatele
- pokud je skupina právě otevřená, okamžitě ji zavře,
- ukáže hlášení,
- refreshne seznam místností.

## 6.15 Use-case: odebrání přítele při otevřeném DM

Tohle je jeden z nejzajímavějších runtime scénářů, protože spojuje sociální logiku, REST, websocket i lokální UI stav.

### Iniciátor odebrání
1. v `UserProfileModal` klikne na „Odebrat z přátel“,
2. frontend zavolá `POST /api/friends/remove`,
3. backend smaže symetricky friendship vztah,
4. frontend pošle websocket `friend_action` s `unfriended`,
5. `App.jsx` lokálně zavře otevřený chat s tímto uživatelem.

### Druhá strana
1. dostane websocket `friend_update` s akcí `unfriended`,
2. `App.jsx` vyhodnotí, zda je právě otevřený DM s tímto uživatelem,
3. pokud ano, okamžitě zavře chat,
4. refreshne friend list a UI se vrátí do defaultního stavu.

### Proč je zde kritické použít refy
Websocket callback může běžet se stale closure nad starým `selectedChatUser`. Proto se používá `selectedChatUserRef`, aby i druhému uživateli chat skutečně zmizel okamžitě a ne až po refreshi.

## 6.16 Use-case: profilová změna

Když uživatel změní avatar nebo bio:
- backend uloží změnu v `users`,
- frontend pošle websocket `profile_change`,
- websocket rozešle přátelům `contact_update`,
- ostatní klienti refreshnou seznam přátel nebo profilový detail.

## 6.17 Use-case: přihlášení admina

### Frontend
Po loginu `AuthContext` normalizuje `role`. V `App.jsx` platí pravidlo:
- pokud `user.role === 'admin'`, nevykresluje se standardní chat aplikace,
- ale `AdminPanel`.

### Důsledek
Admin režim je oddělený už na úrovni UI. Není to jen o tom, že admin „může dělat víc“, ale i o tom, že používá jiný pracovní pohled na systém.

## 6.18 Use-case: čistý start systému od nuly

### Krok 1
`docker compose down -v` smaže kontejnery i DB volume.

### Krok 2
`docker compose up --build` vytvoří novou databázi.

### Krok 3
PostgreSQL init proces provede `backend/init.sql`.

### Krok 4
Seed admina proběhne přes `public/install_admin.php`.

### Krok 5
Uživatel nebo admin se přihlásí a systém začne fungovat od čistého stavu.

## 6.19 Runtime logika notifikačních badge

Unread badge nejsou jen vizuální detail. Jsou výsledkem kombinace více vrstev:
- backend vrací unread notifikace přes `/api/notifications`,
- frontend si drží `unreadIds`,
- websocket `notification` event přidává room do `unreadIds`,
- po otevření room a mark-as-read se badge odstraní.

Stejně tak friend request count není jen lokální čítač – po akci se přepočítává z API, aby nedošlo k desynchronizaci.

## 6.20 Shrnutí kapitoly

Runtime toky ukazují, že Whisp není jen sada oddělených funkcí. Každá důležitá akce prochází více vrstvami a teprve jejich souhra vytváří správné chování systému. Pokud je pochopen tento princip, je mnohem snazší systém prezentovat, ladit i rozšiřovat.


---

# Soubor: 07_bezpecnost_validace_testovani.md

# 7. Bezpečnost, validace a testování

## 7.1 Bezpečnostní model systému

Whisp není bankovní systém ani kritická infrastruktura, ale i tak řeší několik důležitých bezpečnostních oblastí:
- autentizaci identity,
- autorizaci operací,
- ochranu proti neplatným payloadům,
- řízení session lifecycle,
- CORS pravidla,
- základní rate limiting.

Smyslem není dosažení enterprise hardeningu na úrovni zero trust architektury, ale rozumně bezpečný návrh odpovídající rozsahu aplikace.

## 7.2 JWT autentizace

JWT je používán jako přenosný identifikační token mezi klientem a backendem.

### Co token obsahuje
- `iat`
- `exp`
- `sub` – user id
- `role`

### Jak se používá
Po přihlášení nebo registraci backend vrátí token. Frontend ho uloží do localStorage a přikládá do `Authorization` hlavičky přes Axios interceptor.

### Proč je JWT secret v environment proměnné
To zabraňuje hardcodování citlivého tajemství do kódu a umožňuje různé konfigurace mezi prostředími.

## 7.3 Proč samotné JWT nestačí

Kdyby systém používal jen stateless JWT, nebylo by možné:
- token předčasně zneplatnit,
- korektně odhlásit uživatele,
- spolehlivě vynutit expiraci session v databázovém smyslu.

Proto je doplněna tabulka `sessions` a backend při auth neověřuje jen podpis JWT, ale i stav session v DB.

## 7.4 Session invalidace

### Při loginu
Vzniká nový záznam v `sessions`.

### Při logoutu
Session se označí `is_active = FALSE`.

### Při každém chráněném requestu
`AuthMiddleware` ověří:
- token je syntakticky validní,
- JWT podpis sedí,
- session existuje,
- je aktivní,
- a není expirovaná.

Tím se backend chrání proti situaci, kdy má klient stale token uložený v localStorage.

## 7.5 CORS

CORS řeší, které frontend originy smějí volat backend API z browseru.

### Jak je implementován
- allowlist je v `CORS_ALLOWED_ORIGINS`,
- middleware porovnává `Origin`,
- pro povolené originy nastaví příslušné hlavičky,
- pro `OPTIONS` vrací 204,
- nepovolené originy nedostanou autorizované CORS odpovědi.

### Proč není použito `*`
Protože aplikace používá autorizované requesty s tokenem a je lepší mít explicitní allowlist než otevřený wildcard režim.

## 7.6 Validace vstupů

Whisp používá validační třídy. To je architektonicky důležité, protože validace je oddělená od controllerů.

### Co se validuje
- existence required field,
- typy hodnot,
- délka textových vstupů,
- přítomnost ID,
- smysluplnost hodnot,
- search query sanitizace.

### Proč je to důležité
Validace není jen ochrana proti chybám na frontendu. Chrání backend proti:
- prázdným payloadům,
- nesmyslným requestům,
- nekonzistentním datům,
- zbytečným pádům controllerů.

## 7.7 Autorizace doménových akcí

Ne každá validní autentizace znamená oprávnění ke všem akcím.

### Příklady pravidel
- uživatel nemůže přidat sám sebe mezi přátele,
- DM lze otevřít jen s platným přítelem,
- group kick může provést jen admin skupiny,
- admin endpointy vyžadují roli admin,
- uživatel nemůže upravovat nebo mazat cizí zprávy.

Tato pravidla jsou doménová autorizace a leží v controllerech a modelech, ne v CORS nebo JWT vrstvě.

## 7.8 WebSocket autentizace

WebSocket server používá samostatný auth handshake.

### Proces
- klient otevře socket bez tokenu v URL,
- pošle `auth` zprávu s tokenem,
- server token dekóduje,
- server zkontroluje session v DB,
- až pak spojení označí za autentizované.

### Výhoda
Token není v URL, takže je menší riziko úniku přes logy nebo debugging nástroje.

## 7.9 Rate limiting

V projektu je základní rate limiting middleware. Jeho smyslem je omezit nadměrné opakované volání citlivých endpointů.

### Typické cíle
- login,
- register,
- přidání přítele,
- odeslání zprávy.

### Proč není systém přehnaně složitý
Záměr projektu explicitně říká, že rate limiting už je jen základní a nemá se hnát na maximum. To je respektováno. Systém tedy řeší základní ochranu, ale nepřidává Redis nebo distribuovaný throttling.

## 7.10 Error handling

Error handling je ve finálním stavu záměrně ponechaný relativně stabilní a nepřepisovaný do extrému. Backend vrací sjednocenější JSON odpovědi a `public/index.php` má globální exception handler, který brání pádu do syrového HTML outputu při neodchycené chybě.

## 7.11 Testovací vrstvy v projektu

### Manuální testování
Manuální testy jsou u takto interaktivního systému zásadní, protože ověřují:
- skutečné uživatelské scénáře,
- souběh více klientů,
- websocket notifikace,
- stav UI po změnách v reálném čase.

### Smoke testy
Projekt obsahuje:
- backend validator smoke test,
- frontend unit test pro helpery,
- API smoke test skript.

To není plný enterprise test suite, ale je to dobrý základ pro kritické ověření.

## 7.12 Jak testovat backend validátory

`backend/tests/validator_smoke_test.php` ověřuje, že validační vrstva funguje bez fatálních chyb a klíčové třídy lze načíst.

## 7.13 Jak testovat frontend helpery

`frontend/src/tests/friendChatState.test.js` ověřuje pomocné funkce jako:
- zda se má zobrazit unfriend button,
- zda se má zavřít DM po odebrání přítele.

To je důležité, protože právě zde se řešily reálné bugy v UI logice.

## 7.14 API smoke test

Skript `tests/api_smoke_test.sh` slouží k rychlému ověření hlavních API cest. Typicky zahrnuje:
- registraci,
- login,
- auth check,
- základní sociální tok,
- základní chat tok.

## 7.15 Co je vhodné testovat manuálně navíc

Automatické testy nikdy úplně nepokryjí celý uživatelský dojem. U Whispu je důležité manuálně ověřit zejména:
- registraci více účtů,
- login/logout a obnovu po refreshi,
- friend request flow,
- odmítnutí žádosti a zhasnutí badge,
- odebrání přítele při otevřeném DM na obou stranách,
- vytvoření skupiny,
- odebrání člena ze skupiny,
- update profilu a okamžitý refresh ostatním,
- admin zásahy do uživatelů a rooms.

## 7.16 Shrnutí bezpečnostní a QA části

Whisp používá několik rozumných bezpečnostních vrstev:
- JWT,
- databázové sessions,
- middleware auth,
- CORS allowlist,
- validátory,
- základní rate limiting.

Současně je systém testovatelný kombinací smoke testů a manuálních scénářů. To je realistický a praktický přístup odpovídající rozsahu projektu.


---

# Soubor: 08_sdlc_agile_scrum_a_obhajoba.md

# 8. SDLC, Agile/Scrum přístup a doporučení pro obhajobu

## 8.1 SDLC v kontextu projektu

Software Development Life Cycle popisuje kompletní životní cyklus vývoje softwaru. U projektu Whisp se přirozeně promítá do těchto etap:

1. analýza požadavků,
2. návrh architektury,
3. návrh databáze,
4. návrh uživatelského rozhraní,
5. implementace frontendu a backendu,
6. testování,
7. nasazení a provoz,
8. údržba a rozšiřování.

Whisp je vhodný příklad, protože obsahuje jak klasickou CRUD logiku, tak realtime část, administraci, bezpečnostní vrstvu i Docker infrastrukturu.

## 8.2 Analýza požadavků

Na začátku bylo potřeba definovat, co má aplikace skutečně umět. Zadání obsahovalo:
- registraci a login,
- profil uživatele,
- realtime chat,
- soukromé i skupinové konverzace,
- ukládání historie,
- notifikace,
- administrátorskou část,
- moderní webové rozhraní,
- backend v PHP,
- databázi v Dockeru.

Analýza tedy musela zodpovědět otázku, jak tyto požadavky rozdělit do vrstev a jak zajistit, aby výsledkem nebyla jen sada izolovaných funkcí, ale koherentní aplikace.

## 8.3 Návrh systému

Ve fázi návrhu bylo potřeba rozhodnout zejména:
- jak oddělit frontend a backend,
- jak do architektury začlenit realtime komunikaci,
- jak navrhnout datové relace,
- jak reprezentovat přátelství, rooms a membershipy,
- jak řešit session invalidaci a bezpečnost.

Návrh proto skončil u čtyřvrstvé runtime architektury:
- React frontend,
- PHP REST backend,
- PHP WebSocket server,
- PostgreSQL databáze.

## 8.4 Implementace

Implementace probíhala iterativně. Prakticky to znamená, že systém nevznikl „na jeden pokus“, ale postupným doplňováním a zpřesňováním.

### Příklad postupného zrání
- nejprve základní autentizace,
- poté přátelé,
- poté chat a groups,
- poté websocket vrstva,
- následně refaktory: CORS, session kontrola, přesun SQL z controllerů, validátory,
- nakonec čistění hran a UX bugfixy.

To je přesně realistický průběh menšího softwarového projektu.

## 8.5 Testování

Testování nebylo jednorázové na konci, ale průběžné. To odpovídá zdravému vývoji i principům Agile. Průběžné testování odhalilo například:
- nesoulad route kontraktu mezi FE a BE,
- problém stale closure ve websocket handleru,
- chování DM po odebrání z přátel,
- problém s refresh logikou pending request badge,
- startup timing problém websocket kontejneru vůči DB.

## 8.6 Nasazení a provoz

Docker Compose poskytuje provozní obal projektu. To je důležitá součást SDLC fáze deploymentu. Projekt tím není jen „zdrojový kód“, ale reprodukovatelný systém, který lze:
- postavit,
- spustit,
- inicializovat,
- otestovat,
- předat další osobě.

## 8.7 Údržba a rozvoj

Po dokončení základní funkčnosti je z pohledu SDLC důležitá maintenance fáze. U Whispu to znamená:
- opravovat edge case chyby,
- čistit architekturu,
- doplňovat testy,
- stabilizovat websocket vrstvu,
- případně rozšiřovat systém o nové funkce.

## 8.8 Proč dává smysl Agile přístup

Agile neznamená chaos. Znamená iterativní práci s rychlou zpětnou vazbou. U Whispu je Agile vidět zejména v tom, že:
- nejprve vznikl funkční základ,
- poté se doplňovaly další vrstvy,
- následně probíhal refaktoring a stabilizace,
- chyby se řešily podle reálných scénářů při testování.

To je praktičtější než snažit se naplánovat veškerý detail systému zcela dokonale dopředu.

## 8.9 Scrum pohled

Projekt je sice jednotlivcový, ale i tak na něj lze aplikovat Scrum principy v odlehčené podobě.

### Product backlog
Soubor všech funkcí a úkolů:
- auth,
- friend flow,
- DM,
- group chat,
- admin panel,
- websocket sync,
- dokumentace,
- testy.

### Iterace / sprinty
Práce probíhala v tematických blocích:
- základ auth,
- chat,
- realtime,
- bezpečnostní stabilizace,
- clean architecture refaktor,
- finální bugfixy a dokumentace.

### Review a inspekce
Po každé větší změně proběhla kontrola funkčnosti a odhalení regresí. To je v malém projektu analogie sprint review.

## 8.10 Doporučení, jak projekt prezentovat u obhajoby

### Začni problémem, ne technologií
Místo „použil jsem React a PHP“ je lepší začít takto:

„Cílem bylo vytvořit plnohodnotnou realtime komunikační aplikaci, která řeší uživatele, přátelství, soukromé i skupinové chaty, administraci a provoz v Dockeru.“

### Pak ukaž architekturu
Vysvětli, že systém je rozdělen na:
- frontend,
- backend,
- websocket,
- databázi.

### Pak vysvětli klíčové datové vztahy
Obhajující bude zajímat, že rozumíš modelu:
- room,
- membership,
- messages,
- friendships,
- sessions.

### Pak ukaž jeden až dva hluboké runtime scénáře
Nejlepší scénáře k vysvětlení jsou:
1. login + websocket auth,
2. odeslání zprávy,
3. odebrání přítele a okamžitá synchronizace obou klientů.

Právě tady ukážeš, že chápeš systém end-to-end.

## 8.11 Jak vysvětlit Docker jednoduše

Místo obecné teorie řekni:

„Docker jsem použil proto, aby backend, databáze, frontend a websocket server běžely ve stejném, opakovatelném prostředí. Díky tomu lze systém snadno spustit na jiném počítači jedním compose souborem.“

To je stručné, přesné a srozumitelné.

## 8.12 Jak vysvětlit, proč je tam WebSocket zvlášť

„REST API řeší spolehlivé ukládání a pravidla. WebSocket server řeší okamžité doručování změn připojeným klientům. Proto jsou to dvě oddělené vrstvy.“

To je klíčová věta, kterou se vyplatí umět říct plynule.

## 8.13 Co přiznat jako limity projektu

Každý kvalitní projekt má i limity a je dobré je umět pojmenovat:
- websocket vrstva je single-node a drží stav v paměti,
- `ChatSocket` je funkčně bohatý, ale architektonicky přerostlý,
- rate limiting je základní, ne enterprise,
- testování je smysluplné, ale ne 100% automatizované.

Přiznání realistických limitů nepůsobí jako slabina, ale jako známka technické zralosti.

## 8.14 Co naopak vyzdvihnout jako silné stránky

- rozdělení systému do vrstev,
- realtime komunikace,
- JWT + session invalidace,
- Docker orchestrace,
- databázový model,
- administrace,
- čistší backend po refaktorech,
- schopnost opravit složité synchronizační bugy mezi dvěma klienty.

## 8.15 Shrnutí kapitoly

Z pohledu SDLC a Agile přístupu je Whisp dobrý příklad projektu, který prošel realistickým vývojem:
- od požadavků,
- přes návrh,
- implementaci,
- testování,
- refaktoring,
- až po provoz a dokumentaci.

Pro obhajobu je nejdůležitější ukázat, že nerozumíš jen jednotlivým souborům, ale vztahům mezi nimi, důvodům architektonických rozhodnutí a tomu, jak systém funguje jako celek.


---

# Soubor: 09_appendix_endpointy_eventy_soubory.md

# 9. Appendix – Endpointy, eventy a odpovědnosti souborů

## 9.1 REST endpointy

### Auth
- `POST /api/login` – login uživatele
- `POST /api/register` – registrace uživatele
- `POST /api/logout` – odhlášení a invalidace session
- `GET /api/user/me` – načtení vlastního profilu

### User a Friends
- `GET /api/users` – seznam uživatelů
- `PUT /api/users/{id}` – update uživatele
- `DELETE /api/users/{id}` – smazání uživatele
- `GET /api/friends` – seznam přátel
- `GET /api/friends/search` – vyhledání uživatelů k přidání
- `POST /api/friends/add` – odeslání žádosti o přátelství
- `POST /api/friends/accept` – přijetí žádosti
- `POST /api/friends/reject` – odmítnutí žádosti
- `GET /api/friends/requests` – pending incoming žádosti
- `POST /api/friends/remove` – odebrání přítele

### Chat a Group
- `GET /api/rooms` – seznam room uživatele
- `POST /api/chat/open` – otevřít nebo vytvořit DM
- `GET /api/messages/history` – načíst historii room
- `POST /api/messages/send` – uložit novou zprávu
- `POST /api/messages/update` – upravit zprávu
- `POST /api/messages/delete` – smazat zprávu
- `POST /api/groups/create` – vytvořit skupinu
- `GET /api/groups/members` – načíst členy skupiny
- `POST /api/groups/add-member` – přidat člena do skupiny
- `POST /api/groups/leave` – opustit skupinu
- `POST /api/groups/update` – změnit skupinu
- `POST /api/groups/kick` – odebrat člena skupiny

### Notifications
- `GET /api/notifications` – načíst unread notifikace
- `POST /api/chat/mark-read` – označit room za přečtenou

### Admin
- `GET /api/admin/dashboard`
- `GET /api/admin/users`
- `POST /api/admin/users/delete`
- `GET /api/admin/rooms`
- `POST /api/admin/rooms/delete`
- `GET /api/admin/logs`
- `GET /api/admin/users/detail`
- `GET /api/admin/chat/history`
- `POST /api/admin/create-admin`
- `GET /api/admin/rooms/detail`

## 9.2 WebSocket eventy – klient -> server

- `auth`
- `presence:set_active_room`
- `profile_change`
- `group_change`
- `group_kick`
- `message:new`
- `message_update`
- `message_delete`
- `friend_action`
- `contact_deleted`

## 9.3 WebSocket eventy – server -> klient

- `auth_ok`
- `auth_error`
- `message:new`
- `message_update`
- `message_delete`
- `notification`
- `user_status`
- `friend_update`
- `contact_update`
- `group_update`
- `kicked_from_group`
- `contact_deleted`

## 9.4 Odpovědnosti klíčových backend souborů

### `public/index.php`
Bootstrap HTTP aplikace, error handling, CORS, předání requestu routeru.

### `src/Router.php`
Mapování endpointů na controllery a základní rate limiting.

### `src/Config/Database.php`
PDO připojení do PostgreSQL.

### `src/Controllers/*`
Koordinace request/response logiky.

### `src/Models/*`
Data access a SQL logika.

### `src/Middleware/AuthMiddleware.php`
JWT + DB session auth kontrola.

### `src/Middleware/CorsMiddleware.php`
CORS allowlist a preflight handling.

### `src/Middleware/RateLimitMiddleware.php`
Základní request throttling.

### `src/Services/JWTService.php`
Generování a ověřování JWT tokenů.

### `src/Sockets/ChatSocket.php`
Realtime server, websocket auth, connection registry, event dispatch.

### `public/install_admin.php`
Seed admin účtu.

### `init.sql`
Inicializace databázového schématu.

## 9.5 Odpovědnosti klíčových frontend souborů

### `src/main.jsx`
Vstup do React aplikace.

### `src/Context/AuthContext.jsx`
Centrální auth state, Axios instance, login/register/logout.

### `src/App.jsx`
Globální orchestrátor UI, websocket připojení, aktivní chat a modal state.

### `src/Components/UserList.jsx`
Levý panel s rooms, přáteli a group konverzacemi.

### `src/Components/FriendManager.jsx`
Správa friend requestů a vyhledání uživatelů.

### `src/Components/ChatWindow.jsx`
Historie, odesílání, editace a mazání zpráv.

### `src/Components/UserProfileModal.jsx`
Profil uživatele a odebrání z přátel.

### `src/Components/GroupDetailsModal.jsx`
Detail skupiny a management členství.

### `src/Components/ProfileSetup.jsx`
Editace vlastního profilu.

### `src/Components/AdminPanel.jsx`
Admin UI nad backend admin endpointy.

### `src/utils/friendChatState.js`
Helpery pro UI logiku kolem friend/profile/chat stavů.

## 9.6 Praktická orientace v systému

Pokud chceš v kódu rychle něco dohledat, použij tento mentální model:

- když hledáš, **kam jde request**, otevři `Router.php`,
- když hledáš, **proč backend něco povolí nebo zakáže**, otevři controller a model,
- když hledáš, **proč se něco změnilo bez refresh**, otevři `ChatSocket.php` a `App.jsx`,
- když hledáš, **odkud se načítá seznam**, otevři frontend komponentu a odpovídající endpoint,
- když hledáš, **proč se něco neuloží**, ověř model a DB tabulku.


---

# Soubor: 10_soubor_po_souboru_a_obhajobne_poznamky.md

# 10. Soubor po souboru a obhajobné poznámky

## 10.1 Smysl této kapitoly

Při obhajobě nebo při technické kontrole projektu se často objeví otázka typu: „Dobře, ale co přesně dělá tento soubor a proč je tady?“ Tato kapitola proto jde záměrně po souborech a vysvětluje jejich existenci, odpovědnost a důvod umístění.

## 10.2 Backend – public vrstva

### `backend/public/index.php`
Tento soubor je front controller. Jeho úkolem je soustředit veškerý vstup do aplikace do jednoho místa.

Kdyby neexistoval, musely by jednotlivé skripty samy řešit:
- načtení autoloaderu,
- error handling,
- CORS,
- routování.

To by vedlo k duplicitám a nekonzistenci. Proto je `index.php` správně jediné místo, odkud se backend spouští.

### `backend/public/install_admin.php`
Jde o provozní seed script, nikoli o běžný endpoint aplikace. Je odděleně, protože jeho funkce je jednorázová nebo občasná – vytvoření/aktualizace admin účtu. Z hlediska architektury je správně, že není součástí běžných controllerů.

## 10.3 Backend – router a konfigurace

### `backend/src/Router.php`
Router je centrální mapa backendu. Jeho přínos je hlavně pedagogický a architektonický:
- na jednom místě je vidět celý veřejný kontrakt API,
- lze snadno dohledat, kam která URL směřuje,
- routing není rozptýlený po controllerech.

### `backend/src/Config/Database.php`
Database wrapper je infrastrukturní vrstva. Zajišťuje, že připojení k DB je řešeno jednou a konzistentně. Pokud by se DSN skládal v každé třídě zvlášť, projekt by byl hůře udržovatelný.

## 10.4 Backend – middleware

### `AuthMiddleware.php`
Je to „vyhazovač“ před controllerem. Pokud request neobsahuje validní token a session, controller se vůbec nespustí. To je správně, protože by kontroler neměl řešit opakovanou technickou kontrolu pro každý endpoint zvlášť.

### `CorsMiddleware.php`
Řeší transportní politiku pro browser. Důvod jeho existence je čistě infrastrukturní a nepřísluší controllerům.

### `RateLimitMiddleware.php`
Je jednoduchý, ale důležitý. Chránit login a podobné endpointy je vhodné i v menším projektu.

## 10.5 Backend – controllery

### `AuthController.php`
Je orientovaný na identity a session lifecycle. Je přirozené, že obsahuje login, register, me a logout. V architektuře projektu je to „vstupní brána“ uživatelské identity.

### `FriendController.php`
Jeho existence dává smysl proto, že přátelství je samostatná doména. Není to totéž co profil uživatele ani totéž co chat. Friendships mají vlastní statusy a tok request -> accept/reject -> remove.

### `ChatController.php`
Sdružuje všechny REST operace nad konverzacemi. Je to správné, protože messages, rooms a group management patří do jedné domény komunikace.

### `NotificationController.php`
Notifikace jsou oddělená oblast. Kdyby byly nacpané do `ChatController`, zhoršila by se čitelnost. Samostatný controller proto dává smysl.

### `UserController.php`
Řeší profilové a uživatelské operace. Opět jde o přirozené oddělení domény identity od přátelství a chatu.

### `AdminController.php`
Admin kontroler je širší, protože agreguje přehledy a správu nad celým systémem. To je u admin vrstvy očekávatelné.

## 10.6 Backend – modely

### `User.php`
Důvod existence: všechny SQL operace nad uživatelem mají být na jednom místě. Pokud by byly v controllerech, porušovala by se separace odpovědností.

### `Friend.php`
Obsahuje logiku přátelství a search. To je správné, protože přátelství není obecná vlastnost uživatele, ale samostatný vztah dvou uživatelů.

### `Chat.php`
Je bohatý model, protože room/message/membership logika je rozsáhlá. To je přirozené. Chat doména je funkčně nejnáročnější část systému.

### `Notification.php`
Oddělený model pro notifikace zabraňuje tomu, aby notifikační SQL bylo rozptýlené v chatu nebo controllerech.

### `Session.php`
Je důležitý pro session invalidaci. Bez něj by se auth logika v controlleru rozrůstala a session operace by nebyly centralizované.

### `Admin.php`
Admin dotazy jsou často agregační a statistikové. Samostatný admin model je proto vhodný, i když je z principu více query-heavy.

## 10.7 Backend – validators

Validační vrstva je důležitá z hlediska čistoty architektury. Každý validator má smysl tehdy, pokud reprezentuje doménu vstupů:
- auth data,
- friend data,
- chat payloady,
- admin vstupy.

To je lepší než mít jednu gigantickou validační utilitu nebo naopak vše rozházené ve controllerech.

## 10.8 Backend – services

`JWTService.php` je dobrý příklad skutečné service vrstvy. Neobsahuje SQL a není doménově svázaný s jedním controllerem. Poskytuje obecnou službu – tvorbu a validaci tokenů.

## 10.9 Backend – websockety

### `ChatSocket.php`
Tento soubor je dnes záměrně nerozdělený, protože to bylo výslovné omezení projektu. Funkčně však dává smysl – drží realtime logiku pohromadě.

Při obhajobě je férové říct:
- ano, je přerostlý,
- ano, v produkčním enterprise systému by se rozdělil,
- ale v současném projektu je to stále čitelné a funkčně správné těžiště realtime vrstvy.

## 10.10 Frontend – context

### `AuthContext.jsx`
Na frontendu je to klíčový soubor. Nese odpovědnost za auth state celé aplikace. Kdyby tato logika byla rozptýlená po komponentách, aplikace by byla nestabilní a těžko udržovatelná.

## 10.11 Frontend – hlavní orchestrátor

### `App.jsx`
Je to záměrně nejbohatší komponenta klienta. Důvod je jednoduchý: právě zde se setkávají různé dimenze stavu systému:
- auth,
- websocket,
- aktivní chat,
- modaly,
- unread notifikace,
- friend request count,
- přechod do admin režimu.

To není „špatně navržená komponenta“, ale logický orchestrátor. Přesto platí, že je potřeba hlídat, aby nepřerostl přes rozumnou mez. V projektu se proto některé logiky postupně vytahovaly do helperů.

## 10.12 Frontend – komponenty

### `Login.jsx` a `Register.jsx`
Čisté auth formuláře. Mají být jednoduché a tenké, protože hlavní auth logika patří do contextu a backendu.

### `UserList.jsx`
Je to smart komponenta, protože skládá data ze dvou endpointů do jednoho vizuálního seznamu. To je rozumné – backend nevrací přesně stejný tvar dat, jaký UI potřebuje, takže frontend zde provádí prezentační sloučení.

### `FriendManager.jsx`
Je to sociální panel a workflow manager. Vede uživatele přes search, pending requesty a jejich zpracování.

### `ChatWindow.jsx`
Obsahuje nejvíc interakční logiky uvnitř jedné komponenty. To dává smysl, protože zprávy, reply, editace, mazání a scroll logika patří do jednoho souvislého UI celku.

### `UserProfileModal.jsx`
Je to profilový detail a zároveň místo, kde se vykonávají citlivé akce nad vztahem k jinému uživateli.

### `GroupDetailsModal.jsx`
Je to administrační vrstva pro group room z pohledu běžného uživatele.

### `ProfileSetup.jsx`
Je izolovaný editor vlastního profilu. To je správné i z UX hlediska – odděluje „nastavení sebe“ od běžného chat flow.

### `AdminPanel.jsx`
Je samostatný pracovní režim aplikace. Jeho oddělení do jedné velké komponenty je přijatelné, protože admin UI má jiný účel než hlavní chat prostředí.

## 10.13 Proč je v projektu `utils/friendChatState.js`

Vznik tohoto helperu je dobrým příkladem řízeného clean-upu. Některé drobné, ale kritické logiky – například kdy zavřít chat po odebrání přítele – je vhodné mít v malé izolované utilitě. Ne proto, že by to byl velký kus kódu, ale proto, že jde o opakovatelnou podmínku se silným dopadem na UX.

## 10.14 Co je v projektu záměrně pragmatické

Je důležité umět odlišit „nedokonalost“ od „vědomě pragmatického rozhodnutí“.

### Pragmatická rozhodnutí ve Whispu
- vlastní router místo frameworku,
- samostatný websocket proces bez Redis vrstvy,
- PDO bez ORM,
- Vite dev server místo produkčního static serveru,
- jednodušší admin seed přes PHP script.

To nejsou chyby. Jsou to rozhodnutí odpovídající cíli projektu a jeho rozsahu.

## 10.15 Co by se v budoucnu dalo dále čistit

- rozdělit `ChatSocket.php`,
- postupně zavést více integračních testů,
- přidat healthcheck orchestrace pro websocket proti DB,
- sjednotit ještě více response helperů,
- doplnit další indexy podle skutečného provozu.

## 10.16 Shrnutí kapitoly

Při obhajobě se vyplatí nepůsobit dojmem, že „každý soubor tam je náhodou“. Tato kapitola ukazuje, že každý důležitý soubor má v systému svou logickou roli. To je přesně to, co od architektonicky chápaného projektu očekává hodnotitel i technický oponent.
