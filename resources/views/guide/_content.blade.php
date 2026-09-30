{{-- Gemeinsamer Inhalt der Admin-Anleitung – eingebunden in Online-Ansicht UND PDF.
     Reines semantisches HTML (h2/h3/p/ul/ol/table + .cmd/.note/.scenario), damit es
     in dompdf wie im Browser gleich aussieht. Konsolen-Befehle stehen immer in einem
     orangefarbenen .cmd-Kasten mit dem Hinweis, dass sie der Administrator ausführt. --}}

<p class="lead">
    Diese Anleitung richtet sich an <strong>Administratoren</strong> der Schulkantine. Sie erklärt, woher
    die Daten kommen (Linear), die Einrichtung einer Saison, die tägliche Bedienung und enthält
    Testszenarien, mit denen sich prüfen lässt, ob alles funktioniert. Befehle für die
    <strong>Server-Konsole</strong> sind orange hervorgehoben.
</p>

<div class="note">
    <strong>Kernbegriffe vorab:</strong> Eine <em>Bestellung</em> ist die Vorbestellung, eine
    <em>Ausgabe</em> das, was tatsächlich über den Tresen geht. Abgerechnet wird die Bestellung.
    Personen, Klassen, Eltern-Kind-Beziehungen, <strong>Essensverträge</strong> und
    <strong>Essenspreise</strong> kommen jede Nacht aus <strong>Linear</strong> – im Intranet werden sie
    nicht von Hand gepflegt. Es ist immer genau <strong>eine Saison aktiv</strong>; ohne aktive Saison
    sind fast alle Funktionen aus.
</div>

<h2>1. Woher die Daten kommen: Linear</h2>
<p>
    Der Task <strong>Linear/BenutzerImport</strong> (Modul Verwaltung, täglich 03:30) liest Linear und
    gleicht das Intranet ab. Für die Kantine liefert er:
