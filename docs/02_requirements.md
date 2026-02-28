# Požadavky

Tato kapitola popisuje požadavky ve dvou vrstvách:
- **FR (Functional Requirements)** – co systém umí,
- **NFR (Non‑Functional Requirements)** – jaké vlastnosti musí splňovat.

## FR – funkční požadavky

### FR-01 Registrace
Systém umožní registraci uživatele s:
- username, email, password
- implicitní role `user`
- inicializace `avatar_url` na default

**Akceptační kritéria:**
- username i email musí být unikátní
- heslo se neukládá v plaintextu (hash)
- po registraci vrací token a user payload

### FR-02 Přihlášení
Uživatel se přihlásí přes email + password.

**Akceptační kritéria:**
- při úspěchu vznikne session record v DB
- API vrátí JWT token + uživatele
- uživatelův status se nastaví na `online`

### FR-03 Odhlášení
Uživatel se odhlásí, session se deaktivuje.

### FR-04 Profil
Uživatel může:
- zobrazit svůj profil (`/api/user/me`)
- upravit svůj profil (`PUT /api/users/{id}`)
- smazat svůj účet (`DELETE /api/users/{id}`)

### FR-05 Přátelé
Uživatel může:
- hledat uživatele
- poslat žádost o přátelství
- přijmout / odmítnout žádost
- zobrazit seznam přátel
- odebrat přítele

### FR-06 Chat
Uživatel může:
- zobrazit své rooms
- otevřít DM s vybraným uživatelem (nebo vytvořit, pokud neexistuje)
- posílat zprávy, odpovídat na zprávy, upravovat, mazat
- vytvořit skupinu, spravovat členy, opustit ji
- měnit název/avatar skupiny, kickovat člena (admin role v room)

### FR-07 Notifikace
Systém vytváří notifikace pro nové zprávy, pokud příjemce není aktivní v dané místnosti.

### FR-08 Admin
Admin může:
- přehled statistik
- spravovat uživatele a místnosti
- prohlížet logy aktivit
- vytvořit admin účet (instalační endpoint)

## NFR – nefunkční požadavky

### NFR-01 Bezpečnost
- JWT token musí být validován
- token musí existovat jako aktivní session
- vstupy musí být validované
- CORS musí být konzistentní

### NFR-02 Výkon
- historie zpráv musí být paginovatelná (aktuálně není)
- vyhledávání uživatelů musí být limitované

### NFR-03 Udržitelnost
- Controller by neměl obsahovat SQL (preferovat Model/Repository)
- standardizovaný JSON error formát

### NFR-04 Nasaditelnost
- projekt musí jít spustit přes Docker Compose bez ruční instalace DB
- dokumentace musí popsat lokální vývoj i Docker variantu

