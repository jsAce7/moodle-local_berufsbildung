<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Oeffentliche API. Aufsetzende Plugins greifen ausschliesslich hierueber zu.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use local_berufsbildung\persistent\aufbewahrung;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\einsatz;
use local_berufsbildung\persistent\zuordnung;
use local_berufsbildung\service\lehrdauer_resolver;
use local_berufsbildung\service\rahmen_resolver;
use local_berufsbildung\service\zuordnung_service;
use local_berufsbildung\service\wahlpflicht_resolver;
use local_berufsbildung\service\semester_calculator;
use local_berufsbildung\versetzungsplan\luecken_analyse;
use local_berufsbildung\versetzungsplan\plan_service;

/**
 * Beantwortet "wer ist wofuer zustaendig" und "in welchem Semester steht
 * diese Person" - die einzige Wahrheit ist die Zuordnungstabelle.
 */
class api {

    /**
     * Ist diese Person zum Stichtag fuer die/den Lernende/n zustaendig?
     *
     * Eine Zuordnung gilt am Stichtag, wenn gueltig_von <= Stichtag und
     * (gueltig_bis IS NULL oder gueltig_bis >= Stichtag).
     *
     * @param int $berufsbildnerid
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return bool
     */
    public static function is_zustaendig(int $berufsbildnerid, int $lernendeid, ?int $stichtag = null): bool {
        $stichtag ??= time();

        $select = 'berufsbildnerid = :berufsbildnerid
                    AND lernendeid = :lernendeid
                    AND gueltig_von <= :stichtag1
                    AND (gueltig_bis IS NULL OR gueltig_bis >= :stichtag2)';

        return zuordnung::count_records_select($select, [
            'berufsbildnerid' => $berufsbildnerid,
            'lernendeid' => $lernendeid,
            'stichtag1' => $stichtag,
            'stichtag2' => $stichtag,
        ]) > 0;
    }

    /**
     * Alle Lernenden dieser/dieses Berufsbildner/in zum Stichtag.
     *
     * @param int $berufsbildnerid
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return int[] Liste von userids
     */
    public static function get_lernende_for(int $berufsbildnerid, ?int $stichtag = null): array {
        $stichtag ??= time();

        $select = 'berufsbildnerid = :berufsbildnerid
                    AND gueltig_von <= :stichtag1
                    AND (gueltig_bis IS NULL OR gueltig_bis >= :stichtag2)';

        $zuordnungen = zuordnung::get_records_select($select, [
            'berufsbildnerid' => $berufsbildnerid,
            'stichtag1' => $stichtag,
            'stichtag2' => $stichtag,
        ]);

        return array_values(array_unique(array_map(
            static fn (zuordnung $zuordnung): int => $zuordnung->get('lernendeid'),
            $zuordnungen
        )));
    }

    /**
     * Alle Berufsbildner/innen dieser lernenden Person zum Stichtag - die
     * Umkehrung von get_lernende_for().
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return int[] Liste von userids
     */
    public static function get_berufsbildner_for(int $lernendeid, ?int $stichtag = null): array {
        $stichtag ??= time();

        $select = 'lernendeid = :lernendeid
                    AND gueltig_von <= :stichtag1
                    AND (gueltig_bis IS NULL OR gueltig_bis >= :stichtag2)';

        $zuordnungen = zuordnung::get_records_select($select, [
            'lernendeid' => $lernendeid,
            'stichtag1' => $stichtag,
            'stichtag2' => $stichtag,
        ]);

        return array_values(array_unique(array_map(
            static fn (zuordnung $zuordnung): int => $zuordnung->get('berufsbildnerid'),
            $zuordnungen
        )));
    }

