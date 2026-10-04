# CLAUDE.md — `local_berufsbildung`

Projektkonventionen für die Arbeit an diesem Moodle-Plugin. Diese Datei gehört ins Repo-Root.

## Was dieses Plugin ist

Basis-Plugin für die betriebliche Berufsbildung in Moodle. Besitzt die Zuordnung zwischen Berufsbildner/innen und Lernenden, die Auflösung von Beruf/Jahrgang/Semester, den Versetzungsplan-Import und das Einsammeln externer Leistungsnachweise. Hat **keine eigene Fachfunktion** — es beantwortet nur „wer ist wofür zuständig" und „in welchem Semester steht diese Person".

Der vollständige Architekturplan liegt in `docs/plan.md`. Bei Widersprüchen zwischen dieser Datei und dem Plan gilt der Plan. Für Schnitt 1 gilt zusätzlich `docs/schnitt1.md` (liegt im Repo von `local_lerndokumentation`, das darauf aufsetzt) — dort steht die aktuelle Reihenfolge und der reduzierte Umfang.

Aufsetzende Plugins: `local_lerndokumentation`, `local_bildungsbericht` und `local_uekkn`. Sie greifen nie direkt auf die Tabellen dieses Plugins zu, sondern über dessen öffentliche Schnittstellen: `\local_berufsbildung\api`, den Nachweis-Collector (`nachweis\…`), die PDF-Grundlage (`pdf\…`) und einzelne Darstellungs- und Hilfsklassen (`output\nachweis_liste`, `service\kompetenz_baum`).

## Zielumgebung

- Moodle 4.5 LTS bis 5.2, vorbereitet auf 5.3 LTS
- PHP 8.1 (4.5) bis 8.4 (5.2)
- Datenbank: MariaDB (Produktivsystem), PostgreSQL in CI mitgetestet
- Sprache der Oberfläche: Deutsch (Schweiz). `lang/en` ist Pflicht, `lang/de` ist die real genutzte Fassung.

## Architekturregeln — nicht verhandelbar

1. **Kein Kurskontext für Fachlogik.** Alles läuft über `context_user::instance($lernendeid)`.
   Als eng begrenzte Ausnahme darf ein Ausbildungsblock optional auf einen
   bestehenden Moodle-Kurs verweisen, damit in „Meine Lehre" während des
   aktuellen Einsatzes ein Link angezeigt werden kann. Die Verknüpfung
   schreibt weder Einschreibungen noch Rollen, liest keine Kursinhalte und
   umgeht keine Zugriffsrechte; diese bleiben vollständig beim Moodle-Core.
   Ausserhalb dieser reinen Anzeigeverknüpfung ist `$courseid` weiterhin ein
   Designfehler.
2. **Die Zuordnungstabelle ist die einzige Wahrheit — solange die Ausbildung läuft oder die Aufbewahrungsfrist noch nicht abgelaufen ist.** Zuordnungen werden grundsätzlich mit `gueltig_bis` beendet, damit vergangene Zuständigkeiten nachvollziehbar bleiben. Eine berechtigte Verwaltung darf jedoch eine nachweislich falsch erfasste Zuordnung über `api::loesche_zuordnung()` endgültig entfernen. Zusätzlich löscht `retention_monate` (Standard 12) nach dem über `api::get_ausbildungsende()` berechneten Ausbildungsabschluss alle Zuordnungen einer Person automatisch endgültig (`zuordnung_retention_service`), ausser eine Aufbewahrungspflicht ist dokumentiert (`api::hat_aufbewahrungspflicht()`). Bei einer Account-Löschung geschieht dieselbe endgültige Löschung sofort und unbedingt, unabhängig vom Ausbildungsstand.
3. **Stichtag immer mitgeben.** `is_zustaendig()`, `get_ausbildungsstand()` und verwandte Methoden nehmen einen optionalen `$stichtag`. Ein abgeschlossener Bericht aus einem früheren Semester muss auch dann korrekt auflösbar sein, wenn die Zuordnung inzwischen beendet wurde. `null` bedeutet „jetzt", nie stillschweigend annehmen.
4. **Feld `rolle` in der Zuordnung wird abgefragt, nicht angenommen.** In v1 hat es immer den Wert `hauptverantwortlich`. Prüfungen fragen den Wert trotzdem explizit ab — spätere Erweiterung um Fachvorgesetzte (Arbeitsplatzberichte) braucht dann keine Migration.
5. **Der Versetzungsplan ist ein Spiegel, keine Wahrheit.** Er wird importiert und ist in Moodle nicht editierbar. Ein fehlgeschlagener Import lässt den bestehenden Datenbestand unangetastet — nie Einsätze löschen, weil eine Lieferung fehlerhaft war.
6. **Vorbelegungen aus dem Versetzungsplan sind Vorschläge, keine Festlegungen.** Alles, was aus `get_ausgebildete_kompetenzen()` abgeleitet wird, muss in den aufsetzenden Plugins überschreibbar bleiben.
7. **Nachweis-Provider werden nie ungeprüft aufgerufen.** Die Zuständigkeitsprüfung passiert im Basis-Plugin, bevor ein Provider gefragt wird — nie im Provider selbst.
8. **Kein `$DB` ausserhalb von Persistent- und Service-Klassen.**

