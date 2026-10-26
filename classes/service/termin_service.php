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
 * Faelligkeiten als Termine im Moodle-Kalender.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use calendar_event;
use local_berufsbildung\api;
use local_berufsbildung\nachweis\collector;
use local_berufsbildung\nachweis\faelligkeit;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Spiegelt die Faelligkeiten aller Quellen (nachweis\quelle_mit_faelligkeiten)
 * als persoenliche Termine in den Kalender, jeder Person ihre eigenen
 * Aufgaben (faelligkeit::im_kalender_von()): den heute zustaendigen Personen
 * etwa den Bericht ihrer Lernenden, der lernenden Person etwa ihre
 * Rueckmeldung. Anders als "Meine Lernenden" zeigt der Kalender also nicht
 * alles, was ansteht - sonst stuende bei jeder zustaendigen Person jeder
 * Lerndoku-Eintrag jeder lernenden Person. Als Aufgabe ("action event")
 * erscheinen die Termine auch in der Zeitleiste des Dashboards, am Ende des
 * Faelligkeitstags (faelligkeit::kalenderzeitpunkt()).
 *
 * Moodle zeigt nur gespeicherte Termine. Sie sind deshalb eine Kopie, die
 * nachgefuehrt werden muss: stuendlich durch task\sync_termine und sofort,
 * wenn eine Quelle api::termine_nachfuehren() aufruft. Ein Termin gehoert zur
 * Komponente local_berufsbildung, traegt die lernende Person in instance und
 * den Schluessel der Faelligkeit in uuid. Die Zieladresse steht nicht im
 * Termin: termin.php loest sie beim Klick ueber den Collector auf
 * (ziel_fuer()), so fuehrt auch ein Termin, dessen Bericht inzwischen
 * angelegt wurde, auf die richtige Seite.
 */
class termin_service {
    /** @var string Komponente der Termine in der Tabelle event. */
    public const KOMPONENTE = 'local_berufsbildung';

    /** @var collector|null Erst bei Bedarf gebaut, siehe collector() */
    private ?collector $collector;

    /**
     * Konstruktor.
     *
     * @param collector|null $collector Zum Testen von aussen vorgebbar
     */
    public function __construct(?collector $collector = null) {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $this->collector = $collector;
    }

    /**
     * Der Collector, beim ersten Gebrauch gebaut: Er bindet die lib.php
     * aller Plugins ein, und loesche_fuer() braucht ihn nicht.
     *
     * @return collector
     */
    private function collector(): collector {
        return $this->collector ??= new collector();
    }

    /**
     * Ist die Kalenderanbindung eingeschaltet? Standard ja.
     *
     * @return bool
     */
    public static function ist_aktiv(): bool {
        $wert = get_config('local_berufsbildung', 'kalender_termine');

        return $wert === false || $wert === '' ? true : (bool) $wert;
    }

    /**
     * Darf diese Person einen Termin zu dieser lernenden Person sehen? Die
     * eigene Person immer, sonst nur, wer heute zustaendig ist - ein Termin
     * aus einer inzwischen beendeten Zuordnung verschwindet so sofort, nicht
     * erst beim naechsten Abgleich.
     *
     * @param int $userid Besitzer/in des Termins
     * @param int $lernendeid
     * @return bool
     */
    public static function ist_sichtbar(int $userid, int $lernendeid): bool {
        if (!self::ist_aktiv()) {
            return false;
        }

        return $userid === $lernendeid || api::is_zustaendig($userid, $lernendeid);
    }

    /**
     * Wie ist_sichtbar(), fuer einen gespeicherten Termin - fuer den
     * Kalender-Callback. Der Kalender reicht Callbacks ein nachgebautes
     * Ereignis weiter, in dem instance nur bei Aktivitaeten gesetzt ist, bei
     * diesen Terminen also 0; die lernende Person steht nur in der Tabelle.
     *
     * @param int $eventid
     * @return bool
     */
    public static function ist_termin_sichtbar(int $eventid): bool {
        global $DB;

        $termin = $DB->get_record('event', ['id' => $eventid, 'component' => self::KOMPONENTE], 'id, userid, instance');

        return $termin !== false && self::ist_sichtbar((int) $termin->userid, (int) $termin->instance);
    }

