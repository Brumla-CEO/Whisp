# Whisp – kompletní manuální testovací scénář

Tento soubor je navržený jako praktický checklist pro finální ověření aplikace **Whisp** po všech úpravách. Cílem je projít systém od registrace až po administraci a ověřit, že:

- funguje autentizace,
- funguje práce s profily,
- funguje přátelství a žádosti,
- fungují DM a skupiny,
- fungují notifikace a websocket synchronizace,
- fungují admin funkce,
- aplikace se nerozsypává po refreshi, odhlášení nebo neplatných akcích.

---

# 1. Příprava prostředí

Než začneš testovat:

1. Spusť celý projekt.
2. Ověř, že běží:
   - frontend,
   - backend,
   - websocket server,
   - databáze.
3. Otevři si alespoň **dva různé prohlížeče** nebo jedno okno + anonymní režim.
4. Připrav si minimálně tyto účty:
   - **admin**
   - **user A**
   - **user B**
   - **user C**
   - volitelně **user D** pro skupinové a hraniční testy

Doporučené rozložení:
- Prohlížeč 1: user A
- Prohlížeč 2: user B
- Prohlížeč 3 / anonymní okno: user C
- Admin v samostatném okně

---

# 2. Test účtů a přihlášení

## 2.1 Registrace nových účtů
Proveď registraci alespoň 3–4 běžných účtů.

### Ověř:
- registrace projde bez chyby,
- po registraci se uživatel může přihlásit,
- nelze registrovat duplicitní username,
- nelze registrovat duplicitní email,
- neplatný / prázdný formulář se korektně odmítne,
- backend vrací rozumnou chybu a frontend ji zobrazí.

### Scénáře:
- registrace s validními daty,
- registrace bez username,
- registrace bez emailu,
- registrace bez hesla,
- registrace s duplicitním emailem,
- registrace s duplicitním username.

---

## 2.2 Login / logout
U každého testovacího účtu proveď:

1. přihlášení,
2. refresh stránky,
3. odhlášení,
4. znovu přihlášení.

### Ověř:
- login funguje,
- po refreshi uživatel zůstane přihlášený,
- websocket se po loginu připojí,
- po logoutu se stav vyčistí,
- po logoutu nejde otevřít chráněné endpointy,
- po novém loginu vše funguje znovu.

### Hraniční testy:
- špatné heslo,
- neexistující účet,
- login s prázdnými poli.

---

# 3. Test vlastního profilu

Přihlas se jako user A.

## Ověř:
- v hlavičce se zobrazuje správné username,
- po kliknutí na vlastní profil se otevře profilový modal,
- na vlastním profilu **není** tlačítko „Odebrat z přátel“,
- lze upravit profilové údaje podle toho, co aplikace dovoluje,
- změna profilovky / bio / jména se po uložení projeví,
- změny se po refreshi zachovají.

### Doporučené akce:
- změň bio,
- změň avatar / profilovou fotku,
- ověř refresh,
- ověř zobrazení změn v jiném okně jiného uživatele.

---

# 4. Test vyhledávání uživatelů a žádostí o přátelství

## 4.1 Odeslání žádosti
Jako user A vyhledej user B a pošli mu žádost.

### Ověř:
- user B dostane notifikaci / tečku / indikaci,
- žádost se zobrazí v pending requests,
- user A nepošle žádost sám sobě,
- duplicitní žádost nejde vytvořit,
- nelze poslat žádost neexistujícímu uživateli.

---

## 4.2 Odmítnutí žádosti
Jako user B žádost od user A odmítni.

### Ověř:
- request zmizí ze seznamu pending,
- notifikační tečka zhasne ihned,
- user A není přidán do přátel,
- user A vidí po refreshi správný stav,
- žádost se nevrací zpět po obnovení stránky.

---

## 4.3 Znovuodeslání a přijetí žádosti
Pošli znovu žádost A → B a tentokrát ji přijmi.