## Code-Konventionen

- `declare(strict_types=1);` in jeder neuen Klasse
- Namespace `local_berufsbildung\...`, PSR-4-konform zur Verzeichnisstruktur unter `classes/`
- Moodle Coding Style (`phpcs` mit `moodle-cs`)
- Modelle: `\core\persistent` mit vollständigem `define_properties()` inkl. Validierung
- Wertobjekte (`ausbildungsstand`, `nachweis`) mit `readonly`-Properties, nicht als Persistent — sie werden nicht gespeichert, sondern berechnet. Keine `readonly class`: die gibt es erst ab PHP 8.2, Moodle 4.5 läuft ab PHP 8.1.
- Den Nachweis-Collector nie als Standardwert eines Parameters bauen (`= new collector()`): er bindet die `lib.php` aller Plugins ein, und ab PHP 8.3 löst ein relatives `require_once` darin gegen das Verzeichnis der deklarierenden Datei auf (so brach `local_bildungsbericht` unter Moodle 5.2). Stattdessen `?collector $collector = null` und im Rumpf `$collector ?? new collector()`.
- Deutsche Bezeichner in Datenbankfeldern sind gewollt (`gueltig_von`, `beruf`) — Fachdomäne ist deutschsprachig. Klassen- und Methodennamen bleiben englisch.

## Befehle

Aus `~/moodle-dev/` (Entwicklungsumgebung, Details in deren eigener README):

```bash
./scripts/cli.sh php admin/cli/purge_caches.php
./scripts/cli.sh php admin/cli/upgrade.php --non-interactive
./scripts/cli.sh php admin/cli/check_database_schema.php
./scripts/test.sh berufsbildung
./scripts/behat.sh @local_berufsbildung
```

Nach jeder Aufgabe: Abnahmekriterien aus `docs/schnitt1.md` mit diesen Befehlen selbst prüfen, nicht nur behaupten.

## Vorgehen bei Änderungen

- **Neue Tabelle oder Feld**: `db/install.xml` über den XMLDB-Editor ändern, `version.php` hochzählen, `db/upgrade.php` mit Savepoint ergänzen. Beides, nie nur eines.
- **Neue Capability**: `db/access.php` + Sprachstring `berufsbildung:<name>` + `version.php` hochzählen.
- **Neuer Sprachstring**: immer in `de` *und* `en`, alphabetisch nach Schlüssel einsortiert, keine Kommentare zwischen den Strings (sonst meldet der Code-Checker die Reihenfolge).
- **JavaScript**: nur in `amd/src` ändern, danach `amd/build` neu bauen (README, Abschnitt Entwicklung); die CI prüft das mit `moodle-plugin-ci grunt`.
- **Neue API-Methode**: Unit-Test für den Normalfall *und* für den Stichtag-Randfall (Zuordnung endet genau am Stichtag, beginnt genau danach).

## Testanforderungen (Schnitt 1)

- Laufende Zuordnung gilt; beendete Zuordnung gilt am früheren Stichtag, aber nicht heute; noch nicht begonnene Zuordnung gilt nicht
- Semesterberechnung: alle Prüfbeispiele aus `docs/schnitt1.md`, inklusive der beiden `null`-Fälle (Lehre nicht begonnen / beendet)
- `semester_grenzen()` überschreitet nie die Grenze zum nächsten Semester (1. Februar / 1. August)
- Fremde Person hat nie Zuständigkeit, unabhängig von der Capability

Neuer Code ohne Test für den zugehörigen Stichtag- oder Randfall gilt als unvollständig.

## Was nicht in dieses Plugin gehört

- Beurteilungslogik, Formulare für Lernende — gehört in die aufsetzenden Plugins
- Kompetenzdefinitionen — `core_competency` ist bereits die geteilte Grundlage, keine Zwischenschicht
- Alles, was heute nur ein einziges aufsetzendes Plugin braucht — wandert erst hierher, wenn ein zweites es tatsächlich benötigt