    /**
     * Beruf, Lehrjahr und Semester einer lernenden Person zum Stichtag.
     *
     * Null, wenn Beruf oder Jahrgang im Profil fehlen, oder wenn die Lehre
     * zum Stichtag noch nicht begonnen hat bzw. bereits beendet ist.
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return ausbildungsstand|null
     */
    public static function get_ausbildungsstand(int $lernendeid, ?int $stichtag = null): ?ausbildungsstand {
        $parameter = self::resolve_ausbildungsparameter($lernendeid);
        if ($parameter === null) {
            return null;
        }

        $calculator = new semester_calculator($parameter['startmonat'], $parameter['lehrdauer']);
        $stichtag ??= time();
        $semester = $calculator->berechne_semester($parameter['jahrgang'], $stichtag);

        if ($semester === null) {
            return null;
        }

        [$semestervon, $semesterbis] = $calculator->semester_grenzen($parameter['jahrgang'], $semester);

        return new ausbildungsstand(
            beruf: $parameter['beruf'],
            jahrgang: $parameter['jahrgang'],
            semester: $semester,
            lehrjahr: (int) ceil($semester / 2),
            semester_von: $semestervon,
            semester_bis: $semesterbis,
            gesamtsemester: $parameter['lehrdauer'],
        );
    }

    /**
     * Beruf, Jahrgang und die zur Semesterberechnung noetigen
     * Konfigurationswerte einer lernenden Person - die gemeinsame
     * Auflösung, die sowohl get_ausbildungsstand() als auch
     * get_ausbildungsende() brauchen. Null, wenn Beruf oder Jahrgang im
     * Profil fehlen.
     *
     * @param int $lernendeid
     * @return array{beruf: string, jahrgang: int, startmonat: int, lehrdauer: int}|null
     */
    private static function resolve_ausbildungsparameter(int $lernendeid): ?array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $feldbeziehung = get_config('local_berufsbildung', 'profilefield_beruf');
        $feldjahrgang = get_config('local_berufsbildung', 'profilefield_jahrgang');
        $feldbeziehung = $feldbeziehung !== false ? $feldbeziehung : 'beruf';
        $feldjahrgang = $feldjahrgang !== false ? $feldjahrgang : 'jahrgang';

        $profil = profile_user_record($lernendeid, false);
        $beruf = $profil->{$feldbeziehung} ?? null;
        $jahrgangroh = $profil->{$feldjahrgang} ?? null;

        if (empty($beruf) || empty($jahrgangroh)) {
            return null;
        }

        $jahrgang = self::extrahiere_jahrgang((string) $jahrgangroh);
        if ($jahrgang === null) {
            return null;
        }

        $startmonat = get_config('local_berufsbildung', 'startmonat');
        $lehrdauer_standard = get_config('local_berufsbildung', 'lehrdauer_semester');
        $startmonat = min(12, max(1, $startmonat !== false ? (int) $startmonat : 8));
        $lehrdauer_standard = min(8, max(1, $lehrdauer_standard !== false ? (int) $lehrdauer_standard : 8));

        $beruf_dauer_konfiguration = get_config('local_berufsbildung', 'beruf_dauer');
        $beruf_dauer_konfiguration = $beruf_dauer_konfiguration !== false ? (string) $beruf_dauer_konfiguration : '';
        $lehrdauer = (new lehrdauer_resolver())->loese_auf((string) $beruf, $beruf_dauer_konfiguration, $lehrdauer_standard);

