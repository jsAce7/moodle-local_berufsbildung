#Requires -Version 5.1
<#
.SYNOPSIS
    Uebermittelt den Versetzungsplan (CSV) an local_berufsbildung in Moodle.

.DESCRIPTION
    Implementiert die Schnittstelle aus docs/schnittstelle_versetzungsplan.md:
    liest die CSV-Datei, kodiert sie Base64 und ruft den Webservice
    local_berufsbildung_import_versetzungsplan auf. Gedacht fuer den
    periodischen Aufruf ueber die Windows-Aufgabenplanung (Task Scheduler),
    funktioniert aber genauso interaktiv.

    Empfehlungen aus Abschnitt 5 der Schnittstelle sind umgesetzt:
    - Erst mit -Testlauf pruefen, insbesondere nach jeder Aenderung an der
      Excel-Struktur.
    - Fehler sind laut: ein nicht erreichbarer Endpunkt, ein abgelaufenes
      Token oder status=fehlgeschlagen/abgewiesen fuehren zu einer
      Fehlermeldung auf Stderr und Exit-Code 1, damit die Aufgabenplanung
      den Lauf als fehlgeschlagen markiert.
    - Der HTTP-Status allein reicht nicht: Moodle antwortet auf
      Webservice-Fehler mitunter mit 200 und einem 'exception'-Feld im
      JSON - das wird gesondert geprueft.

    Nach der Zusammenfassung listet das Skript je Person die Einsaetze, die
    der Lauf entfernt ('entfaellt') oder angelegt ('neu') hat. Dieselben
    Aenderungen haelt Moodle fest (Ausbildungsplanung > Versetzungsplan-Verlauf).

.PARAMETER MoodleUrl
    Basis-URL der Moodle-Instanz, z.B. https://moodle.example.ch (ohne
    abschliessenden Schraegstrich).

.PARAMETER CsvPath
    Pfad zur Versetzungsplan-CSV. Muss UTF-8 ohne BOM, Semikolon-getrennt
    sein - siehe beispiele/versetzungsplan.csv fuer das Format. Ein
    vorhandenes BOM wird entfernt und protokolliert, damit ein per Excel
    exportiertes UTF-8-BOM die Kopfzeilenpruefung in Moodle nicht zum
    Scheitern bringt.

.PARAMETER TokenEnvVar
    Name der Umgebungsvariable, die das Webservice-Token des Dienstkontos
    enthaelt. Das Token gehoert nie in den Quelltext oder in die
    Aufgabenplanung selbst - siehe Abschnitt 4 der Schnittstelle.

.PARAMETER Quelle
    Freitext zur Herkunft der Lieferung, erscheint im Moodle-Importprotokoll.

.PARAMETER Testlauf
    Nur pruefen und Protokoll ausgeben, nichts in Moodle schreiben.

.PARAMETER RueckgangBestaetigt
    Ein deutlicher Rueckgang der verarbeiteten Personen gegenueber der
    letzten Lieferung ist beabsichtigt (z.B. nach einem
    Lehrabschluss-Jahrgang) und soll trotz Schutzmechanismus uebernommen
    werden.

.PARAMETER LogPath
    Optionale Log-Datei. Ohne Angabe wird nur nach Stdout/Stderr protokolliert
    (was die Aufgabenplanung ueblicherweise ohnehin umleitet). Das Log nennt
    je geaendertem Einsatz die Mailadresse der Person - die Datei deshalb wie
    die CSV selbst nur fuer Berechtigte lesbar ablegen. Zeilen, die aelter
    als ein Jahr sind, entfernt jeder Lauf, wie Moodle sein
    Aenderungsprotokoll.

.EXAMPLE
    # Erst-Einrichtung: pruefen, ohne zu schreiben
    .\import-versetzungsplan.ps1 -MoodleUrl https://moodle.example.ch `
        -CsvPath C:\Versetzungsplan\aktuell.csv -Testlauf

