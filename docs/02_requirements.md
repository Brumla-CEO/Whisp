# 02 – Požadavky (Requirements)

Tato kapitola definuje požadavky ve dvou vrstvách:
- **FR (Functional Requirements)** — co systém musí umět
- **NFR (Non-Functional Requirements)** — jaké vlastnosti musí splňovat

Požadavky byly sepsány před zahájením implementace a sloužily jako záchytné body
při plánování sprintů i zpětném hodnocení.

---

## Funkční požadavky (FR)

### FR-01 Registrace
Systém umožní registraci nového uživatele.

**Vstup:** username, email, password  
**Chování:**
- username i email musí být unikátní v celém systému
- heslo se hashuje algoritmem bcrypt (`password_hash(PASSWORD_DEFAULT)`)
- uživateli se přiřadí výchozí role `user`
- avatar se inicializuje jako DiceBear URL odvozená od username
- po úspěšné registraci se vrátí JWT token a user objekt

**Akceptační kritéria:**
- duplicitní username vrátí HTTP 409
- duplicitní email vrátí HTTP 409
- heslo kratší než 6 znaků vrátí HTTP 400
- neplatný formát emailu vrátí HTTP 400

### FR-02 Přihlášení
Uživatel se přihlásí pomocí emailu a hesla.

**Chování:**
- při úspěchu se vytvoří záznam v tabulce `sessions` (is_active = TRUE)
- vrátí se JWT token s expirací 24 hodin a user objekt
- status uživatele se nastaví na `online`
- zaznamená se activity log s akcí LOGIN

**Akceptační kritéria:**
- neplatné přihlašovací údaje vrátí HTTP 401
- přihlášení generuje novou session při každém přihlášení

### FR-03 Odhlášení
Uživatel se odhlásí a session se deaktivuje.

**Chování:**
- session záznam se označí `is_active = FALSE`
- status uživatele se nastaví na `offline`
- token přestane být platný okamžitě (bez čekání na expiraci)

### FR-04 Správa profilu
Přihlášený uživatel může spravovat svůj profil.

**Operace:**
- zobrazit vlastní profil (`GET /api/user/me`)
- upravit profil — username, email, bio, avatar URL (`PUT /api/users/{id}`)
- smazat vlastní účet (`DELETE /api/users/{id}`)

**Akceptační kritéria:**
- uživatel může upravovat pouze vlastní profil
- admin může smazat jakýkoli účet
- smazání účtu kaskádově odstraní sessions, friendships a memberships
- zprávy smazaného uživatele zůstávají v DB se sender_id = NULL

### FR-05 Systém přátel
Uživatel může spravovat síť přátel.

**Operace:**
- vyhledat uživatele podle username (`GET /api/friends/search?q=...`)
- odeslat žádost o přátelství (`POST /api/friends/add`)
- přijmout žádost (`POST /api/friends/accept`)
- odmítnout žádost (`POST /api/friends/reject`)
- zobrazit seznam přátel (`GET /api/friends`)
- odebrat přítele (`POST /api/friends/remove`)
- zobrazit příchozí žádosti (`GET /api/friends/requests`)

**Akceptační kritéria:**
- admin účty nejsou vyhledatelné ani přidatelné jako přátelé
- nelze přidat sám sebe
- nelze odeslat duplicitní žádost

### FR-06 Chat — zprávy
Uživatel může komunikovat s ostatními uživateli.

**Operace:**
- zobrazit seznam místností (`GET /api/rooms`)
- otevřít DM chat s přítelem (`POST /api/chat/open`)
- načíst historii zpráv (`GET /api/messages/history?room_id=...`)
- odeslat zprávu (`POST /api/messages/send`)
- upravit vlastní zprávu (`POST /api/messages/update`)
- smazat vlastní zprávu (`POST /api/messages/delete`)

**Akceptační kritéria:**
- soukromý DM lze otevřít pouze s aktuálním přítelem
- smazání zprávy je soft delete (is_deleted = TRUE), obsah se nahradí "Odstraněno"
- citace (reply) na smazanou zprávu zůstávají zachovány

