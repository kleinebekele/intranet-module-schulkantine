---
titel: Teilnehmer und Schul-Chips
route: module.schulkantine.eaters.index
kategorie: Schulkantine – Verwaltung
position: 25
---

rollen: admin

Teilnehmer sind die Benutzer des Intranets. Angelegt werden sie **nicht hier**, sondern über
die Benutzerverwaltung beziehungsweise den nächtlichen Linear-Import. Auf dieser Seite pflegen
Sie nur die Kantinen-Zusatzdaten.

Suchen Sie jemanden über das Suchfeld, bleibt der Filter erhalten, wenn Sie die Person
bearbeiten und danach zurückkehren.

## Was hier geht und was nicht

rollen: admin

Hier: Verträglichkeiten (Allergien und Diäten), Schul-Chips und – nur für Testkonten – ein
Testvertrag.

Nicht hier: Name, E-Mail-Adresse, Rollen – und damit auch nicht die Kundengruppe und nicht
der Essensvertrag. Beides kommt aus Linear.

## Essensvertrag

rollen: admin

Bestellen darf nur, wer in Linear einen laufenden **Essensvertrag** hat. Die Spalte
**Vertrag** zeigt ihn an:

| Art | Gruppe |
|---|---|
| 27 | Klasse 1–4 (OGS) |
| 28 | Schüler 5–13 |
| 29 | Lehrer / Mitarbeiter |
| 50 | Eltern |

Blau heißt „aus Linear". Der Vertrag bestimmt auch den Preis bei Menüs mit „Preis aus Linear"
und den OGS-Preis. Neue oder beendete Verträge wirken nach dem nächsten nächtlichen Import.

Die Sperre für Personen ohne Vertrag greift erst, wenn der Linear-Import einmal scharf (nicht
im Probelauf) gelaufen ist – vorher darf jeder bestellen.

## Testvertrag

rollen: admin

Zum Ausprobieren lässt sich unter **Bearbeiten → Essensvertrag** ein **Testvertrag**
(27/28/29/50) setzen. Er wirkt wie ein echter auf Bestellrecht und Preis und erscheint in der
Liste **gelb** mit dem Zusatz „(Test)". Das geht nur bei Konten, die **nicht** aus Linear
stammen – mit echten Personen wird nicht getestet.

## Verträglichkeiten von Amts wegen

rollen: admin

Eltern pflegen ihre Angaben normalerweise selbst unter **Meine Daten**. Der Weg hier ist für
die Fälle, in denen das nicht passiert – telefonisch gemeldete Allergien, Personen ohne
eigenen Zugang.

Beides schreibt in dieselben Felder; die zuletzt gespeicherte Fassung gilt.

## Zusatz-Infos aus dem Import

rollen: admin

Eine freie Zusatzangabe je Teilnehmer – bei uns typischerweise die Klasse. Sie kommt
ausschließlich aus einer CSV-Datei in der Import-Ablage und wird stündlich automatisch
eingelesen.

Der Knopf **Jetzt importieren** macht dasselbe sofort. Er ist der richtige Griff, wenn eine
frische Datei abgelegt wurde oder der stündliche Lauf auf dem Server (noch) nicht
eingerichtet ist.

## Schul-Chips ausgeben

rollen: admin

Ein Schul-Chip wird einer Person zugeordnet und kann mit **Pfand** ausgegeben werden. Für die
Abrechnung zählt der Monat: Ausgabe belastet, Rückgabe schreibt gut.

Nehmen Sie einen Chip deshalb über **Zurückgeben** aus dem Verkehr und nicht über Löschen –
sonst fehlt die Gutschrift. Ist ein Chip **verloren**, gibt es kein Pfand zurück. Löschen ist
für Fälle gedacht, in denen ein Chip versehentlich angelegt wurde.

Eine Person kann mehrere Chips haben. Eigene Chips, die Eltern selbst registriert haben,
stehen hier nur zur Ansicht und tragen kein Pfand.
