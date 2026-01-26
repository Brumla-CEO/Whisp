# Docs

### `./README.md`

- Duplikace na konci souboru
- Chybi reference na *.md soubory v `./docs`, staci reference na `./docs/README.md`, pripadne zkopirovat rozcestnim do
  hlavniho `./README.md` a ten v `docs` smazat. Proc? Hlavni dokumentace by mela jit na GitHubu videt. Ted k ni neexistuje 
  jednoducha cesta.

### `./docs/methodology.md`

Nekde nakonec k vysvetleni Agile pripadne k popisu Waterfall vs Agile pristup by mela zaznit jedna dulezita vec. Agile,
byt agilni, mimo jine znamena byt pripraven na zmeny. Prave proto Agile klade duraz na kratsi iterace, kterym predchazi kratsi
a jednodussi analyza, planing a design. Smyslem review/dema po konci iterace je schopnost reagovat na aktualni stav coz
muze kompletne zmenit treba nejakou feature nebo cely smer projektu. Na konci sprintu je typicky demo pro zakaznika, ktery
si klidne muze rict takhle ne protoze XYZ, v pristim sprintu to zkusme jinak. Tim padem ma zakaznik lepsi kontrolu nad
vyvojem sveho SW, nemusi vse definovat hned na zacatku a kontroluje jak vynaklada sve fin. prostredky.

Samozrejeme je treba to zkratit, polidstit, pocestit, ale da idea je klicova.

### `./docs/backlog.md`

Backlog jsou obecne vsechny tasky na jedne hromade serazene od nejdulezitejsiho po nice to have. Vetsinou se tam respektuje
co je treba z hlediska MVP. Backlog rozhodne neni rozvrzeni sprintu. Proste to definuje kompletni praci na projektu, ktery
zakaznik, tym, product owner na projektu predvida. Kdyz je to high level nebo casove narocna vec (cela velka feature), pak se
ji vetsinou rika Epic. Ta se dale rozpada na user stories, ktere uz jsou dostatecne male na to, aby se vzali do Sprintu.
User story se da samozrejme dale delit na tasky, sub-tasky atd. pokud je to treba. Dulezite ale je, ze user story je nejaka
deliverable - vec ktera je nejak uchopitelna, dodatelna behem sprintu a tudiz na jejim konci demovatelna.

Tasky se pouzivaji, aby se v prubehu sprintu lepe trackovalo kdo na cem dela a jestli se na tom nezasekl. Ty to takhle rozpadat
nemusis, protoze si jen jeden a tudiz to nedava smysl. Na vetsim tymovem projektu je to tak, ze splnit task by nemelo trvat
dele nez den dva. Kazdy den se dela tzv. daily standup meeting (cca do 15 min), kde kazdy rika na cem delal vcera, dnes, do 
ceho se hodla pustit zitra a jestli se na necem nezaseknul. Kdyz praci jen predstira a uz paty den mluvi o praci na stejnem tasku,
pak cely tym vi, ze je neco spatne -> zlepseni efektivity, odhalovani problemu v early fazi atd.

Sprinty si planuj klidne delsi, ale sprint nerovna se backlog. Spise by jsi mel nejaky sprint nadefinovat, napsat si jak
dlouho jsi si na nej dal a vyhodnotit na konci, jestli jsi jeho cil splnil nebo ne, respektive jestli jsi dodal vsechny user
stories ke kterym jsi se zavazal.

V tom backlogu je problem ze vlastne delas dlouhy a kompletni planning pro cely projekt, coz je spatne. Po sprintu 1 muze nasledujici
sprint nabrat uplne jiny smer, protoze si to aktualni stav projektu zada!

### Komentare k dalsi docs - mnohdy chybi

Scrum neni o brutalni dokumentaci. Snazis se jen, aby byla up to date a aby slouzila svemu ucelu. Sprinty, jak zminovano vyse,
planuj postupne a spise jen monitoruj jejich prubeh. Klidne rekni, ze prvni/multa faze projektu byla bez sprintu, seznamoval jsi
se s nastroji, technologiemi apod. Az zacnes 'sprintovat', tak si nejdriv naplanuj ceho chces v nasledujicich 2-4 tydnech dosahnout,
napis si user story a pak monitoruj jak to stihnes. Na konci si napis jestli jsi mel neco funkcniho, co se nepovedlo, pripadne jestli
to neovlivni sprint dalsi. Sprinty mej klidne ruzne dlouhe a konec jednoho sprintu neznamena automaticky zacatek noveho. Klidne
si dej par dnu 'pauzu' na dalsi planning.

Implementace, testing, deployment. Tyhle 3 veci se vetsinou snoubi v jednu a jde o nejake best practices, ktere u tebe zavedeme ve
vhodnou dobu pozdeji. Dulezite je psat nejaky user-guide.md ktery popise jak nastavit prostredi, co nainstalovat, jak vse zbuildit,
spustit, otestovat a pripadne i nasadit. Klidne tyto veci shrn do jednoho dokumentu, nebo kdyz to bude dlouhe tak to klidne rozdel,
ale vetsinou o tom nechces moc psat, ale spise to delat. Proto user guide, ktery rika how to start nez zdlouhavy popis. Nejaky
vetsi detail pak muzes popsat v CI/CD, ale to uz je o tech best practices, ktere budes zavadet a popisovat pozdeji.

# Implementace

Nemel jsem moc cas kouknout do toho vic, ale vsiml jsem si Dockerfile(s) v ruznych adresarich a docker-compose.yaml. Tady
je par problemu co vidim:

1. Vim co s tim, ale vetsina lidi ne, takze by to melo popsano v user guidu:
   - Install Docker Desktop
   - How to run: `docker compose up`
   - Co kde najdu, na jakych portech co otevrit apod. Nasel jsem napr. UI na http://localhost:5173/, ale moc funkcni to nebylo.

2. Obecne problemy

Docker pomaha s ruznymi vecmi, ktere patri do CI/CD pytle. Ty se na to asi divas hodne z pohledu CD a tudiz deploymentu, jakoze
neco mam a potrebuji to nekde nejak rozjet, deploynout casem do produkcniho prostredi, na nejaky server atd. Proc myslim ze to tak
mas nastaveno v hlave: mas docker file na kazdou cast systemu, coz v tuto fazi vubec neni treba. Potrebujes hlavne tu DB at
ji nemusis instalovat, ale zbytek musis jako vyvojar mit pravdepodobne nainstalovano. Napr. React.js FE se vetsinou vyviji tak,
ze mas nainstalovany Node.js, mas definovany package.json se zavislostmi a mas skript ktery ti spusti FE ve watch modu - kdyz menis
soubory, rovnou ti to ukazuje live zmenu v prohlizeci. Tohle je neco co potrebujes pro vyvoj a ty jsi ted ve vyvojove fazi.

Nevim jak vyvijis backend ale zase predpokladam, ze mas neco na lokale aby jsi to mohl live kodit a rovnou testovat, ze vse bezi
jak ma. Proto Dockerfile pro BE a FE je urcite v tuto chvili hodne predcasni a muze poslouzit tak maximalne me k tomu, at nemusim
nic instalovat. Ty by jsi mel mit hlavne dev guide, kde reknes vyvojarum nainstaluj si XYZ, spust docker-compose aby jsi mel na lokale
DB, takhle spustis UI, takhle BE a vse najdes na nejake URL. Do DB se dostanes pomoci tak a onak, pouziva to nejaky username/password apod.
Tohle uplne chybi, takze kdyby jsi delal v tymu a prisel ti nekdo novy, musis sednout a vse mu dlouze vysvetlovat. Tohle ma byt super jednoduche.






