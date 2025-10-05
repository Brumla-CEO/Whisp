# 📖 Branching Strategy – Whisp (Live Chat Application)

## 🧭 Cíl dokumentu
Tento dokument popisuje, jak je projekt **Whisp** organizován z hlediska práce s větvemi (branching), verzování a spolupráce v rámci vývoje.  
Cílem je udržet přehledný, stabilní a týmově udržitelný vývoj pomocí principu **Trunk-Based Development (TBD)**.

---

## 🌳 1. Trunk-Based Development (TBD)

### Co to znamená
Trunk-Based Development je vývojová strategie, kde:
- existuje **jedna hlavní větev (`main`)**, která obsahuje vždy stabilní a aktuální verzi projektu,
- všechny nové funkce, opravy a úpravy se vyvíjejí v **krátkodobých feature branchích**, které se po dokončení **mergnou zpět do `main`**.

### Hlavní zásady
1. **`main` = jediný zdroj pravdy**  
   - obsahuje vždy funkční, aktuální a otestovaný kód i dokumentaci.
2. **Krátkodobé branche**  
   - pro každou novou funkci nebo úpravu se vytváří nová branch (životnost obvykle několik dní).
3. **Pull Request (PR)**  
   - každá změna do `main` prochází přes PR, který musí být zkontrolován reviewerem.
4. **Code review**  
   - cílem je kvalita, ne kontrola – každý PR musí být srozumitelný, přehledný a odůvodněný.
5. **Po mergnutí se branch maže**  
   - udržuje se tím čistý repozitář bez dlouhodobých větví.

---

## 🧩 2. Typy větví a jejich pojmenování

### Prefixy branchí
| Prefix | Účel | Příklad |
|:-------|:------|:--------|
| `feat/` | Nová funkce (feature) | `feat/websocket-auth` |
| `fix/` | Oprava chyby nebo bugfix | `fix/login-validation` |
| `refactor/` | Refaktorování nebo úprava struktury kódu | `refactor/user-service` |
| `test/` | Testování nebo přidání unit testů | `test/message-service` |

---