### FR-07 Skupinové chaty
Uživatel může vytvářet a spravovat skupinové konverzace.

**Operace:**
- vytvořit skupinu (`POST /api/groups/create`)
- přidat člena skupiny (`POST /api/groups/add-member`)
- opustit skupinu (`POST /api/groups/leave`)
- upravit název/avatar skupiny (`POST /api/groups/update`)
- vyhodit člena (`POST /api/groups/kick`)
- zobrazit členy skupiny (`GET /api/groups/members`)

**Akceptační kritéria:**
- skupina musí mít alespoň 3 členy (zakladatel + min. 2 přátelé)
- kick a update jsou dostupné jen adminovi skupiny
- při odchodu posledního admina se vlastnictví předá nejdéle přihlášenému členu

### FR-08 Notifikace
Systém informuje uživatele o nepřečtených zprávách.

**Chování:**
- notifikace vzniká pouze pokud příjemce nemá danou místnost aktivní (presence tracking)
- notifikace se zobrazí jako badge v seznamu místností
- přečtení označí notifikaci jako is_read = TRUE

### FR-09 Admin panel
Administrátor má přístup k rozšířeným nástrojům správy.

**Operace:**
- dashboard se statistikami (počty uživatelů, místností, zpráv, online)
- seznam a správa uživatelů (zobrazení, smazání)
- seznam a správa místností (zobrazení, smazání, history)
- audit logy aktivit
- vytvoření nového admin účtu

**Akceptační kritéria:**
- nelze smazat posledního administrátora
- admin nemůže smazat sám sebe
- všechny admin akce se logují

---

## Nefunkční požadavky (NFR)

### NFR-01 Bezpečnost
- Hesla nesmí být uložena v plaintextu — povinně bcrypt
- Každý API požadavek musí být ověřen JWT tokenem
- JWT token musí mít aktivní session v DB (server-side allow-list)
- Veškerý SQL přístup přes PDO prepared statements (SQL Injection prevention)
- CORS musí povolovat pouze přesně definované domény (ENV whitelist)
- Klíčové endpointy musí být chráněny Rate Limitingem
- Chybové odpovědi API nesmí odhalovat interní detaily (stack trace, SQL)

### NFR-02 Real-time výkon
- Zprávy musí být doručeny ostatním uživatelům do 1 sekundy
- Notifikace musí respektovat presence tracking (neposílat zbytečné notifikace)
- Online/Offline status musí být aktualizován automaticky bez explicitního API volání

### NFR-03 Portabilita a nasaditelnost
- Projekt musí jít spustit příkazem `docker compose up --build` bez ruční instalace
- Všechny citlivé hodnoty (DB hesla, JWT secret) musí být v ENV proměnných
- Aplikace musí fungovat na Windows, macOS i Linux

### NFR-04 Udržovatelnost kódu
- Backend musí dodržovat MVC vzor — Controller nevolá SQL přímo, používá Model
- Validace vstupů musí být v dedikovaných Validator třídách
- API odpovědi musí mít konzistentní strukturu (ApiResponse třída)
- Kód nesmí obsahovat debug výpisy (`error_log` debugging, `var_dump`) v produkci

### NFR-05 Testovatelnost
- Klíčová business logika (validátory, JWT service) musí být pokryta unit testy
- CI pipeline musí automaticky spouštět testy při každém push

---

## Prioritizace (MoSCoW)

| Požadavek | Priorita |
|-----------|----------|
| FR-01 až FR-03 (Auth) | Must Have |
| FR-06 (Chat) | Must Have |
| FR-05 (Přátelé) | Must Have |
| FR-07 (Skupiny) | Should Have |
| FR-08 (Notifikace) | Should Have |
| FR-09 (Admin) | Could Have |
| NFR-01 (Bezpečnost) | Must Have |
| NFR-02 (Real-time) | Must Have |
| NFR-03 (Docker) | Must Have |
| NFR-04 (Kód) | Should Have |
| NFR-05 (Testy) | Should Have |