        return [
            'beruf' => (string) $beruf,
            'jahrgang' => $jahrgang,
            'startmonat' => $startmonat,
            'lehrdauer' => $lehrdauer,
        ];
    }

    /**
     * Extrahiert die vierstellige Jahreszahl aus dem Jahrgang-Profilfeld.
     * Unterstuetzt sowohl ein reines Jahr ("2026") als auch ein
     * kombiniertes Feld, das zusaetzlich den Beruf enthaelt ("AU 2026",
     * fuer die automatische Kursgruppierung nach Beruf und Jahr genutzt) -
     * der Beruf kommt in diesem Fall ohnehin aus dem eigenen, separat
     * konfigurierten Profilfeld (profilefield_beruf) und wird hier
     * ignoriert.
     *
     * @param string $wert
     * @return int|null null, wenn keine vierstellige Jahreszahl gefunden wird
     */
    private static function extrahiere_jahrgang(string $wert): ?int {
        if (preg_match('/\d{4}/', $wert, $treffer) !== 1) {
            return null;
        }

        return (int) $treffer[0];
    }

    /**
     * Timestamp des Endes der Ausbildung (letztes Semester, letzter Tag) -
     * unabhaengig davon, ob dieser Zeitpunkt in der Vergangenheit oder
     * Zukunft liegt. Null, wenn Beruf oder Jahrgang nicht aufloesbar sind.
     *
     * @param int $lernendeid
     * @return int|null
     */
    public static function get_ausbildungsende(int $lernendeid): ?int {
        $parameter = self::resolve_ausbildungsparameter($lernendeid);
        if ($parameter === null) {
            return null;
        }

        $calculator = new semester_calculator($parameter['startmonat'], $parameter['lehrdauer']);
        [, $ende] = $calculator->semester_grenzen($parameter['jahrgang'], $parameter['lehrdauer']);

        return $ende;
    }

    /**
     * Ist fuer diese Person zum Stichtag eine Aufbewahrungspflicht
     * dokumentiert? Verhindert sowohl die automatische Retention-Loeschung
     * als auch eine vorzeitige Loeschung auf Anfrage.
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return bool
     */
    public static function hat_aufbewahrungspflicht(int $lernendeid, ?int $stichtag = null): bool {
        $stichtag ??= time();

        return aufbewahrung::record_exists_select(
            'lernendeid = :lernendeid AND gueltig_von <= :stichtag1 AND (gueltig_bis IS NULL OR gueltig_bis >= :stichtag2)',
            ['lernendeid' => $lernendeid, 'stichtag1' => $stichtag, 'stichtag2' => $stichtag]
        );
    }

    /**
     * Ist die Ausbildung zum Stichtag bereits abgeschlossen? Die Grenze
     * ist exklusiv - der letzte Tag der Ausbildung selbst gilt noch nicht
     * als "beendet". Nicht aufloesbar (fehlende Profildaten) bedeutet
     * bewusst "nein", nie stillschweigend "ja" - eine automatische
     * Loeschung soll nie auf einem unklaren Stand basieren.
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return bool
     */
    public static function ist_ausbildung_beendet(int $lernendeid, ?int $stichtag = null): bool {
        $stichtag ??= time();
        $ende = self::get_ausbildungsende($lernendeid);

        return $ende !== null && $stichtag > $ende;
    }

    /**
     * Duerfen die personenbezogenen Ausbildungsdaten dieser Person auf
     * Anfrage geloescht werden? Ausbildung muss beendet sein und keine
     * Aufbewahrungspflicht dokumentiert - bewusst ohne Fristelement, das
     * unterscheidet diese Methode von aufbewahrungsfrist_abgelaufen(): eine
     * fruehzeitige Loeschanfrage nach Abschluss wird sofort honoriert,
     * nicht erst nach Ablauf der Frist.
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return bool
     */
    public static function darf_personendaten_geloescht_werden(int $lernendeid, ?int $stichtag = null): bool {
        return self::ist_ausbildung_beendet($lernendeid, $stichtag)
            && !self::hat_aufbewahrungspflicht($lernendeid, $stichtag);
    }

    /**
     * Ist die konfigurierte Aufbewahrungsfrist (Standard 12 Monate) seit
     * Ausbildungsabschluss abgelaufen, und liegt keine Aufbewahrungspflicht
     * vor? Nur diese Methode - nicht darf_personendaten_geloescht_werden()
     * - steuert die automatische Retention-Loeschung der Scheduled Tasks.
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null bedeutet "jetzt"
     * @return bool
     */
    public static function aufbewahrungsfrist_abgelaufen(int $lernendeid, ?int $stichtag = null): bool {
        $stichtag ??= time();
        $ende = self::get_ausbildungsende($lernendeid);
        if ($ende === null) {
            return false;
        }

        $retentionmonate = min(120, max(1, (int) (get_config('local_berufsbildung', 'retention_monate') ?: 12)));
        $ablauf = strtotime("+{$retentionmonate} months", $ende);

        return $stichtag >= $ablauf && !self::hat_aufbewahrungspflicht($lernendeid, $stichtag);
    }

    /**
     * Zuordnung anlegen. Eine bestehende laufende Zuordnung dieser
     * lernenden Person **mit derselben Rolle** - unabhaengig vom bisherigen
     * Berufsbildner/in - wird automatisch zum Vortag von $gueltig_von
     * beendet, nie stillschweigend ueberschrieben. Eine Zuordnung mit einer
     * anderen Rolle bleibt unangetastet: pro (Lernende/r, Rolle) ist immer
     * nur eine laufend, aber verschiedene Rollen duerfen gleichzeitig
     * laufen - z.B. eine/n hauptverantwortliche/n Berufsbildner/in und
     * gleichzeitig eine Stellvertretung.
     *
     * $beruf ist absichtlich kein Pflichtfeld: die/der Lernende traegt den
     * Beruf schon im eigenen Profil (siehe get_ausbildungsstand()). Wird
     * hier nichts uebergeben, wird der aktuelle Profilwert zum Zeitpunkt der
     * Zuordnung uebernommen und in dieser Zeile eingefroren - spaeter im
     * Profil geaenderte Werte aendern die Historie nicht rueckwirkend.
     * Explizit uebergeben werden kann er trotzdem, z.B. beim CSV-Import
     * oder wenn das Profil (noch) nicht gepflegt ist.
     *
     * @param int $berufsbildnerid
     * @param int $lernendeid
     * @param string $beruf Leer = aus dem Profil der/des Lernenden uebernehmen
     * @param int $gueltig_von Timestamp
     * @param string $rolle
     * @param int|null $kohorten_link_id Herkunft, falls durch den Kohorten-Sync erzeugt
     * @return zuordnung
     */
    public static function set_zuordnung(
        int $berufsbildnerid,
        int $lernendeid,
        string $beruf,
        int $gueltig_von,
        string $rolle = 'hauptverantwortlich',
        ?int $kohorten_link_id = null
    ): zuordnung {
        if ($beruf === '') {
            // Bei vorgezogenen Zuordnungen den Beruf am Starttag statt
            // ausschliesslich zum heutigen Zeitpunkt aufloesen.
            $beruf = self::get_ausbildungsstand($lernendeid, $gueltig_von)?->beruf ?? '';
        }

        return (new zuordnung_service())->anlegen(
            $berufsbildnerid,
            $lernendeid,
            $beruf,
            $gueltig_von,
            $rolle,
            $kohorten_link_id
        );
    }

    /**
     * Zuordnung beenden (setzt gueltig_bis, loescht nicht - siehe
     * CLAUDE.md, Architekturregel 2). $gueltig_bis = null macht eine
     * beendete Zuordnung wieder laufend - zum Korrigieren eines falsch
     * gesetzten Enddatums, ohne die Zuordnung neu anlegen zu muessen.
     *
     * @param int $zuordnungid
     * @param int|null $gueltig_bis Timestamp, null = wieder laufend
     */
    public static function beende_zuordnung(int $zuordnungid, ?int $gueltig_bis): void {
        (new zuordnung_service())->beenden($zuordnungid, $gueltig_bis);
    }

    /** Loescht eine nachweislich falsch erfasste Zuordnung endgueltig. */
    public static function loesche_zuordnung(int $zuordnungid): void {
        (new zuordnung_service())->loeschen($zuordnungid);
    }

    /**
     * Loescht eine Kohorten-Verknuepfung ohne erzeugte Zuordnungen.
     *
     * @param int $kohortenlinkid ID der Kohorten-Verknuepfung.
     * @return void
     */
    public static function loesche_kohorten_link(int $kohortenlinkid): void {
        (new \local_berufsbildung\service\kohorten_link_service())->loeschen($kohortenlinkid);
    }

    /**
     * Einsaetze einer lernenden Person aus dem Versetzungsplan, optional
     * auf einen Zeitraum eingeschraenkt. Nur teilweise ueberschneidende
     * Einsaetze zaehlen mit (siehe versetzungsplan\plan_service).
     *
     * @param int $lernendeid
     * @param int|null $von Timestamp, null = kein unterer Rand
     * @param int|null $bis Timestamp, null = kein oberer Rand
     * @return einsatz[]
     */
    public static function get_einsaetze(int $lernendeid, ?int $von = null, ?int $bis = null): array {
        return (new plan_service())->get_einsaetze($lernendeid, $von, $bis);
    }

    /**
     * Vereinigung der Kompetenzen aller betrieblichen Einsaetze im
     * Zeitraum - ein Vorschlag, keine Festlegung (Architekturregel 6).
     *
     * @param int $lernendeid
     * @param int $von Timestamp
     * @param int $bis Timestamp
     * @return int[] Deduplizierte competencyids
     */
    public static function get_ausgebildete_kompetenzen(int $lernendeid, int $von, int $bis): array {
        return (new plan_service())->get_ausgebildete_kompetenzen($lernendeid, $von, $bis);
    }

    /**
     * Idnumbers der Wahlpflicht-HK eines Berufs. Wahlpflicht-HK erscheinen
     * nicht als Lücke, wenn sie im individuellen Ausbildungsweg nicht
     * gewählt wurden.
     *
     * @param string $beruf Beruf-Code
     * @return string[]
     */
    public static function get_wahlpflicht_hk_for_beruf(string $beruf): array {
        $konfiguration = get_config('local_berufsbildung', 'beruf_wahlpflicht_hk');

        return (new wahlpflicht_resolver())->loese_auf($beruf, $konfiguration !== false ? (string) $konfiguration : '');
    }

    /**
     * Der Einsatz, in dem sich die lernende Person gerade befindet.
     *
     * @param int $lernendeid
     * @return einsatz|null
     */
    public static function get_aktueller_einsatz(int $lernendeid): ?einsatz {
        return (new plan_service())->get_aktueller_einsatz($lernendeid);
    }

    /**
     * Kompetenzrahmen (core_competency\competency_framework::idnumber) fuer
     * einen Beruf, oder null wenn dafuer keiner konfiguriert ist. Liefert
     * bewusst nur den Zeiger, nicht den Rahmen selbst - core_competency
     * bleibt die geteilte Grundlage, keine Zwischenschicht (siehe
     * CLAUDE.md "Was nicht in dieses Plugin gehört").
     *
     * @param string $beruf
     * @return string|null
     */
    public static function get_kompetenzrahmen_for_beruf(string $beruf): ?string {
        $konfiguration = get_config('local_berufsbildung', 'beruf_rahmen_mapping');
        $konfiguration = $konfiguration !== false ? (string) $konfiguration : '';

        return (new rahmen_resolver())->loese_auf($beruf, $konfiguration);
    }

    /**
     * Handlungskompetenzen, die bis zum Stichtag in keinem betrieblichen
     * Einsatz vorkamen - ein Vorschlag fuer die Ausbildungsplanung, keine
     * Festlegung (Architekturregel 6).
     *
     * @param int $lernendeid
     * @param int|null $stichtag Timestamp, null = jetzt
     * @return int[] competencyids ohne Abdeckung
     */
    public static function get_luecken(int $lernendeid, ?int $stichtag = null): array {
        return (new luecken_analyse())->get_luecken($lernendeid, $stichtag);
    }

    /**
     * Bezeichnung eines Ausbildungsblocks, z.B. fuer die automatische
     * Abteilungs-Anzeige in aufsetzenden Plugins - siehe
     * versetzungsplan\einsatz und docs/plan.md §5.7.
     *
     * @param int $blockid
     * @return string|null null, wenn der Block nicht existiert
     */
    public static function get_block_name(int $blockid): ?string {
        $block = block::get_record(['id' => $blockid]);

        return $block !== false ? (string) $block->get('name') : null;
    }
}