</p>
<table>
    <thead>
        <tr><th>Was</th><th>Woher in Linear</th><th>Wirkung in der Kantine</th></tr>
    </thead>
    <tbody>
        <tr><td>Personen</td><td>Adresse (Schüler/Lehrer/Eltern/Mitarbeiter = J)</td><td>Benutzerkonten; Einladungen werden nur <em>vorgemerkt</em> und erst nach Freigabe in der Verwaltung verschickt.</td></tr>
        <tr><td>Eltern ↔ Kind</td><td>Verknüpfungen</td><td>Eltern bestellen für ihre Kinder.</td></tr>
        <tr><td>Kundengruppe</td><td>Klasse (neuester Zeitraum)</td><td>Rolle <code>kantine_ogs</code> für die OGS-Klassen (Einstellung „OGS-Klassen" am Task, Standard 1–4), sonst <code>kantine_student</code>.</td></tr>
        <tr><td>Essensvertrag</td><td><code>Linear2.dbo.MgVert</code>, Art 27/28/29/50, laufend</td><td>Rolle <code>kantine_vertrag_&lt;Art&gt;</code> am <strong>Esser</strong> (<code>AbwAdrNr</code>). Nur wer einen Vertrag hat, darf bestellen.</td></tr>
        <tr><td>Essenspreis</td><td><code>Linear2.dbo.MgArtDat</code> (gilt ab Jahr/Monat)</td><td>Preis je Vertragsart, abgelegt in der Kantine (Menüs „Preis aus Linear", OGS-Preis).</td></tr>
    </tbody>
</table>
<table>
    <thead>
        <tr><th>Vertragsart</th><th>Gruppe</th></tr>
    </thead>
    <tbody>
        <tr><td>27</td><td>Klasse 1–4 (OGS)</td></tr>
        <tr><td>28</td><td>Schüler 5–13</td></tr>
        <tr><td>29</td><td>Lehrer / Mitarbeiter</td></tr>
        <tr><td>50</td><td>Eltern</td></tr>
    </tbody>
</table>
<div class="note">
    <strong>Wichtig:</strong> Der Task startet im <strong>Probelauf</strong> (Häkchen in seinen Einstellungen) –
    dann berichtet er nur, was er tun würde. Die <strong>Bestellsperre ohne Vertrag greift erst</strong>,
    wenn der Import einmal scharf gelaufen ist und Vertragsrollen vergeben hat; vorher darf jeder
    bestellen. Ist Linear nicht lesbar oder liefert keine Verträge/Preise, bleibt der bisherige Stand
    unverändert. <strong>Nach Linear wird nichts geschrieben</strong> – die Kantine liest nur.
</div>

<h2>2. Rollen und Zugriff</h2>
<p>
    Wer welche Kantinen-Seite sieht, legen die <strong>Rollen am Menüpunkt</strong> fest
    (Verwaltung → Module), jeweils mit einer <strong>Zugriffsstufe</strong>: <em>lesen</em>,
    <em>bearbeiten</em> oder <em>verwalten</em> (anlegen/löschen). Admins dürfen alles; diese Anleitung
    sehen nur Admins.
</p>
<table>
    <thead>
        <tr><th>Rolle</th><th>Typischer Einsatz</th></tr>
    </thead>
    <tbody>
        <tr><td><strong>Koch</strong> <code>kantine_koch</code></td><td>Ausgabe-Übersicht, Mengenliste, Bewertungs-Report.</td></tr>
        <tr><td><strong>Kellner</strong> <code>kantine_kellner</code></td><td>Ausgabe-Terminal: ausgeben, spontan buchen.</td></tr>
        <tr><td><strong>OGS-Betreuer</strong> <code>kantine_ogs_betreuer</code></td><td>OGS-Sammelliste (Ausgabe → Details).</td></tr>
        <tr><td><strong>OGS</strong> <code>kantine_ogs</code> (aus Linear)</td><td>Isst im Ja/Nein-Modus (Abo).</td></tr>
        <tr><td><strong>Schüler</strong> <code>kantine_student</code> (aus Linear)</td><td>Bestellt Menüs/Gerichte.</td></tr>
        <tr><td><strong>Benutzer</strong> <code>user</code></td><td>Alle anderen (Gruppe „Sonstige"): bestellt für sich und seine Kinder.</td></tr>
        <tr><td><strong>Vertrag</strong> <code>kantine_vertrag_27/28/29/50</code> (aus Linear)</td><td>Bestellrecht und Preisgruppe.</td></tr>
    </tbody>
</table>

<h2>3. Einrichtung und Updates (Server-Konsole)</h2>
<p>Installiert und aktualisiert wird über das Deploy-Skript des Intranets. Es zieht die Module nach,
    migriert, übernimmt die Menüpunkte und baut die Caches neu.</p>
<div class="cmd">
    <span class="badge">Admin · Server-Konsole</span>
    <pre>./deploy.sh                      # Module aktualisieren, migrieren, Menüpunkte, Caches
php artisan storage:link         # einmalig: Bilder/PDFs öffentlich verlinken</pre>
</div>
<div class="note">
    Ohne einen <strong>Cron</strong> für <code>php artisan schedule:run</code> (minütlich) laufen weder
    der nächtliche Linear-Import noch der stündliche Info-Import.
</div>

<h3>3.1 Testdaten (Seeder)</h3>
<p>Für Test- und Beta-Betrieb. Alle Seeder sind <em>wiederholbar</em> und überschreiben keine echten Daten.</p>
<div class="cmd">
    <span class="badge">Admin · Server-Konsole</span>
    <pre># Test-Benutzer für alle Rollen (Passwort test1234):
php artisan kantine:seed-testusers

# Beispiel-Katalog: Kategorien + Gerichte mit Platzhalterbildern:
php artisan kantine:seed-dishes

# OGS-Testkinder mit aktivem Abo (Standard 50) – und wieder weg:
php artisan kantine:seed-ogs-testkinder --count=20
php artisan kantine:seed-ogs-testkinder --remove</pre>
</div>

<h2>4. Test-Benutzer und Testvertrag</h2>
<p><code>kantine:seed-testusers</code> legt diese Konten an, <strong>alle mit Passwort <code>test1234</code></strong>:</p>
<table>
    <thead>
        <tr><th>E-Mail</th><th>Rolle / Funktion</th></tr>
    </thead>
    <tbody>
        <tr><td>admin@kantine.test</td><td>Administrator</td></tr>
        <tr><td>koch@kantine.test</td><td>Koch</td></tr>
        <tr><td>kellner@kantine.test</td><td>Kellner</td></tr>
        <tr><td>ogs-betreuer@kantine.test</td><td>OGS-Betreuer</td></tr>
        <tr><td>eltern@kantine.test</td><td>Elternteil der beiden Kinder unten</td></tr>
        <tr><td>kind-schueler@kantine.test</td><td>Kind, Gruppe Schüler</td></tr>
        <tr><td>kind-ogs@kantine.test</td><td>Kind, Gruppe OGS</td></tr>
        <tr><td>schueler@kantine.test</td><td>Schüler, eigenständig</td></tr>
        <tr><td>sonstige@kantine.test</td><td>Gruppe Sonstige</td></tr>
    </tbody>
</table>
<div class="note">
    <strong>Testvertrag:</strong> Die Testkonten stammen nicht aus Linear und haben deshalb keinen
    Vertrag. Unter <strong>Teilnehmer → bearbeiten → Essensvertrag</strong> lässt sich für sie ein
    Testvertrag (27/28/29/50) setzen; er wirkt wie ein echter auf Bestellrecht und Preis. Bei Konten
    aus Linear ist das nicht möglich – mit echten Personen wird nicht getestet.
</div>

<h2>5. Bedienung</h2>

<h3>5.1 Saison (Saisons &amp; Kalender)</h3>
<p>Die Saison-Seite hat drei Tabs: <strong>Schließtage</strong>, <strong>Menüs</strong> und
    <strong>Einstellungen</strong>.</p>
<ul>
    <li><strong>Einstellungen:</strong> Name, Beginn/Ende, Bundesland, Öffnungs-Wochentage, aktiv.
        Außerdem die Fristen (Bestellschluss am vorigen Öffnungstag, Abbestellen am selben Tag), der
        automatische Freigabe-Vorlauf in Wochen und Anzeige-Schalter (Zusatzstoffe, Allergene, Diäten,
        Bewertungen).</li>
    <li><strong>OGS-Preis:</strong> Standardmäßig ist „Preis aus Linear übernehmen (Vertrag Klasse
        1–4)" angehakt – dann gilt der importierte Linear-Preis. Der eingetragene Fixpreis gilt nur ohne
        Haken oder solange noch keine Linear-Preise importiert sind.</li>
    <li><strong>Schließtage:</strong> Ferien und Feiertage per Knopf aus einer öffentlichen Quelle holen,
        danach von Hand ergänzen. Die Fristen rechnen in Öffnungstagen – ein falscher Schließtag
        verschiebt sie.</li>
</ul>

<h3>5.2 Menüs (Saison → Tab „Menüs")</h3>
<ul>
    <li>Ein Menü ist eine <strong>Vorlage</strong>: Name, Preis, Wochentage und aus welcher Kategorie wie
        viele Gerichte. Die konkreten Gerichte kommen je Tag im Speiseplan dazu.</li>
    <li><strong>„Preis aus Linear (je Vertragsgruppe)":</strong> Jeder Esser zahlt den Linear-Preis
        seines Vertrags (bei mehreren den günstigsten). Wer keinem Vertrag zuzuordnen ist, zahlt den
        <strong>teuersten</strong> Preis. Formular und Menüliste zeigen die gemeldeten Preise samt Stand
        des letzten Imports.</li>
    <li>Änderungen wirken zunächst nur auf die Vorlage. <strong>„Menüs ausrollen (Push)"</strong> bringt
        sie auf alle offenen Wochen; eine einzelne Woche lässt sich im Speiseplan neu anlegen.</li>
</ul>

<h3>5.3 Kategorien und Gerichte</h3>
<ul>
    <li>Je Kategorie und Tag darf ein Esser <strong>ein</strong> Gericht vorbestellen.</li>
    <li>Zwei Häkchen je Kategorie: <strong>vorbestellbar</strong> und <strong>spontan</strong>. „Nur
        spontan" steht im Speiseplan, erscheint aber nicht auf „Essen bestellen". Reihenfolge per Ziehen.</li>
    <li>Gerichte mit Preis, Foto (wird sofort gespeichert), Allergenen, Zusatzstoffen und ungeeigneten
        Diäten. Die Liste lässt sich filtern und sortieren; der Filter bleibt beim Bearbeiten erhalten.</li>
</ul>

<h3>5.4 Speiseplan und Wochenfreigabe</h3>
<ol>
    <li>Je Öffnungstag oben die <strong>Menüs</strong> füllen (Suchfeld je Slot, „Menü speichern").
        Unvollständige Menüs sind markiert und nicht bestellbar.</li>
    <li>Darunter je Kategorie Einzelgerichte über „+ hinzufügen".</li>
    <li><strong>Freigabe</strong> immer für eine ganze Woche: automatisch nach Vorlauf oder von Hand.
        Eine freigegebene Woche ist <strong>festgeschrieben</strong>; „Zur Bearbeitung freigeben" geht nur,
        solange keine aktive Bestellung vorliegt.</li>
    <li>Je Tag „Bestellungen" aufklappen: wer hat was bestellt; einzelne Bestellungen lassen sich
        entfernen (umgeht die Fristen).</li>
</ol>

<h3>5.5 Teilnehmer</h3>
<ul>
    <li>Übersicht mit Gruppe, Info (z. B. Klasse), <strong>Vertrag</strong> (blau = aus Linear,
        gelb = Testvertrag), Verträglichkeiten und Chip. Der Suchfilter bleibt beim Bearbeiten erhalten.</li>
    <li><strong>Bearbeiten:</strong> Allergien und Diäten, Essensvertrag (siehe Abschnitt 4), Schul-Chips.</li>
    <li><strong>Schul-Chips</strong> mit oder ohne Pfand ausgeben (Chip am USB- oder COM-Leser einlesen).
        <em>Zurücknehmen</em> schreibt das Pfand gut, <em>verloren</em> ohne Erstattung, <em>entfernen</em>
        nur bei Fehleingaben. OGS-Kinder brauchen keinen Chip.</li>
    <li><strong>Infos</strong> (z. B. Klasse) kommen aus einer CSV in <code>storage/app/kantinen-import</code>
        (<code>externe_id;Info</code>), stündlich oder per Knopf „Infos importieren". Wer fehlt, behält
        seine Info; leere zweite Spalte löscht sie.</li>
</ul>

<h3>5.6 Bestellen (Eltern, Schüler, Mitarbeiter)</h3>
<ul>
    <li><strong>Ohne Essensvertrag</strong> sieht man statt der Tage den Hinweis, im Sekretariat
        nachzufragen; Bestellen und Abo sind gesperrt.</li>
    <li><strong>Menü-Modus</strong> (Schüler, Sonstige): ein Menü als Ganzes <em>oder</em> je Kategorie ein
        Gericht. Der angezeigte Menüpreis ist der Preis <em>dieses</em> Essers.</li>
    <li><strong>OGS</strong>: Abo mit Standard-Wochentagen („Tage ändern"); einzelne Tage an- oder
        abmelden.</li>
    <li><strong>Fristen:</strong> Bestellen bis zum vorigen Öffnungstag (Standard 14:00), Abbestellen bis
        zum selben Tag (Standard 09:00). Danach verbindlich.</li>
    <li>Unter <strong>Meine Daten</strong> pflegen Eltern Verträglichkeiten, eigene Chips, das Budget für
        Spontankäufe und welche Kategorien ihr Kind vorbestellen darf.</li>
</ul>

<h3>5.7 Ausgabe-Übersicht</h3>
<ul>
    <li>Tab „<strong>Ausgabe Übersicht</strong>": Mengen je Gericht (Mengen-PDF), No-Shows, OGS-Zahl.</li>
    <li>Tab „<strong>Details</strong>": jeder Esser einzeln, dazu die <strong>OGS-Sammelliste</strong>
        (auch als PDF).</li>
</ul>

<h3>5.8 Ausgabe-Terminal (Kellner)</h3>
<ul>
    <li>Vollbild für den Touch-Tresen. Chip am NFC-, USB- oder COM-Leser – oder Person über die Suche.</li>
    <li>Umschalter <strong>Vorbesteller / OGS</strong>, Wochenübersicht, Kalender für andere Tage.</li>
    <li>Ausgabe je Position: <em>genommen</em>, <em>Alternative</em> oder <em>abgelehnt</em>; dazu
        <strong>Nachschlag</strong> und <strong>spontane Mitnahme</strong> (nur spontan erlaubte
        Kategorien, nicht für OGS).</li>
    <li>Allergie-/Diät-Warnungen sperren nichts, sie weisen nur hin.</li>
</ul>

<h3>5.9 Essen bewerten</h3>
<p>Jeder bewertet tatsächlich erhaltene Essen mit Daumen hoch/runter (abschaltbar je Saison). Der
    anonyme Report je Gericht ist über die Gerichte-Liste erreichbar.</p>

<h3>5.10 Abrechnung</h3>
<ul>
    <li>„<strong>Auswertung</strong>" (Verwaltung) und „<strong>Meine Abrechnung</strong>" (Eltern):
        Menü-Bestellungen (Preis zum Bestellzeitpunkt), OGS-Tage × OGS-Preis, spontane Abholungen und
        Chip-Pfand. No-Shows werden berechnet, rechtzeitig Abbestelltes nicht.</li>
    <li>Export als CSV und PDF. Bezahlt wird extern; es gibt bewusst keinen Knopf „bezahlt".</li>
    <li>Eine Übergabe der Abrechnung an Linear gibt es noch nicht.</li>
</ul>

<h2>6. Testszenarien</h2>
<p>Anmelden jeweils mit dem genannten Testbenutzer (Passwort <code>test1234</code>).</p>

<div class="scenario">
    <h4>Szenario 1 – Saison, Menü, Speiseplan</h4>
    <p><strong>Als:</strong> admin@kantine.test</p>
    <ol>
        <li>Saison anlegen, aktiv setzen, Ferien holen.</li>
        <li>Im Tab „Menüs" ein Menü mit „Preis aus Linear" anlegen und ausrollen.</li>
        <li>Im Speiseplan der nächsten Woche die Menüs füllen und die Woche freigeben.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Menüs vollständig, Woche festgeschrieben; der Preiskasten zeigt
        die Linear-Preise und den Stand des Imports.</p>
</div>

<div class="scenario">
    <h4>Szenario 2 – Bestellsperre ohne Vertrag</h4>
    <p><strong>Als:</strong> admin@kantine.test, danach eltern@kantine.test</p>
    <ol>
        <li>Unter Teilnehmer bei kind-schueler keinen Testvertrag setzen.</li>
        <li>Als Eltern „Essen bestellen" öffnen.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Beim Kind steht der Hinweis „kein Essensvertrag – im Sekretariat
        nachfragen", Bestellen ist gesperrt. (Nur wenn der Linear-Import bereits scharf gelaufen ist.)</p>
</div>

<div class="scenario">
    <h4>Szenario 3 – Preis je Vertrag</h4>
    <p><strong>Als:</strong> admin@kantine.test, danach eltern@kantine.test</p>
    <ol>
        <li>kind-schueler Testvertrag 28, eltern Testvertrag 50 geben.</li>
        <li>Als Eltern für beide dasselbe Linear-Menü bestellen.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Das Kind zahlt den Preis der Art 28, der Elternteil den der Art 50;
        die Teilnehmerliste zeigt beide Testverträge gelb.</p>
</div>

<div class="scenario">
    <h4>Szenario 4 – OGS-Abo</h4>
    <p><strong>Als:</strong> eltern@kantine.test (für kind-ogs, mit Testvertrag 27)</p>
    <ol>
        <li>Abo aktivieren, Standardtage auf Mo–Do setzen.</li>
        <li>Einen Tag zusätzlich abmelden.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Das Kind isst Mo–Do außer dem abgemeldeten Tag; die Kosten
        rechnen mit dem OGS-Preis (Linear-Preis Art 27).</p>
</div>

<div class="scenario">
    <h4>Szenario 5 – Ausgabe am Terminal</h4>
    <p><strong>Als:</strong> kellner@kantine.test</p>
    <ol>
        <li>Terminal für den Bestelltag öffnen, kind-schueler per Chip oder Suche aufrufen.</li>
        <li>Ausgabe bestätigen, einen Nachschlag buchen.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Die Ausgabe ist gebucht; in „Ausgabe" erscheint sie, die No-Shows
        sinken.</p>
</div>

<div class="scenario">
    <h4>Szenario 6 – Rollen-Rechte</h4>
    <p><strong>Als:</strong> koch@kantine.test</p>
    <ol>
        <li>„Ausgabe" öffnen, dann das Terminal aufrufen.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Die Übersicht ist sichtbar, das Terminal nicht (sofern der
        Menüpunkt nur Kellnern freigegeben ist).</p>
</div>

<div class="scenario">
    <h4>Szenario 7 – Bewerten und Abrechnung</h4>
    <p><strong>Als:</strong> eltern@kantine.test, danach admin@kantine.test</p>
    <ol>
        <li>Ein ausgegebenes Essen bewerten.</li>
        <li>„Meine Abrechnung" prüfen; als Admin „Auswertung" für den Monat öffnen und CSV/PDF laden.</li>
    </ol>
    <p class="exp"><strong>Erwartet:</strong> Bewertung gespeichert, Report anonym; Beträge in Eltern- und
        Verwaltungsansicht stimmen überein.</p>
</div>

<h2>7. Referenz: Konsolen-Befehle</h2>
<div class="cmd">
    <span class="badge">Admin · Server-Konsole</span>
    <pre># Update
./deploy.sh

# Teilnehmer-Infos sofort einlesen (sonst stündlich)
php artisan kantine:import-infos

# Chip-Codes aus dem Altsystem (CSV: AdrNr;Code) – ohne --live nur Probelauf
php artisan kantine:import-chips datei.csv
php artisan kantine:import-chips datei.csv --live [--quelle=eltern] [--pfand]

# Testdaten
php artisan kantine:seed-testusers
php artisan kantine:seed-dishes
php artisan kantine:seed-ogs-testkinder [--count=50] [--remove]</pre>
</div>
<div class="note">
    <strong>Nur für den Test:</strong> Test-Konten, Testverträge und Beispiel-Gerichte dienen dem
    Ausprobieren. Vor dem echten Start die Testkonten entfernen (<code>--remove</code> bei den
    OGS-Testkindern, die übrigen in der Benutzerverwaltung).
</div>
