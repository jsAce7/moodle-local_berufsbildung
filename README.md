# local_berufsbildung

Basis-Plugin für die betriebliche Berufsbildung in Moodle. Beantwortet zwei Fragen für alle aufsetzenden Plugins: **wer ist wofür zuständig** (Zuordnung Berufsbildner/in ↔ Lernende) und **in welchem Semester steht diese Person** (Ausbildungsstand). Zusätzlich: Import und Verwaltung des betrieblichen Versetzungsplans.

Dieses Plugin hat keine eigene Fachfunktion. Alles Fachliche — Lerndokumentation, später Bildungsbericht — baut über `\local_berufsbildung\api` darauf auf.

> **Status:** `MATURITY_ALPHA`, Version `0.1.0`. Funktional weit entwickelt und automatisiert getestet, aber noch nicht für den produktiven Einsatz mit echten Personendaten freigegeben — siehe [CHANGELOG.md](CHANGELOG.md) und die "Was noch fehlt"-Hinweise unten.

## Was dieses Plugin macht

- **Zuordnung**: Berufsbildner/innen werden Lernenden zugeordnet, einzeln, in Bulk oder per Kohorte (einmalig oder laufend synchronisiert). Regulär werden sie mit einem Enddatum beendet; eine bestätigte Löschaktion ist für eindeutig falsch erfasste Zuordnungen verfügbar.
- **Aufbewahrung**: eine konfigurierbare Frist (Standard 12 Monate) nach dem berechneten Lehrabschluss löscht Zuordnungen automatisch endgültig, ausser eine Aufbewahrungspflicht ist dokumentiert. Dieselbe Regel steuert über `\local_berufsbildung\api` auch die Lerndoku-Inhalte in `local_lerndokumentation`.
- **Ausbildungsstand**: Beruf, Lehrjahr und Semester werden aus zwei Profilfeldern (Beruf, Jahrgang) berechnet, mit konfigurierbarer Lehrdauer je Beruf.
- **Versetzungsplan**: wöchentlicher CSV-Import (Webservice oder manueller Upload) der betrieblichen Einsätze, mit Kompetenzabdeckung je Ausbildungsblock.
- **Nachweis-Sammlung**: aufsetzende Plugins registrieren sich als Nachweis-Provider; "Meine Lernenden" (Berufsbildner/in) und "Meine Lehre" (Lernende) zeigen eingesammelte Nachweise über Plugin-Grenzen hinweg — sowie, falls ein Kompetenzrahmen konfiguriert ist, die noch nicht abgedeckten Handlungskompetenzbereiche, nach Bereich gruppiert und mit Bezugsgrösse (`api::get_luecken_nach_bereich()`).
- **Eigene Übersicht für Lernende**: "Meine Lehre" zeigt Semesterstand, den laufenden Einsatz aus dem Versetzungsplan, den Zeitstrahl aller Einsätze und die eigenen Nachweise nach Semester gruppiert. Die Seite trägt die vier Phasen der Ausbildung (`api::get_ausbildungsphase()`): Profil unvollständig, Lehre beginnt erst, laufend, abgeschlossen — nach dem Lehrabschluss wird sie zum Rückblick, statt leer zu werden.

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
| Profile field: start of apprenticeship (optional) | Profilfeld (am besten ein Datumsfeld) mit einem abweichenden Lehrbeginn je Person, z. B. 15. August, Februar oder Einstieg in ein späteres Semester. Leer = 1. des Startmonats im Jahrgang. Bestimmt nur den Beginn der Probezeit (`api::get_lehrbeginn()`), nicht die Semester. |
| Training start month | Monat, in dem alle Lehren starten |
| Training length in semesters | Standard-Lehrdauer, überschreibbar je Beruf |
| Competency framework per occupation | Beruf → `core_competency`-Rahmen-Zuordnung |
| Threshold for incomplete deliveries | Vollständigkeitsschutz beim Versetzungsplan-Import |
| Aging warning after (days) | Alterungshinweis, wenn der letzte Import zu lange zurückliegt |
| Retention period after training completion (months) | Frist bis zur automatischen Löschung von Zuordnungen (und, über `local_lerndokumentation`, der Lerndoku-Inhalte) nach Lehrabschluss. Standard: 12. |

**Welcher Kompetenzrahmen?** Die obersten Knoten sind die betrieblichen Handlungskompetenzen (HK); darunter können die konkreten Lernkompetenzen (LK) liegen. Im Versetzungsplan werden die LK dem Ausbildungsblock zugeordnet. Das Plugin rechnet eine LK bei der Lückenanalyse auf alle übergeordneten HK hoch und zeigt deshalb nur noch offene Pflicht-HK. Die in der AU-Umsetzung als `W` gekennzeichneten HK sind standardmässig als Wahlpflicht hinterlegt und erscheinen nicht als Lücke; die Zuordnung lässt sich in der Einstellung *Wahlpflicht-Handlungskompetenzen je Beruf* an den verwendeten Berufscode und die ID-Nummern des Rahmens anpassen.

Der Rahmen muss der lernenden Person dafür **nicht zusätzlich zugewiesen** werden (kein Lernplan, keine Kurs-Verknüpfung nötig) — `\core_competency\api::add_evidence()` legt den `user_competency`-Datensatz beim ersten Kompetenznachweis automatisch an.

Dokumentierte Aufbewahrungspflichten (Ausnahmen von der automatischen Löschung) werden auf einer eigenen Seite verwaltet: *Site administration ▸ Plugins ▸ Local plugins ▸ Vocational training ▸ Retention obligations*, Capability `local/berufsbildung:manageaufbewahrung`.

