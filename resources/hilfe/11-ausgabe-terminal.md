---
titel: Ausgabe-Terminal
route: module.schulkantine.servings.terminal
kategorie: Schulkantine – Betrieb
position: 11
---

rollen: kantine_kellner, admin

Das Terminal ist die Vollbild-Ansicht für den Tresen: großes Bild, wenige Knöpfe, für einen
Touchscreen gedacht. Es zeigt bewusst weder Menü noch Seitenleiste.

## Ablauf am Tresen

rollen: kantine_kellner, admin

Chip auflegen, das Terminal erkennt den Esser und zeigt, was für ihn eingetragen ist. Sie
bestätigen die Ausgabe, ergänzen bei Bedarf Spontankäufe und buchen.

Kein Chip zur Hand? Über die Suche finden Sie die Person auch über den Namen.

## Wenn das Gerät kein NFC kann

rollen: kantine_kellner, admin

Das Chip-Menü unten links (NFC-Scan auf Android, alten COM-Leser verbinden, Chip-Auswahl)
ist standardmäßig ausgeblendet. Es wird je Gerät eingeschaltet: das Terminal einmal mit
`?chip=an` am Ende der Adresse öffnen, mit `?chip=aus` wieder aus. Das Gerät merkt sich das.

Die Chip-Auswahl ist als Rückfalltür und zum Testen gedacht – im Alltag ist der echte Chip
schneller. USB-Leser, die die Kennung wie eine Tastatur eintippen, brauchen das Menü nicht.

## Was die Warnungen bedeuten

rollen: kantine_kellner, admin

Erscheint bei einem Esser eine Allergie- oder Diät-Warnung, passt das gewählte Gericht nicht
zu dem, was die Eltern hinterlegt haben. Das System sperrt nichts – es sagt Ihnen nur
Bescheid, damit Sie nachfragen können.

## Abgelehnt und Alternative

rollen: kantine_kellner, admin

Zwei Fälle, die sich unterscheiden lassen sollten:

- **Abgelehnt**: Der Esser wollte das Essen nicht. Es ist bestellt und wird berechnet, aber
  es lag nichts auf dem Teller.
- **Alternative**: Er hat etwas anderes bekommen als bestellt.

Beides ist später von der Bewertung ausgenommen – in beiden Fällen sagt das protokollierte
Gericht nichts darüber aus, wie das Essen geschmeckt hat.