    /**
     * Schluessel einer Faelligkeit, eindeutig je lernende Person: Quelle und
     * Bezeichnung, ohne Datum und Adresse. Verschiebt sich die Frist, wird
     * derselbe Termin verschoben statt ersetzt; wird ein Bericht angelegt,
     * aendert sich nur die Adresse, und die loest termin.php beim Klick auf.
     *
     * @param faelligkeit $faelligkeit
     * @return string
     */
    public static function schluessel(faelligkeit $faelligkeit): string {
        return sha1($faelligkeit->quellekey . "\n" . $faelligkeit->bezeichnung);
    }

    /**
     * Gleicht die Termine aller lernenden Personen ab: der heute aktiven und
     * aller, zu denen noch Termine bestehen - sonst blieben die Termine einer
     * Person stehen, deren letzte Zuordnung endete. Ist die Anbindung
     * ausgeschaltet, entfernt der Lauf alle Termine.
     *
     * @return array{angelegt: int, geaendert: int, entfernt: int}
     */
    public function synchronisiere(): array {
        global $DB;

        $lernendeids = array_merge(
            api::get_aktive_lernende(),
            array_map('intval', $DB->get_fieldset_select(
                'event',
                'DISTINCT instance',
                'component = :komponente',
                ['komponente' => self::KOMPONENTE]
            ))
        );

        $ergebnis = ['angelegt' => 0, 'geaendert' => 0, 'entfernt' => 0];
        foreach (array_unique($lernendeids) as $lernendeid) {
            foreach ($this->nachfuehren((int) $lernendeid) as $art => $anzahl) {
                $ergebnis[$art] += $anzahl;
            }
        }

        return $ergebnis;
    }

    /**
     * Gleicht die Termine einer lernenden Person ab - in ihrem eigenen
     * Kalender und in denen der zustaendigen Personen. Legt fehlende an,
     * verschiebt oder benennt geaenderte um und entfernt, was nicht mehr
     * faellig ist oder wem die Zustaendigkeit fehlt.
     *
     * @param int $lernendeid
     * @return array{angelegt: int, geaendert: int, entfernt: int}
     */
    public function nachfuehren(int $lernendeid): array {
        global $DB;

        $soll = self::ist_aktiv() ? $this->soll_termine($lernendeid) : [];
        $bestehend = $DB->get_records(
            'event',
            ['component' => self::KOMPONENTE, 'eventtype' => 'user', 'instance' => $lernendeid],
            'id'
        );

        $ergebnis = ['angelegt' => 0, 'geaendert' => 0, 'entfernt' => 0];
        $vorhanden = [];
        $entfernen = [];
        foreach ($bestehend as $termin) {
            $schluessel = $termin->userid . '|' . $termin->uuid;
            if (!isset($soll[$schluessel]) || isset($vorhanden[$schluessel])) {
                $entfernen[] = (int) $termin->id;
                continue;
            }
            $vorhanden[$schluessel] = true;

            $ziel = $soll[$schluessel];
            if (
                $termin->name !== $ziel->name
                || $termin->description !== $ziel->description
                || (int) $termin->timestart !== $ziel->timestart
            ) {
                calendar_event::load($termin)->update($ziel, false);
                $ergebnis['geaendert']++;
            }
        }

        self::entferne($entfernen);
        $ergebnis['entfernt'] = count($entfernen);

        foreach ($soll as $schluessel => $ziel) {
            if (!isset($vorhanden[$schluessel])) {
                calendar_event::create($ziel, false);
                $ergebnis['angelegt']++;
            }
        }

        return $ergebnis;
    }