### Ověř:
- request zmizí z pending,
- přátelství se objeví v seznamu přátel na obou stranách,
- notifikace se zaktualizuje,
- přátelství je vidět i po refreshi,
- websocket refreshne seznam bez nutnosti F5, pokud je to implementované.

---

# 5. Test seznamu přátel

Po přijetí přátelství mezi A a B ověř:

- oba uživatelé se vidí jako přátelé,
- stav online/offline se synchronizuje,
- kliknutí na přítele otevře správný profil nebo chat,
- profil přítele zobrazuje tlačítko „Odebrat z přátel“ pouze u cizího uživatele,
- žádný jiný uživatel se omylem nezobrazí v přátelích.

---

# 6. Test odebrání z přátel

Toto je kritický test.

## Scénář 1 – bez otevřeného chatu
1. User A odebere user B z přátel.

### Ověř:
- přátelství zmizí na obou stranách,
- po refreshi se nevrátí,
- user B také okamžitě nebo po refreshi vidí, že už nejsou přátelé,
- DM už nelze nově otevřít.

---

## Scénář 2 – oba mají otevřený DM
1. User A a user B si otevřou vzájemný DM.
2. User A otevře profil user B.
3. User A klikne na „Odebrat z přátel“.

### Ověř:
#### U user A:
- chat se zavře okamžitě,
- UI se vrátí do defaultního stavu,
- nelze dále posílat zprávy,
- nezůstane viset starý chat v hlavním panelu.

#### U user B:
- chat se zavře okamžitě bez F5,
- UI se vrátí do defaultního stavu,
- pokud se pokusí psát, nesmí to projít,
- po kliknutí zpět na chat nesmí jít DM znovu otevřít.

### Ověř navíc:
- popupy / alerty nejsou přehnaně duplicitní,
- pokud nějaký alert je, dává smysl a není jich několik za sebou.

---

# 7. Test přímých zpráv (DM)

## 7.1 Otevření DM
Jako user A otevři chat s user B.

### Ověř:
- backend vrátí room / DM správně,
- chat se zobrazí,
- historie se načte,
- refresh stránky nepoškodí stav,
- při opakovaném otevření se nevytváří zbytečně nový DM room.

---

## 7.2 Posílání zpráv
Pošli mezi A a B několik zpráv.

### Ověř:
- zpráva se uloží,
- zpráva se objeví ihned na obou stranách,
- websocket doručí update v reálném čase,
- notifikace funguje, pokud druhý uživatel nemá aktivně otevřený ten samý chat,
- pokud druhý uživatel chat otevřený má, systém se nechová jako nepřečtená notifikace navíc.

---

## 7.3 Editace a mazání zpráv
Pokud aplikace umí upravit a smazat zprávy, otestuj:

- edit vlastní zprávy,
- smazání vlastní zprávy,
- synchronizaci změny na druhé straně,
- po refreshi zůstává správný stav,
- cizí zprávu nelze editovat nebo mazat, pokud to role nesmí.

---

# 8. Test skupin

## 8.1 Vytvoření skupiny
Jako user A vytvoř skupinu a přidej do ní B a C.

### Ověř:
- skupina vznikne,
- všichni členové ji vidí v seznamu,
- název a avatar skupiny se zobrazují správně,
- historie a členové skupiny se načtou,
- websocket změnu doručí ostatním.

---

## 8.2 Posílání zpráv ve skupině
Ve skupině pošli několik zpráv od různých uživatelů.

### Ověř:
- zprávy se zobrazují všem členům,
- notifikace fungují správně,
- člen mimo skupinu skupinu nevidí,
- po refreshi je vše stále konzistentní.

---

## 8.3 Přidání člena do skupiny
Jako admin skupiny přidej user D.

### Ověř:
- nový člen skupinu uvidí,
- může číst historii podle pravidel aplikace,
- websocket doručí update,
- ostatní členové vidí změnu členství.

---

