# local_berufsbildung

Basis-Plugin für die betriebliche Berufsbildung in Moodle. Beantwortet zwei Fragen für alle aufsetzenden Plugins: **wer ist wofür zuständig** (Zuordnung Berufsbildner/in ↔ Lernende) und **in welchem Semester steht diese Person** (Ausbildungsstand). Zusätzlich: Import und Verwaltung des betrieblichen Versetzungsplans.

Dieses Plugin hat keine eigene Fachfunktion. Alles Fachliche — Lerndokumentation, später Bildungsbericht — baut über `\local_berufsbildung\api` darauf auf.

> **Status:** `MATURITY_ALPHA`, Version `0.1.0`. Funktional weit entwickelt und automatisiert getestet, aber noch nicht für den produktiven Einsatz mit echten Personendaten freigegeben — siehe [CHANGELOG.md](CHANGELOG.md) und die "Was noch fehlt"-Hinweise unten.

## Was dieses Plugin macht

- **Zuordnung**: Berufsbildner/innen werden Lernenden zugeordnet, einzeln, in Bulk oder per Kohorte (einmalig oder laufend synchronisiert). Zuordnungen werden nie von Hand gelöscht, nur mit Enddatum versehen — mit einer Ausnahme, siehe "Aufbewahrung" unten.
- **Aufbewahrung**: eine konfigurierbare Frist (Standard 12 Monate) nach dem berechneten Lehrabschluss löscht Zuordnungen automatisch endgültig, ausser eine Aufbewahrungspflicht ist dokumentiert. Dieselbe Regel steuert über `\local_berufsbildung\api` auch die Lerndoku-Inhalte in `local_lerndokumentation`.
- **Ausbildungsstand**: Beruf, Lehrjahr und Semester werden aus zwei Profilfeldern (Beruf, Jahrgang) berechnet, mit konfigurierbarer Lehrdauer je Beruf.
- **Versetzungsplan**: wöchentlicher CSV-Import (Webservice oder manueller Upload) der betrieblichen Einsätze, mit Kompetenzabdeckung je Ausbildungsblock.
- **Nachweis-Sammlung**: aufsetzende Plugins registrieren sich als Nachweis-Provider; "Meine Lernenden" (Berufsbildner/in) und "Meine Lehre" (Lernende) zeigen eingesammelte Nachweise über Plugin-Grenzen hinweg.

## Voraussetzungen

- Moodle 4.5 LTS bis 5.2 (PHP 8.1–8.4)
- MariaDB (Produktivsystem), PostgreSQL wird mitgetestet

## Installation

1. Ins Verzeichnis `local/berufsbildung` des Moodle-Codebase kopieren oder verlinken.
2. `admin/cli/upgrade.php --non-interactive` ausführen, oder über die Weboberfläche installieren.
3. Zwei benutzerdefinierte Profilfelder anlegen (Beruf, Jahrgang) und unter *Site administration ▸ Vocational training ▸ Settings* zuordnen.

## Konfiguration

Alle Einstellungen unter *Site administration ▸ Plugins ▸ Local plugins ▸ Vocational training*:

| Einstellung | Zweck |
|---|---|
| Profile field: occupation / cohort year | Welches Profilfeld Beruf bzw. Jahrgang enthält |
| Training start month | Monat, in dem alle Lehren starten |
| Training length in semesters | Standard-Lehrdauer, überschreibbar je Beruf |
| Competency framework per occupation | Beruf → `core_competency`-Rahmen-Zuordnung |
| Threshold for incomplete deliveries | Vollständigkeitsschutz beim Versetzungsplan-Import |
| Aging warning after (days) | Alterungshinweis, wenn der letzte Import zu lange zurückliegt |
| Retention period after training completion (months) | Frist bis zur automatischen Löschung von Zuordnungen (und, über `local_lerndokumentation`, der Lerndoku-Inhalte) nach Lehrabschluss. Standard: 12. |

Dokumentierte Aufbewahrungspflichten (Ausnahmen von der automatischen Löschung) werden auf einer eigenen Seite verwaltet: *Site administration ▸ Plugins ▸ Local plugins ▸ Vocational training ▸ Retention obligations*, Capability `local/berufsbildung:manageaufbewahrung`.

