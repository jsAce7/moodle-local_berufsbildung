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
| Optionale Anzeigeverknüpfung eines Ausbildungsblocks zu einem Moodle-Kurs | Einschreibungen, Rollen, Kursinhalte und Zugriffsrechte |
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
  rolle              varchar(20)    -- 'hauptverantwortlich' oder 'stellvertretung'
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

**Feld `rolle`**: `hauptverantwortlich` ist der Normalfall, `stellvertretung` eine zusätzliche Zuständigkeit, etwa während einer Ferienabwesenheit. Je Lernende/r und Rolle läuft höchstens eine Zuordnung, verschiedene Rollen dürfen gleichzeitig laufen. Weitere Werte, etwa für Fachvorgesetzte (Arbeitsplatzberichte), brauchen keine Schema-Migration. Aufrufende Plugins müssen den Wert abfragen, nicht annehmen.

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

Die Liste oben zeigt den Kern. Vollständig und verbindlich dokumentiert ist die API in `classes/api.php`. Versetzungsplan, Kompetenzraster und Wahlpflicht stehen in den Abschnitten 5.6 und 5.9.

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

Der Versetzungsplan wird **ausserhalb von Moodle gepflegt** — als gemeinsam genutzte Excel-Datei, auf die alle Beteiligten im Betrieb zugreifen: Kalenderwochen als Spalten, eine Zeile pro lernender Person, in den Zellen die Nummern der Ausbildungsblöcke.

Moodle besitzt diesen Plan nicht, sondern spiegelt ihn. Er wird importiert und ist in Moodle **nicht editierbar** — sonst entstehen zwei Wahrheiten, und die Frage „welche gilt" beantwortet niemand zuverlässig.

Moodle liest die Excel auch nicht selbst. Ein Skript im Betrieb wertet sie aus und liefert die normalisierten Daten über einen Webservice. Damit hängt die Schnittstelle nicht am Layout der Tabelle.

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
  courseid           int(10)        -- optionaler FK -> course.id; Anzeige-Link, keine Einschreibung

  UNIQUE KEY (nummer)
```

`beruf` wird beim manuellen Anlegen des Blocks gesetzt (Auswahl aus den auf der Seite „Kompetenzrahmen je Beruf" konfigurierten Codes) und schränkt die LK-Auswahl in `block_lk` auf den zum Beruf konfigurierten Kompetenzrahmen ein — ohne dieses Feld standen bei mehreren konfigurierten Berufen alle Rahmen gemischt in einem Dropdown. Leer bleibt es typischerweise bei berufsübergreifenden `ist_betrieb=0`-Blöcken (Schule, üK, Ferien, Militär), die für alle Berufe denselben Blockcode verwenden — `nummer` bleibt deshalb weiterhin der alleinige, berufsunabhängige Schlüssel aus der Excel-Zelle (siehe §5.1); der Import kennt `beruf` nicht und muss es auch nicht kennen, da Blöcke ohnehin manuell gepflegt werden.

`courseid` ist eine manuell gepflegte, optionale Ergänzung zum Block. Sie
verknüpft ihn mit einem bereits bestehenden Moodle-Kurs. Während eines
aktuellen Einsatzes erscheint dessen Link in „Meine Lehre". Die Verknüpfung
nimmt keine Einschreibung vor, ändert keine Rollen und umgeht keine Moodle-
Zugriffsrechte; beim Öffnen des Links prüft Moodle den Kurszugriff wie
üblich.

```
local_berufsbildung_block_lk
  id
  blockid            int(10)
  competencyid       int(10)

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

Der Plan wird **nicht als Excel gelesen**. Ein Skript im Betrieb wertet die Excel aus und liefert wöchentlich eine normalisierte CSV über einen Webservice. Damit ist das Layout der Excel von der Schnittstelle entkoppelt: Verschiebt jemand eine Spalte, ändert sich das Skript, nicht Moodle.

Die vollständige Spezifikation liegt in `schnittstelle_versetzungsplan.md`. Kurzfassung:

```csv
email;block;kw_von;kw_bis;bemerkung
anna.muster@firma.ch;4;2027-W15;2027-W26;
anna.muster@firma.ch;7;2027-W27;2027-W33;
```

Alternativ eine Zeile je Woche (`email;block;kw`) — dann fasst Moodle aufeinanderfolgende Wochen mit demselben Block selbst zusammen. Die Kopfzeile entscheidet, welches Format vorliegt.

**Kalenderwochen** in ISO 8601 mit explizitem Jahresbezug. `2027-W03` wird auf Montag, 18. Januar bis Sonntag, 24. Januar 2027 abgebildet. Ein Format ohne Jahr wäre bei einem Plan über vier Lehrjahre nicht eindeutig.

