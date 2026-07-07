# Kantinen-Modul — Konzept & Anforderungen (lebendes Dokument)

> Status: **in Arbeit** · Start 2026-07-02 · wandert später ins Modul-Repo.
> Regel: Hier kommt ALLES rein — lieber zu viel als zu wenig. Offene Punkte mit ❓ markieren.

## 1. Zweck & Kontext
Modul für die Schulkantine — Auslöser des gesamten Intranet-Projekts. **Kein reiner Speiseplan**,
sondern ein Bestell-, Ausgabe- und Auswertungssystem. Eine physische Küche beliefert mehrere
Kundengruppen. Keine Zahlungsanbieter-Anbindung; die eigentliche Abrechnung passiert an anderer
Stelle — das Modul liefert dafür saubere Daten (Export).

**Modul-Key:** `schulkantine` (Namespace `Intranet\\Modules\\Schulkantine`, `do1emu/module-schulkantine`,
Routen `module.schulkantine.*`). Name wegen der Eltern-Besonderheit; das generische `kantine` bleibt für ein
evtl. späteres, schlankeres Modul reserviert.

## 2. Akteure (wer nutzt das System?)
- **Esser / Teilnehmer** — isst & wird abgerechnet; namentlich erfasst. **Jeder Intranet-Nutzer** kann essen
  (Personal, Lehrer, Verwaltung, ggf. Eltern) — plus Kinder ohne Login (über Vormund).
- **Elternteil / Vormund** — bestellt für sein(e) Kind(er); hat Login.
- **Küchen-/Ausgabepersonal** — Ausgabelisten, Ausgabe abhaken, spontane Abholung erfassen, Mengen sehen.
- **Verwaltung / Admin** — Kundengruppen, Teilnehmer, Gerichte, Speisepläne, Kalender, Rechte, Export.
- ❓ Weitere? (z. B. Küchenleitung mit Sonderrechten, externe Einrichtungen …)