### Rollen und Zugang

Das Plugin legt zwei Rollen an:

| Rolle | Kontext | Zweck |
|---|---|---|
| `berufsbildner` (Berufsbildner/in) | Nutzerkontext der lernenden Person | Trägt selbst keine Capabilities — die vergeben die aufsetzenden Plugins. Wird je bestehender Zuordnung zugewiesen und bleibt nach deren Ende bestehen, damit eine frühere Zuständigkeit zum damaligen Stichtag noch auflösbar ist. Entzogen erst mit der Datenbereinigung (Aufbewahrungsfrist, Löschung der Zuordnung, Account-Löschung). |
| `berufsbildung_planung` (Ausbildungsplanung) | Systemkontext | Trägt `local/berufsbildung:manageblocks` und öffnet damit die Ausbildungsblöcke samt Kompetenzabdeckung. Wird jeder Person zugewiesen, die mindestens eine **laufende** Zuordnung hat, und wieder entzogen, sobald die letzte davon beendet ist. |

Der Unterschied im Entzug ist beabsichtigt: die personenbezogene Rolle ist stichtagsgeprüfter Lesezugriff und muss für einen Bericht aus einem früheren Semester noch greifen; die Planungsrolle ist systemweites Schreibrecht auf Stammdaten und endet deshalb mit der Betreuung.

Beide werden vom stündlichen Task `sync_role_assignments` gepflegt, zusätzlich sofort beim Anlegen, Beenden und Löschen einer Zuordnung. Die selbst vergebenen Zuweisungen sind mit `component = 'local_berufsbildung'` markiert; von Hand vergebene bleiben unangetastet und werden nie entzogen. Eine Ausbildungsleitung, die selbst keine Lernenden betreut, wird deshalb einfach von Hand global der Rolle *Ausbildungsplanung* zugewiesen.

#### Wo sich die Rollen von Hand vergeben lassen

`set_role_contextlevels()` registriert jede Rolle für **genau ein** Kontextlevel, und `get_assignable_roles()` bietet eine Rolle nur auf der Seite an, die zu diesem Level gehört:

| Rolle | Einzige Stelle, an der sie angeboten wird |
|---|---|
| `berufsbildung_planung` | *Website-Administration ▸ Nutzer/innen ▸ Rechte ändern ▸ **Globale Rollen zuweisen*** |
| `berufsbildner` | Profil der **lernenden** Person ▸ *Einstellungen* ▸ „Rollen relativ zu diesem Nutzer zuweisen" |

In einem Kurs ist keine der beiden zuweisbar — dort erscheinen nur Rollen mit `CONTEXT_COURSE`. Das ist Absicht (Architekturregel 1: kein Kurskontext).

`create_role()` trägt in `role_allow_assign` und `role_allow_view` nichts ein, und genau daran filtert `get_assignable_roles()` für alle, die nicht Administrator/in sind. `role_matrix_service` trägt deshalb bei Installation und Upgrade nach, dass die Rolle *Manager* die Rolle *Ausbildungsplanung* vergeben darf und beide Rollennamen sehen kann. Wer das einer anderen Rolle erlauben will, setzt das Häkchen unter *Rollen verwalten ▸ Rollenzuweisungen erlauben*.

`berufsbildner` bleibt bewusst **nicht** von Hand vergebbar, nur sichtbar: die Rolle allein öffnet nichts, weil die aufsetzenden Plugins neben ihrer Capability immer auch `api::is_zustaendig()` prüfen. Zuständigkeit entsteht über eine Zuordnung, nicht über eine Rollenzuweisung (Architekturregel 2).

Die Trennung der beiden Rollen ist ebenso beabsichtigt: `berufsbildner` hängt am Nutzerkontext einer einzelnen lernenden Person. Global zugewiesen würden alle ihre Capabilities — auch die künftig von aufsetzenden Plugins vergebenen — für *alle* Personen gelten und damit die Stichtagsprüfung in `api::is_zustaendig()` unterlaufen.

Berufsbildner/innen erreichen die Blockverwaltung über den Button **Ausbildungsblöcke verwalten** auf *Meine Lernenden*. Die primäre Navigationsleiste trägt bewusst nur die beiden täglichen Einstiege *Meine Lehre* und *Meine Lernenden*; die Blockpflege ist Stammdatenarbeit und hängt deshalb als Nebeneingang daran. Wer `manageblocks` ohne eigene Lernende hat — eine von Hand zugewiesene *Ausbildungsplanung* —, nimmt den Weg über *Website-Administration ▸ Ausbildungsverwaltung ▸ Planung*. Änderungen wirken systemweit für alle Berufe, und `local_berufsbildung_block_lk` führt keine Änderungshistorie — nachvollziehbar ist über `usermodified`/`timemodified` nur die jeweils letzte Änderung eines noch bestehenden Eintrags, Entfernungen sind spurlos. Wer die Pflege einem kleineren Kreis vorbehalten will, entzieht der Rolle *Ausbildungsplanung* die Capability `local/berufsbildung:manageblocks` und weist sie gezielt einer eigenen Rolle zu; der Zugang über die Navigation und den Admin-Baum richtet sich allein nach dieser Capability.

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
- Der Bildungsbericht (`local_bildungsbericht`) als weitere Nachweisquelle existiert noch nicht.

## Lizenz

GNU GPL v3 or later.
