# User guide (uživatelský návod)

## 1) Spuštění aplikace
Aplikaci spustíš přes Docker:
```bash
docker compose up --build
```

Poté otevři:
- UI: `http://localhost:5173`

## 2) Registrace / přihlášení
- nejdřív registrace (username/email/password)
- potom login (email/password)
- po přihlášení se session drží v `localStorage`

## 3) Přátelé
- v části FriendManager můžeš vyhledat uživatele
- poslat žádost
- v Requests přijmout/odmítnout
- accepted přátelé se zobrazí v seznamu

## 4) Chat
- Rooms list zobrazuje DM i skupiny
- otevření DM: vybereš přítele
- posílání zpráv: textarea + send
- edit/mazání zprávy: pouze autor

## 5) Skupiny
- vytvoření skupiny: CreateGroupModal (název + členové)
- správa skupiny: GroupDetailsModal (members, kick, leave)
- při kicku přijde realtime event `kicked_from_group`

## 6) Notifikace
- notifikace vznikají, pokud nejsi v aktivní místnosti
- po otevření room se notifikace označí jako přečtené (`/api/chat/mark-read`)

## 7) Admin
Pokud máš roli `admin`, uvidíš AdminPanel:
- dashboard
- správa users/rooms
- logs

