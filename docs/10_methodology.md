# Metodika vývoje (hybrid Agile)

Cílem metodiky je popsat **jak** je projekt řízen a jak se vyhodnocuje progres. U školního projektu je důležité:
- mít jasně popsaný proces,
- ale nepřepálit to zbytečnou dokumentací.

## Přístup
Zvolený přístup je **Agile hybrid**:
- práce v iteracích (sprint-like cykly),
- backlog jako jediný zdroj práce,
- průběžná revize směru projektu.

### Proč Agile hybrid
Agile není „hodně dokumentace“. Agile = schopnost reagovat na změnu.
- krátké iterace
- rychlé demo
- rychlá zpětná vazba
- možnost upravit směr projektu bez toho, aby se vše muselo plánovat na začátku

To je vhodné zejména pro projekt, kde se požadavky vyvíjí během implementace.

## Backlog vs sprint plán
- **Backlog** = seznam všech plánovaných prací, seřazený podle priority (MVP -> nice-to-have).
- **Sprint plan** = výběr konkrétních user stories na následující období.

Backlog není kalendář. Kalendář je sprint progress.

## User story forma
Použitá forma:
> Jako <typ uživatele> chci <funkci>, abych <přínos>.

K user story jsou:
- akceptační kritéria
- technické poznámky
- odhad

## Iterace
Iterace jsou 2–3 týdny (v praxi pro single‑developer projekt flexibilně).
Každá iterace má:
- cíl (Sprint Goal)
- scope (vybrané user stories)
- demo/review (i kdyby jen interní: screenshoty, krátký popis)

## Evidence progresu
- commity v GitHubu (nejsilnější důkaz)
- sprint progress dokument (souhrn co bylo hotové a co ne)
- issue.md (tech debt a audit)