Für den Versetzungsplan-Webservice zusätzlich: Dienst *Berufsbildung: Versetzungsplan-Import* unter *Site administration ▸ Server ▸ Web services* aktivieren, Dienstkonto mit der Capability `local/berufsbildung:importplan` anlegen, Token ausstellen. Der Dienst ist standardmässig deaktiviert.

## Öffentliche API

Aufsetzende Plugins greifen ausschliesslich über `\local_berufsbildung\api` zu, nie direkt auf die Tabellen dieses Plugins. Wichtigste Methoden:

```php
api::is_zustaendig(int $berufsbildnerid, int $lernendeid, ?int $stichtag = null): bool
api::get_lernende_for(int $berufsbildnerid, ?int $stichtag = null): array
api::get_ausbildungsstand(int $lernendeid, ?int $stichtag = null): ?ausbildungsstand
api::get_einsaetze(int $lernendeid, ?int $von = null, ?int $bis = null): array
api::get_ausgebildete_kompetenzen(int $lernendeid, int $von, int $bis): array
api::get_luecken(int $lernendeid, ?int $stichtag = null): array
api::get_block_name(int $blockid): ?string
api::get_ausbildungsende(int $lernendeid): ?int
api::ist_ausbildung_beendet(int $lernendeid, ?int $stichtag = null): bool
api::hat_aufbewahrungspflicht(int $lernendeid, ?int $stichtag = null): bool
api::darf_personendaten_geloescht_werden(int $lernendeid, ?int $stichtag = null): bool
api::aufbewahrungsfrist_abgelaufen(int $lernendeid, ?int $stichtag = null): bool
```

Alle Stichtag-Parameter akzeptieren `null` für "jetzt" — nie stillschweigend annehmen.

## Entwicklung

```bash
./scripts/cli.sh php admin/cli/purge_caches.php
./scripts/cli.sh php admin/cli/upgrade.php --non-interactive
./scripts/cli.sh php admin/cli/check_database_schema.php
./scripts/test.sh berufsbildung
./scripts/behat.sh @local_berufsbildung
```

Projektkonventionen, Architekturregeln und der vollständige Plan liegen in [CLAUDE.md](CLAUDE.md) und [docs/plan.md](docs/plan.md).

## Datenschutz und Aufbewahrung

`classes/privacy/provider.php` implementiert Moodles Datenschutz-API vollständig für Export. Einsatz gilt als offizieller Ausbildungsnachweis und wird bei einer Löschanfrage **nicht** gelöscht (Architekturregel 5) — nur der ausführende Account eines Versetzungsplan-Imports wird anonymisiert.

Zuordnung folgt seit dem Retention-Feature einer feineren Regel (Architekturregel 2): **während laufender Ausbildung** bleibt sie immer unangetastet, auch bei einer eigenen Löschanfrage. **Nach Lehrabschluss** wird eine Löschanfrage sofort honoriert, und unabhängig von jeder Anfrage löscht der wöchentliche Task `task\zuordnung_retention` automatisch, sobald die konfigurierte Frist (Standard 12 Monate) verstrichen ist — beides ausser bei dokumentierter Aufbewahrungspflicht. **Eine Account-Löschung** entfernt die Zuordnung immer sofort und unbedingt, auch mitten in einer laufenden Ausbildung. Details und Begründung stehen als Kommentar direkt im Provider.

## Was noch fehlt

- Eine erste Behat-Abdeckung existiert (`tests/behat/`), aber nicht für jede Seite. Eine CI-Pipeline (`.github/workflows/ci.yml`) liegt bereit, ist aber noch nie gegen einen echten Remote gelaufen — dieses Repo hat noch keinen Git-Remote.
- Mehrere offene Fachfragen (Lehrverlängerung/Wiederholung, Stellvertretung) sind bewusst zurückgestellt, siehe `docs/plan.md` §13.
- Die Lücken-Analyse (`api::get_luecken()`) hat noch keine Oberfläche — wartet auf `local_bildungsbericht`.

## Lizenz

GNU GPL v3 or later.
