---
titel: Kundengruppen
route: module.schulkantine.customer-groups.index
kategorie: Schulkantine – Verwaltung
position: 21
---

rollen: admin

Es gibt genau **drei** Gruppen, und sie lassen sich weder anlegen noch löschen: OGS, Schüler
und Sonstige. Was Sie hier einstellen, sind ihre Betriebsparameter.

## Warum nur drei

rollen: admin

Jede Gruppe hängt fest an einer Rolle:

| Gruppe | Rolle | Modus |
|---|---|---|
| OGS | Kantine: OGS | Essen ja / nein |
| Schüler | Kantine: Schüler | Menü-Auswahl |
| Sonstige | Benutzer (`user`) | Menü-Auswahl |

Weil jeder Benutzer die Rolle `user` hat, fällt jeder irgendwo hinein – niemand steht ohne
Gruppe da. (Bestellen darf trotzdem nur, wer einen Essensvertrag hat.) Die Zuordnung geht nach Priorität von oben nach
unten: Wer OGS ist, ist OGS, auch wenn er zusätzlich `user` hat.

## Eine Person umgruppieren

rollen: admin

Nicht hier: Die Gruppe ist keine eigene Angabe, sondern folgt der Rolle – und die Rollen
„Kantine: OGS" und „Kantine: Schüler" vergibt der **nächtliche Linear-Import** anhand der
Klasse. Welche Klassen als OGS gelten, steht in der Einstellung „OGS-Klassen" am Task
Linear/BenutzerImport (Standard 1–4). Ein Kind, das nach der vierten Klasse aus der OGS
herauswächst, wird damit beim nächsten Import automatisch Schüler.

Von Hand angelegte Konten (ohne Linear-Herkunft) bekommen die Rolle unter **Verwaltung →
Benutzer**; der Import fasst sie nicht an.

## Gruppe und Essensvertrag sind zweierlei

rollen: admin

Die Gruppe bestimmt nur, **wie** bestellt wird. **Ob** jemand überhaupt bestellen darf und
zu welchem Preis, entscheidet der Essensvertrag aus Linear (siehe Teilnehmer).

## Der Bestellmodus

rollen: admin

**Essen ja/nein** heißt: ein Tagesangebot, das Kind isst mit oder nicht. **Menü-Auswahl**
heißt: Auswahl aus dem Speiseplan, pro Kategorie höchstens ein Gericht am Tag.

Den Modus einer Gruppe zu ändern, ändert die Bestellweise aller ihrer Mitglieder – und passt
nicht mehr zu bereits erfassten Bestellungen der laufenden Saison. Wenn überhaupt, dann zum
Saisonwechsel.
