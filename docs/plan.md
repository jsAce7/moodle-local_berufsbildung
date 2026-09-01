# Konzept: `local_berufsbildung`

Basis-Plugin für die betriebliche Berufsbildung in Moodle. Es besitzt die Zuordnung zwischen Berufsbildner/innen und Lernenden sowie die Auflösung von Beruf, Lehrjahr und Semester — und stellt beides als API für aufsetzende Plugins bereit.

Dieses Plugin hat **keine eigene Fachfunktion**. Es liefert keine Berichte, keine Formulare für Lernende, keine Beurteilung. Es beantwortet drei Fragen: *Wer ist für wen zuständig?*, *In welchem Beruf und Semester steht diese Person?* und *In welcher Abteilung war sie wann?*

---

## 1. Warum ein eigenes Plugin

Geplant sind mehrere Anwendungen auf derselben Grundlage:

- `local_bildungsbericht` — halbjährliche Standortbestimmung (in Umsetzung)
- `local_lerndokumentation` — laufendes Journal der Lernenden (geplant)
- später: Arbeitsplatzberichte der Fachvorgesetzten aus den Rotationseinsätzen

Alle drei brauchen dieselbe Antwort auf „wer darf hier was sehen". Würde jedes Plugin seine eigene Zuordnungstabelle führen, müsste die Ausbildungsadministration dieselbe Zuweisung mehrfach pflegen — und die Stände würden auseinanderlaufen. Genau das soll dieses Plugin verhindern.

Die Trennung jetzt zu ziehen kostet ein Verzeichnis und eine Dependency-Zeile. Später ist es eine Datenmigration über mehrere Plugins hinweg mit Übergangsphase.

---

## 2. Abgrenzung

| Gehört hierher | Gehört ins Feature-Plugin |
|---|---|
| Wer ist wem zugeordnet, seit wann, in welcher Rolle | Was man mit dieser Zuständigkeit tun darf |
| Rollen-Sync in den Moodle-User-Kontext | Fachliche Capabilities |
| CSV-Import der Zuordnungen | Fachliche Datenmodelle |
| Beruf, Lehrjahr, Semester einer lernenden Person | Vorlagen, Zustandsmaschinen, PDF |
| Einsammeln von Nachweisen über Plugin-Grenzen | Wie ein Nachweis fachlich verwertet wird |
| Kompetenzabdeckung der Ausbildungsblöcke | Was aus dieser Information gefolgert wird |
| Import und Spiegelung des Versetzungsplans | — der Plan selbst gehört der Excel, nicht Moodle |

**Bewusst nicht hier:**

- Kompetenzrahmen — `core_competency` ist bereits eine geteilte Grundlage, eine Zwischenschicht bringt nichts
- Capabilities der Feature-Plugins — dieses Plugin definiert die *Beziehung*, nicht die *Befugnis*. Sonst kann später niemand Einsicht in die Lerndokumentation bekommen, ohne auch den Bildungsbericht geöffnet zu bekommen.
- Alles, was heute nur ein einziges Feature-Plugin braucht. Im Zweifel bleibt es dort und wandert erst herunter, wenn ein zweites Plugin es tatsächlich benötigt.

---

## 3. Datenmodell

```
local_berufsbildung_zuordnung
  id
  berufsbildnerid    int(10)        -- FK -> user.id
  lernendeid         int(10)        -- FK -> user.id
  beruf              varchar(255)   -- Freitext: Kurzcode ('AU_EFZ') oder ausgeschriebene Bezeichnung ('Automatiker/in EFZ'), je nach Profilfeld
  rolle              varchar(20)    -- v1: immer 'hauptverantwortlich'
  gueltig_von        int(10)
  gueltig_bis        int(10)        -- NULL = laufend
  bemerkung          varchar(255)
  timecreated, timemodified, usermodified

  UNIQUE KEY (berufsbildnerid, lernendeid, gueltig_von)
  INDEX (lernendeid, gueltig_bis)
  INDEX (berufsbildnerid, gueltig_bis)
```

**Zuordnungen werden nie gelöscht**, nur mit `gueltig_bis` beendet. Sonst ist später nicht mehr nachvollziehbar, wer in einem vergangenen Semester zuständig war — und genau das ist bei einer Rückfrage der kantonalen Ausbildungsberatung die entscheidende Information.

*Ausnahme seit dem Retention-Feature*: `retention_monate` (Standard 12) nach dem berechneten Ausbildungsabschluss werden alle Zuordnungen einer Person automatisch endgültig gelöscht, ausser eine Aufbewahrungspflicht ist dokumentiert — siehe Abschnitt 13 Punkt 4 und `CLAUDE.md` Architekturregel 2.