## 3. Glossar (damit wir dieselbe Sprache sprechen)
- **Kundengruppe** — Einrichtung/Gruppe; legt den **Bestellmodus** fest (`ja_nein` | `menü`).
- **Esser / Teilnehmer** — isst & wird abgerechnet; gehört zu genau einer Kundengruppe. Kann ein Intranet-Nutzer
  sein (Selbst-Bestellung, `user_id` gesetzt) **oder** ein Kind ohne Login (über Vormund). Auch Erwachsene
  (Personal etc.) sind Esser → brauchen ebenfalls eine passende Kundengruppe (z. B. „Personal"). ❓ wie gruppiert?
- **Vormund (guardianship)** — Verknüpfung Elternteil → Kind (Bestellrecht).
- **Gericht (dish)** — Katalog-Eintrag; trägt Allergene/Zusatzstoffe; gehört zu **genau einer Kategorie**.
- **Kategorie (category)** — z. B. Hauptmenü, Nachtisch, Getränk, Eis (admin-pflegbar). Steuert: **max. 1 Variante
  je Esser/Tag** pro Kategorie; und ob **spontane Abholung** erlaubt ist (dann begrenzt durch **Vorrat**).
- **Menü** — Angebot = Gruppe × Tag × Gericht (im Menü-Modus mehrere Linien, z. B. Menü 1 / 2 / vegetarisch).
- **Öffnungstag (serving_day)** — Kalender: wann hat die Kantine (für wen) geöffnet.
- **Vorbestellung (order)** — *Absicht*, vorab, mit Bestellschluss.
- **Ausgabe (serving)** — *tatsächliche* Essensausgabe vor Ort. Quelle der Wahrheit „wer hat was gegessen".
- **Ausgabeliste** — Tagesansicht der Ausgaben je Gruppe zum Abhaken; aus Vorbestellungen vorbefüllt, spontan ergänzbar.
- **Sonderkost (Allergien/Diäten)** — pro Esser hinterlegte Einschränkungen. **Allergien** = Gericht darf bestimmte
  Allergene nicht enthalten (gleiche Liste wie die Gericht-Kennzeichnung). **Diäten** = Gericht muss geeignet sein
  (vegetarisch, vegan, halal, laktosefrei …). Passt ein Gericht nicht → **Warnung**.
- **OGS-Gruppe** — Kundengruppe im **ja/nein-Modus**; Kinder bestellen nur „esse heute: ja/nein". Das Tages-Angebot
  ist **ein** Gericht („OGS-Essen") — was dahintersteckt, entscheidet die Küche je Speiseplan.
- **Sammelliste (OGS)** — Tagesliste der OGS-Kinder mit „ja"; der OGS-Betreuer sammelt sie ein und bringt sie zur Kantine.
- **Saison (Schuljahr)** — oberster Zeitrahmen (z. B. 2026/2027) mit Start/Ende und **Bundesland**. Darunter hängen
  Kalender, Gruppen-Zugehörigkeit der Kinder und Bestellungen. Verhindert saisonübergreifendes Bestellen.
- **Schließtag** — Tag ohne Kantine (Ferien/Feiertag/Sonderfall). Je Saison **per API** (Bundesland) gezogen und
  **voll editierbar** (Sonderferien, bewegliche Ferientage, manuell).
- **Dauerbestellung (OGS-Saison-Abo)** — Standard „isst an allen Öffnungstagen der Saison"; Eltern verwalten nur Abbestellungen.
- **Bewertung (Daumen)** — Kind bewertet Essen hoch/runter; Personal sieht nur die **Anzahl** (anonym).

## 4. User Stories (Arbeitsstand — bitte ergänzen!)
### Elternteil / Esser
- Als Elternteil möchte ich für mein Kind für kommende Tage Essen vorbestellen (ja/nein bzw. Menüwahl).
- Als größeres Kind möchte ich selbst vorbestellen.
- Als Elternteil möchte ich eine Vorbestellung stornieren. ❓ bis wann?
- Als Elternteil möchte ich Allergene/Zusatzstoffe eines Gerichts sehen.
- Als Elternteil möchte ich beim Bestellen **gewarnt** werden, wenn ein Gericht nicht zur Sonderkost meines Kindes passt.
- Als **OGS-Elternteil** möchte ich für die **ganze Saison** bestellen und nur bei Krankheit/Urlaub **abbestellen** (nicht täglich klicken).

### Küchen-/Ausgabepersonal
- Ich möchte pro Tag & Gruppe eine **Ausgabeliste** zum Abhaken (wer hat bestellt, welches Menü) — druckbar / am Bildschirm.
- Ich möchte beim Abhaken markieren, dass ein Kind sein Essen **erhalten** hat.
- Ich möchte auf der Ausgabeliste **Allergie-/Diät-Warnungen** je Kind sehen.
- Ich möchte eine **spontane Abholung** erfassen (Kind ohne Vorbestellung nimmt ein Gericht) — wer & was.
  Deckt zugleich den Fall ab: *Frist verpasst / vergessen zu bestellen* → am Tresen als Ausgabe erfassen.
- Ich möchte sehen, **wie viele Portionen** je Menü zu kochen sind (Mengenplanung aus Vorbestellungen).
- Ich möchte eine Auswertung **„bestellt, aber nicht abgeholt"** (No-Shows / Verschwendung) sehen.

### OGS
- Als **OGS-Betreuer** möchte ich eine **Sammelliste** sehen (welche meiner Kinder heute essen), um sie
  einzusammeln und zur Kantine zu bringen.
- Als **kellner** möchte ich bei der Ausgabe zwischen **OGS-Liste** und **Tagesmenü-Liste** wählen (eigene Zeiträume).

### Feedback/Bewertung
- Als **Kind** möchte ich mein Essen mit **Daumen hoch/runter** bewerten.
- Als **Küchenpersonal** möchte ich sehen, **welche Essen gut ankommen** — nur die **Anzahl** der Daumen, **nicht wer** bewertet hat.

### Verwaltung / Admin
- Ich möchte Kundengruppen, Teilnehmer, Gerichte, Speisepläne und den Öffnungskalender pflegen.
- Ich möchte einen **Export** der Ausgaben/Bestellungen für die externe Abrechnung erzeugen.
- Als **kantinenadmin** möchte ich eine **Saison (Schuljahr)** anlegen (Start/Ende, Bundesland).
- Als **kantinenadmin** möchte ich **Ferien & Feiertage per Knopfdruck** aus einer öffentlichen API ziehen und als
  Schließtage anlegen — und danach jeden Schließtag **manuell bearbeiten** (Sonderferien, bewegliche Ferientage).

## 5. Regeln & Policies (zu entscheiden)
- **Fristen (vom Kantinen-Admin einstellbar, mit diesen Standardwerten):**
  - **Bestellschluss (neu bestellen / ändern):** am *vorherigen Öffnungstag* um **14:00**. Maßgeblich ist der
    Öffnungskalender, nicht der Wochentag — Beispiel: für **Montag** gilt **Donnerstag 14:00** (Fr/Sa/So
    geschlossen), nicht Sonntag.
  - **Abbestellen (stornieren):** noch am **selben Tag bis 09:00** möglich (z. B. Kind krank).
  - Folge fürs Modell: Fristen werden **gegen den Öffnungskalender berechnet** (Service-Logik, die geschlossene
    Tage überspringt), nicht per simpler Wochentags-Rechnung. → Der Kalender wird tragend, nicht Deko.
  - **Entschieden:** Fristen gelten **global** (vorerst nicht je Gruppe). Die **Zeiten** (14:00 / 09:00) sind
    vom Kantinen-Admin einstellbar; der Vorlauf „voriger Öffnungstag" bleibt vorerst fix. → je-Gruppe evtl. später.
- **Verbindlichkeit & Abrechnung (entschieden):** Sobald die **Abbestell-Frist (Standard 09:00 am selben Tag)**
  abgelaufen ist, wird die Vorbestellung **verbindlich und berechnet — unabhängig davon, ob abgeholt wird**
  (No-Show zahlt trotzdem).
  - Rechtzeitig (vor 09:00) abbestellt → **nicht** berechnet.
  - Spontane Abholung ohne Vorbestellung → **berechnet** (hat gegessen).
  - Die tatsächliche **Ausgabe ist NICHT die Abrechnungsbasis**, sondern dient dem Betrieb (Essen ausgeben,
    No-Shows sichtbar machen).
  - Fürs Modell: `orders` mit **Status** (bestellt → storniert / verbindlich) + **Preis-Snapshot**
    (Preis zum Verbindlich-Werden festhalten, damit spätere Preisänderungen alte Abrechnungen nicht ändern).
  - **Preise (entschieden):** **fix je Gericht**, unabhängig vom Käufer (keine Gruppen-/Menü-Preise, keine
    ermäßigten Sätze/Pauschalen). Feld `dishes.price`. Der **Preis-Snapshot** auf der Bestellung bleibt (falls
    sich der Gericht-Preis später einmal ändert, stimmt die alte Abrechnung weiterhin).
- **Auswahl je Kategorie (entschieden):** Ein Esser bestellt **pro Kategorie max. 1 Variante** je Tag
  (z. B. 1 Hauptmenü + 1 Nachtisch + 1 Getränk + 1 Eis). Kategorien sind admin-pflegbar.
- **Spontane Abholung hängt an der Kategorie (entschieden):** Die Kategorie legt fest, ob spontane Abholung
  erlaubt ist — und dann nur **solange der Vorrat reicht**. Der Bestand bleibt jedoch **küchenintern** und wird
  (vorerst) **nicht im Intranet erfasst**. Hauptmenü z. B. meist nur vorbestellbar (muss gekocht werden), Getränk/Eis auch spontan.
  - **Entschieden:** „max. 1 pro Kategorie" gilt **nur für Vorbestellungen (Menü-Modus)**. **Spontane Abholung
    hat kein Limit** (nur der physische Küchen-Vorrat begrenzt, nicht das System) — ein Kind darf mehrere Artikel
    nehmen (z. B. für einen Freund).
    **Kein System-Budget pro Kind** (regeln die Eltern selbst). Die ja/nein-Gruppe wählt keine Kategorien,
    nur „Essen ja/nein".
  - **Vorrat (entschieden – vorerst NICHT im System):** Bestandsführung bleibt **küchenintern**; das Intranet
    erfasst keinen Vorrat und dekrementiert nichts. → mögliches späteres Update, aktuell nicht auf der Agenda.
- **Preise:** je Gruppe/Gericht unterschiedlich? Nur für den Export relevant?
- **Sonderkost (entschieden – JA):** Pro Esser werden **Allergien** (aus der 14-Allergene-Liste) und **Diäten**
  (vegetarisch, vegan, halal, laktosefrei … – admin-pflegbar) hinterlegt. Beim Vorbestellen, auf der Ausgabeliste
  und bei spontaner Abholung erscheint eine **Warnung**, wenn ein Gericht nicht passt (enthält Allergen / erfüllt
  Diät nicht).
  - **Entschieden:** **immer nur warnen** – Bestellung/Abholung bleibt in allen Fällen möglich (keine Sperre,
    keine Override-Logik nötig). Unpassende Gerichte werden **markiert (⚠️), nicht ausgeblendet**.
- **Gäste / Verschenktes (entschieden – ignorieren):** Keine Gäste-Erfassung. Wenn die Küche mal etwas
  verschenkt, muss das **nicht dokumentiert** werden. Kein anonymer Esser nötig — jede Ausgabe hängt an einem Teilnehmer.
- **OGS-Logik (entschieden):** OGS ist eine **Kundengruppe (ja/nein)** — **kein** virtueller Standort und **keine**
  extra „Gericht je Gruppe verfügbar"-Tabelle. Der ohnehin **je Gruppe geführte Speiseplan** löst es: die OGS-Gruppe
  bekommt pro Öffnungstag **ein** Angebot („OGS-Essen"); da es nur im OGS-Speiseplan steht, ist es automatisch nur
  für OGS-Kinder bestellbar. Inhalt entscheidet die Küche.
  - **Ausgabe je Gruppe/Zeitfenster:** OGS wird zu einem **eigenen Zeitraum** ausgegeben; der `kellner` wählt die
    Liste (OGS vs. Tagesmenü). Ausgabelisten sind also **je Kundengruppe** filterbar; Zeitfenster als Gruppen-Attribut.
  - **Entschieden:** Ein Kind gehört **pro Saison** zu **genau einer** Kundengruppe (`eater` → 1 `group` je Saison). Kein n:m.
  - ❓ Trägt bei OGS der **Betreuer** das ja/nein ein — oder wie sonst die Eltern/Esser?
- **Saison-Logik (Schuljahr) (entschieden):** Oberster Container mit **Start/Ende + Bundesland**. Darunter hängen
  Kalender/Schließtage, die **Gruppen-Zugehörigkeit je Kind** und alle **Bestellungen**.
  - **Kalender-Import:** Ferien & Feiertage werden je Saison **per öffentlicher API (Bundesland) auf Knopfdruck**
    gezogen und als Schließtage angelegt. Alle Schließtage bleiben **voll editierbar** (Sonderferien, bewegliche
    Ferientage, manuell hinzufügen/löschen). Öffnungs-Wochentage sind konfigurierbar (z. B. Mo–Do).
  - **Gruppe je Saison:** OGS = Grundschule **Klasse 1–4**, ab **Klasse 5** normaler Schüler → nach den Sommerferien
    wechselt das Kind ggf. die Gruppe. Deshalb Gruppe **pro Saison**.
  - **Bestellungen saison-gebunden:** kein saisonübergreifendes Bestellen (verhindert Bestellungen für Zeiträume,
    in denen das Kind eine andere Gruppe hat).
  - ❓ API-Vorschlag: **OpenHolidays API** (Feiertage + Schulferien je Bundesland, kostenlos, ohne Key) — ok?
  - ❓ Kommt die Gruppen-Zuordnung je Saison über den **User-Import** (jährlich) + manuelle Korrektur?
- **Dauerbestellung / OGS-Saison-Abo (geplant):** OGS-Eltern bestellen für die **ganze Saison** (Standard: isst an
  allen Öffnungstagen) und müssen nur noch **abbestellen** (Krankheit/Urlaub). Gilt für **ja/nein-Gruppen**; pro Tag
  wird die Teilnahme zur Abbestell-Frist verbindlich (gleiche Abrechnungsregel). `subscriptions`-Tabelle; Tages-
  Bestellungen daraus abgeleitet. ❓ ableiten oder materialisieren (Impl.-Detail)? ❓ auch Default für Menü-Gruppen?
- **Bewertung / Daumen (geplant):** Kinder bewerten ihr Essen **Daumen hoch/runter**; Auswertung je Gericht.
  **Datenschutz:** Personal sieht **nur die Anzahl**, **nicht wer** bewertet hat. Einzelstimmen bleiben anonym (nur
  zum Verhindern von Doppel-/Änderungsstimmen gespeichert). `meal_ratings`-Tabelle.

## 6. Datenmodell (Arbeitsstand — folgt aus 4 & 5)
- **Stammdaten:** `customer_groups` (Modus + Ausgabe-Zeitfenster), `categories` (Flag: spontane Abholung erlaubt), `dishes` (→ genau eine
  Kategorie, **Fixpreis**, + Diät-Eignung), `allergens`/`additives`/`diets` (+ Verknüpfungstabellen)
- **Menschen:** `users`, `cafeteria_eaters` (+ `eater_allergens`, `eater_diets`), `eater_season_group` (Gruppe je Saison), `guardianships`
- **Saison & Kalender:** `seasons` (Start/Ende, Bundesland), `closed_days` (Schließtage: Datum, Grund, Quelle api|manuell)
  — Öffnungstage = Saison-Zeitraum ∩ Öffnungs-Wochentage − Schließtage
- **Speiseplan:** `menus` (Gruppe × Tag × Gericht) — innerhalb einer Saison
- **Vorbestellung:** `orders` — **eine Zeile je gewähltem Gericht** (max. 1 pro Kategorie/Tag), mit Status
  bestellt/storniert/verbindlich + Preis-Snapshot
- **Dauerbestellung:** `subscriptions` (OGS-Saison-Abo; Tages-Bestellungen daraus abgeleitet)
- **Ausgabe & Betrieb:** `servings` (Ausgaben/Abholungen) — Ausgabeliste ist eine Ansicht darauf, **je Kundengruppe
  filterbar** (OGS: eigene Liste & Zeitfenster).
  *(Vorrat/Bestand bleibt küchenintern — vorerst nicht modelliert.)*
- **Auswertung & Feedback:** Reports/Export (Abrechnung) · `meal_ratings` (Daumen; Personal-Report **nur aggregiert**)
- **Einstellungen:** `cafeteria_settings` (global: Bestellschluss-Zeit, Abbestell-Zeit)

## 7. Phasenplan (Bau-Reihenfolge)
1. **Stammdaten** — Saison & Kalender (inkl. Ferien-/Feiertags-Import), Gruppen, Kategorien, Gerichte, Allergene/Diäten, Teilnehmer
2. **Speiseplan** — Menüs je Gruppe/Tag
3. **Vorbestellung** — bestellen/stornieren, Bestellschluss, **OGS-Saison-Abo (Dauerbestellung)**
4. **Ausgabe & Betrieb** — Ausgabelisten, Abhaken, spontane Abholung
5. **Auswertung & Abrechnung** — Mengen, Export
6. **Feedback (optional)** — Daumen-Bewertung + Auswertung „beliebteste Essen" (anonym)

## 8. Rollen & Rechte (wird die „Anleitung" im Modul)
Rechte laufen über das **bestehende Rollen-System des Core** + den **User-Import** (bringt Rollen und
Vormund-Zuordnungen mit). **Keine eigenen Rechte-Tabellen im Modul** — Rollen werden per Policy/Middleware
geprüft, Navigation rollenbasiert eingeblendet. Rollen sind **additiv**.

**Modul-Rollen (Vorschlag):**
- `kantinenadmin` — Vollzugriff.
- `koch` — Gerichte/Allergene/Diäten, Kategorien, Speiseplan, Öffnungskalender, Vorrat/Mengen.
- `kellner` — Ausgabe abhaken + spontane Abholung, Ausgabelisten (wählt zwischen OGS- und Tagesmenü-Liste).
- `ogs-betreuer` — sieht die **Sammelliste** seiner OGS-Gruppe (heute essende Kinder); sonst keine Rechte.
- *(jeder eingeloggte User)* — Speiseplan ansehen, für sich/seine Kinder (ab)bestellen. **Essen kann jeder.**

| Aktion | kantinenadmin | koch | kellner | jeder User |
|---|:--:|:--:|:--:|:--:|
| Speiseplan / Menüs pflegen | ✅ | ✅ | – | – |
| Gerichte, Allergene, Diäten | ✅ | ✅ | – | – |
| Kategorien pflegen | ✅ | ✅ | – | – |
| Kundengruppen pflegen | ✅ | – | – | – |
| Öffnungskalender pflegen | ✅ | ✅ | – | – |
| Teilnehmer & Vormund pflegen | ✅ | – | – | – |
| Fristen, Preise, Einstellungen | ✅ | – | – | – |
| Ausgabe abhaken / spontan erfassen | ✅ | – | ✅ | – |
| Ausgabelisten & Mengen ansehen | ✅ | ✅ | ✅ | – |
| OGS-Sammelliste ansehen \* | ✅ | – | – | – |
| Export / Abrechnung | ✅ | – | – | – |
| Speiseplan ansehen | ✅ | ✅ | ✅ | ✅ |
| Für sich / Kind (ab)bestellen | ✅ | ✅ | ✅ | ✅ |
| Essen bewerten (Daumen) | ✅ | ✅ | ✅ | ✅ |
| Bewertungs-Auswertung (nur Anzahl) | ✅ | ✅ | – | – |

\* **`ogs-betreuer`** sieht zusätzlich die **OGS-Sammelliste seiner Gruppe** (heute essende Kinder).

**Konfigurierbar statt fest?** Optional bekommt der `kantinenadmin` später eine **Oberfläche**, um selbst
festzulegen, welche Rolle was darf (statt fester Matrix). Vorerst gilt die Matrix oben als Standard; die
konfigurierbare Variante ist ein mögliches Update. *(Der Core hat bereits ein Rollen-Adminpanel als Basis.)*

## 9. Sammelbox: das „und und und" (hier reinkippen)
- …
- …
- …
