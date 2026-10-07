---
titel: Bestell-Terminal
route: module.schulkantine.bestellterminal.index
kategorie: Schulkantine – Betrieb
position: 13
---

rollen: kantine_bestellterminal, admin

Das Bestell-Terminal ist die Vollbild-Ansicht für die Terminals in der Schule. Dort bestellt
man sein Essen ohne Benutzername und Passwort: Chip auflegen, bestellen, „Fertig".

## Gerät einrichten

rollen: admin

Das Terminal braucht ein eigenes Intranet-Konto mit der Rolle **Kantine: Bestell-Terminal
(Gerätekonto)**. Mit diesem Konto am Gerät anmelden und die Seite „Bestell-Terminal" öffnen –
am besten als Startseite im Vollbild (Kiosk-Modus von Chrome oder Edge).

Das Gerätekonto bestellt selbst nichts. Bestellt wird immer für den Inhaber des aufgelegten
Chips und dessen Kinder – genau wie unter „Essen bestellen".

Erkannt werden die USB-Leser, die die Kennung wie eine Tastatur eintippen, und der alte
Chipleser am COM-Anschluss. Den COM-Leser einmal je Gerät über „COM-Leser verbinden"
unten rechts freigeben, danach verbindet er sich selbst.

## Bestellen am Terminal

rollen: kantine_bestellterminal, admin

Chip an den Leser halten – die Woche erscheint mit allen Tagen, Gerichten und Menüs, wie
unter „Essen bestellen". Antippen bestellt, nochmal antippen bestellt ab. Es gelten dieselben
Fristen.

Mit **Fertig** abmelden. Wer eine Minute nichts tippt, wird automatisch abgemeldet; die
letzten Sekunden zählt ein Hinweis herunter. Legt jemand anderes seinen Chip auf, wechselt das
Terminal direkt zu dieser Person.

Ein unbekannter Chip meldet „Dieser Chip ist nicht bekannt". Dann den Chip in der
Teilnehmerverwaltung zuordnen.
