{{-- Gemeinsamer Inhalt der Admin-Anleitung – eingebunden in Online-Ansicht UND PDF.
     Reines semantisches HTML (h2/h3/p/ul/ol/table + .cmd/.note/.scenario), damit es
     in dompdf wie im Browser gleich aussieht. Konsolen-Befehle stehen immer in einem
     orangefarbenen .cmd-Kasten mit dem Hinweis, dass sie der Administrator ausführt. --}}

<p class="lead">
    Diese Anleitung richtet sich an <strong>Administratoren</strong> der Schulkantine. Sie erklärt die
    Ersteinrichtung, die tägliche Bedienung und enthält konkrete Testszenarien, mit denen sich prüfen
    lässt, ob alles funktioniert. Befehle, die auf der <strong>Server-Konsole</strong> auszuführen sind,
    sind orange hervorgehoben.
</p>

<div class="note">
    <strong>Kernbegriffe vorab:</strong> Eine <em>Bestellung (Order)</em> ist die Vorbestellung; eine
    <em>Ausgabe (Serving)</em> ist das, was tatsächlich über den Tresen geht. Kundengruppen (OGS,
    Schüler, Sonstige) ergeben sich automatisch aus den <em>Rollen</em> – sie werden nicht von Hand
    zugewiesen. Es kann immer nur <strong>eine Saison aktiv</strong> sein; ohne aktive Saison sind fast
    alle Funktionen deaktiviert.
</div>

<h2>1. Rollen im Überblick</h2>
<p>Wer was darf, hängt an der Rolle des Benutzers. Admins dürfen grundsätzlich alles.</p>
<table>
    <thead>
        <tr><th>Rolle</th><th>Darf</th></tr>
    </thead>
    <tbody>
        <tr><td><strong>Administrator</strong></td><td>Alles: Stammdaten, Speiseplan, Auswertung, Teilnehmer, diese Anleitung.</td></tr>
        <tr><td><strong>Koch</strong> <code>kantine_koch</code></td><td>Ausgabelisten &amp; Mengenliste <em>ansehen</em> (nicht abhaken).</td></tr>
        <tr><td><strong>Kellner</strong> <code>kantine_kellner</code></td><td>Ausgabe <em>abhaken</em> + spontane Abholung erfassen (darf auch alles sehen).</td></tr>
        <tr><td><strong>OGS-Betreuer</strong> <code>kantine_ogs_betreuer</code></td><td>OGS-Sammelliste ansehen.</td></tr>
        <tr><td><strong>Schüler</strong> <code>kantine_student</code></td><td>Isst nach dem <em>Menü-Modus</em> (Gericht je Kategorie wählen).</td></tr>
        <tr><td><strong>OGS</strong> <code>kantine_ogs</code></td><td>Isst nach dem <em>Ja/Nein-Modus</em> (Abo an allen Öffnungstagen).</td></tr>
        <tr><td><strong>Elternteil</strong> / Benutzer <code>user</code></td><td>Bestellt für sich und seine Kinder, pflegt Sonderkost, bewertet Essen.</td></tr>
    </tbody>
</table>

<h2>2. Ersteinrichtung (auf der Server-Konsole)</h2>
<p>Die folgenden Schritte erledigt der Administrator einmalig beim Aufsetzen – bzw. bei Updates.</p>

<h3>2.1 Modul installieren bzw. aktualisieren</h3>
<div class="cmd">
    <span class="badge">Admin · Server-Konsole</span>
    <pre># Neu installieren (einmalig):
composer require do1emu/module-schulkantine

# Später aktualisieren:
composer update do1emu/module-schulkantine -o</pre>
</div>

<h3>2.2 Datenbank, Module &amp; Dateiablage einrichten</h3>
<div class="cmd">
    <span class="badge">Admin · Server-Konsole</span>
    <pre>php artisan migrate --force      # Tabellen anlegen/aktualisieren
