# Backlog (produktový)

Backlog je seřazený od nejdůležitějších věcí (MVP) po „nice to have“. Každá položka je user story (deliverable).

## Epic E1: Auth & základ uživatele (MVP)
### US-1 Registrace
Jako návštěvník chci registraci, abych mohl používat aplikaci.
- AC: unikátní username/email, hash hesla, vrátit token + user

### US-2 Přihlášení
Jako uživatel chci login, abych mohl přistupovat k chatům.
- AC: vytvořit session record, vrátit token + user

### US-3 Odhlášení
Jako uživatel chci logout, abych ukončil session.
- AC: nastavit session is_active=false

### US-4 Profil
Jako uživatel chci upravit profil (bio/avatar), aby mě ostatní poznali.

## Epic E2: Přátelé (MVP)
### US-5 Vyhledávání uživatelů
Jako uživatel chci hledat uživatele, abych je mohl přidat.

### US-6 Žádost o přátelství
Jako uživatel chci poslat žádost, abych navázal kontakt.

### US-7 Přijetí/odmítnutí žádosti
Jako uživatel chci spravovat žádosti, abych kontroloval kontakty.

### US-8 Seznam přátel
Jako uživatel chci seznam, abych viděl kontakty.

## Epic E3: Chat (MVP)
### US-9 DM místnosti
Jako uživatel chci otevřít přímý chat s přítelem.

### US-10 Posílání zpráv
Jako uživatel chci posílat zprávy do room.

### US-11 Historie zpráv
Jako uživatel chci historii zpráv.

### US-12 Editace a mazání zpráv
Jako autor zprávy chci upravit/smazat zprávu.

## Epic E4: Skupiny (v1)
### US-13 Vytvoření skupiny
Jako uživatel chci vytvořit skupinu a pozvat členy.

### US-14 Správa členů
Jako admin skupiny chci přidat/kicknout členy a opustit skupinu.

### US-15 Úprava skupiny
Jako admin skupiny chci změnit název/avatar.

## Epic E5: Realtime (v1)
### US-16 Realtime zprávy
Jako uživatel chci, aby se zprávy zobrazily bez refresh.

### US-17 Presence & notifikace
Jako uživatel chci notifikace pouze když nejsem v aktivní místnosti.

## Epic E6: Admin (v1)
### US-18 Dashboard
Jako admin chci přehled statistik.

### US-19 Správa uživatelů a místností
Jako admin chci mazat problematické uživatele/místnosti.

### US-20 Audit logy
Jako admin chci vidět logy aktivit.

## Epic E7: Hardening (P0/P1)
- sjednocení CORS
- přesun JWT secret do ENV
- kontrola expires_at v sessions
- rate limiting
- jednotný error kontrakt
- transakce pro multi-step operace

