<img src="pix/logo.svg" alt="" width="72" align="right">

# Berufsbildung (`local_berufsbildung`)

Basis-Plugin für die betriebliche Berufsbildung in Moodle. Beantwortet zwei Fragen für alle aufsetzenden Plugins: **wer ist wofür zuständig** (Zuordnung Berufsbildner/in ↔ Lernende) und **in welchem Semester steht diese Person** (Ausbildungsstand). Zusätzlich: Import und Verwaltung des betrieblichen Versetzungsplans und eine gemeinsame Gestaltung für alle PDF-Dokumente der Berufsbildung.

Dieses Plugin hat keine eigene Fachfunktion. Alles Fachliche — Lerndokumentation (`local_lerndokumentation`), üK-Kompetenznachweise (`local_uekkn`) und Bildungsbericht (`local_bildungsbericht`) — baut über `\local_berufsbildung\api` darauf auf.

> **Status:** Version 0.8.6, `MATURITY_BETA`. Funktional vollständig und automatisiert getestet. Freigegeben für einen begleiteten Pilotbetrieb mit echten Personendaten, noch nicht für den allgemeinen Betrieb, siehe [CHANGELOG.md](CHANGELOG.md).

## Inhalt

- [Was dieses Plugin macht](#was-dieses-plugin-macht)
- [Installation](#installation)
- [Einstellungen](#einstellungen)
- [Rollen und Zugang](#rollen-und-zugang)
- [Geplante Aufgaben](#geplante-aufgaben)
- [Öffentliche API](#öffentliche-api)
- [Datenschutz und Aufbewahrung](#datenschutz-und-aufbewahrung)
- [Entwicklung](#entwicklung)
- [Lizenz](#lizenz)

## Was dieses Plugin macht

- **Zuordnung**: Berufsbildner/innen werden Lernenden zugeordnet, einzeln, in Bulk oder per globaler Gruppe (Kohorte, einmalig oder laufend synchronisiert). Regulär werden sie mit einem Enddatum beendet; eine bestätigte Löschaktion ist für eindeutig falsch erfasste Zuordnungen verfügbar. Die Übersicht ist ein Report-Builder-Bericht mit Filtern und Export; für den Jahreswechsel gibt es einen CSV-Import.
- **Teilnahmeart**: Je Person entweder reguläre Lehre oder extern nur in überbetrieblichen Kursen (üK). Externe erscheinen weiterhin bei ihren Berufsbildner/innen und mit ihren üK-Nachweisen, sehen aber keinen Einstieg „Meine Lehre" (`api::ist_uek_extern()`). Gepflegt wird die Teilnahmeart in der Zuordnungsübersicht.
- **Aufbewahrung**: eine konfigurierbare Frist (Standard 12 Monate) nach dem berechneten Lehrabschluss löscht Zuordnungen automatisch endgültig, ausser eine Aufbewahrungspflicht ist dokumentiert. Dieselbe Regel steuert über `api::aufbewahrungsfrist_abgelaufen()` auch die Löschung in `local_lerndokumentation`, `local_bildungsbericht` und `local_uekkn`.
- **Ausbildungsstand**: Beruf, Lehrjahr und Semester werden aus zwei Profilfeldern (Beruf, Jahrgang) berechnet, mit konfigurierbarer Lehrdauer je Beruf.
- **Versetzungsplan**: wöchentlicher CSV-Import (Webservice oder manueller Upload) der betrieblichen Einsätze, mit Kompetenzabdeckung je Ausbildungsblock. Ein Block kann auf einen bestehenden Moodle-Kurs verweisen, der während des Einsatzes in „Meine Lehre" verlinkt wird; Einschreibungen und Rechte bleiben dabei unberührt. Die Schnittstelle steht in [docs/schnittstelle_versetzungsplan.md](https://github.com/jsAce7/moodle-local_berufsbildung/blob/main/docs/schnittstelle_versetzungsplan.md), Beispieldaten und ein PowerShell-Importskript in [beispiele/](https://github.com/jsAce7/moodle-local_berufsbildung/tree/main/beispiele).
- **Ausbildungsblöcke und ihre Kompetenzen**: Die Blockverwaltung zeigt je Beruf eine Tabelle. Je Block wird festgelegt, welche Handlungskompetenzen (HK) oder Leistungskriterien (LK) dort vermittelt werden; ein Block lässt sich samt dieser Zuordnung kopieren. Die LK-Übersicht je Beruf zeigt alle LK des Rahmens mit den Blöcken, in denen sie vorkommen, und filtert auf die LK, die noch in keinem Block sind. Oberhalb der Blöcke meldet eine Einrichtungsprüfung je Beruf, was dem Kompetenzraster fehlt: kein Rahmen, keine Blöcke, keine Kompetenzen in aktiven betrieblichen Blöcken oder Wahlpflicht ohne verlangte Anzahl.
- **Kompetenzraster**: Ist dem Beruf ein Kompetenzrahmen zugeordnet, zeigt das Raster alle Handlungskompetenzen wie im Bildungsplan, jede mit einem von vier Ständen: vollständig (alle LK kamen bis zum Stichtag vor), teilweise, im Plan später eingeplant oder nicht im Plan (`api::get_kompetenzraster()`). Je Zelle öffnet ein Dialog die LK, gruppiert nach „fehlt noch“, „später eingeplant“ und „bereits vorgekommen“, mit Block und Kalenderwochen der Einsätze. Bei fehlenden LK nennt er die Blöcke, in denen sie vorkämen (`api::get_bloecke_je_kompetenz()`). Pflicht und Wahlpflicht stehen als Streifen in den Farben des Bildungsplans; für die Wahlpflicht zeigt das Raster je Gruppe von Bereichen, wie viele HK verlangt und wie viele vollständig sind. Die offenen Pflicht-HK je Bereich liefert `api::get_luecken_nach_bereich()` für aufsetzende Plugins.
- **Nachweis-Sammlung**: aufsetzende Plugins registrieren sich als Nachweisquelle; „Meine Lernenden" (Berufsbildner/in) und „Meine Lehre" (Lernende) zeigen die eingesammelten Tätigkeiten über Plugin-Grenzen hinweg, gruppiert nach Quelle und mit dem Semester in jeder Zeile. Dazu kommen, was eine Quelle meldet: Fälligkeiten, noch Ausstehendes, Erfassen-Aktionen und Schnellaktionen.
- **Übersicht für Berufsbildner/innen**: „Meine Lernenden" zeigt je betreute Person eine Zeile mit Semesterleiste, laufendem Ausbildungsblock und überfälligen Aufgaben, durchsuchbar nach Namen und filterbar nach Beruf. Badges nennen die offenen Pflicht- und Wahlpflicht-Handlungskompetenzen. Aufgeklappt stehen Kompetenzraster und Tätigkeiten.
- **Eigene Übersicht für Lernende**: „Meine Lehre" zeigt Semesterstand, den laufenden Einsatz aus dem Versetzungsplan, die eigenen Fälligkeiten, das Kompetenzraster, den Versetzungsplan nach Semestern und die eigenen Tätigkeiten. Die Seite trägt die vier Phasen der Ausbildung (`api::get_ausbildungsphase()`): Profil unvollständig, Lehre beginnt erst, laufend, abgeschlossen — nach dem Lehrabschluss wird sie zum Rückblick, statt leer zu werden.
- **PDF-Grundlage**: `local_berufsbildung\pdf` liefert Schrift, Logo, Akzentfarbe, Laufkopf, Fusszeile und Zeichenbausteine für die PDFs von `local_uekkn`, `local_bildungsbericht` und `local_lerndokumentation`, damit alle Dokumente gleich aussehen.

## Installation

**Voraussetzungen**

- Moodle 4.5 LTS bis 5.2 (PHP 8.1 bis 8.4). `version.php` verlangt mindestens Moodle 4.5 (`2024100700`); die CI prüft Moodle 4.5 mit PHP 8.1 und Moodle 5.2 mit PHP 8.4.
- MariaDB (Produktivsystem). PostgreSQL wird in der CI mitgetestet.
- Keine anderen Plugins (`$plugin->dependencies` ist leer). Für Kompetenzraster, Lückenanalyse und die Kompetenzabdeckung der Blöcke müssen die Kompetenzen von Moodle aktiviert und ein Kompetenzrahmen vorhanden sein. Für den automatischen Versetzungsplan-Import braucht es Webservices mit REST.

**Schritte**

1. Das Plugin installieren:
   - **per ZIP**: *Website-Administration ▸ Plugins ▸ Plugin installieren*; die ZIP-Datei enthält den Ordner `berufsbildung/` und liegt jedem [GitHub-Release](https://github.com/jsAce7/moodle-local_berufsbildung/releases) bei (selbst erstellen siehe [Entwicklung](#entwicklung)),
   - **oder von Hand**: nach `local/berufsbildung` des Moodle-Codes kopieren oder verlinken.
2. Das Upgrade ausführen, über *Website-Administration ▸ Mitteilungen* oder per CLI:
   `php admin/cli/upgrade.php --non-interactive`
   Dabei entstehen die Rollen `berufsbildner`, `berufsbildung_planung` und `berufsbildung_leitung`, siehe [Rollen und Zugang](#rollen-und-zugang).
3. Zwei benutzerdefinierte Profilfelder anlegen (Beruf, Jahrgang) und unter *Website-Administration ▸ Berufsbildung ▸ Einstellungen und Datenschutz ▸ Einstellungen* zuordnen. Die Auswahl bietet nur bereits vorhandene Profilfelder an; voreingestellt sind die Kurznamen `beruf` und `jahrgang`. Optional ein drittes Feld für einen abweichenden Lehrbeginn, am besten vom Typ Datum.
4. Optional: unter *Website-Administration ▸ Berufsbildung ▸ Ausbildungsplanung ▸ Kompetenzrahmen je Beruf* jedem Beruf seinen Kompetenzrahmen zuordnen, siehe [Einstellungen](#einstellungen).
5. Optional, für den Versetzungsplan-Webservice: Dienst *Berufsbildung: Versetzungsplan-Import* unter *Website-Administration ▸ Server ▸ Webservices ▸ Externe Webservices* aktivieren, Dienstkonto mit der Capability `local/berufsbildung:importplan` anlegen, als autorisierte Person beim Dienst eintragen und Token ausstellen. Der Dienst ist standardmässig deaktiviert und auf autorisierte Personen beschränkt. Details in [docs/schnittstelle_versetzungsplan.md](https://github.com/jsAce7/moodle-local_berufsbildung/blob/main/docs/schnittstelle_versetzungsplan.md), Abschnitt 4.

## Einstellungen

*Website-Administration ▸ Berufsbildung ▸ Einstellungen und Datenschutz ▸ Einstellungen* (nur mit `moodle/site:config`):

| Einstellung | Standard | Bedeutung |
|---|---|---|
| Profilfeld: Beruf | `beruf` | Welches Profilfeld den Beruf enthält (z. B. `AU_EFZ`) |
| Profilfeld: Jahrgang | `jahrgang` | Profilfeld mit dem Jahr des Lehrbeginns. „2026" genügt, ein kombiniertes Feld wie „AU 2026" wird ebenfalls erkannt — gelesen wird nur die Jahreszahl. |
| Profilfeld: Lehrbeginn (optional) | keines | Profilfeld (am besten ein Datumsfeld) mit einem abweichenden Lehrbeginn je Person, z. B. 15. August, Februar oder Einstieg in ein späteres Semester. Leer = 1. des Startmonats im Jahrgang. Bestimmt nur den Beginn der Probezeit (`api::get_lehrbeginn()`), nicht die Semester. |
| Startmonat der Lehre | 8 (August) | Monat, in dem alle Lehren starten |
| Lehrdauer in Semestern (Standard) | 8 | Standard-Lehrdauer (1 bis 8 Semester), überschreibbar je Beruf |
| Lehrdauer je Beruf | leer | Abweichende Lehrdauer, eine Zeile je Beruf im Format `CODE=Semester`, z. B. `PM_EFZ=6` |
| Wahlpflicht-Handlungskompetenzen je Beruf | `AU_EFZ=a.04,a.05,…,d.07` | Handlungskompetenzen, die in der Lückenanalyse nicht als fehlend gelten, siehe unten |
| Anzahl Wahlpflicht-Handlungskompetenzen je Beruf | leer | Wie viele Wahlpflicht-HK der Bildungsplan verlangt, je Gruppe von Bereichen, z. B. `AU_EFZ=a,b,c:1; d:1` (eine aus a, b und c, eine aus d) oder `AU_EFZ=3` für den ganzen Beruf. Ohne Eintrag zeigt das Raster keinen Wahlpflicht-Stand. |
| Schwelle für unvollständige Lieferungen (%) | 20 | Vollständigkeitsschutz beim Versetzungsplan-Import: Enthält eine Lieferung deutlich weniger Personen als die vorherige, wird sie abgewiesen |
| Alterungshinweis nach (Tagen) | 10 | Alterungshinweis, wenn der letzte Import zu lange zurückliegt |
| Aufbewahrungsfrist nach Ausbildungsabschluss (Monate) | 12 | Frist bis zur automatischen Löschung von Zuordnungen (und, über die aufsetzenden Plugins, ihrer Inhalte) nach Lehrabschluss. Wirksam sind Werte von 1 bis 120. |
| Logo für den Kopf der PDF-Dokumente | keines | PNG oder JPEG, gilt für alle PDF-Dokumente der Berufsbildung |
| Akzentfarbe der PDF-Dokumente | `#1F4A6D` | Farbe für Abschnittstitel, Tabellenköpfe und Gruppenbänder |

**Lehrverlängerung und Wiederholung** haben kein eigenes Korrekturfeld: Die Ausbildungsadministration passt den Jahrgang im Profil an und setzt den Lehrbeginn auf das tatsächliche Startdatum, damit die Probezeit am ursprünglichen Beginn bleibt. Der Jahrgang verschiebt nur um ganze Jahre, eine Verlängerung um ein Semester lässt sich so nicht abbilden ([docs/konzept.md](https://github.com/jsAce7/moodle-local_berufsbildung/blob/main/docs/konzept.md) §13.2).

Logo und Akzentfarbe werden bei Installation und Upgrade aus `local_uekkn`, ersatzweise aus `local_bildungsbericht` übernommen, solange hier noch nichts eingestellt ist. Eine eigene Einstellung wird dabei nicht überschrieben.

**Welcher Kompetenzrahmen?** Der Rahmen ist wie der Bildungsplan dreistufig aufgebaut: Handlungskompetenzbereiche, darunter die Handlungskompetenzen (HK), darunter die Leistungskriterien (LK). In der Blockverwaltung wird einem Ausbildungsblock eine ganze HK oder einzelne LK darunter zugeordnet. Als vollständig gilt eine HK im Raster erst, wenn alle ihre LK in einem Einsatz vorkamen; ist eine HK als Ganzes einem Block zugeordnet, zählen alle ihre LK. Die in der AU-Umsetzung als `W` gekennzeichneten HK sind standardmässig als Wahlpflicht hinterlegt und erscheinen nicht als Lücke; die Zuordnung lässt sich in der Einstellung *Wahlpflicht-Handlungskompetenzen je Beruf* an den verwendeten Berufscode und die ID-Nummern des Rahmens anpassen. Der Rahmen-Präfix darf dabei fehlen: `a.04` trifft auch `7777BE a.04`.

Welcher Rahmen zu welchem Beruf gehört, steht nicht in den Einstellungen, sondern auf einer eigenen Seite: *Website-Administration ▸ Berufsbildung ▸ Ausbildungsplanung ▸ Kompetenzrahmen je Beruf*, Capability `local/berufsbildung:manageblocks`. Die Seite bietet die Berufe aus dem Beruf-Profilfeld zur Auswahl an und setzt voraus, dass die Kompetenzen von Moodle aktiviert sind.

Der Rahmen muss der lernenden Person dafür **nicht zusätzlich zugewiesen** werden (kein Lernplan, keine Kurs-Verknüpfung nötig) — `\core_competency\api::add_evidence()` legt den `user_competency`-Datensatz beim ersten Kompetenznachweis automatisch an.

Dokumentierte Aufbewahrungspflichten (Ausnahmen von der automatischen Löschung) werden auf einer eigenen Seite verwaltet: *Website-Administration ▸ Berufsbildung ▸ Einstellungen und Datenschutz ▸ Aufbewahrungspflichten*, Capability `local/berufsbildung:manageaufbewahrung`.

## Rollen und Zugang

Das Plugin legt drei Rollen an:

| Rolle | Kontext | Zweck |
|---|---|---|
| `berufsbildner` (Berufsbildner/in) | Nutzerkontext der lernenden Person | Trägt selbst keine Capabilities — die vergeben die aufsetzenden Plugins. Wird je bestehender Zuordnung zugewiesen und bleibt nach deren Ende bestehen, damit eine frühere Zuständigkeit zum damaligen Stichtag noch auflösbar ist. Entzogen erst mit der Datenbereinigung (Aufbewahrungsfrist, Löschung der Zuordnung, Account-Löschung). |
| `berufsbildung_planung` (Ausbildungsplanung) | Systemkontext | Trägt `local/berufsbildung:manageblocks` und öffnet damit die Ausbildungsblöcke samt Kompetenzabdeckung und LK-Übersicht und die Seite *Kompetenzrahmen je Beruf*. Wird jeder Person zugewiesen, die mindestens eine **laufende** Zuordnung hat, und wieder entzogen, sobald die letzte davon beendet ist. |
| `berufsbildung_leitung` (Leitung Berufsbildung) | Systemkontext | Für die Leitung Berufsbildung, die ohne eigene Zuordnung über alle Lernenden hinweg liest, etwa die üK-Noten eines Jahrgangs. Trägt selbst keine Capabilities — die vergeben die aufsetzenden Plugins (siehe deren README). Wird **nie automatisch** zugewiesen, sondern von Hand als globale Rolle. |

Der Unterschied im Entzug ist beabsichtigt: die personenbezogene Rolle ist stichtagsgeprüfter Lesezugriff und muss für einen Bericht aus einem früheren Semester noch greifen; die Planungsrolle ist systemweites Schreibrecht auf Stammdaten und endet deshalb mit der Betreuung.

Die Leitungsrolle ist bewusst von der Planungsrolle getrennt: Diese bekommt jede Person mit laufender Zuordnung automatisch, ein Leserecht ohne Zuständigkeitsprüfung darf deshalb nie an ihr hängen.

**Die Leitungsrolle hält sich selbst intakt.** Sie hat keinen Archetyp, „Rolle zurücksetzen“ würde ihr deshalb alle Rechte und das Kontextlevel nehmen; danach liesse sie sich nicht einmal mehr global zuweisen. `leitungsrolle_service` stellt beides wieder her: sofort, sobald der Rolle eine Capability entzogen wird (Observer auf `capability_unassigned`), und zusätzlich stündlich im Task `sync_role_assignments`. Welche Capabilities die Rolle braucht, meldet jedes aufsetzende Plugin über den Callback `<plugin>_berufsbildung_leitung_capabilities()` in seiner `lib.php`. Wiederhergestellt wird nur, was gar nicht gesetzt ist: Wer der Leitung ein Recht **bewusst entziehen** will, setzt es unter *Rollen verwalten ▸ Leitung Berufsbildung ▸ Bearbeiten ▸ Erweitert* auf **Verhindern** oder **Verbieten**. „Nicht gesetzt“ kommt zurück.

`berufsbildner` und `berufsbildung_planung` werden vom stündlichen Task `sync_role_assignments` gepflegt, zusätzlich sofort beim Anlegen, Beenden und Löschen einer Zuordnung. Die selbst vergebenen Zuweisungen sind mit `component = 'local_berufsbildung'` markiert; von Hand vergebene bleiben unangetastet und werden nie entzogen. Eine Ausbildungsleitung, die selbst keine Lernenden betreut, wird deshalb einfach von Hand global der Rolle *Ausbildungsplanung* zugewiesen.

### Capabilities

Alle im Systemkontext. Die Verwaltungsseiten hängen unter *Website-Administration ▸ Berufsbildung*:

| Capability | Standardmässig bei | Öffnet |
|---|---|---|
| `local/berufsbildung:viewzuordnung` | Manager | *Zuordnungen und Gruppen ▸ Zuordnungen* (Übersicht, ohne zu ändern) |
| `local/berufsbildung:managezuordnung` | Manager | in der Zuordnungsübersicht die Aktionen Anlegen, Importieren, Beenden, Löschen und Teilnahmeart setzen, dazu *Zuordnungen und Gruppen ▸ Verknüpfungen mit globalen Gruppen* |
| `local/berufsbildung:manageblocks` | Manager, Ausbildungsplanung | *Ausbildungsplanung ▸ Ausbildungsblöcke* samt Kompetenzzuordnung, Kopieren und LK-Übersicht je Beruf, dazu *Kompetenzrahmen je Beruf* |
| `local/berufsbildung:importplan` | niemand | *Ausbildungsplanung ▸ Versetzungsplan-Import* und der Webservice. Wird gezielt dem Dienstkonto zugewiesen. |
| `local/berufsbildung:manageaufbewahrung` | Manager | *Einstellungen und Datenschutz ▸ Aufbewahrungspflichten* |

Die Systemeinstellungen bleiben Sache von `moodle/site:config`.

### Wo sich die Rollen von Hand vergeben lassen

`set_role_contextlevels()` registriert jede Rolle für **genau ein** Kontextlevel, und `get_assignable_roles()` bietet eine Rolle nur auf der Seite an, die zu diesem Level gehört:

| Rolle | Einzige Stelle, an der sie angeboten wird |
|---|---|
| `berufsbildung_planung`, `berufsbildung_leitung` | *Website-Administration ▸ Nutzer/innen ▸ Rechte ändern ▸ **Globale Rollen zuweisen*** |
| `berufsbildner` | Profil der **lernenden** Person ▸ *Einstellungen* ▸ „Rollen relativ zu diesem Nutzer zuweisen" |

In einem Kurs ist keine der drei zuweisbar — dort erscheinen nur Rollen mit `CONTEXT_COURSE`. Das ist Absicht (Architekturregel 1: kein Kurskontext).

`create_role()` trägt in `role_allow_assign` und `role_allow_view` nichts ein, und genau daran filtert `get_assignable_roles()` für alle, die nicht Administrator/in sind. `role_matrix_service` trägt deshalb bei Installation und Upgrade nach, dass die Rolle *Manager* die Rollen *Ausbildungsplanung* und *Leitung Berufsbildung* vergeben darf und alle drei Rollennamen sehen kann. Wer das einer anderen Rolle erlauben will, setzt das Häkchen unter *Rollen verwalten ▸ Rollenzuweisungen erlauben*.

`berufsbildner` bleibt bewusst **nicht** von Hand vergebbar, nur sichtbar: die Rolle allein öffnet nichts, weil die aufsetzenden Plugins neben ihrer Capability immer auch `api::is_zustaendig()` prüfen. Zuständigkeit entsteht über eine Zuordnung, nicht über eine Rollenzuweisung (Architekturregel 2).

Die Trennung der beiden Rollen ist ebenso beabsichtigt: `berufsbildner` hängt am Nutzerkontext einer einzelnen lernenden Person. Global zugewiesen würden alle ihre Capabilities — auch die künftig von aufsetzenden Plugins vergebenen — für *alle* Personen gelten und damit die Stichtagsprüfung in `api::is_zustaendig()` unterlaufen.

### Navigation

Die primäre Navigationsleiste trägt bewusst nur die beiden täglichen Einstiege *Meine Lehre* und *Meine Lernenden*, vor der Website-Administration. *Meine Lehre* erscheint dort für alle mit Beruf und Jahrgang im Profil, auch vor Lehrbeginn und nach dem Abschluss (dann als Rückblick), nicht aber für externe üK-Teilnehmende; *Meine Lernenden* nur, wer heute mindestens eine laufende Zuordnung hat.

Berufsbildner/innen erreichen die Blockverwaltung über den Button **Ausbildungsblöcke verwalten** auf *Meine Lernenden*. Die Blockpflege ist Stammdatenarbeit und hängt deshalb als Nebeneingang daran. Wer `manageblocks` ohne eigene Lernende hat — eine von Hand zugewiesene *Ausbildungsplanung* —, nimmt den Weg über *Website-Administration ▸ Berufsbildung ▸ Ausbildungsplanung ▸ Ausbildungsblöcke*. Änderungen wirken systemweit für alle Berufe, und `local_berufsbildung_block_lk` führt keine Änderungshistorie — nachvollziehbar ist über `usermodified`/`timemodified` nur die jeweils letzte Änderung eines noch bestehenden Eintrags, Entfernungen sind spurlos. Wer die Pflege einem kleineren Kreis vorbehalten will, entzieht der Rolle *Ausbildungsplanung* die Capability `local/berufsbildung:manageblocks` und weist sie gezielt einer eigenen Rolle zu; der Zugang über die Navigation und den Admin-Baum richtet sich allein nach dieser Capability.

## Geplante Aufgaben

Drei geplante Aufgaben (*Website-Administration ▸ Server ▸ Geplante Aufgaben*):

| Task | Wann | Was |
|---|---|---|
| `task\sync_kohorten` | stündlich, Minute zufällig | gleicht die Verknüpfungen mit globalen Gruppen ab: neue Mitglieder erhalten eine Zuordnung, die Zuordnung ausgeschiedener Mitglieder wird beendet, nie gelöscht |
| `task\sync_role_assignments` | stündlich, Minute zufällig | gleicht die Rollen *Berufsbildner/in* und *Ausbildungsplanung* mit den Zuordnungen ab |
| `task\zuordnung_retention` | sonntags 03:30 | löscht Zuordnungen nach Ablauf der Aufbewahrungsfrist |

## Öffentliche API

Aufsetzende Plugins greifen auf die Daten dieses Plugins ausschliesslich über `\local_berufsbildung\api` zu, nie direkt auf seine Tabellen. Wichtigste Methoden:

```php
api::is_zustaendig(int $berufsbildnerid, int $lernendeid, ?int $stichtag = null): bool
api::is_zustaendig_heute_oder_am(int $berufsbildnerid, int $lernendeid, ?int $stichtag): bool
api::get_lernende_for(int $berufsbildnerid, ?int $stichtag = null): array
api::get_berufsbildner_for(int $lernendeid, ?int $stichtag = null): array
api::get_aktive_lernende(?int $stichtag = null, bool $ohneuekextern = false): array
api::get_ausbildungsstand(int $lernendeid, ?int $stichtag = null): ?ausbildungsstand
api::get_ausbildungsphase(int $lernendeid, ?int $stichtag = null): string
api::get_semester_grenzen(int $lernendeid): array
api::get_lehrbeginn(int $lernendeid): ?int
api::get_einsaetze(int $lernendeid, ?int $von = null, ?int $bis = null): array
api::get_ausgebildete_kompetenzen(int $lernendeid, int $von, int $bis): array
api::get_luecken(int $lernendeid, ?int $stichtag = null): array
api::get_kompetenzraster(int $lernendeid, ?int $stichtag = null): array
api::get_luecken_nach_bereich(int $lernendeid, ?int $stichtag = null): array
api::get_wahlpflicht_gruppen_for_beruf(string $beruf): array
api::get_bloecke_je_kompetenz(string $beruf): array
api::get_block_name(int $blockid): ?string
api::get_berufe(): array
api::ist_uek_extern(int $userid): bool
api::get_ausbildungsende(int $lernendeid): ?int
api::ist_ausbildung_beendet(int $lernendeid, ?int $stichtag = null): bool
api::hat_aufbewahrungspflicht(int $lernendeid, ?int $stichtag = null): bool
api::darf_personendaten_geloescht_werden(int $lernendeid, ?int $stichtag = null): bool
api::aufbewahrungsfrist_abgelaufen(int $lernendeid, ?int $stichtag = null): bool
```

Alle Stichtag-Parameter akzeptieren `null` für "jetzt" — nie stillschweigend annehmen. Die vollständige Liste mit Beschreibungen steht in [classes/api.php](classes/api.php).

**Nachweisquellen.** Ein aufsetzendes Plugin meldet sich mit der Funktion `<komponente>_berufsbildung_nachweis_provider()` in seiner `lib.php` an. Sie gibt ein Objekt zurück, das `\local_berufsbildung\nachweis\provider` umsetzt. Optionale Interfaces im selben Namespace erweitern eine Quelle: `erfassbare_quelle`, `quelle_mit_hinweis`, `quelle_mit_faelligkeiten`, `quelle_mit_zusammenfassung`, `quelle_mit_ausstehenden`, `quelle_mit_zustaendigen_aktion` und `quelle_mit_schnellaktion`. `nachweis\collector` prüft die Zuständigkeit, bevor er eine Quelle fragt (Architekturregel 7). Angemeldet sind heute `local_lerndokumentation`, `local_uekkn` und `local_bildungsbericht`.

## Datenschutz und Aufbewahrung

[classes/privacy/provider.php](classes/privacy/provider.php) implementiert Moodles Datenschutz-API für Export und Löschung. Erfasst sind Zuordnungen (als lernende Person und als Berufsbildner/in), Verknüpfungen mit globalen Gruppen, Einsätze aus dem Versetzungsplan, ausgeführte Versetzungsplan-Importe, dokumentierte Aufbewahrungspflichten und die Teilnahmeart, dazu für Verwaltende, welche Ausbildungsblöcke und Kompetenzzuordnungen sie zuletzt geändert haben.

Einsatz gilt als offizieller Ausbildungsnachweis und wird bei einer Löschanfrage **nicht** gelöscht (Architekturregel 5), ebenso wenig die Verknüpfungen mit globalen Gruppen — nur der ausführende Account eines Versetzungsplan-Imports und die bearbeitende Person eines Ausbildungsblocks werden anonymisiert.

Zuordnung folgt einer feineren Regel (Architekturregel 2): **während laufender Ausbildung** bleibt sie immer unangetastet, auch bei einer eigenen Löschanfrage. Der Lehrabschluss selbst beendet eine laufende Zuordnung bewusst nicht, `gueltig_bis` bleibt `NULL` ([docs/konzept.md](https://github.com/jsAce7/moodle-local_berufsbildung/blob/main/docs/konzept.md) §13.4). **Nach Lehrabschluss** wird eine Löschanfrage sofort honoriert, und unabhängig von jeder Anfrage löscht der wöchentliche Task `task\zuordnung_retention` automatisch, sobald die konfigurierte Frist (Standard 12 Monate) verstrichen ist — beides ausser bei dokumentierter Aufbewahrungspflicht. **Eine Löschung des Kontos der lernenden Person** entfernt ihre Zuordnungen immer sofort und unbedingt, auch mitten in einer laufenden Ausbildung. Mit den Zuordnungen verschwinden jeweils auch die Aufbewahrungsvermerke und die Teilnahmeart der Person. **Eine Löschung des Kontos einer Berufsbildner/in** beendet deren laufende Zuordnungen, löscht noch nicht begonnene und deaktiviert ihre Verknüpfungen mit globalen Gruppen; die beendeten Zuordnungen bleiben als Ausbildungshistorie der Lernenden bis zu deren Aufbewahrungsfrist. Details und Begründung stehen als Kommentar direkt im Provider.

## Entwicklung

Aus der Entwicklungsumgebung `~/moodle-dev/`:

```bash
./scripts/cli.sh php admin/cli/purge_caches.php
./scripts/cli.sh php admin/cli/upgrade.php --non-interactive
./scripts/cli.sh php admin/cli/check_database_schema.php
./scripts/test.sh berufsbildung          # PHPUnit
./scripts/behat.sh @local_berufsbildung  # Behat
./scripts/lint.sh local/berufsbildung    # phpcs (moodle-cs)
```

Es gibt 379 PHPUnit-Tests in 38 Dateien. Behat deckt mit 15 Szenarien Zuordnungen, die Kompetenzauswahl und das Kopieren von Blöcken, die LK-Übersicht, das Kompetenzraster auf „Meine Lehre“ und „Meine Lernenden“ ab, nicht jede Seite. Die CI ([.github/workflows/ci.yml](.github/workflows/ci.yml), `moodle-plugin-ci`) läuft bei jedem Push und Pull Request auf GitHub Actions, mit Moodle 4.5 / PHP 8.1 / PostgreSQL und Moodle 5.2 / PHP 8.4 / MariaDB; Moodle 5.0 und 5.1 laufen nicht eigens mit.

**JavaScript** liegt als AMD-Modul in `amd/src`, das gebaute `amd/build` gehört mit ins Repository. Nach einer Änderung neu bauen. Weil das Plugin ausserhalb des Moodle-Baums liegt und dort nur im Container eingehängt ist, wird es für Grunt vorübergehend eingebunden (Node und `node_modules` im Moodle-Verzeichnis vorausgesetzt):

```bash
sudo mount --bind ~/moodle-dev/plugins/berufsbildung ~/moodle-dev/moodle/local/berufsbildung
(cd ~/moodle-dev/moodle && npx grunt amd --root=local/berufsbildung)
sudo umount ~/moodle-dev/moodle/local/berufsbildung
```

Die CI prüft mit `moodle-plugin-ci grunt`, dass `amd/build` zu `amd/src` passt.

`cli/testdaten.php` legt für einen manuellen Testlauf die Profilfelder `beruf` und `jahrgang`, drei Testnutzer mit festem Passwort und eine Zuordnung an — nur für Entwicklungs- und Testsysteme. Es bricht ab, wenn die Debug-Meldungen nicht auf DEVELOPER stehen, und ist nicht Teil der Release-ZIP:

```bash
./scripts/cli.sh php local/berufsbildung/cli/testdaten.php
```

Projektkonventionen und Architekturregeln liegen in [CLAUDE.md](CLAUDE.md), das Konzept mit den Begründungen in [docs/konzept.md](https://github.com/jsAce7/moodle-local_berufsbildung/blob/main/docs/konzept.md), die Änderungsgeschichte in [CHANGELOG.md](CHANGELOG.md).

**Release veröffentlichen**: `$plugin->release` und `$plugin->version` in `version.php` hochzählen, im CHANGELOG den Abschnitt `## [<release>] — <datum>` anlegen und auf `main` pushen. Ändert der Push `$plugin->release`, legt der Job `release` in der CI nach grüner Prüfung den Tag `v<release>` und ein GitHub-Release mit dem ZIP `local_berufsbildung-v<release>.zip` an; die Notizen sind der CHANGELOG-Abschnitt. Alpha-, Beta- und RC-Versionen erscheinen als Vorabversion. Fehlt der CHANGELOG-Abschnitt, schlägt der Job fehl. Ist ein Release ausgeblieben, etwa weil ein späterer Push den Lauf abgelöst hat, holt ein Start von Hand (*Actions ▸ CI ▸ Run workflow* auf `main`) es nach.

**ZIP von Hand erstellen** (ohne CI-Dateien, CLAUDE.md, `docs/`, `beispiele/` und `cli/testdaten.php`, alles über `export-ignore` in [.gitattributes](.gitattributes)):

```bash
git archive --format=zip --prefix=berufsbildung/ -o ../local_berufsbildung-<version>.zip HEAD
```

## Lizenz

© 2026 jsAce7

Dieses Plugin ist freie Software: Es darf unter den Bedingungen der GNU General Public License, wie von der Free Software Foundation veröffentlicht, weitergegeben und/oder verändert werden, entweder gemäss Version 3 der Lizenz oder (nach Wahl) jeder späteren Version (GNU GPL v3 or later). Es wird ohne jede Gewährleistung verbreitet, siehe <https://www.gnu.org/licenses/>.

Der vollständige Lizenztext steht in [COPYING.txt](COPYING.txt).
