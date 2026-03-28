# 12 – Sprint Progress (Průběh vývoje)

Tento dokument zachycuje realistickou časovou osu vývoje projektu Whisp.
Každá iterace má cíl, výstupy a poznámky z průběhu.

---

## Sprint 0 – Setup & návrh
**Období:** 18. 11. 2025 – 24. 11. 2025

**Cíl:** Spustit vývojové prostředí a navrhnout databázi.

**Výstupy:**
- Inicializace Git repozitáře se strukturou složek
- Návrh databázového schématu — 9 tabulek, UUID klíče, soft delete
- Docker Compose se 4 kontejnery (db, backend, websocket, frontend)
- Proof-of-concept Ratchet WebSocket serveru

**Poznámky:** Nejvíce času zabralo rozhodování o architektuře.
Klíčové rozhodnutí: REST pro CRUD, WebSocket výhradně pro real-time.

---

## Sprint 1 – Auth + základ profilu (MVP)
**Období:** 25. 11. 2025 – 8. 12. 2025

**Cíl:** Funkční registrace, přihlášení a JWT autentizace.

**Výstupy:**
- AuthController (register, login, logout, me)
- JWTService (generate, decode) s firebase/php-jwt
- AuthMiddleware — double validation (JWT + DB session)
- User model (create, findById, findByEmail, updateStatus, logActivity)
- Session model (create, deactivateByToken)
- React: Login.jsx, Register.jsx, AuthContext.jsx s Axios interceptory
- Activity logging: LOGIN, LOGOUT, REGISTER

**Poznámky:** Double validation (JWT + sessions tabulka) byl klíčový bezpečnostní
kompromis. JWT by zůstal platný po odhlášení bez DB kontroly.

---

## Sprint 2 – Systém přátel
**Období:** 9. 12. 2025 – 22. 12. 2025

**Cíl:** Kompletní systém přátelství.

**Výstupy:**
- Friend model (sendRequest, acceptRequest, rejectRequest, remove)
- Friend model: getFriends, getPendingRequests, searchAvailableUsers
- FriendController (add, accept, reject, remove, index, requests, search)
- Ochrana: admini nejsou vyhledatelní
- FriendManager.jsx s záložkami Hledat a Žádosti
- UserList.jsx — sidebar s přáteli a jejich statusy

**Poznámky:** Rozhodnutí filtrovat admin účty z vyhledávání přišlo přirozeně
při testování — admin by jinak mohl být přidán jako přítel.

---

## Sprint 3 – Chat (REST vrstva)
**Období:** 6. 1. 2026 – 19. 1. 2026

**Cíl:** Ukládání a čtení zpráv, základní chat UI.

**Výstupy:**
- Chat model (getUserRooms, getOrCreateDmRoom, canAccessRoom)
- Chat model (getRoomMessages, sendMessage, deleteMessage, editMessage)
- ChatController (getRooms, openDm, sendMessage, getHistory, deleteMessage, updateMessage)
- ChatWindow.jsx — zobrazení zpráv, vstupní pole, edit, delete
- Soft delete implementace (is_deleted flag)
- Reply/citace zpráv (reply_to_id FK)

**Poznámky:** `canAccessRoom()` je bezpečnostně klíčová funkce — ověří nejen
membership, ale i aktivní přátelství pro DM místnosti.

---

## Sprint 4 – Real-time komunikace
**Období:** 20. 1. 2026 – 2. 2. 2026

**Cíl:** WebSocket server s JWT autentizací a real-time doručením zpráv.

**Výstupy:**
- ChatSocket.php — plná implementace Ratchet MessageComponentInterface
- JWT autentizace přes první `auth` zprávu (ne query parameter)
- Multi-tab podpora: $userConnections[userId][resourceId]
- Broadcast message:new, message_update, message_delete
- Online/Offline status — automatická aktualizace při connect/disconnect
- Presence tracking (activeRoomId v connMeta)

**Poznámky:** Multi-tab podpora byla nejsložitější část. Bez ní by zavření
jedné ze dvou záložek uživatele nesprávně označilo jako offline.

---

## Sprint 5 – Skupiny + notifikace
**Období:** 3. 2. 2026 – 16. 2. 2026

**Cíl:** Skupinové chaty s kompletní správou a notifikacemi.