php artisan modules:sync         # Modul + Menüpunkte übernehmen
php artisan storage:link         # Bilder/PDFs öffentlich verlinken</pre>
</div>
<div class="note">
    <strong>Nach jedem Update</strong> von Modul oder Core erneut <code>php artisan migrate --force</code>
    und <code>php artisan modules:sync</code> ausführen. Neue Menüpunkte (wie diese „Anleitung")
    erscheinen erst nach <code>modules:sync</code>.
</div>

<h3>2.3 Testdaten einspielen (Seeder)</h3>
<p>
    Für Test- und Beta-Betrieb bringt das Modul zwei <strong>Seeder</strong> mit. Sie sind
    <em>wiederholbar</em> (mehrfaches Ausführen legt nichts doppelt an) und überschreiben keine echten Daten.
</p>
<div class="cmd">
    <span class="badge">Admin · Server-Konsole</span>
    <pre># Test-Benutzer für alle Rollen anlegen (Passwort test1234):
php artisan kantine:seed-testusers

# Beispiel-Katalog: 5 Kategorien + 32 Gerichte inkl. Platzhalterbildern:
php artisan kantine:seed-dishes</pre>
</div>
<ul>
    <li><code>kantine:seed-testusers</code> – erzeugt je Rolle einen Testbenutzer und verknüpft ein
        Elternteil mit zwei Kindern. Optional anderes Passwort: <code>--password=…</code>.</li>
    <li><code>kantine:seed-dishes</code> – erzeugt Kategorien und Gerichte mit Allergenen/Zusatzstoffen
        und für jedes Gericht ein farbiges SVG-Platzhalterbild (nur wo noch kein Foto hinterlegt ist).</li>
</ul>

<h2>3. Test-Benutzer</h2>
<p>Diese Konten legt <code>php artisan kantine:seed-testusers</code> an. <strong>Alle haben das Passwort
    <code>test1234</code>.</strong> Damit lassen sich alle Rollen durchspielen.</p>
<table>
    <thead>
        <tr><th>E-Mail</th><th>Passwort</th><th>Rolle / Funktion</th></tr>
    </thead>
    <tbody>
        <tr><td>admin@kantine.test</td><td>test1234</td><td>Administrator (Vollzugriff)</td></tr>
        <tr><td>koch@kantine.test</td><td>test1234</td><td>Koch – Ausgabelisten ansehen</td></tr>
        <tr><td>kellner@kantine.test</td><td>test1234</td><td>Kellner – Ausgabe abhaken</td></tr>
        <tr><td>ogs-betreuer@kantine.test</td><td>test1234</td><td>OGS-Betreuer – OGS-Sammelliste</td></tr>
        <tr><td>eltern@kantine.test</td><td>test1234</td><td>Elternteil – bestellt für die zwei Kinder unten</td></tr>
        <tr><td>kind-schueler@kantine.test</td><td>test1234</td><td>Kind, Gruppe <em>Schüler</em> (Menü-Modus)</td></tr>
        <tr><td>kind-ogs@kantine.test</td><td>test1234</td><td>Kind, Gruppe <em>OGS</em> (Ja/Nein-Modus)</td></tr>
        <tr><td>schueler@kantine.test</td><td>test1234</td><td>Schüler, eigenständig</td></tr>
        <tr><td>sonstige@kantine.test</td><td>test1234</td><td>Esser der Gruppe <em>Sonstige</em></td></tr>
    </tbody>
</table>

<h2>4. Bedienung Schritt für Schritt</h2>

<h3>4.1 Grundgerüst anlegen (Admin)</h3>
<ol>
    <li><strong>Saison</strong> (Menü „Saisons &amp; Kalender") → „Neue Saison": Schuljahr mit Name,
        Start-/Enddatum und OGS-Fixpreis anlegen und <strong>aktiv</strong> setzen (es ist immer nur eine
        Saison aktiv).</li>
    <li><strong>Schließtage</strong> in der Saison eintragen (Ferien, Feiertage) – optional per
        Ferien-/Feiertags-Import.</li>
    <li><strong>Kategorien</strong> und <strong>Gerichte</strong> pflegen (oder per Seeder). Kategorien
        steuern u. a., ob spontane Abholung erlaubt ist.</li>
    <li><strong>Kundengruppen</strong> (OGS/Schüler/Sonstige) bei Bedarf umbenennen oder Abholzeiten
        anpassen – der Bestellmodus ist fest.</li>
    <li><strong>Teilnehmer</strong>: pro Benutzer Allergene/Diäten und (für Schüler/Sonstige) Schul-Chips
        verwalten. OGS-Kinder bekommen keinen Chip.</li>
</ol>

<h3>4.2 Speiseplan &amp; Wochenfreigabe (Admin)</h3>
<ol>
    <li>Menü „<strong>Speiseplan</strong>": im Wochenraster je Öffnungstag über „Gericht hinzufügen"
        ein oder mehrere Gerichte eintragen (z. B. Hauptgang + Dessert).</li>
    <li><strong>Woche freigeben:</strong> Über die Buttons oben – „Freigeben" macht die Woche sofort
        bestellbar, „Sperren" hält sie zurück, „Automatik" folgt dem eingestellten Vorlauf.</li>
    <li>Eltern können erst bestellen, wenn die Woche freigegeben ist (automatisch nach Vorlauf oder
        manuell). Ein Gericht/eine Woche lässt sich nicht mehr sperren/löschen, sobald Bestellungen
        vorliegen.</li>
</ol>

<h3>4.3 Bestellen (Eltern / Schüler)</h3>
<ul>
    <li>Menü „<strong>Essen bestellen</strong>": zeigt das Wochenraster; Kinder zuerst, der Benutzer
        selbst zuletzt.</li>
    <li><strong>Menü-Modus</strong> (Schüler/Sonstige): je Tag und Kategorie ein Gericht wählen. Leere
        Auswahl = abbestellt.</li>
    <li><strong>Ja/Nein-Modus</strong> (OGS): Abo an/aus. Bei aktivem Abo isst das Kind an allen
        Öffnungstagen; einzelne Tage lassen sich abbestellen.</li>
    <li><strong>Fristen:</strong> Bestellschluss am Vortag (Standard 14:00), Abbestellen am selben Tag
        (Standard 09:00). Nach Fristablauf sind Änderungen gesperrt.</li>
    <li><strong>Sonderkost-Warnung:</strong> Enthält ein Gericht ein Allergen/eine Diät des Kindes,
        erscheint eine rote Warnung.</li>
</ul>

<h3>4.4 Essensausgabe (Kellner / Koch)</h3>
<ul>
    <li>Menü „<strong>Ausgabe</strong>": Liste der Esser mit ihren Bestellungen (Tabs Menü / OGS).</li>
    <li><strong>Abhaken</strong> (nur Kellner/Admin): Häkchen „ausgegeben" setzen bzw. zurücknehmen.</li>
    <li><strong>NFC-Chip:</strong> Chip scannen → der Esser wird erkannt, ein Fenster zeigt seine
        Bestellungen samt Sonderkost-Warnungen; dort die Ausgabe bestätigen. Ergebnis je Position:
        <em>genommen</em>, <em>Alternative</em> oder <em>abgelehnt</em> (mit Grund).</li>
    <li><strong>Spontane Abholung:</strong> für Kategorien, die Walk-in erlauben (z. B. Getränke) – nicht
        für OGS-Kinder.</li>
    <li><strong>Mengenliste</strong> für die Küche (auch als PDF), <strong>No-Shows</strong> zeigt
        bestellte, aber nicht abgeholte Essen.</li>
    <li><strong>Koch</strong> sieht diese Listen nur, kann aber nicht abhaken.</li>
</ul>

<h3>4.5 OGS-Sammelliste (OGS-Betreuer)</h3>
<p>Menü „<strong>OGS-Sammelliste</strong>": zeigt die heute teilnehmenden OGS-Kinder mit
    Allergen-/Diät-Hinweisen und Ausgabestatus.</p>

<h3>4.6 Essen bewerten (alle) &amp; Bewertungs-Report (Betrieb)</h3>
<ul>
    <li>Menü „<strong>Essen bewerten</strong>": jedes Haushaltsmitglied bewertet die tatsächlich
        erhaltenen Essen per Daumen hoch/runter – jederzeit änderbar. Abgelehnte Essen/Alternativen sind
        nicht bewertbar.</li>
    <li>Der aggregierte, <strong>anonyme</strong> Bewertungs-Report (je Gericht Anzahl Daumen
        hoch/runter + Quote) ist für Koch/Kellner/Admin über die Gerichte-Liste erreichbar.</li>
</ul>

<h3>4.7 Auswertung / Abrechnung (Admin)</h3>
<ul>
    <li>Menü „<strong>Auswertung</strong>": monatliche Übersicht je Haushalt – Menü, OGS, Spontan, Pfand,
        Summe und Bezahlt-Status.</li>
    <li>Über die Personen-Detailseite „<strong>Als bezahlt markieren</strong>" (bzw. rückgängig).</li>
    <li>Export als <strong>CSV</strong> (Excel) und <strong>PDF</strong>.</li>
    <li>Die eigentliche Zahlung läuft <strong>extern</strong> – das System hält nur fest, was als bezahlt
        markiert wurde.</li>
</ul>

<h2>5. Testszenarien</h2>
<p>Diese Szenarien prüfen die wichtigsten Abläufe. Melde dich jeweils mit dem genannten Testbenutzer an
    (Passwort <code>test1234</code>).</p>

<div class="scenario">
    <h4>Szenario 1 – Grundgerüst &amp; Speiseplan</h4>
    <p><strong>Als:</strong> admin@kantine.test</p>
    <ol>
        <li>Saison anlegen und aktiv setzen, ein paar Schließtage eintragen.</li>
        <li><code>php artisan kantine:seed-dishes</code> ausführen (oder Gerichte manuell anlegen).</li>
        <li>Im Speiseplan der nächsten Woche je Öffnungstag ein Hauptgericht + Dessert eintragen.</li>
        <li>Die Woche „Freigeben".</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Speiseplan gefüllt, Woche als freigegeben markiert.</p>
</div>

<div class="scenario">
    <h4>Szenario 2 – Eltern bestellen im Menü-Modus</h4>
    <p><strong>Als:</strong> eltern@kantine.test</p>
    <ol>
        <li>„Essen bestellen" öffnen – die freigegebene Woche wird angezeigt, das Schüler-Kind
            (kind-schueler) erscheint.</li>
        <li>Für mehrere Tage je ein Gericht wählen, an einem Tag die Auswahl wieder leeren (abbestellen).</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Auswahl wird gespeichert; der offene Monatsbetrag oben
        aktualisiert sich; nach Fristablauf ist keine Änderung mehr möglich.</p>
</div>

<div class="scenario">
    <h4>Szenario 3 – OGS-Abo (Ja/Nein)</h4>
    <p><strong>Als:</strong> eltern@kantine.test (für kind-ogs)</p>
    <ol>
        <li>Beim OGS-Kind das Abo aktivieren – es isst an allen Öffnungstagen.</li>
        <li>Einen einzelnen Tag abbestellen.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Alle Öffnungstage außer dem abbestellten sind als „isst"
        markiert.</p>
</div>

<div class="scenario">
    <h4>Szenario 4 – Ausgabe abhaken</h4>
    <p><strong>Als:</strong> kellner@kantine.test</p>
    <ol>
        <li>„Ausgabe" für den Bestelltag öffnen.</li>
        <li>Beim Schüler-Kind „ausgegeben" abhaken, danach wieder zurücknehmen.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Häkchen lässt sich setzen und zurücknehmen; die Mengenliste
        verändert sich entsprechend.</p>
</div>

<div class="scenario">
    <h4>Szenario 5 – Rollen-Rechte prüfen</h4>
    <p><strong>Als:</strong> koch@kantine.test</p>
    <ol>
        <li>„Ausgabe" öffnen – die Liste ist sichtbar.</li>
        <li>Versuchen abzuhaken.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Der Koch kann alles <em>sehen</em>, aber nicht abhaken
        (nur Kellner/Admin dürfen das).</p>
</div>

<div class="scenario">
    <h4>Szenario 6 – OGS-Sammelliste</h4>
    <p><strong>Als:</strong> ogs-betreuer@kantine.test</p>
    <ol>
        <li>„OGS-Sammelliste" öffnen.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Die heute teilnehmenden OGS-Kinder werden mit
        Allergen-/Diät-Hinweisen angezeigt.</p>
</div>

<div class="scenario">
    <h4>Szenario 7 – Bewerten &amp; Report</h4>
    <p><strong>Als:</strong> eltern@kantine.test, danach admin@kantine.test</p>
    <ol>
        <li>Nach einer Ausgabe unter „Essen bewerten" ein erhaltenes Essen mit Daumen hoch/runter
            bewerten und die Bewertung ändern.</li>
        <li>Als Admin in der Gerichte-Liste den aggregierten Bewertungs-Report prüfen.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Bewertung wird gespeichert/geändert; der Report zeigt
        je Gericht die Anzahl Daumen hoch/runter – ohne Personenbezug.</p>
</div>

<div class="scenario">
    <h4>Szenario 8 – Abrechnung</h4>
    <p><strong>Als:</strong> admin@kantine.test</p>
    <ol>
        <li>„Auswertung" öffnen, den passenden Monat wählen.</li>
        <li>Eine Person „Als bezahlt markieren", danach CSV/PDF exportieren.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Beträge je Haushalt stimmen; die Person ist als bezahlt
        markiert; CSV/PDF werden heruntergeladen.</p>
</div>

<div class="scenario">
    <h4>Szenario 9 – Sichtbarkeit dieser Anleitung</h4>
    <p><strong>Als:</strong> kellner@kantine.test (oder ein Nicht-Admin)</p>
    <ol>
        <li>Die Kantine öffnen und die Menüpunkte prüfen.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Der Menüpunkt „Anleitung" ist nur für Administratoren
        sichtbar; ein direkter Aufruf durch Nicht-Admins wird mit Fehler 403 abgewiesen.</p>
</div>

<h2>6. Referenz: alle Konsolen-Befehle</h2>
<p>Zur schnellen Übersicht – alle Befehle führt der <strong>Administrator auf der Server-Konsole</strong>
    im Projektverzeichnis aus.</p>
<div class="cmd">
    <span class="badge">Admin · Server-Konsole</span>
    <pre># Installation / Update
composer require do1emu/module-schulkantine
composer update do1emu/module-schulkantine -o

# Einrichtung nach Installation/Update
php artisan migrate --force
php artisan modules:sync
php artisan storage:link

# Testdaten (wiederholbar, überschreibt keine echten Daten)
php artisan kantine:seed-testusers
php artisan kantine:seed-dishes</pre>
</div>
<div class="note">
    <strong>Nur für den Test:</strong> Test-Konten und Beispiel-Gerichte dienen dem Ausprobieren. Vor dem
    echten Start sollten die Test-Benutzer entfernt und echte Benutzer sowie der reale Speiseplan
    angelegt werden.
</div>