**Feld `rolle`**: in v1 hat es immer den Wert `hauptverantwortlich`. Es existiert, damit die spätere Erweiterung um Fachvorgesetzte (Arbeitsplatzberichte) keine Schema-Migration braucht. Aufrufende Plugins müssen den Wert abfragen, nicht annehmen.

---

## 4. Öffentliche API

Alles, was andere Plugins nutzen, läuft über `\local_berufsbildung\api`. Direkter Tabellenzugriff aus Feature-Plugins ist nicht vorgesehen — die Tabellenstruktur ist Implementierungsdetail.

```php
namespace local_berufsbildung;

class api {

    /** Ist diese Person zum Stichtag für die/den Lernende/n zuständig? */
    public static function is_zustaendig(
        int $berufsbildnerid,
        int $lernendeid,
        ?int $stichtag = null,
        ?string $rolle = null
    ): bool;

    /** Alle Lernenden dieser/dieses Berufsbildner/in zum Stichtag. */
    public static function get_lernende_for(
        int $berufsbildnerid,
        ?int $stichtag = null
    ): array;

    /** Alle zuständigen Personen für eine/n Lernende/n zum Stichtag. */
    public static function get_berufsbildner_for(
        int $lernendeid,
        ?int $stichtag = null
    ): array;

    /** Beruf, Lehrjahr und laufendes Semester einer lernenden Person. */
    public static function get_ausbildungsstand(int $lernendeid): ?ausbildungsstand;

    /** Zuordnung anlegen; beendet eine bestehende automatisch, falls nötig. */
    public static function set_zuordnung(
        int $berufsbildnerid,
        int $lernendeid,
        string $beruf,
        int $gueltig_von,
        string $rolle = 'hauptverantwortlich'
    ): zuordnung;

    /** Zuordnung beenden (setzt gueltig_bis, löscht nicht). */
    public static function beende_zuordnung(int $zuordnungid, int $gueltig_bis): void;
}
```

`$stichtag = null` bedeutet „jetzt". Der Parameter ist wichtig, weil ein abgeschlossener Bericht auch dann noch korrekt angezeigt werden muss, wenn die Zuordnung inzwischen beendet wurde.

**Wertobjekt `ausbildungsstand`**

```php
readonly class ausbildungsstand {
    public string $beruf;         // 'AU_EFZ'
    public int $lehrjahr;         // 1..4
    public int $semester;         // 1..8
    public ?int $lehrbeginn;      // Timestamp, falls hinterlegt
}
```

Wird aus den Moodle-Profilfeldern aufgelöst, die auch `enrol_autoenrol` für die Gruppenbildung nutzt. Welche Felder das genau sind, ist über die Plugin-Einstellungen konfigurierbar (`profilefield_beruf`, `profilefield_jahrgang`) — nicht hartcodiert, damit sich eine Umbenennung im Profil nicht durch alle Plugins zieht.

**Semesterberechnung**

Alle Lehren beginnen am **1. August**. Damit genügen Beruf und Jahrgang — ein separates Feld für den Lehrbeginn ist nicht nötig, und der Jahrgang ist ohnehin schon im Profil vorhanden.

```
lehrbeginn = 1. August des Jahrgangs
monate     = (jahr_heute − jahrgang) × 12 + (monat_heute − 8)
semester   = floor(monate ÷ 6) + 1
```

| Semester | Zeitraum |
|---|---|
| ungerade (1, 3, 5, 7) | 1. August – 31. Januar |
| gerade (2, 4, 6, 8) | 1. Februar – 31. Juli |

Liegt das Ergebnis ausserhalb von 1 bis 8, gibt die Methode `null` zurück — die Lehre hat noch nicht begonnen oder ist beendet.

Startmonat (Standard 8) und Lehrdauer in Semestern (Standard 8) sind Plugin-Einstellungen, nicht hartcodiert. Bei EBA-Berufen wären es vier Semester.

Die Berechnung liegt in `\local_berufsbildung\service\semester_calculator`, getrennt von der Profilfeld-Auflösung — so ist sie ohne Moodle-Nutzer testbar.

---

## 5. Versetzungsplan (Import über Webservice)

Der Versetzungsplan wird **ausserhalb von Moodle gepflegt** — als Excel auf SharePoint, die der ganzen Firma zur Verfügung steht: Kalenderwochen als Spalten, eine Zeile pro lernender Person, in den Zellen die Nummern der Ausbildungsblöcke.

Moodle besitzt diesen Plan nicht, sondern spiegelt ihn. Er wird importiert und ist in Moodle **nicht editierbar** — sonst entstehen zwei Wahrheiten, und die Frage „welche gilt" beantwortet niemand zuverlässig.

