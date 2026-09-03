# Beispieldaten und Import-Skript: Versetzungsplan

Die vollständige Spezifikation steht in [`../docs/schnittstelle_versetzungsplan.md`](../docs/schnittstelle_versetzungsplan.md). Diese beiden Dateien sind die lauffähige Umsetzung davon.

## `versetzungsplan.csv`

Muster im **Blockformat** (`kw_von`/`kw_bis`) — dem in der Schnittstelle empfohlenen Format, wenn zusammenhängende Einsatzzeiträume bereits bekannt sind. Drei Lernende, Blöcke immer numerisch: in der Regel dreistellig, mit den zweistelligen Ausnahmen `90` (Berufsfachschule, nicht betrieblich) und `91` (überbetrieblicher Kurs) — was die Nummer bedeutet, steht in `bemerkung`.

```csv
email;block;kw_von;kw_bis;bemerkung
anna.muster@firma.ch;104;2027-W15;2027-W26;
anna.muster@firma.ch;207;2027-W27;2027-W33;
anna.muster@firma.ch;90;2027-W34;2027-W34;Blockwoche Berufsfachschule
...
```

Zum Testen mit echten Kontodaten: `email` durch eine tatsächlich in Moodle vorhandene Adresse ersetzen, `block` durch eine gültige Blocknummer aus `local_berufsbildung`. Unbekannte E-Mails und Blöcke brechen den Import **nicht** ab — sie werden übersprungen bzw. ohne Kompetenzabdeckung angelegt und im Protokoll gemeldet.

Alternatives Wochenformat (eine Zeile je Kalenderwoche, Spalte `kw` statt `kw_von`/`kw_bis`) ist in der Schnittstelle unter „Alternative: eine Zeile je Woche" beschrieben — beide Formate dürfen nicht gemischt werden, die Kopfzeile entscheidet.

**Wichtig:** UTF-8 **ohne BOM**, Semikolon als Trennzeichen. Ein Editor, der beim Speichern ein BOM hinzufügt, macht aus der ersten Spalte `﻿email` statt `email`, und die Kopfzeilenprüfung in Moodle scheitert. `import-versetzungsplan.ps1` entfernt ein vorhandenes BOM automatisch, damit gängige Excel-Exporte trotzdem funktionieren.

## `import-versetzungsplan.ps1`

PowerShell-Skript für den periodischen Aufruf über die **Windows-Aufgabenplanung** (Task Scheduler). Liest die CSV, kodiert sie Base64 und ruft den Webservice `local_berufsbildung_import_versetzungsplan` auf. Setzt die Empfehlungen aus Abschnitt 5 der Schnittstelle um: erst Testlauf, laute Fehler (Exit-Code 1 bei Fehlschlag), Prüfung des `exception`-Felds unabhängig vom HTTP-Status.

`Get-Help .\import-versetzungsplan.ps1 -Full` zeigt alle Parameter.

### Einrichtung in Moodle

Siehe Abschnitt 4 der Schnittstelle: Webservice aktivieren, Dienstkonto mit `local/berufsbildung:importplan` anlegen, externen Dienst mit der Funktion einrichten, Token erzeugen.

### Token bereitstellen

Das Token gehört nie in das Skript oder in die Aufgabenplanung selbst. Auf dem Rechner, der den Task ausführt, als Umgebungsvariable auf Benutzer- oder Maschinenebene setzen:

```powershell
[Environment]::SetEnvironmentVariable('MOODLE_BERUFSBILDUNG_TOKEN', '<token>', 'Machine')
```

Danach neu anmelden bzw. den Dienst neu starten, damit die Variable im Prozessumfeld der Aufgabenplanung ankommt.

### Ersteinrichtung: Testlauf

```powershell
.\import-versetzungsplan.ps1 -MoodleUrl https://moodle.example.ch `
    -CsvPath C:\Versetzungsplan\aktuell.csv -Testlauf
```

Protokoll prüfen, insbesondere nach jeder Änderung an der Excel-Struktur — siehe Abschnitt 5 der Schnittstelle. Erst danach den produktiven Task einrichten.

### Task in der Windows-Aufgabenplanung anlegen

```powershell
$aktion  = New-ScheduledTaskAction -Execute 'pwsh.exe' -Argument (
    '-NoProfile -ExecutionPolicy Bypass -File "C:\Versetzungsplan\import-versetzungsplan.ps1" ' +
    '-MoodleUrl https://moodle.example.ch -CsvPath C:\Versetzungsplan\aktuell.csv ' +
    '-LogPath C:\Versetzungsplan\import.log'
)
$trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Sunday -At 22:00
$einstellungen = New-ScheduledTaskSettingsSet -StartWhenAvailable

Register-ScheduledTask -TaskName 'Versetzungsplan-Import' -Action $aktion -Trigger $trigger `
    -Settings $einstellungen -User 'DIENSTKONTO' -RunLevel Limited `
    -Description 'Woechentlicher Versetzungsplan-Import nach local_berufsbildung'
```

Empfehlungen:

- **Ausserhalb der Arbeitszeit**, damit die Excel nicht gerade in Bearbeitung ist (unkritisch, aber sauberer — siehe Abschnitt 5).
- **Konto mit Umgebungsvariable**: der Task muss unter dem Benutzer laufen, für den `MOODLE_BERUFSBILDUNG_TOKEN` gesetzt ist, oder die Variable liegt auf Maschinenebene.
- **Fehler sichtbar machen**: die Aufgabenplanung merkt sich den letzten Exit-Code (Reiter *Verlauf*). Für eine aktive Benachrichtigung zusätzlich eine Aktion registrieren, die bei Task-Fehlschlag auslöst (Ereignisanzeige-Trigger auf die fehlgeschlagene Aufgabe, oder ein Monitoring, das `$LASTEXITCODE`/die Log-Datei auswertet) — ein Skript, das monatelang still nichts tut, ist schlimmer als eines, das laut scheitert.

### Jahreswechsel im August

Der Import ersetzt bei jedem Lauf immer den vollständigen aktuellen Plan — ein Jahreswechsel ist technisch also einfach die nächste reguläre Lieferung, kein Sonderfall im Skript. Zwei Dinge sind trotzdem zu beachten:

- **Neue Zuordnungen zuerst.** Eine Zeile wird nur verarbeitet, wenn die Person eine aktive Zuordnung zu einer Berufsbildnerin oder einem Berufsbildner hat (Abschnitt 2a der Schnittstelle). Für neu startende Lernende also zuerst die Zuordnung in Moodle anlegen — spätestens dann greift `local_berufsbildung`, auch wenn dieselbe (unveränderte) CSV erst danach nochmals eintrifft. Austretende Lernende brauchen im Import selbst nichts: fehlen sie in der neuen CSV, bleiben ihre alten Einsätze einfach stehen, bis die Aufbewahrungsfrist greift.
- **Vollständigkeitsschutz bewusst übersteuern.** Verlässt ein Abschluss-Jahrgang die Zuordnung, sinkt `personen_verarbeitet` bei der ersten Lieferung des neuen Schuljahres voraussichtlich deutlich (Standardschwelle 20 %) — die Lieferung würde sonst mit `status: abgewiesen` zurückgewiesen. Für diesen einen Übergangslauf `-RueckgangBestaetigt` setzen (mit vorherigem `-Testlauf`, um die gemeldete Zahl zu prüfen), danach wieder ohne laufen lassen, damit die Schwelle ihren Zweck für den Rest des Jahres behält.