**Zuordnung der Personen** über `user.email`. Die Mailadresse wird aus dem zentralen Benutzerverzeichnis nach Moodle synchronisiert, sodass Änderungen automatisch nachziehen — anders als bei einer separat gepflegten Kennungstabelle. Das Feld `idnumber` scheidet aus, weil es typischerweise schon von dieser Synchronisation mit einer Verzeichniskennung belegt ist, die in der Excel nicht vorkommt.

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

**Regelweg: Webservice.** Ein Skript im Betrieb liefert wöchentlich. Einrichtung in Moodle: Dienstkonto (kein persönliches Konto), Rolle mit `local/berufsbildung:importplan` im Systemkontext, externer Dienst mit der Funktion `local_berufsbildung_import_versetzungsplan`, Token, nach Möglichkeit IP-Einschränkung.

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

### 5.9 Kompetenzraster, Lückenanalyse und Wahlpflicht

Das Kompetenzraster zeigt den Kompetenzrahmen eines Berufs so, wie er im Bildungsplan steht: Handlungskompetenzbereiche als Zeilen, Handlungskompetenzen (HK) als Spalten. Es ist ein Spiegel des Versetzungsplans (Architekturregel 5) und ein Vorschlag für die Ausbildungsplanung (Architekturregel 6), kein Beurteilungsstatus. Ob eine Kompetenz erreicht wurde, entscheiden die aufsetzenden Plugins.

**Grundlage: Kompetenzen der Ausbildungsblöcke.** Ein Ausbildungsblock wird auf Ebene der Leistungskriterien (LK) gepflegt (`block_kompetenzen.php`). Eine als Ganzes zugeordnete HK deckt alle ihre LK ab. Nur Blöcke mit `ist_betrieb` zählen.

**Stand je HK**, zum Stichtag:

| Stand | Bedeutung |
|---|---|
| `abgedeckt` | Mindestens ein LK der HK kam bis zum Stichtag in einem Einsatz vor. Ein Einsatz, der genau am Stichtag beginnt, zählt bereits. |
| `eingeplant` | Ein LK der HK kommt erst in einem späteren Einsatz des vorliegenden Plans vor. |
| `offen` | Kein Einsatz des vorliegenden Plans enthält ein LK der HK. |

Eine HK ist selten ganz in einem Block; abgeschlossen ist sie meist erst gegen Ende der Lehre. Deshalb trägt jede HK zusätzlich den Stand **je LK** (`raster_kompetenz::$leistungskriterien`) und je LK die Einsätze, in denen es vorkam oder geplant ist (`raster_kompetenz::$lkeinsaetze`).

Für die **Anzeige** ergibt das vier Stände (`raster_kompetenz::anzeigestand()`):

| Anzeige | Bedeutung | Darstellung |
|---|---|---|
| `vollstaendig` | alle LK kamen bis zum Stichtag vor | Häkchen, kräftige Fläche |
| `teilweise` | ein Teil der LK kam vor | Sanduhr, mittlere Fläche |
| `eingeplant` | noch kein LK kam vor, welche sind eingeplant | Kalender, helle Fläche |
| `offen` | kein LK steht im Plan | weiss |