.EXAMPLE
    # Produktivlauf, wie ihn die Aufgabenplanung periodisch ausfuehrt
    .\import-versetzungsplan.ps1 -MoodleUrl https://moodle.example.ch `
        -CsvPath C:\Versetzungsplan\aktuell.csv -LogPath C:\Versetzungsplan\import.log
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$MoodleUrl,

    [Parameter(Mandatory = $true)]
    [ValidateScript({ Test-Path -LiteralPath $_ -PathType Leaf })]
    [string]$CsvPath,

    [string]$TokenEnvVar = 'MOODLE_BERUFSBILDUNG_TOKEN',

    [string]$Quelle = 'versetzungsplan-skript',

    [switch]$Testlauf,

    [switch]$RueckgangBestaetigt,

    [string]$LogPath
)

$ErrorActionPreference = 'Stop'

function Write-Log {
    param(
        [Parameter(Mandatory = $true)][string]$Meldung,
        [ValidateSet('INFO', 'WARN', 'ERROR')][string]$Stufe = 'INFO'
    )

    $zeile = '{0} [{1}] {2}' -f (Get-Date -Format 's'), $Stufe, $Meldung

    if ($Stufe -eq 'ERROR') {
        Write-Error $Meldung -ErrorAction Continue
    } elseif ($Stufe -eq 'WARN') {
        Write-Warning $Meldung
    } else {
        Write-Output $zeile
    }

    if ($LogPath) {
        Add-Content -LiteralPath $LogPath -Value $zeile
    }
}

function Exit-MitFehler {
    param([string]$Meldung)
    Write-Log -Stufe 'ERROR' -Meldung $Meldung
    exit 1
}

# --- Log auf ein Jahr kuerzen --------------------------------------------
# Jede Zeile beginnt mit dem Zeitstempel aus Write-Log (Format 's'). Zeilen
# ohne lesbaren Zeitstempel stammen nicht vom Skript und bleiben stehen.
if ($LogPath -and (Test-Path -LiteralPath $LogPath -PathType Leaf)) {
    $grenze = (Get-Date).AddYears(-1)
    $kultur = [System.Globalization.CultureInfo]::InvariantCulture
    $behalten = @(Get-Content -LiteralPath $LogPath | Where-Object {
        $zeitpunkt = [datetime]::MinValue
        $lesbar = $_.Length -ge 19 -and
            [datetime]::TryParseExact($_.Substring(0, 19), 's', $kultur, [System.Globalization.DateTimeStyles]::None, [ref]$zeitpunkt)
        -not $lesbar -or $zeitpunkt -ge $grenze
    })
    Set-Content -LiteralPath $LogPath -Value $behalten
}

# --- Token laden -------------------------------------------------------
$token = [Environment]::GetEnvironmentVariable($TokenEnvVar, 'Process')
if ([string]::IsNullOrWhiteSpace($token)) {
    # Prozessumgebung leer -> auch auf Benutzer-/Maschinenebene nachsehen,
    # falls die Aufgabenplanung die Variable dort gesetzt hat statt im
    # Prozess selbst.
    $token = [Environment]::GetEnvironmentVariable($TokenEnvVar, 'User')
}
if ([string]::IsNullOrWhiteSpace($token)) {
    $token = [Environment]::GetEnvironmentVariable($TokenEnvVar, 'Machine')
}
if ([string]::IsNullOrWhiteSpace($token)) {
    Exit-MitFehler "Umgebungsvariable '$TokenEnvVar' ist leer oder nicht gesetzt - Token fehlt."
}

# --- CSV lesen, BOM entfernen, Base64 kodieren --------------------------
$bytes = [System.IO.File]::ReadAllBytes($CsvPath)

$bom = [System.Text.Encoding]::UTF8.GetPreamble()
if ($bytes.Length -ge $bom.Length -and $null -eq (Compare-Object $bytes[0..($bom.Length - 1)] $bom -SyncWindow 0)) {
    Write-Log "UTF-8-BOM in '$CsvPath' gefunden und entfernt (Schnittstelle verlangt BOM-frei)." -Stufe 'WARN'
    $bytes = $bytes[$bom.Length..($bytes.Length - 1)]
}

