# Changelog

Alle nennenswerten Änderungen an diesem Plugin werden hier festgehalten. Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [0.2.0] — 2026-09-05

### Hinzugefügt

- **„Meine Ausbildung" zeigt den Versetzungsplan**: der laufende Einsatz als Kopfzeile („wo bin ich gerade") und der vollständige Zeitstrahl aller Einsätze mit vergangen/aktuell/kommend (`classes/output/einsatz_karte.php`, `classes/output/einsatz_timeline.php`). Die dafür nötigen API-Methoden gab es bereits, sie hatten bisher nur keinen Anzeige-Ort.
- **Ausbildungsphase** (`api::get_ausbildungsphase()` mit den Konstanten `PHASE_UNBEKANNT`, `PHASE_VOR_BEGINN`, `PHASE_LAUFEND`, `PHASE_BEENDET`) und **`api::get_ausbildungsbeginn()`** als Gegenstück zu `get_ausbildungsende()`. `get_ausbildungsstand()` liefert in drei fachlich verschiedenen Fällen `null`; aufsetzende Plugins mussten bisher raten, welcher davon vorlag.
- **`api::get_semester_grenzen()`**: Grenzen aller Semester einer Ausbildung, damit fremde Datensätze (etwa Nachweise) nach Semester einsortiert werden können, ohne je Datum das Profil neu aufzulösen.
- **`api::get_luecken_nach_bereich()`** und das Wertobjekt `bereich_abdeckung`: dieselbe Lückenanalyse wie `get_luecken()`, aber nach Handlungskompetenzbereich gruppiert und mit Bezugsgrösse („vier von sechs abgedeckt"). Bereiche, deren HK ausschliesslich Wahlpflicht sind, entfallen, statt als „0 von 0" zu erscheinen.
- **Nachweise nach Semester gruppiert** auf „Meine Ausbildung" (`nachweis_liste::render()` nimmt optional Semestergrenzen entgegen) — eine lernende Person denkt ihre Ausbildung in Semestern, nicht in liefernden Plugins. Ohne Semestergrenzen bleibt es bei der Gruppierung nach Quelle, wie sie „Meine Lernenden" weiterhin nutzt.
- `kw_converter::zu_wochennummer()` für die Anzeige einer Kalenderwoche, die bei unbekanntem Format bewusst nicht wirft.

### Behoben

- **„Meine Ausbildung" wurde am Tag des Lehrabschlusses schlagartig leer.** Da `get_ausbildungsstand()` nach Ausbildungsende `null` liefert, fiel die gesamte Seite auf die Meldung „Für Ihr Profil ist kein Beruf oder Jahrgang hinterlegt" zurück — inhaltlich falsch (der Beruf *ist* hinterlegt), und alle Nachweise verschwanden aus der Ansicht, obwohl sie während der Aufbewahrungsfrist weiterhin existieren. Die Seite unterscheidet die Fälle jetzt: fehlendes Profil, Lehre beginnt erst (mit Startdatum und, falls vorhanden, bereits dem Versetzungsplan), laufende Lehre und abgeschlossene Lehre (Rückblick mit Abschlussdatum, Zeitstrahl und Nachweisen). Der Stand des letzten Semesters wird dafür über den Stichtag-Parameter aufgelöst, wie ihn Architekturregel 3 vorsieht.

### Geändert

- `luecken_liste::render()` nimmt `bereich_abdeckung[]` statt `int[]` entgegen und kennt eine kompakte Variante für die Roster-Karten in „Meine Lernenden". Die Lückenanalyse wird auf „Meine Ausbildung" nur noch während der laufenden Lehre gezeigt: nach dem Abschluss wäre sie keine Planung mehr, sondern ein Urteil.

## [0.1.0] — 2026-08-29

Erste funktionale Version. Nicht produktiv freigegeben — siehe README.md, Abschnitt "Was noch fehlt".

### Hinzugefügt

- **Nutzerführung**: Die bisher doppeldeutige Seite „Meine Lehre“ heisst neu „Meine Ausbildung“; Lernendenkarten verweisen deutlicher auf Profil und Lerndokumentation.
- **Ausbildungsstand**: Beruf/Jahrgang aus Profilfeldern, Semester-/Lehrjahrberechnung mit konfigurierbarer Lehrdauer je Beruf (`api::get_ausbildungsstand()`). Das Jahrgang-Profilfeld darf auch ein kombiniertes Feld sein, das zusätzlich den Beruf enthält (z. B. "AU 2026", wie es manche Schulen für die automatische Kursgruppierung pflegen) — die Jahreszahl wird daraus extrahiert, der Beruf kommt weiterhin aus dem separat konfigurierten Profilfeld.
- **Zuordnung**: CRUD-Verwaltung, Rollenmodell (mehrere gleichzeitige Rollen je Person möglich, Auswahl im Formular als Dropdown mit den aktuell genutzten Werten "hauptverantwortlich"/"stellvertretung" — das Feld selbst bleibt bewusst Freitext ohne Schema-Einschränkung, siehe CLAUDE.md Architekturregel 4), Bulk- und Kohorten-Zuordnung, laufende Kohorten-Synchronisation (stündlicher Task), CSV-Import für den Jahreswechsel, automatischer Rollen-Sync in Moodles Rollensystem.
- **Report-Builder-Übersicht** der Zuordnungen mit Filtern und Export.
- **Nachweis-Provider-Mechanismus**: aufsetzende Plugins registrieren sich, "Meine Lernenden"/"Meine Lehre" sammeln Nachweise über Plugin-Grenzen hinweg ein, mit vorheriger Zuständigkeitsprüfung.
- **Versetzungsplan**: CSV-Parser (Block- und Wochenformat), Import-Pipeline mit Idempotenz, Vollständigkeitsschutz und Testlauf-Modus, Ausbildungsblöcke mit Kompetenzabdeckung, Webservice-Funktion `local_berufsbildung_import_versetzungsplan`, manueller Upload als Rückfallweg, Alterungshinweis.
- **Öffentliche API** für Einsätze, ausgebildete Kompetenzen und aktuellen Einsatz (`get_einsaetze()`, `get_ausgebildete_kompetenzen()`, `get_aktueller_einsatz()`).
- **Lücken-Analyse** (`api::get_luecken()`): Handlungskompetenzen ohne Abdeckung, basierend auf einer zentral gepflegten Beruf→Kompetenzrahmen-Zuordnung. Jetzt sichtbar auf "Meine Lehre" und "Meine Lernenden" (`classes/output/luecken_liste.php`) — vorher nur über die API abrufbar, ohne Anzeige-Ort.
- **Datenschutz-Provider** (`classes/privacy/provider.php`): vollständiger Export für Zuordnung, Kohorten-Link, Einsatz, Versetzungsplan-Import und neu Aufbewahrungspflichten; Löschanfragen anonymisieren den ausführenden Account eines Imports und lassen Einsatz als Ausbildungsnachweis bestehen (Architekturregel 5). Zuordnung folgt seit dem Retention-Feature einer feineren Regel, siehe unten.
- **Aufbewahrungsfrist nach Lehrabschluss** (`api::get_ausbildungsende()`, `api::ist_ausbildung_beendet()`, `api::hat_aufbewahrungspflicht()`, `api::darf_personendaten_geloescht_werden()`, `api::aufbewahrungsfrist_abgelaufen()`, neue Einstellung `retention_monate`, Standard 12): Zuordnungen werden automatisch endgültig gelöscht (`zuordnung_retention_service`, Task `task\zuordnung_retention`), sobald die Frist seit dem berechneten Lehrabschluss verstrichen ist — ausser eine dokumentierte Aufbewahrungspflicht liegt vor (neue Tabelle `local_berufsbildung_aufbewahrung`, Verwaltungsseite `aufbewahrung.php`, Capability `local/berufsbildung:manageaufbewahrung`). Eine Löschanfrage nach Lehrabschluss wird aus demselben Grund vorzeitig honoriert; eine Account-Löschung löscht immer sofort und unbedingt, auch während laufender Ausbildung. `kohorten_sync_service` erzeugt für eine Person mit abgeschlossener Ausbildung bewusst keine neue Zuordnung mehr, sonst würde eine bereits gelöschte Zuordnung beim nächsten Sync-Lauf wieder auferstehen.
- `api::get_block_name()` — Bezeichnung eines Ausbildungsblocks für aufsetzende Plugins, u.a. Grundlage der automatischen Abteilungs-Vorbelegung in `local_lerndokumentation`.
- Erste Behat-Testabdeckung (`tests/behat/`) und eine `.github/workflows/ci.yml` (moodle-plugin-ci, Moodle 4.5–5.2 × PHP 8.1–8.4).
- 110 automatisierte Tests (PHPUnit), Datenbankschema-Check grün.

### Behoben

- **`beruf`-Feld auf Zuordnung und Kohorten-Verknüpfung akzeptiert jetzt Freitext** (`PARAM_TEXT`, Spaltenbreite auf 255 Zeichen erweitert) statt streng `PARAM_ALPHANUMEXT`: Schulen, die im Beruf-Profilfeld die ausgeschriebene Bezeichnung statt eines Kurzcodes pflegen (z. B. "Automatiker/in EFZ" statt "AU_EFZ"), bekamen beim automatischen Übernehmen aus dem Profil (Kohorten-Sync, manuelles Zuordnungsformular, CSV-Import mit leerer Beruf-Spalte) eine `invalid_persistent_exception` ("Übermittelte Daten sind ungültig") wegen des enthaltenen Schrägstrichs/Leerzeichens. Der CSV-Import hätte den Wert zuvor sogar stillschweigend auf Buchstaben/Zahlen zusammengestutzt, statt abzubrechen.

### Bekannte Einschränkungen

- Behat deckt bislang nur den zentralen Zuordnungs-Workflow ab, nicht jede Seite. Die CI-Pipeline ist noch nie gegen einen echten Git-Remote gelaufen.
- Lehrverlängerung/Wiederholung und Stellvertretung sind dokumentierte, bewusst zurückgestellte offene Fragen (`docs/plan.md` §13).
- Lehrabschluss beendet eine einzelne laufende Zuordnung nicht automatisch (`gueltig_bis` bleibt `NULL`) — bewusste Entscheidung, siehe `docs/plan.md` §13.4. Sie wird trotzdem automatisch gelöscht, sobald die Aufbewahrungsfrist danach verstrichen ist.