## 8.4 Opuštění skupiny
Jako běžný člen skupiny skupinu opusť.

### Ověř:
- skupina zmizí ze seznamu,
- nelze do ní dál psát,
- nelze otevřít její historii,
- po refreshi se nevrátí.

---

## 8.5 Vyhození člena ze skupiny
Jako admin skupiny vyhoď user B.

### Ověř:
- user B dostane okamžité upozornění,
- otevřený group chat se u user B zavře hned,
- user B se vrátí na defaultní obrazovku,
- user B už do skupiny nemůže psát,
- po refreshi skupina už v seznamu není.

---

# 9. Test notifikací

## 9.1 Žádosti o přátelství
Ověř:
- notifikační tečka se rozsvítí po příchozí žádosti,
- po přijetí nebo odmítnutí ihned zhasne,
- po refreshi zůstává správný počet.

## 9.2 Nové zprávy
Ověř:
- při příchozí zprávě do neaktivního chatu se objeví indikace nepřečtené zprávy,
- po otevření chatu a označení jako přečtené tečka zmizí,
- po refreshi není stav rozbitý.

## 9.3 Hraniční scénáře
- více žádostí najednou,
- více nepřečtených chatů,
- notifikace po logout/login,
- notifikace po refreshi.

---

# 10. Test websocket synchronizace

Použij alespoň dva uživatele současně.

## Ověř realtime scénáře:
- změna online/offline stavu,
- příchozí žádost o přátelství,
- přijetí žádosti,
- odmítnutí žádosti,
- odebrání z přátel,
- nová zpráva v DM,
- nová zpráva ve skupině,
- vyhození ze skupiny,
- změna skupinového detailu,
- změna profilu uživatele.

### Sleduj:
- zda změny přijdou druhému uživateli bez F5,
- zda se po reconnectu websocketu aplikace nerozsype,
- zda po logoutu socket opravdu skončí,
- zda po dalším loginu socket znovu funguje.

---

# 11. Test refreshů a persistence stavu

U několika scénářů proveď refresh stránky:

- po loginu,
- při otevřeném DM,
- při otevřené skupině,
- po přijetí žádosti,
- po odmítnutí žádosti,
- po odebrání z přátel,
- po změně profilu,
- po vytvoření skupiny.

### Ověř:
- aplikace se po refreshi nezasekne,
- token/session jsou stále validní,
- zobrazená data odpovídají realitě,
- websocket se znovu naváže,
- UI není v rozporu s databází.

---

# 12. Test logout a opětovného loginu

Pro user A a B otestuj:

1. login,
2. otevření chatu,
3. logout,
4. login znovu.

### Ověř:
- socket se po logoutu uzavře,
- po loginu se obnoví,
- starý stav aplikace nezůstane viset,
- nepřihlášený uživatel nevidí chráněná data.

---

# 13. Negativní testy běžného uživatele

Vyzkoušej akce, které by neměly projít:

- odeslat žádost sobě,
- otevřít DM s uživatelem, který už není přítel,
- ručně obnovit starý chat po odebrání z přátel,
- poslat zprávu do skupiny, kde už nejsi člen,
- otevřít historii skupiny, ze které jsi byl vyhozen,
- otevřít profil s neexistujícím ID, pokud to UI dovolí,
- dvojklikem / více kliky odeslat více stejných akcí za sebou.

### Ověř:
- backend vrací rozumnou chybu,
- frontend se nerozsype,
- uživatel neobejde logiku aplikace.

---

# 14. Test admin panelu

Přihlas se jako admin.

## 14.1 Dashboard
Ověř:
- načtou se statistiky,
- čísla dávají smysl,
- panel se načítá bez chyb.

## 14.2 Uživatelé
Ověř:
- načtení seznamu uživatelů,
- otevření detailu uživatele,
- mazání uživatele,
- po smazání se změna projeví v systému,
- smazaný uživatel už se nepřihlásí,
- pokud měl otevřený chat, druhé straně to nezboří UI.

