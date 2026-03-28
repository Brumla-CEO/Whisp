# 14 – User Guide (Uživatelský průvodce)

Tento dokument popisuje, jak používat aplikaci Whisp z pohledu koncového uživatele.

---

## Spuštění aplikace

Aplikaci otevřete v libovolném moderním prohlížeči (Chrome, Firefox, Edge, Safari) na adrese:

```
http://localhost:5173
```

Na úvodní obrazovce si vyberete mezi **Přihlášením** a **Registrací**.

---

## Registrace

Pokud ještě nemáte účet:

1. Klikněte na **"Nemáte účet? Zaregistrujte se"**
2. Vyplňte uživatelské jméno, email a heslo (minimálně 6 znaků)
3. Klikněte na **"Vytvořit účet"**

Po úspěšné registraci jste automaticky přihlášeni a přesměrováni do aplikace.

**Možné chybové hlášky:**

| Hláška | Příčina | Řešení |
|--------|---------|--------|
| "Uživatelské jméno je již obsazené" | Username existuje | Zvolte jiné jméno |
| "Tento email je již registrovaný" | Email existuje | Použijte jiný email nebo se přihlaste |
| "Heslo musí mít alespoň 6 znaků" | Příliš krátké heslo | Zadejte delší heslo |
| "Zadej platný email" | Špatný formát emailu | Opravte formát (např. jmeno@domena.cz) |

---

## Přihlášení

1. Zadejte váš email a heslo
2. Klikněte na **"Přihlásit se"**

Přihlašovací session je platná 24 hodin. Po vypršení budete automaticky odhlášeni.

---

## Rozhraní aplikace

Po přihlášení uvidíte:
- **Levý sidebar** — seznam přátel a skupin s posledními zprávami
- **Hlavní oblast** — chat okno otevřené konverzace
- **Horní lišta** — tlačítko správce přátel (👤+) a nastavení profilu (⚙️)

---

## Přidání přítele a zahájení chatu

Aby bylo možné zahájit soukromý chat, musíte nejprve navázat přátelství:

1. Klikněte na **👤+ (Správce přátel)** v horní liště
2. Záložka **🔍 Hledat** — zadejte jméno uživatele
3. Klikněte na **"Poslat žádost"** u vybraného uživatele
4. Druhý uživatel musí přijmout žádost v záložce **📩 Žádosti**
5. Po přijetí se přítel objeví v levém panelu
6. Kliknutím na jeho jméno otevřete chat

**Poznámka:** Admin účty nejsou v vyhledávání zobrazeny a nelze je přidat jako přátele.

---

## Správa žádostí o přátelství

V záložce **📩 Žádosti** vidíte všechny příchozí žádosti:
- **✔ Přijmout** — naváže přátelství a přítel se zobrazí v sidebaru
- **✕ Odmítnout** — žádost se odstraní

---

## Odeslání zprávy

1. Klikněte na přítele nebo skupinu v levém panelu
2. Napište zprávu do vstupního pole
3. Stiskněte **Enter** nebo klikněte na tlačítko odeslat ▶

Zprávy se doručují ostatním uživatelům v reálném čase bez obnovení stránky.

---

## Práce se zprávami

Na každou zprávu najeďte myší — zobrazí se kontextové menu **⋮**:

| Akce | Dostupnost | Popis |
|------|-----------|-------|
| ↩ Odpovědět | Jakákoli zpráva | Cituje zprávu jako kontext odpovědi |
| ✏️ Upravit | Pouze vlastní zprávy | Otevře editační pole s původním textem |
| 🗑 Smazat | Pouze vlastní zprávy | Zpráva se zobrazí jako "🚫 Odstraněno" |

**Smazání zprávy je nevratné** — obsah se nenávratně odstraní, ale "Odstraněno"
zůstane zobrazeno kvůli zachování kontextu konverzace.

---

## Skupinový chat

### Vytvoření skupiny
1. Klikněte na **+** v levém panelu (vedle "Zprávy")
2. Zadejte název skupiny
3. Vyberte minimálně 2 přátele ze seznamu (celkem 3 členové včetně vás)
4. Klikněte na **"Vytvořit"**

### Správa skupiny
Klikněte na název skupiny v záhlaví chatu pro otevření detailů:

| Akce | Kdo může | Popis |
|------|---------|-------|
| ✎ Upravit | Admin skupiny | Změní název a avatar skupiny |
| + Přidat další lidi | Admin skupiny | Přidá přátele do skupiny |
| Vyhodit | Admin skupiny | Odebere člena ze skupiny |
| Opustit skupinu | Kdokoli | Odejde ze skupiny |

**Předání vlastnictví:** Pokud admin skupinu opustí a zbývají další členové,
vlastnictví se automaticky předá nejdéle přihlášenému členovi.

---

## Notifikace

Červená tečka u konverzace v levém panelu signalizuje nepřečtené zprávy.

**Jak fungují notifikace:**
- Notifikace vzniká, pokud máte otevřenou jinou místnost (nebo jste offline)
- Při otevření místnosti se notifikace automaticky označí jako přečtené
- Pokud máte místnost právě otevřenou, notifikace se nevytváří

---

## Nastavení profilu

Klikněte na **⚙️** v horní liště:

### Avatar
- **Generovaný** — náhodně vygenerovaný avatár z DiceBear (klikněte 🎲 pro nový)
- **Vlastní URL** — zadejte odkaz na vlastní obrázek (formát: https://...)

### Uživatelské jméno
Musí být unikátní v rámci celé aplikace.

### Bio
Krátký popis zobrazený ostatním uživatelům (maximum 200 znaků).

### Uložení změn
Klikněte na **"Uložit změny"**. Po uložení se stránka obnoví.

---

## Smazání účtu

V nastavení profilu — sekce **"Odstranění účtu"**:

1. Klikněte na **"Chci smazat svůj profil"**
2. Zadejte své uživatelské jméno jako potvrzení
3. Klikněte na **"Navždy odstranit"**

**Upozornění:** Tato akce je nevratná!
- Váš účet, přátelství a členství ve skupinách budou smazány
- Vaše zprávy zůstanou v konverzacích zobrazeny jako od "Smazaného uživatele"

---

## Administrátorský panel

Pokud máte roli **admin**, po přihlášení se zobrazí admin rozhraní:

| Záložka | Obsah |
|---------|-------|
| 📊 Přehled | Statistiky platformy: počty uživatelů, místností, zpráv; posledních 20 audit logů |
| 👥 Uživatelé | Seznam všech uživatelů, filtrování, detail aktivity, smazání účtu |
| 💬 Místnosti | Přehled všech chatů (typ, vlastník, počet zpráv), náhled obsahu |
| 📜 Logy | Audit záznamy všech klíčových akcí s filtrováním |

**Admin omezení:**
- Admin nemůže být přidán jako přítel (není viditelný ve vyhledávání)
- Nelze smazat posledního administrátora systému
- Admin nemůže smazat sám sebe

---

## Odhlášení

Klikněte na **"Odhlásit"** v menu profilu (⚙️).
Session se okamžitě deaktivuje a budete přesměrováni na přihlašovací stránku.

---

## Technické požadavky

- Moderní webový prohlížeč s podporou WebSocket API
- Chrome 80+, Firefox 75+, Edge 80+, Safari 13.1+
- Aplikace vyžaduje aktivní internetové/síťové připojení k serveru