Moodle liest die Excel auch nicht selbst. Ein Skript auf Firmenseite wertet sie aus und liefert die normalisierten Daten über einen Webservice. Damit hängt die Schnittstelle nicht am Layout der Tabelle.

### 5.1 Die Trennlinie

| Excel und Skript wissen | Moodle weiss |
|---|---|
| Wer ist in welcher Kalenderwoche in welchem Block | Welche Handlungskompetenzen ein Block vermittelt |

Die **Blocknummer ist der Schlüssel** zwischen beiden. Sie steht in der Excel-Zelle und in der Moodle-Tabelle. Ändert sich der Plan, ändert sich nur die Excel; ändert sich die Kompetenzabdeckung eines Blocks, nur Moodle.

Das ist auch die Antwort auf die Frage, warum nicht die ganze Zuordnung in die Excel wandert: Kompetenzabdeckung ist Ausbildungswissen, das gepflegt und versioniert werden will, und niemand will das in einer Zelle mit Zeilenumbrüchen führen.

### 5.2 Datenmodell

```
local_berufsbildung_block
  id
  nummer             varchar(20)    -- exakt wie in der Excel-Zelle
  name               varchar(255)   -- Klartext, in Moodle gepflegt
  beruf              varchar(255)   -- Freitext wie zuordnung.beruf; steuert den Kompetenzrahmen
                                     -- bei der LK-Zuordnung. Leer = berufsübergreifend
  ist_betrieb        int(1)         -- 0 für Schule, üK, Ferien, Militär
  aktiv              int(1)

  UNIQUE KEY (nummer)
```