    /**
     * Entfernt Termine direkt aus der Tabelle statt ueber
     * calendar_event::delete(): Das schriebe den Titel samt Namen der
     * lernenden Person ins Protokoll (calendar_event_deleted), gerade auch
     * bei einer Loeschung ihrer Daten, und braucht den Benutzerkontext der
     * Besitzerin oder des Besitzers - den gibt es nach einer
     * Account-Loeschung nicht mehr. Dateien haben diese Termine keine.
     *
     * @param int[] $eventids
     */
    private static function entferne(array $eventids): void {
        global $DB;

        if (!empty($eventids)) {
            $DB->delete_records_list('event', 'id', $eventids);
        }
    }

    /**
     * Die Termine, die zu einer lernenden Person bestehen sollen, je
     * "userid|uuid". Ohne zustaendige Person keine - auch nicht fuer die
     * lernende Person selbst: dann laeuft keine Ausbildung, die etwas
     * verlangt.
     *
     * Die Texte entstehen in der Sprache der Website: Der Abgleich laeuft
     * meist im Task, sofort nachgefuehrt aber in der Sitzung derjenigen
     * Person, die gerade etwas erledigt hat. Ihre Sprache darf weder in
     * fremden Kalendern landen noch den Schluessel aendern.
     *
     * @param int $lernendeid
     * @return array<string, stdClass>
     */
    private function soll_termine(int $lernendeid): array {
        global $CFG;

        $lernende = \core_user::get_user($lernendeid);
        if (!$lernende || $lernende->deleted) {
            return [];
        }
        $berufsbildnerids = api::get_berufsbildner_for($lernendeid);
        if (empty($berufsbildnerids)) {
            return [];
        }

        $soll = [];
        $vorher = force_current_language($CFG->lang);
        try {
            // Die Zustaendigkeit ist mit get_berufsbildner_for() geprueft;
            // die Quellen liefern fuer alle Abrufenden dasselbe.
            $faelligkeiten = $this->collector()->get_faelligkeiten($lernendeid, $lernendeid);
            $quellen = $this->collector()->get_quelle_namen();
            $name = fullname($lernende);

            // Frueheste zuerst (Collector): bei gleichem Schluessel bleibt sie.
            foreach ($faelligkeiten as $faelligkeit) {
                $schluessel = self::schluessel($faelligkeit);
                $quelle = $quellen[$faelligkeit->quellekey] ?? '';
                $titel = get_string('termin:titel', 'local_berufsbildung', (object) [
                    'bezeichnung' => $faelligkeit->bezeichnung,
                    'name' => $name,
                ]);
                $zeitpunkt = $faelligkeit->kalenderzeitpunkt();
                if ($faelligkeit->im_kalender_von(false)) {
                    foreach ($berufsbildnerids as $berufsbildnerid) {
                        $soll[$berufsbildnerid . '|' . $schluessel] ??= self::termin(
                            $berufsbildnerid,
                            $lernendeid,
                            $schluessel,
                            $titel,
                            $quelle,
                            $zeitpunkt
                        );
                    }
                }
                if ($faelligkeit->im_kalender_von(true)) {
                    $soll[$lernendeid . '|' . $schluessel] ??= self::termin(
                        $lernendeid,
                        $lernendeid,
                        $schluessel,
                        $faelligkeit->eigene_bezeichnung,
                        $quelle,
                        $zeitpunkt
                    );
                }
            }
        } finally {
            force_current_language($vorher);
        }

        return $soll;
    }

