---
titel: Saisons und Öffnungskalender
route: module.schulkantine.seasons.index
kategorie: Schulkantine – Verwaltung
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
denen die Kantine grundsätzlich öffnet.

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

Die Fristen rechnen **in Öffnungstagen**. Der Bestellschluss für einen Montag liegt beim
vorherigen Öffnungstag – ist das ein Donnerstag, weil Freitag geschlossen ist, dann gilt
Donnerstag.

Ein falsch gesetzter Schließtag verschiebt also stillschweigend Bestellfristen. Nach jeder
Änderung am Kalender lohnt ein Blick auf die Bestellseite, ob die Fristen noch stimmen.

## Der allererste Öffnungstag

rollen: admin

Für den ersten Kantinentag einer Saison gibt es keinen vorherigen Öffnungstag – die Frist
fällt dann auf den Kalendertag davor zurück. Ohne diese Ausnahme wäre der erste Tag nach den
Sommerferien nie bestellbar.