Die Lückenanalyse (`get_luecken_nach_bereich()`, Badge „x noch offen") bleibt beim Status oben: eine HK ist keine Planungslücke mehr, sobald eines ihrer LK vorkam. Die Zelle zeigt „4 von 12 LK vorgekommen, 2 eingeplant"; ein Klick öffnet die LK-Liste mit Block und Kalenderwochen je LK.

**Pflicht und Wahlpflicht.** Welche HK eines Berufs Wahlpflicht sind, steht in der Einstellung `beruf_wahlpflicht_hk` (`AU_EFZ=a.04,a.05,…`). Wahlpflicht-HK zählen nicht in die Pflicht-Bezugsgrösse („x von 14 Pflicht-HK"). Wie viele davon der Bildungsplan verlangt, steht in `beruf_wahlpflicht_anzahl`, je Gruppe von Bereichen:

```
AU_EFZ=a,b,c:1; d:1     eine aus a, b und c zusammen, eine aus d
AU_EFZ=3                drei aus dem ganzen Beruf
```

Ohne Eintrag zeigt das Raster keine Wahlpflicht-Angabe. Eine Gruppe gilt als erfüllt, wenn genügend ihrer Wahlpflicht-HK **vollständig** vorkamen; teilweise und eingeplante zählen dafür noch nicht.

**API**

```php
/** Raster zum Stichtag, Bereiche in Rahmenreihenfolge. Leer ohne Ausbildungsstand oder Rahmen. */
public static function get_kompetenzraster(int $lernendeid, ?int $stichtag = null): array; // raster_bereich[]

/** Nur die Lücken (nicht abgedeckte Pflicht-HK) je Bereich. */
public static function get_luecken_nach_bereich(int $lernendeid, ?int $stichtag = null): array; // bereich_abdeckung[]

/** Dieselbe Lückensicht aus einem bereits berechneten Raster, ohne erneuten Datenbankzugriff. */
public static function abdeckung_aus_raster(array $raster): array; // bereich_abdeckung[]

/** Ende des letzten Einsatzes - bis wohin der vorliegende Plan reicht. */
public static function get_planungshorizont(int $lernendeid): ?int;

/** ID-Nummern der Wahlpflicht-HK eines Berufs. */
public static function get_wahlpflicht_hk_for_beruf(string $beruf): array; // string[]

/** Verlangte Wahlpflicht-HK je Gruppe von Bereichen. Leer, wenn nichts hinterlegt ist. */
public static function get_wahlpflicht_gruppen_for_beruf(string $beruf): array; // wahlpflicht_gruppe[]

/** ID-Nummer des Kompetenzrahmens eines Berufs, aus beruf_rahmen_mapping. */
public static function get_kompetenzrahmen_for_beruf(string $beruf): ?string;

/** Kürzel aus einer ID-Nummer, wie das Raster es zeigt: aus "7777BE b.07" wird "b.07". */
public static function get_kompetenz_kuerzel(string $idnumber): string;
```

**Wertobjekte** (berechnet, nicht gespeichert, `readonly`-Properties):

```php
class raster_bereich {
    public int $bereichid;        // competencyid des Bereichs
    public array $kompetenzen;    // raster_kompetenz[], Rahmenreihenfolge
    public string $kuerzel;       // "a" - für die Wahlpflicht-Gruppen
    // soll(), luecken(), anzahl_abgedeckt() - nur Pflicht-HK
}

class raster_kompetenz {
    public int $competencyid;     // die HK
    public string $status;        // STATUS_ABGEDECKT | STATUS_EINGEPLANT | STATUS_OFFEN
    public bool $istwahlpflicht;
    public array $leistungskriterien; // competencyid => Status je LK
    public array $lkeinsaetze;        // competencyid => einsatz-ids je LK
    // anzeigestand() => vollstaendig | teilweise | eingeplant | offen
}

class wahlpflicht_gruppe {
    public array $bereiche;       // Kürzel, leer = ganzer Beruf
    public int $anzahl;           // verlangt
    // stand(raster) => [vollstaendig, teilweise, eingeplant]; offen(raster) => noch nicht vollständig
}
```

**Darstellung.** `output\kompetenzraster::render($raster, $horizont, $kompakt, $wahlpflichtgruppen)` zeichnet das Raster. Die Fläche einer Zelle zeigt den Stand, ein Streifen oben Pflicht (gelb) oder Wahlpflicht (grün) wie im Bildungsplan. Verwendet auf „Meine Lehre" und „Meine Lernenden"; auf der Kachel einer lernenden Person stehen die fehlenden Pflicht-HK und die noch offenen Wahlpflicht-HK als Badges.

**Aufwand.** Das Raster lädt Einsätze, Blöcke und Zuordnungen je in einer Abfrage und leitet übergeordnete Kompetenzen aus dem bereits geladenen Rahmen ab. Die Zahl der Datenbankabfragen hängt nicht von der Zahl der LK ab (Test `raster_analyse_test::test_abfragen_wachsen_nicht_mit_den_leistungskriterien`); „Meine Lernenden" rechnet es für jede Person.

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

### Direktlink zum Erfassen

Eine Quelle kann zusätzlich zu `provider` das Interface `erfassbare_quelle` implementieren (`get_erfassen_url()`, `get_erfassen_label()`). Der Collector sammelt daraus über `get_erfassen_aktionen()` Direktlinks ein, „Meine Lehre" zeigt sie pro Quelle als Schaltfläche. Bewusst nur für die eigene Person: der Collector prüft `abrufendeid === lernendeid`, nicht `is_zustaendig()` — hier erfasst niemand für eine andere Person.

**Gespiegelt für die Berufsbildner/in**: Auf „Meine Lernenden" (`meine_lernenden.php`) gilt derselbe Direktlink-Gedanke, aber die/der Berufsbildner erfasst *für* eine lernende Person, geprüft über `is_zustaendig()` statt Identität. Dafür gibt es das eigene Interface `quelle_mit_zustaendigen_aktion` (`get_zustaendigen_aktion($abrufendeid, $lernendeid)`), eingesammelt über `collector::get_zustaendigen_aktionen()` — nicht `erfassbare_quelle`, deren Vertrag fest auf „nur eigene Person" ausgelegt ist. Die Quelle liefert eine ganze `erfassen_aktion` statt URL und Beschriftung getrennt, weil beides davon abhängt, was als Nächstes ansteht. Der Bildungsbericht hat entschieden: die Aktion öffnet den nächsten offenen Bericht (anlegen, falls noch nicht begonnen, sonst erfassen), sie legt keinen beliebigen neuen an — Berichte werden über die Fälligkeiten materialisiert.

**Schnellaktionen auf der geschlossenen Kachel**: Was die Berufsbildner/in jederzeit und nebenbei für eine Person tut — die Notizen zum Bildungsbericht —, passt nicht in `quelle_mit_zustaendigen_aktion`: jene beantwortet „was steht als Nächstes an" und steht im ausgeklappten Detail. Dafür gibt es `quelle_mit_schnellaktion` (`get_schnellaktion($abrufendeid, $lernendeid)`), eingesammelt über `collector::get_schnellaktionen()` mit derselben Zuständigkeitsgrenze. Die Schaltfläche steht in der Kopfzeile der Kachel, aber ausserhalb von `<details>` — im `<summary>` wäre sie ein verschachteltes Bedienelement. Eine `schnellaktion` darf ein AMD-Modul mitbringen, das „Meine Lernenden" einmal je Seite lädt; ohne JavaScript führt der Link auf die URL der Aktion. Das Basis-Plugin selbst hat weiterhin keine Build-Kette.

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

Steht im Repo selbst und wird hier nicht nachgeführt. Die Klassen unter `classes/` folgen PSR-4 im Namensraum `local_berufsbildung\…`.

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

Die ursprünglichen Phasen 1–6 sind umgesetzt. Ihre Abnahmekriterien stecken in den Tests unter `tests/`, die Änderungsgeschichte steht in `CHANGELOG.md`.

---

## 13. Geklärte Fragen

Ursprünglich offene Punkte, alle geklärt (Stand 2026-10-04). Die Nummern bleiben, weil Code und Changelog darauf verweisen.

1. **Profilfelder** *(gelöst)*: Welche Profilfelder Beruf, Jahrgang und optional den Lehrbeginn halten, wird in den Plugin-Einstellungen gewählt.
2. **Lehrverlängerung und Wiederholung** *(entschieden: kein Korrekturfeld)*: Bei einer verlängerten oder wiederholten Lehre passt die Ausbildungsadministration den Jahrgang im Profil an. Semester, Lehrjahr, Ausbildungsende und Aufbewahrungsfrist verschieben sich damit mit. Damit die Probezeit am ursprünglichen Beginn bleibt, wird zusätzlich das Profilfeld Lehrbeginn auf das tatsächliche Startdatum gesetzt. Grenze: Der Jahrgang verschiebt nur um ganze Jahre; eine Verlängerung um ein Semester lässt sich so nicht abbilden.
3. **Stellvertretung** *(gelöst)*: Eine Zuordnung mit der Rolle `stellvertretung` läuft neben der hauptverantwortlichen und gibt dieselbe Zuständigkeit (siehe Abschnitt 3).
4. **Lehrabschluss** *(entschieden)*: Der Abschluss wird über `api::get_ausbildungsende()` erkannt (Lehrdauer-Semestergrenze aus Beruf/Jahrgang). Eine laufende Zuordnung (`gueltig_bis IS NULL`) wird beim Abschluss bewusst nicht automatisch beendet. Sie bleibt laufend, bis `zuordnung_retention_service` sie nach Ablauf von `retention_monate` (Standard 12) endgültig **löscht**, ausser eine Aufbewahrungspflicht ist dokumentiert.
5. **Mailadresse in der Excel** *(gelöst)*: Die Geschäftsmailadresse steht in der Versetzungsplan-Excel und dient als Kennung (siehe `docs/schnittstelle_versetzungsplan.md`).
6. **Kompetenzabdeckung der Ausbildungsblöcke** *(gelöst)*: Wird in Moodle je Block gepflegt (`block_kompetenzen.php`).
7. **Erinnerungsfrist** *(gelöst)*: Einstellung `versetzungsplan_alterung_tage`, Standard 10 Tage.
8. **Bewertbarkeit früher ausgebildeter Kompetenzen** *(entschieden)*: Die Vorbelegung im Bildungsbericht betrachtet nur den Berichtszeitraum. Früher ausgebildete Kompetenzen lassen sich im Bericht von Hand ergänzen.
9. **üK-Provider** *(gelöst)*: `local_uekkn` liefert die üK-Note als `ergebnis`. `competencyid` bleibt `null`, weil üK-Kriterien eigene Codes tragen und nicht auf `core_competency` abgebildet sind.