    /**
     * Daten eines Termins fuer calendar_event: persoenlich (ohne Kurs), als
     * Aufgabe, damit er in der Zeitleiste steht.
     *
     * @param int $userid In wessen Kalender
     * @param int $lernendeid
     * @param string $schluessel
     * @param string $titel
     * @param string $quelle Anzeigename der Quelle
     * @param int $datum faelligkeit::kalenderzeitpunkt()
     * @return stdClass
     */
    private static function termin(
        int $userid,
        int $lernendeid,
        string $schluessel,
        string $titel,
        string $quelle,
        int $datum
    ): stdClass {
        return (object) [
            'name' => $titel,
            'description' => $quelle,
            'format' => FORMAT_PLAIN,
            'courseid' => 0,
            'groupid' => 0,
            'categoryid' => 0,
            'userid' => $userid,
            'modulename' => '',
            'instance' => $lernendeid,
            'component' => self::KOMPONENTE,
            'eventtype' => 'user',
            'type' => CALENDAR_EVENT_TYPE_ACTION,
            'uuid' => $schluessel,
            'timestart' => $datum,
            'timesort' => $datum,
            'timeduration' => 0,
            'visible' => 1,
        ];
    }

    /**
     * Wohin ein Termin fuehrt: die Adresse der Faelligkeit, die er heute
     * abbildet. Null, wenn der Termin nicht dieser Person gehoert, sie nicht
     * mehr zustaendig ist oder die Faelligkeit erledigt ist.
     *
     * @param int $eventid
     * @param int $userid Wer den Termin oeffnet
     * @return string|null
     */
    public function ziel_fuer(int $eventid, int $userid): ?string {
        global $CFG, $DB;

        $termin = $DB->get_record('event', ['id' => $eventid, 'component' => self::KOMPONENTE, 'userid' => $userid]);
        if (!$termin) {
            return null;
        }
        $lernendeid = (int) $termin->instance;

        $vorher = force_current_language($CFG->lang);
        try {
            $faelligkeiten = $this->collector()->get_faelligkeiten($userid, $lernendeid);
        } catch (moodle_exception $e) {
            return null;
        } finally {
            force_current_language($vorher);
        }

        foreach ($faelligkeiten as $faelligkeit) {
            if (
                self::schluessel($faelligkeit) === $termin->uuid
                && $faelligkeit->im_kalender_von($userid === $lernendeid)
            ) {
                return $faelligkeit->url;
            }
        }

        return null;
    }

    /**
     * Raeumt nach einem Termin auf, der nicht mehr gilt (ziel_fuer() fand
     * nichts): gleicht die Termine seiner lernenden Person ab und nennt die
     * Uebersicht, auf der die heutigen Faelligkeiten stehen - "Meine Lehre"
     * fuer die eigenen, sonst "Meine Lernenden". Ist der Termin schon weg
     * (ein Abgleich kam dem Klick zuvor), entscheidet, ob die Person heute
     * Lernende betreut.
     *
     * @param int $eventid
     * @param int $userid Wer den Termin oeffnete
     * @return moodle_url
     */
    public function veralteten_termin_bereinigen(int $eventid, int $userid): moodle_url {
        global $DB;

        $lernendeid = $DB->get_field(
            'event',
            'instance',
            ['id' => $eventid, 'component' => self::KOMPONENTE, 'userid' => $userid]
        );
        if ($lernendeid !== false) {
            $this->nachfuehren((int) $lernendeid);
            $eigene = (int) $lernendeid === $userid;
        } else {
            $eigene = empty(api::get_lernende_for($userid));
        }

        return $eigene
            ? new moodle_url('/local/berufsbildung/meine_lehre.php')
            : new moodle_url('/local/berufsbildung/meine_lernenden.php');
    }

    /**
     * Entfernt alle Termine zu einer Person: die zu ihren Faelligkeiten in
     * fremden Kalendern und die in ihrem eigenen Kalender. Fuer die Loeschung
     * ihrer Daten (zuordnung_retention_service::loesche_fuer_lernende()).
     *
     * @param int $userid
     * @return int Anzahl entfernter Termine
     */
    public function loesche_fuer(int $userid): int {
        global $DB;

        $eventids = $DB->get_fieldset_select(
            'event',
            'id',
            'component = :komponente AND (instance = :lernendeid OR userid = :userid)',
            ['komponente' => self::KOMPONENTE, 'lernendeid' => $userid, 'userid' => $userid]
        );
        self::entferne($eventids);

        return count($eventids);
    }
}
