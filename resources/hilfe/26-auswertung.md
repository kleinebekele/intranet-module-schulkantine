---
titel: Auswertung und Abrechnung
route: module.schulkantine.reports.index
kategorie: Schulkantine – Verwaltung
position: 26
---

rollen: admin

Die Monatsabrechnung je Person – als Liste, als CSV und als PDF. Das Modul rechnet nur aus,
gebucht und kassiert wird außerhalb.

## Woraus sich eine Zeile zusammensetzt

rollen: admin

Vier Posten:

- **Menü-Bestellungen** mit dem beim Verbindlich-Werden festgehaltenen Preis.
- **OGS-Teilnahme** mal Saison-Fixpreis – abgeleitet aus der Dauerbestellung abzüglich der
  Abbestellungen.
- **Spontane Abholungen** mit ihrem Preis.
- **Chip-Pfand**: im Monat der Ausgabe belastend, im Monat der Rückgabe gutschreibend.

Personen ohne einen einzigen Posten tauchen nicht auf.

## Warum die Ausgabe nicht die Rechnung ist

rollen: admin

Abgerechnet wird die **Bestellung**, nicht die Ausgabe. Wer bestellt und nicht abgeholt hat,
zahlt trotzdem – das Essen wurde gekocht. Rechtzeitig Abbestelltes zählt nicht mit.

No-Shows stehen deshalb nur zur Information in der Zeile. Sie sind kein Abzug, sondern eine
Beobachtung.

## Bezahlt-Status

rollen: admin

Es gibt bewusst keinen Knopf „bezahlt". Der Status kommt aus dem externen
Zahlungsabgleich – ein von Hand gesetzter Haken würde nur eine zweite, konkurrierende
Wahrheit erzeugen.

## Export

rollen: admin

Die CSV-Datei ist für die Weiterverarbeitung gedacht, das PDF für die Ablage und den
Postversand. Beide fußen auf demselben Stand.

Erstellen Sie eine Abrechnung erst, wenn der Monat vorbei und der letzte Ausgabetag erfasst
ist – eine später nachgetragene spontane Abholung ändert sonst die Zahlen unter einer schon
verschickten Rechnung.

## Einzelne Person nachsehen

rollen: admin

Aus der Liste heraus kommen Sie in die Einzelansicht: Dort steht Tag für Tag, was
zusammengerechnet wurde. Das ist die Ansicht für Rückfragen von Eltern.
