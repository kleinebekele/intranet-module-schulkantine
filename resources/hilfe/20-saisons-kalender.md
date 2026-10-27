---
titel: Saisons und Öffnungskalender
route: module.schulkantine.seasons.index
kategorie: Mensa – Verwaltung
position: 20
---

rollen: admin

Die Saison ist der oberste Rahmen – in der Regel ein Schuljahr. An ihr hängen Kalender,
Speisepläne und alle Bestellungen. Es gibt immer genau **eine aktive** Saison; sie ist es, in
der bestellt und ausgegeben wird.

Die Detailseite einer Saison ist in drei Tabs gegliedert: **Schließtage**, **Menüs** und
**Einstellungen** (dort steht das Saison-Formular – einen eigenen Bearbeiten-Knopf gibt es
nicht mehr).

## Eine Saison anlegen (Tab „Einstellungen")

rollen: admin

Nötig sind Start, Ende, das **Bundesland** (für den Ferien-Import) und die Wochentage, an
denen die Mensa grundsätzlich öffnet.

Für OGS gehört hier auch der **OGS-Preis** hin: OGS-Essen werden nicht je Gericht
abgerechnet, sondern mit einem Preis je Teilnahmetag. Standardmäßig ist **„Preis aus Linear
übernehmen (Vertrag Klasse 1–4)"** angehakt – dann gilt der Preis, den der nächtliche
Linear-Import für diese Vertragsart meldet. Der eingetragene Fixpreis gilt nur, wenn Sie den
Haken entfernen oder solange noch keine Linear-Preise importiert sind.

Außerdem stehen hier die Fristen (Bestellschluss, Abbestellen), der automatische
Freigabe-Vorlauf und die Schalter, ob Zusatzstoffe, Allergene, Diäten und Bewertungen
angezeigt werden.

## Menüs (Tab „Menüs")

rollen: admin

Ein **Menü** ist eine feste Zusammenstellung zu einem Festpreis (z. B. Hauptgericht +
Nachtisch). Hier legen Sie die **Vorlage** an: Name, Preis, an welchen Wochentagen es
angeboten wird und **aus welcher Kategorie wie viele Gerichte** es enthält. Welche Gerichte
konkret drinstecken, wählen Sie NICHT hier, sondern je Öffnungstag im **Speiseplan** – so
müssen Sie nicht jede Kombination einzeln anlegen.

Mit **„Preis aus Linear (je Vertragsgruppe)"** kostet das Menü je Esser den Preis seines
Essensvertrags aus Linear (bei mehreren Verträgen den günstigsten). Wer keinem Vertrag
zuzuordnen ist, zahlt den **teuersten** gemeldeten Preis. Welche Preise gemeldet wurden und
von wann der Stand ist, zeigt der blaue Kasten im Formular und in der Menüliste. Der im Menü
eingetragene Preis gilt dann nur, solange noch keine Linear-Preise importiert sind.

Menüs erstellt man meist zu Saisonbeginn. Bearbeiten (Preis ändern, weiteres Menü) wirkt sich
zunächst **nur auf die Vorlage** aus. Erst der Knopf **„Menüs ausrollen (Push)"** rollt die
aktiven Menüs auf alle offenen (noch nicht freigegebenen) Wochen aus. Eine einzelne Woche
lässt sich im Speiseplan gezielt neu pushen.

## Ferien und Feiertage holen

rollen: admin

Ein Knopf zieht Feiertage und Schulferien des gewählten Bundeslandes aus einer öffentlichen
Quelle und legt sie als Schließtage an.

Das ist ein Startpunkt, kein Endergebnis: Bewegliche Ferientage, pädagogische Tage und
Sonderschließungen kennt keine bundesweite Quelle. Alle Schließtage bleiben deshalb
bearbeitbar, und Sie können jederzeit welche hinzufügen oder löschen.

## Warum der Kalender tragend ist

rollen: admin

Der Bestellschluss liegt am **vorigen Werktag** (Mo–Fr) zur eingestellten Uhrzeit – auch
in den Ferien und auch, wenn die Mensa an dem Tag geschlossen hat. Übersprungen werden nur
Schließtage vom Typ **Feiertag**. Für den ersten Tag nach den Ferien gilt also der letzte
Ferien-Werktag.

Ein Feiertag, der als „Ferien" eingetragen ist, zählt deshalb als Werktag. Der Import legt
Feiertage in den Ferien als Feiertag an; nach dem Update einmal neu importieren.

## Nachträglich ändern (Admin)

rollen: admin

Wer als Admin **als Person handelt** (Teilnehmer → „Als Person handeln"), ist an keine Frist
gebunden: Bestellen und Abbestellen gehen jederzeit, auch für vergangene Tage. Die Tage
zeigen dann „Frist vorbei · als Admin änderbar". Gesperrt bleibt nur ein Monat, der für
den Esser schon an Linear abgerechnet ist.