if ($bytes.Length -eq 0) {
    Exit-MitFehler "CSV-Datei '$CsvPath' ist leer."
}

$csvdaten = [Convert]::ToBase64String($bytes)

# --- Webservice aufrufen -------------------------------------------------
$endpunkt = "$($MoodleUrl.TrimEnd('/'))/webservice/rest/server.php"

$body = @{
    wstoken            = $token
    wsfunction         = 'local_berufsbildung_import_versetzungsplan'
    moodlewsrestformat = 'json'
    csvdaten           = $csvdaten
    quelle             = $Quelle
    testlauf           = if ($Testlauf) { '1' } else { '0' }
    rueckgang_bestaetigt = if ($RueckgangBestaetigt) { '1' } else { '0' }
}

Write-Log "Sende '$CsvPath' an $endpunkt (testlauf=$($body.testlauf))."

try {
    $antwort = Invoke-RestMethod -Uri $endpunkt -Method Post -Body $body -TimeoutSec 60
} catch {
    # Deckt sowohl Netzwerkfehler als auch HTTP-Fehlerstatus ab - beides
    # muss laut scheitern, nicht stillschweigend uebersprungen werden.
    Exit-MitFehler "Aufruf des Webservice fehlgeschlagen: $($_.Exception.Message)"
}

# Moodle antwortet auf Webservice-Fehler mitunter mit HTTP 200 und einem
# 'exception'-Feld im JSON statt eines Fehlerstatus - deshalb wird das
# unabhaengig vom HTTP-Status geprueft.
if ($antwort.PSObject.Properties.Name -contains 'exception') {
    $meldung = if ($antwort.PSObject.Properties.Name -contains 'message') { $antwort.message } else { 'unbekannter Fehler' }
    Exit-MitFehler "Moodle-Webservice-Fehler ($($antwort.exception)): $meldung"
}

# --- Ergebnis auswerten ---------------------------------------------------
$zusammenfassung = 'Status: {0}  unveraendert: {1}  Zeilen gelesen: {2}  Personen verarbeitet: {3}  ausserhalb Geltungsbereich: {4}  Einsaetze erzeugt: {5}' -f `
    $antwort.status, $antwort.unveraendert, $antwort.zeilen_gelesen, $antwort.personen_verarbeitet, `
    $antwort.zeilen_ausserhalb_geltungsbereich, $antwort.einsaetze_erzeugt
Write-Log $zusammenfassung

foreach ($zeile in $antwort.protokoll) {
    Write-Log $zeile -Stufe 'WARN'
}

# Je Person die Einsaetze, die wegfallen oder neu dazukommen - beim Testlauf
# und beim Zurueckhalten die, die eine Uebernahme aendern wuerde. Gefiltert
# statt @($antwort.aenderungen), weil @($null) in PowerShell 5.1 ein Element
# hat und eine aeltere Moodle-Version das Feld nicht kennt.
$aenderungen = @($antwort.aenderungen | Where-Object { $_ })
if ($aenderungen.Count -gt 0) {
    $personen = @($aenderungen | Select-Object -ExpandProperty email -Unique).Count
    Write-Log ('Einsatzaenderungen: {0} bei {1} Personen' -f $aenderungen.Count, $personen)
    foreach ($aenderung in $aenderungen) {
        $zeitraum = if ($aenderung.kw_von -eq $aenderung.kw_bis) { $aenderung.kw_von } else { "$($aenderung.kw_von) - $($aenderung.kw_bis)" }
        Write-Log ('  {0,-9} {1}  Block {2}  {3}' -f $aenderung.art, $aenderung.email, $aenderung.block, $zeitraum)
    }
}

switch ($antwort.status) {
    'fehlgeschlagen' { Exit-MitFehler 'Import fehlgeschlagen - nichts wurde geschrieben. Siehe Protokoll oben.' }
    'abgewiesen' { Exit-MitFehler 'Lieferung abgewiesen (Vollstaendigkeitsschutz). Bei Bedarf mit -RueckgangBestaetigt erneut senden.' }
    default { Write-Log 'Import abgeschlossen.' }
}

exit 0
