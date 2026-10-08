---
titel: Bestell-Terminal
route: module.schulkantine.bestellterminal.index
kategorie: Schulkantine – Betrieb
position: 13
---

rollen: admin

Das Bestell-Terminal ist die Vollbild-Ansicht für die Terminals in der Schule. Es braucht
keine Intranet-Anmeldung: Jeder sieht dort den Speiseplan der Woche, bestellen kann man nach
Chip-Anmeldung – Chip auflegen, bestellen, „Fertig".

## Gerät einrichten

rollen: admin

Unter **Schulkantine → Bestell-Terminal** stehen die Adresse des Terminals und die
**freigegebenen Netze**. Nur von dort ist das Terminal erreichbar; ohne Eintrag ist es
nirgends erreichbar. Die Seite zeigt auch, mit welcher Adresse der Server Sie gerade sieht –
am besten einmal an einem Schul-Terminal nachsehen und von außerhalb gegenprüfen.

Am Terminal die Adresse als Startseite öffnen, am besten im Kiosk-Modus von Chrome oder Edge.

Erkannt werden die USB-Leser, die die Kennung wie eine Tastatur eintippen, und der alte
Chipleser am COM-Anschluss.

Der Hinweis „Chip auflegen" oben rechts und der Knopf „COM-Leser verbinden" unten rechts
sind ausgeblendet. Sie erscheinen nur, wenn das Terminal mit `?chip=an` am Ende der Adresse
geöffnet wird – für ein Gerät, das sie immer braucht, gehört das in die Kiosk-Verknüpfung.
Den COM-Leser einmal je Gerät darüber freigeben, danach verbindet er sich selbst – auch ohne
`?chip=an`.

## Bestellen am Terminal

rollen: admin

Ohne Chip zeigt das Terminal den Speiseplan der Woche; ein Tipp auf ein Gericht zeigt die
Details mit Allergenen und Zusatzstoffen. Mit Chip erscheint dieselbe Ansicht wie unter
„Essen bestellen" – für den Chip-Inhaber und seine Kinder, mit denselben Fristen.

Mit **Fertig** abmelden. Wer eine Minute nichts tippt, wird automatisch abgemeldet; die
letzten Sekunden zählt ein Hinweis herunter. Legt jemand anderes seinen Chip auf, wechselt das
Terminal direkt zu dieser Person.

Ein unbekannter Chip meldet „Dieser Chip ist nicht bekannt". Dann den Chip in der
Teilnehmerverwaltung zuordnen.