## 14.3 Místnosti / rooms
Ověř:
- seznam místností,
- detail místnosti,
- načtení historie místnosti,
- smazání místnosti,
- po smazání místnost zmizí z UI běžným uživatelům.

## 14.4 Logy
Ověř:
- načtou se logy,
- logy odpovídají akcím, které jsi provedl.

## 14.5 Vytvoření admina
Pokud máš funkci create-admin:
- vytvoř nebo povyš testovací účet,
- ověř, že nový admin může otevřít admin panel,
- běžný uživatel se do admin části nedostane.

---

# 15. Test inicializačních skriptů a prostředí

Pokud je to součást odevzdání, otestuj i setup projektu od nuly.

## Ověř:
- databáze se vytvoří korektně,
- init skript proběhne bez chyb,
- admin creation script funguje,
- `.env` / `.env.example` odpovídá tomu, co backend potřebuje,
- nová instalace jde zvednout bez ručního zásahu do kódu.

---

# 16. Test výkonu a stability při běžném používání

Nejde o benchmark, ale o praktický sanity check.

## Vyzkoušej:
- rychlé přepínání mezi více chaty,
- více žádostí o přátelství za sebou,
- více zpráv za sebou,
- otevření více oken / tabů téhož uživatele,
- odhlášení v jednom tabu a sledování chování v druhém.

### Ověř:
- UI se nesype,
- websocket se nechová chaoticky,
- nedochází k duplicitním akcím,
- notifikace se nerozjíždí.

---

# 17. Finální checklist „hotovo“

Aplikaci můžeš považovat za finálně ověřenou, pokud platí:

- [ ] registrace funguje
- [ ] login/logout funguje
- [ ] vlastní profil neukazuje „Odebrat z přátel"
- [ ] profil cizího uživatele tlačítko ukazuje správně
- [ ] žádosti o přátelství fungují
- [ ] přijetí žádosti funguje
- [ ] odmítnutí žádosti funguje
- [ ] notifikační tečka po rejectu zhasne ihned
- [ ] přátelé se zobrazují korektně
- [ ] DM jde otevřít jen tam, kde má
- [ ] odebrání z přátel zavře DM na obou stranách bez F5
- [ ] skupiny fungují
- [ ] vyhození ze skupiny funguje okamžitě
- [ ] websocket realtime synchronizace funguje
- [ ] refresh stránky nerozbíjí stav
- [ ] admin panel funguje
- [ ] mazání uživatele / room funguje správně
- [ ] aplikace nepadá do HTML/PHP error výstupů
- [ ] ruční negativní scénáře neobcházejí pravidla systému

---

# 18. Doporučený praktický testovací plán na jeden průchod

Jestli chceš celý systém projet efektivně v jednom sledu, udělej to takto:

1. vytvoř 4 běžné účty + 1 admin účet,
2. přihlas všechny,
3. uprav profil user A,
4. pošli žádost A → B,
5. B odmítne,
6. ověř zhasnutí notifikace,
7. A pošle žádost znovu,
8. B přijme,
9. otevři DM A ↔ B,
10. pošli zprávy oběma směry,
11. vytvoř skupinu A + B + C,
12. pošli zprávy do skupiny,
13. vyhoď B ze skupiny,
14. ověř okamžité zavření group chatu,
15. znovu otevři DM A ↔ B,
16. A odebere B z přátel,
17. ověř okamžité zavření DM na obou stranách,
18. jako admin smaž testovacího user C,
19. jako admin projdi rooms, logs, detail uživatele,
20. proveď refresh všech aktivních oken,
21. logout/login znovu,
22. ověř, že nic nezůstalo v rozbitém stavu.

---

# 19. Poznámky k testování

Doporučení:
- zapisuj si chyby průběžně,
- používej více oken najednou,
- sleduj browser console i backend logy,
- po kritických scénářích udělej refresh,
- u websocket scénářů testuj vždy oba uživatele současně.