**Výstupy:**
- Chat model: createGroup, addGroupMember, removeGroupMember, leaveGroupSafe
- Chat model: getMemberRole, updateGroupInfo, deleteRoom
- ChatController: createGroup, getGroupMembers, addGroupMember, leaveGroup, updateGroup, kickMember
- WS eventy: group_kick, kicked_from_group, group_change, group_update
- Notification model (createMessageNotification, getUnreadByUserId, markAsRead)
- NotificationController (getUnread, markRead)
- Notifikační logika v ChatSocket (presence check)
- GroupDetailsModal.jsx, CreateGroupModal.jsx

**Poznámky:** Logika předání vlastnictví skupiny při odchodu admina
(`leaveGroupSafe`) je transakční operace — musela být v jedné transakci.

---

## Sprint 6 – Admin panel
**Období:** 17. 2. 2026 – 23. 2. 2026

**Cíl:** Administrátorský panel s audit logy.

**Výstupy:**
- Admin model (getDashboardStats, getUsers, getRooms, getLogs, getRoomHistory)
- AdminController (dashboard, users, rooms, logs, deleteUser, deleteRoom, createAdmin)
- Ochrana: nelze smazat posledního admina, admin nemůže smazat sám sebe
- AdminPanel.jsx (4 záložky: Přehled, Uživatelé, Místnosti, Logy)
- HTTP polling pro admin data (30s interval, ne WebSocket)

**Poznámky:** Záměrné rozhodnutí použít HTTP polling pro admin panel místo
WebSocket — admin potřebuje konzistentní přehled, ne nutně real-time.

---

## Sprint 7 – Bezpečnostní hardening
**Období:** 24. 2. 2026 – 2. 3. 2026

**Cíl:** Zabezpečit aplikaci standardními bezpečnostními praktikami.

**Výstupy:**
- RateLimitMiddleware — 4 limitovaná endpointy
- CorsMiddleware — dynamický whitelist z ENV
- Dedikované Validator třídy pro všechny domény
- Sjednocení error responses (ApiResponse třída)
- Global exception handler v index.php
- Přesun JWT_SECRET do ENV proměnných

**Poznámky:** Největší práce bylo sjednocení error responses — původně
jednotlivé Controllery míchaly přímé echo s ApiResponse.

---

## Sprint 8 – Testování + CI/CD
**Období:** 3. 3. 2026 – 16. 3. 2026

**Cíl:** Unit testy a automatizovaná CI pipeline.

**Výstupy:**
- AuthValidatorTest.php — 19 testů (login, register, hraniční případy, DataProvider)
- FriendAndChatValidatorTest.php — 18 testů
- JWTServiceTest.php — 11 testů (generate, decode, expired, wrong secret)
- Login.test.jsx — 5 Vitest testů
- ci.yml — GitHub Actions pipeline (PHP 8.2 + Node 20)
- Výsledek: Tests: 49, Assertions: 59, OK ✔

**Poznámky:** Psaní testů odhalilo několik edge cases v validátorech,
které by jinak přešly bez povšimnutí.

---

## Sprint 9 – Docker optimalizace a deployment
**Období:** 17. 3. 2026 – 23. 3. 2026

**Cíl:** Produktionové Dockerfiles a deployment dokumentace.

**Výstupy:**
- Optimalizované Dockerfiles pro backend a frontend
- Deployment guide (docs/15_deployment.md)
- .env.example soubor
- .gitignore aktualizace (.phpunit.result.cache)

---

## Sprint 10 – Finalizace a obhajoba
**Období:** 24. 3. 2026 – 31. 3. 2026

**Cíl:** Finální dokumentace, bugfixing, příprava obhajoby.

**Výstupy:**
- Kompletní dokumentace (15 MD souborů)
- Závěrečné čistění kódu (debug logy, zbytečné komentáře)
- Aktualizace README souborů
- Příprava prezentace

---

## Celkový přehled

| Metrika | Hodnota |
|---------|---------|
| Celková délka vývoje | ~4,5 měsíce |
| Počet sprintů | 10 |
| PHP soubory | 25+ |
| JSX komponenty | 12 |
| Unit testů | 54 (49 PHP + 5 JS) |
| REST endpointů | 35+ |
| WS event typů | 17 |
| DB tabulek | 9 |
| Docker kontejnerů | 4 |