`beruf` wird beim manuellen Anlegen des Blocks gesetzt (Auswahl aus den auf der Seite „Kompetenzrahmen je Beruf" konfigurierten Codes) und schränkt die LK-Auswahl in `block_lk` auf den zum Beruf konfigurierten Kompetenzrahmen ein — ohne dieses Feld standen bei mehreren konfigurierten Berufen alle Rahmen gemischt in einem Dropdown. Leer bleibt es typischerweise bei berufsübergreifenden `ist_betrieb=0`-Blöcken (Schule, üK, Ferien, Militär), die für alle Berufe denselben Blockcode verwenden — `nummer` bleibt deshalb weiterhin der alleinige, berufsunabhängige Schlüssel aus der Excel-Zelle (siehe §5.1); der Import kennt `beruf` nicht und muss es auch nicht kennen, da Blöcke ohnehin manuell gepflegt werden.

```
local_berufsbildung_block_lk
  id
  blockid            int(10)
  competencyid       int(10)
  intensitaet        varchar(20)    -- 'schwerpunkt' | 'teilweise'

  UNIQUE KEY (blockid, competencyid)
```

Das ist der einzige Teil, der in Moodle gepflegt wird. Einmal angelegt, selten geändert.

```
local_berufsbildung_einsatz
  id
  userid             int(10)
  blockid            int(10)
  von                int(10)        -- Montag der ersten KW
  bis                int(10)        -- Sonntag der letzten KW
  kw_von             varchar(8)     -- '2027-W15', für Rückverfolgung
  kw_bis             varchar(8)
  importid           int(10)        -- aus welchem Importlauf

  INDEX (userid, von)
```

Aufeinanderfolgende Kalenderwochen mit derselben Blocknummer werden beim Import zu **einem** Einsatz zusammengefasst. Aus zwölf Zellen mit „4" wird ein Einsatz über zwölf Wochen — sonst hätte man pro Lehrling und Jahr zweiundfünfzig Datensätze und keine brauchbare Zeitachse.

```
local_berufsbildung_import
  id
  quelle             varchar(50)    -- 'webservice' | 'upload'
  daten_hash         char(64)       -- unveränderte Lieferung wird nicht erneut verarbeitet
  zeitpunkt          int(10)
  ausgefuehrt_von    int(10)
  zeilen_gelesen     int(10)
  personen_erkannt   int(10)
  einsaetze_erzeugt  int(10)
  status             varchar(20)    -- 'ok' | 'mit_warnungen' | 'abgewiesen' | 'fehlgeschlagen'
  protokoll          text           -- Zeile für Zeile, was nicht zugeordnet werden konnte
```

### 5.3 Import

Der Plan wird **nicht als Excel gelesen**. Ein Skript auf Firmenseite wertet die Excel aus und liefert wöchentlich eine normalisierte CSV über einen Webservice. Damit ist das Layout der Excel von der Schnittstelle entkoppelt: Verschiebt jemand eine Spalte, ändert sich das Skript, nicht Moodle.

Die vollständige Spezifikation liegt in `schnittstelle_versetzungsplan.md`. Kurzfassung:

```csv
email;block;kw_von;kw_bis;bemerkung
anna.muster@firma.ch;4;2027-W15;2027-W26;
anna.muster@firma.ch;7;2027-W27;2027-W33;
```

Alternativ eine Zeile je Woche (`email;block;kw`) — dann fasst Moodle aufeinanderfolgende Wochen mit demselben Block selbst zusammen. Die Kopfzeile entscheidet, welches Format vorliegt.

**Kalenderwochen** in ISO 8601 mit explizitem Jahresbezug. `2027-W03` wird auf Montag, 18. Januar bis Sonntag, 24. Januar 2027 abgebildet. Ein Format ohne Jahr wäre bei einem Plan über vier Lehrjahre nicht eindeutig.

**Zuordnung der Personen** über `user.email`. Die Mailadresse wird von Entra ID nach Moodle synchronisiert, sodass Änderungen automatisch nachziehen — anders als bei einer separat gepflegten Kennungstabelle. Das Feld `idnumber` scheidet aus, weil es mit dem Entra-Objektbezeichner belegt ist, der in der Excel nicht vorkommt.

**Verarbeitet wird nur, wer eine aktive Zuordnung hat.** Das Skript liefert immer alle Lernenden; welche davon in Moodle ankommen, entscheidet der Zuordnungsbestand. Damit steuerst du die Einführungsphase über die normale Zuordnungsverwaltung: zwei Zuordnungen für den Pilot, die übrigen Zeilen laufen ins Leere, und beim Ausweiten ändert sich am Skript nichts.

Übersprungene Zeilen sind keine Fehler und erscheinen nicht als Warnungen, sondern werden separat gezählt — sonst stünden im Protokoll bei zwei Pilot-Lernenden achtzehn Meldungen.

Nicht zuordenbare Mailadressen von Personen, die eine Zuordnung haben sollten, landen dagegen sehr wohl im Protokoll.

**Unbekannte Blocknummern** werden ohne Kompetenzabdeckung angelegt und protokolliert. Die Zeitachse stimmt dann, nur die Vorbelegung im Bildungsbericht fehlt für diesen Block.

**Vollständige Lieferung**: Jeder Lauf enthält den gesamten Plan, nicht nur Änderungen. Moodle ersetzt den Bestand der gelieferten Personen vollständig — sonst blieben in der Excel gelöschte Einsätze stehen.

**Schutz vor unvollständigen Lieferungen**: Enthält ein Lauf deutlich weniger *verarbeitete* Personen als der vorherige, wird er abgewiesen statt verarbeitet. Gezählt wird der verarbeitete Bestand, nicht die Zeilenzahl — sonst schlüge die Schwelle beim Ausweiten des Teilnehmerkreises in die falsche Richtung an. Das fängt den Fall ab, dass das Skript wegen eines Fehlers eine halbe Datei erzeugt und Moodle daraufhin sämtliche Einsätze löscht. Ein beabsichtigter Rückgang wird über einen Parameter bestätigt.

**Idempotenz**: Über den Hash der Daten. Bei wöchentlicher Lieferung eines Plans, der sich einige Male im Jahr ändert, ist die unveränderte Lieferung der Normalfall — sie wird erkannt und ohne Schreibvorgang beendet.

**Ein fehlgeschlagener Import lässt den bestehenden Datenbestand unangetastet.** Nie Einsätze löschen, weil eine Lieferung fehlerhaft war.

**Testlauf**: Der Webservice nimmt einen Parameter, der nur prüft und das Protokoll zurückgibt, ohne zu schreiben. Für die Einrichtung und nach jeder Änderung an der Excel-Struktur.

### 5.4 Auslösung

**Regelweg: Webservice.** Ein Skript auf Firmenseite liefert wöchentlich. Einrichtung in Moodle: Dienstkonto (kein persönliches Konto), Rolle mit `local/berufsbildung:importplan` im Systemkontext, externer Dienst mit der Funktion `local_berufsbildung_import_versetzungsplan`, Token, nach Möglichkeit IP-Einschränkung.

**Rückfallweg: manueller Upload** unter `local/berufsbildung/import_plan.php`, mit derselben Vorschau und demselben Protokoll. Nimmt dieselbe CSV entgegen. Wird gebraucht, solange das Skript noch nicht steht, und wenn ausserplanmässig etwas nachgezogen werden muss.

Beide Wege enden im selben Importvorgang. Die Verarbeitungslogik kennt den Weg nicht.

**Alterungshinweis**: Liegt der letzte erfolgreiche Import länger als eine konfigurierbare Frist zurück, erscheint ein Hinweis auf der Übersichtsseite. Keine Sperre — aber es soll niemandem entgehen, dass die Vorbelegung im Bildungsbericht auf einem veralteten Plan beruht.

### 5.5 Verantwortung für Fehler

Läuft das Skript per Zeitplan, muss ein Fehlschlag auf der Skript-Seite gemeldet werden — nicht erreichbarer Endpunkt, abgelaufenes Token, abgewiesene Lieferung. Moodle kann nur melden, was bei ihm ankommt; eine Lieferung, die nie versucht wurde, sieht es nicht.

Der Alterungshinweis aus 5.4 ist die Rückversicherung dagegen: Er greift auch dann, wenn auf der anderen Seite niemand etwas bemerkt hat.

### 5.6 API

```php
public static function get_einsaetze(int $lernendeid, ?int $von = null, ?int $bis = null): array;

/** Vereinigung der Kompetenzen aller betrieblichen Einsätze im Zeitraum. */
public static function get_ausgebildete_kompetenzen(int $lernendeid, int $von, int $bis): array;

public static function get_aktueller_einsatz(int $lernendeid): ?einsatz;
```

Einsätze, die sich nur teilweise mit dem Zeitraum überschneiden, zählen mit. Ein Block, der Mitte Januar endet, gehört zum Semester, das am 31. Januar endet.

### 5.7 Verwendung

**Bildungsbericht**: Vorbelegung der Checkbox „in diesem Semester nicht ausgebildet" für alle Handlungskompetenzen, die in keinem Einsatz des Semesters vorkamen. Als Vorschlag, überschreibbar.

**Lerndokumentation**: Der Einsatz zum Arbeitsdatum liefert die Abteilung, statt sie eintippen zu lassen.

**Lückenanalyse**: Welche Handlungskompetenzen kamen bis zum aktuellen Lehrjahr in keinem Block vor? Im dritten Lehrjahr ein Hinweis für die Ausbildungsplanung — und weil der Plan importiert ist, sieht man die Lücke, bevor sie eintritt.

### 5.8 Wenn kein Import vorliegt

Alles funktioniert unverändert, nur ohne Vorbelegung, ohne automatische Abteilung und ohne Lückenanalyse. Der Versetzungsplan ist eine Verbesserung, keine Voraussetzung.

---

## 6. Nachweis-Provider

Neben der Zuordnung besitzt das Basis-Plugin einen zweiten geteilten Mechanismus: das Einsammeln von Leistungsnachweisen über Plugin-Grenzen hinweg.

**Das Problem**: Der Berufsbildner soll die üK-Resultate seiner Lernenden sehen können, ohne im üK-Kurs eingeschrieben zu sein — und diese Resultate sollen sich in den Bildungsbericht übernehmen lassen. Würde der Bildungsbericht dafür direkt auf das üK-Plugin zugreifen, entstünde eine Abhängigkeitskette, die beim nächsten Datenlieferanten (Arbeitsplatzberichte, Berufsfachschulnoten) erneut gebaut werden müsste.

**Die Lösung**: Das Basis-Plugin definiert, was ein Nachweis ist. Jedes Plugin, das welche liefern kann, registriert sich als Provider. Konsumenten fragen nur das Basis-Plugin und kennen die Quellen nicht.

### Interface

```php
namespace local_berufsbildung\nachweis;

interface provider {
    /** Nachweise für eine lernende Person in einem Zeitraum. */
    public function get_nachweise(int $lernendeid, int $von, int $bis): array;

    /** Anzeigename der Quelle, z.B. "Überbetriebliche Kurse". */
    public function get_quelle_name(): string;

    /** Eindeutiger Schlüssel der Quelle, z.B. 'uek'. */
    public function get_quelle_key(): string;
}
```

### Wertobjekt

```php
readonly class nachweis {
    public string $quelle_key;     // 'uek'
    public string $bezeichnung;    // 'üK 3: Steuerungstechnik'
    public int $datum;
    public ?string $ergebnis;      // '5.0' | 'bestanden' | 'teilweise erfüllt'
    public ?int $competencyid;     // optional, wenn die Quelle Kompetenzen kennt
    public ?string $url;           // Link zur Quelle in Moodle
}
```

Bewusst arm gehalten: keine Notenlogik, keine üK-Spezifika, keine Skalen. Eine Quelle, die differenzierter arbeitet, verlinkt über `url` auf ihre eigene Detailansicht.

### Registrierung

Provider registrieren sich über den Standard-Plugin-Callback:

```php
// In lib.php des liefernden Plugins:
function local_uek_kompetenznachweis_berufsbildung_nachweis_provider(): \local_berufsbildung\nachweis\provider {
    return new \local_uek_kompetenznachweis\nachweis_provider();
}
```

Das Basis-Plugin sammelt sie über `get_plugins_with_function()` ein. Es kennt keine einzelne Quelle namentlich; ein neues lieferndes Plugin braucht keine Änderung hier.

### Berechtigung

**Die Zuständigkeitsprüfung passiert im Basis-Plugin, bevor ein Provider aufgerufen wird** — nie im Provider selbst. Sonst hängt die Absicherung an jeder einzelnen Implementierung, und ein nachlässig geschriebener Provider öffnet Leistungsdaten für alle.

```php
public static function get_nachweise(int $lernendeid, int $von, int $bis): array {
    global $USER;
    if (!self::is_zustaendig($USER->id, $lernendeid)
        && !has_capability('local/berufsbildung:viewallnachweise', \context_system::instance())) {
        throw new \required_capability_exception(/* ... */);
    }
    // erst jetzt die Provider abfragen
}
```

Zusätzliche Capability: `local/berufsbildung:viewallnachweise` (System) für die Ausbildungsleitung.

### Übersichtsseite

`local/berufsbildung/nachweise.php` — „meine Lernenden", pro Person alle Nachweise aus allen registrierten Quellen, nach Datum sortiert und nach Quelle filterbar. Der Berufsbildner braucht dafür keine Kurseinschreibung; die Berechtigung kommt aus der Zuordnung.

Das ist die dritte eigenständige Verwendung des Basis-Plugins und ein guter Beleg dafür, dass die Trennung trägt.

### Was Konsumenten beachten müssen

Wer Nachweise weiterverarbeitet (etwa der Bildungsbericht beim Übernehmen in eine Beurteilungszeile), muss die Werte **kopieren, nicht referenzieren**. Andernfalls ändert sich ein bereits unterschriebener Bericht rückwirkend, wenn in der Quelle eine Korrektur erfolgt.

---

## 7. Rollen-Sync

Das Plugin legt bei der Installation eine Rolle `berufsbildner` mit `contextlevel = CONTEXT_USER` an, sofern sie nicht existiert. Sie trägt **keine** eigenen Capabilities — die vergeben die Feature-Plugins.

Der Scheduled Task `\local_berufsbildung\task\sync_role_assignments` gleicht die Tabelle mit den Moodle-Rollenzuweisungen ab:

- Aktive Zuordnung ohne Rollenzuweisung → zuweisen
- Rollenzuweisung ohne aktive Zuordnung → entziehen
- Bestehende, unveränderte → unangetastet lassen

Der Task setzt `component = 'local_berufsbildung'` bei der Rollenzuweisung. Damit erkennt er seine eigenen Zuweisungen wieder und fasst manuell vergebene Rollen nicht an.

**Warum überhaupt Rollen, wenn die Tabelle führend ist**
Damit Moodle-Kernfunktionen (Nutzerauswahl, Berichte, Profilsichtbarkeit) ohne Sonderbehandlung funktionieren. Die Rolle sagt „darf grundsätzlich", die Tabelle sagt „ist aktuell zuständig". Feature-Plugins prüfen beides — Capability aus dem eigenen Plugin plus `api::is_zustaendig()`.

---

## 8. Verwaltung

**Übersichtsseite** `local/berufsbildung/zuordnung.php`
Report-Builder-Datasource mit Filtern nach Beruf, Lehrjahr, Berufsbildner/in und Status (laufend / beendet). Export nach CSV und Excel kommt damit gratis.

**CSV-Import**
Für den Jahreswechsel, wenn ein ganzer Lehrjahrgang neu zugeordnet wird. Spalten: Benutzername Berufsbildner/in, Benutzername Lernende/r, Beruf, gültig ab. Vorschau vor dem Übernehmen, Zeilen mit Fehlern werden einzeln gemeldet statt den ganzen Import abzubrechen.

**Einzelbearbeitung**
Zuordnung anlegen und beenden über ein einfaches Formular. Beim Anlegen einer neuen Zuordnung für eine/n Lernende/n, die/der bereits eine laufende hat, wird die alte automatisch zum Vortag beendet — mit Hinweis im Formular, nicht stillschweigend.

---

## 9. Capabilities

| Capability | Kontext | Zweck |
|---|---|---|
| `local/berufsbildung:managezuordnung` | System | Zuordnungen anlegen, beenden, importieren |
| `local/berufsbildung:viewzuordnung` | System | Übersicht einsehen, ohne zu ändern |
| `local/berufsbildung:viewallnachweise` | System | Nachweise aller Lernenden sehen (Ausbildungsleitung) |
| `local/berufsbildung:manageblocks` | System | Ausbildungsblöcke und ihre Kompetenzabdeckung pflegen |
| `local/berufsbildung:importplan` | System | Versetzungsplan importieren |

Mehr nicht. Alles Fachliche gehört in die aufsetzenden Plugins. Die Nachweis-Einsicht für die eigenen Lernenden braucht keine Capability — sie folgt aus der Zuordnung.

---

## 10. Verzeichnisstruktur

```
local/berufsbildung/
├── classes/
│   ├── api.php
│   ├── ausbildungsstand.php
│   ├── nachweis/
│   │   ├── provider.php             -- Interface für liefernde Plugins
│   │   ├── nachweis.php             -- Wertobjekt
│   │   └── collector.php            -- sammelt Provider ein, prüft Zuständigkeit
│   ├── versetzungsplan/
│   │   ├── plan_service.php         -- Abfrage der Einsätze und Kompetenzabdeckung
│   │   ├── csv_parser.php           -- beide Formate, Validierung
│   │   ├── import_service.php       -- Verarbeitung, Ersetzung, Protokoll
│   │   ├── kw_converter.php         -- ISO-Kalenderwoche -> Zeitraum
│   │   └── luecken_analyse.php
│   ├── persistent/
│   │   ├── zuordnung.php
│   │   ├── block.php
│   │   ├── block_lk.php
│   │   ├── einsatz.php
│   │   └── plan_import.php
│   ├── form/
│   │   ├── zuordnung_form.php
│   │   └── import_form.php
│   ├── reportbuilder/
│   │   ├── local/entities/zuordnung_entity.php
│   │   └── datasource/zuordnungen.php
│   ├── external/
│   │   └── import_versetzungsplan.php
│   ├── task/
│   │   └── sync_role_assignments.php
│   ├── event/
│   │   ├── zuordnung_created.php
│   │   └── zuordnung_ended.php
│   ├── privacy/provider.php
│   └── import/zuordnung_csv_importer.php
├── db/
│   ├── access.php
│   ├── install.xml
│   ├── install.php          -- legt die Rolle berufsbildner an
│   ├── upgrade.php
│   └── tasks.php
├── lang/
│   ├── de/local_berufsbildung.php
│   └── en/local_berufsbildung.php
├── tests/
│   ├── api_test.php
│   ├── sync_role_assignments_test.php
│   ├── csv_importer_test.php
│   ├── nachweis_collector_test.php
│   ├── plan_service_test.php
│   ├── csv_parser_test.php
│   ├── kw_converter_test.php
│   ├── privacy_provider_test.php
│   ├── generator/lib.php
│   └── behat/zuordnung_verwalten.feature
├── zuordnung.php
├── nachweise.php
├── bloecke.php
├── einsaetze.php
├── import_plan.php      -- manueller Upload als Rückfallweg
├── import.php
├── settings.php
├── lib.php
└── version.php
```

---

## 11. Einbindung durch Feature-Plugins

In der `version.php` des aufsetzenden Plugins:

```php
$plugin->dependencies = ['local_berufsbildung' => 2026090100];
```

Moodle erzwingt die Abhängigkeit dann bei Installation und Upgrade.

Im Code des Feature-Plugins, an genau einer Stelle gekapselt:

```php
public static function can_manage(int $lernendeid, ?int $stichtag = null): bool {
    global $USER;
    $context = \context_user::instance($lernendeid);
    return has_capability('local/bildungsbericht:manage', $context)
        && \local_berufsbildung\api::is_zustaendig($USER->id, $lernendeid, $stichtag);
}
```

---

## 12. Umsetzung

**Phase 1 — Kern**
Datenmodell, Persistent-Klasse, `api` mit allen Methoden, Capabilities, Rollenanlage bei Installation.
*Abnahme*: Installation auf 4.5 und 5.2 sauber, `check_database_schema.php` grün, Unit-Tests für alle API-Methoden inklusive Stichtagslogik.

**Phase 2 — Sync und Verwaltung**
`sync_role_assignments`, Übersichtsseite über Report Builder, Einzelbearbeitung, CSV-Import mit Vorschau.
*Abnahme*: Zuordnung anlegen führt nach Task-Lauf zur Rollenzuweisung; `gueltig_bis` setzen entzieht sie; manuell vergebene Rollen bleiben unangetastet; Import von 30 Zeilen mit zwei Fehlern meldet genau diese zwei und importiert die übrigen 28.

**Phase 3 — Nachweis-Provider**
Interface, Wertobjekt, Collector mit Zuständigkeitsprüfung, Übersichtsseite `nachweise.php`.
*Abnahme*: Ein Test-Provider wird eingesammelt; ein Berufsbildner sieht die Nachweise seiner Lernenden und bekommt bei fremden Lernenden eine Exception; die Prüfung greift auch dann, wenn der Provider selbst keine Berechtigung prüft.

**Phase 4 — Versetzungsplan**
Ausbildungsblöcke mit Kompetenzabdeckung, `kw_converter`, `csv_parser` für beide Formate, Webservice-Funktion mit Testlauf, manueller Upload als Rückfallweg, `get_ausgebildete_kompetenzen()`, Lückenanalyse.
*Abnahme*: Zwölf Zeilen im Wochenformat mit demselben Block ergeben einen Einsatz, nicht zwölf; eine unbekannte Personalnummer landet im Protokoll, ohne den Lauf abzubrechen; eine unveränderte Lieferung wird beim zweiten Aufruf ohne Schreibvorgang beendet; eine Lieferung mit deutlich weniger Personen wird abgewiesen und löscht nichts; `2027-W03` wird korrekt auf den 18. bis 24. Januar 2027 abgebildet; der Testlauf schreibt nachweislich nichts; ein Einsatz, der nur teilweise in den Semesterzeitraum fällt, zählt mit.

**Phase 5 — Ausbildungsstand und Datenschutz**
Profilfeld-Auflösung mit konfigurierbaren Feldnamen, `ausbildungsstand`, Privacy-Provider.
*Abnahme*: Semester wird für eine Testperson korrekt berechnet; Datenschutz-Export enthält die Zuordnungen; Löschung einer Person entfernt ihre Zuordnungen in beiden Rollen.

**Phase 6 — Härtung**
GitHub Actions mit `moodle-plugin-ci`, Matrix über Moodle 4.5 / 5.1 / 5.2 und PHP 8.1–8.4, README, CHANGELOG.

---

## 13. Offene Punkte

1. **Profilfelder**: Welche Felder halten heute Beruf und Lehrjahr, und wie werden sie gepflegt? Beeinflusst die Auflösung des Ausbildungsstands.
2. **Lehrverlängerung und Wiederholung**: Die Semesterberechnung aus dem Jahrgang stimmt nicht mehr, wenn eine Lehre verlängert wird. Braucht es ein Korrekturfeld pro Person, oder wird das über eine angepasste Jahrgangsangabe gelöst?
3. **Stellvertretung**: Braucht es eine Vertretungsregelung für Ferienabwesenheiten, oder reicht die Ausbildungsleitung mit `:viewall` im jeweiligen Feature-Plugin?
4. **Lehrabschluss** *(teilweise gelöst)*: Der Abschluss wird über `api::get_ausbildungsende()` erkannt (Lehrdauer-Semestergrenze aus Beruf/Jahrgang). Zwölf Monate danach werden Zuordnungen automatisch endgültig **gelöscht** (nicht nur beendet), ausser eine Aufbewahrungspflicht ist dokumentiert — siehe `zuordnung_retention_service`. Weiterhin offen: Wird die einzelne laufende Zuordnung (`gueltig_bis IS NULL`) beim Abschluss selbst automatisch beendet, oder bleibt das von Hand? Heute bleibt sie unverändert laufend bis zur automatischen Löschung zwölf Monate später.
5. **Mailadresse in der Excel**: Steht sie dort schon, oder muss das Skript sie aus einer anderen Kennung auflösen?
6. **Kompetenzabdeckung der Ausbildungsblöcke**: Liegt irgendwo dokumentiert vor, welche Handlungskompetenzen in welchem Block vermittelt werden? Das ist der Datenbestand, den Moodle beisteuern muss und ohne den die Vorbelegung nicht funktioniert.
7. **Erinnerungsfrist**: Nach wie vielen Wochen ohne Import soll der Hinweis erscheinen, dass der Versetzungsplan veraltet sein könnte?
8. **Bewertbarkeit früher ausgebildeter Kompetenzen**: Das Formular formuliert „in diesem Semester nicht ausgebildet". Soll eine Kompetenz, die im zweiten Semester ausgebildet wurde, im vierten weiterhin bewertbar sein? Beeinflusst, ob die Vorbelegung nur den Semesterzeitraum oder die ganze bisherige Lehrzeit betrachtet.
9. **üK-Provider**: Welche Felder liefert das bestehende üK-Plugin sinnvoll als `ergebnis` — Note, bestanden/nicht bestanden, oder Kompetenzstand? Und kennt es Kompetenz-IDs aus `core_competency`, sodass `competencyid` gefüllt werden kann?
