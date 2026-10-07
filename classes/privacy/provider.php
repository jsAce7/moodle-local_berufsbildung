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
 * Privacy Subsystem implementation.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_berufsbildung\api;
use local_berufsbildung\service\zuordnung_retention_service;
use local_berufsbildung\service\zuordnung_service;

/**
 * Alle personenbezogenen Daten dieses Plugins haengen an context_user, nie
 * an einem Kurskontext (Architekturregel 1). block/block_lk sind reine
 * Ausbildungsstruktur ohne Bezug zu Lernenden, wie
 * core_competency\competency_framework selbst. Personenbezogen ist dort nur
 * usermodified: wer einen Block oder eine Kompetenzzuordnung zuletzt
 * geaendert hat.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Beschreibt die personenbezogenen Daten dieses Plugins.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_berufsbildung_zuordnung', [
            'berufsbildnerid' => 'privacy:metadata:zuordnung:berufsbildnerid',
            'lernendeid' => 'privacy:metadata:zuordnung:lernendeid',
            'beruf' => 'privacy:metadata:zuordnung:beruf',
            'rolle' => 'privacy:metadata:zuordnung:rolle',
            'gueltig_von' => 'privacy:metadata:zuordnung:gueltig_von',
            'gueltig_bis' => 'privacy:metadata:zuordnung:gueltig_bis',
        ], 'privacy:metadata:zuordnung');

        $collection->add_database_table('local_berufsbildung_kohorten_link', [
            'berufsbildnerid' => 'privacy:metadata:kohortenlink:berufsbildnerid',
            'rolle' => 'privacy:metadata:kohortenlink:rolle',
            'beruf' => 'privacy:metadata:kohortenlink:beruf',
        ], 'privacy:metadata:kohortenlink');

        $collection->add_database_table('local_berufsbildung_einsatz', [
            'userid' => 'privacy:metadata:einsatz:userid',
            'von' => 'privacy:metadata:einsatz:von',
            'bis' => 'privacy:metadata:einsatz:bis',
        ], 'privacy:metadata:einsatz');

        $collection->add_database_table('local_berufsbildung_plan_import', [
            'ausgefuehrt_von' => 'privacy:metadata:planimport:ausgefuehrt_von',
            'quelle' => 'privacy:metadata:planimport:quelle',
            'zeitpunkt' => 'privacy:metadata:planimport:zeitpunkt',
        ], 'privacy:metadata:planimport');

        $collection->add_database_table('local_berufsbildung_aufbewahrung', [
            'lernendeid' => 'privacy:metadata:aufbewahrung:lernendeid',
            'grund' => 'privacy:metadata:aufbewahrung:grund',
            'gueltig_von' => 'privacy:metadata:aufbewahrung:gueltig_von',
            'gueltig_bis' => 'privacy:metadata:aufbewahrung:gueltig_bis',
        ], 'privacy:metadata:aufbewahrung');

        $collection->add_database_table('local_berufsbildung_teilnahmeprofil', [
            'userid' => 'privacy:metadata:teilnahmeprofil:userid',
            'art' => 'privacy:metadata:teilnahmeprofil:art',
        ], 'privacy:metadata:teilnahmeprofil');

        $collection->add_database_table('local_berufsbildung_block', [
            'usermodified' => 'privacy:metadata:block:usermodified',
        ], 'privacy:metadata:block');

        $collection->add_database_table('local_berufsbildung_block_lk', [
            'usermodified' => 'privacy:metadata:block_lk:usermodified',
        ], 'privacy:metadata:block_lk');

        return $collection;
    }

    /**
     * Kontexte, in denen Daten dieser Person liegen.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :userlevel
                   AND ctx.instanceid = :userid
                   AND (
                        EXISTS (SELECT 1 FROM {local_berufsbildung_zuordnung} z
                                 WHERE z.berufsbildnerid = :uid1 OR z.lernendeid = :uid2)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_kohorten_link} k WHERE k.berufsbildnerid = :uid3)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_einsatz} e WHERE e.userid = :uid4)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_plan_import} p WHERE p.ausgefuehrt_von = :uid5)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_aufbewahrung} a WHERE a.lernendeid = :uid6)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_teilnahmeprofil} t WHERE t.userid = :uid7)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_block} b WHERE b.usermodified = :uid8)
                     OR EXISTS (SELECT 1 FROM {local_berufsbildung_block_lk} bl WHERE bl.usermodified = :uid9)
                   )";

        $contextlist->add_from_sql($sql, [
            'userlevel' => CONTEXT_USER,
            'userid' => $userid,
            'uid1' => $userid, 'uid2' => $userid, 'uid3' => $userid, 'uid4' => $userid, 'uid5' => $userid,
            'uid6' => $userid, 'uid7' => $userid, 'uid8' => $userid, 'uid9' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Personen mit Daten in diesem Kontext.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }

        $userid = (int) $context->instanceid;

        $hasdata = $DB->record_exists_select(
            'local_berufsbildung_zuordnung',
            'berufsbildnerid = :uid1 OR lernendeid = :uid2',
            ['uid1' => $userid, 'uid2' => $userid]
        )
            || $DB->record_exists('local_berufsbildung_kohorten_link', ['berufsbildnerid' => $userid])
            || $DB->record_exists('local_berufsbildung_einsatz', ['userid' => $userid])
            || $DB->record_exists('local_berufsbildung_plan_import', ['ausgefuehrt_von' => $userid])
            || $DB->record_exists('local_berufsbildung_aufbewahrung', ['lernendeid' => $userid])
            || $DB->record_exists('local_berufsbildung_teilnahmeprofil', ['userid' => $userid])
            || $DB->record_exists('local_berufsbildung_block', ['usermodified' => $userid])
            || $DB->record_exists('local_berufsbildung_block_lk', ['usermodified' => $userid]);

        if ($hasdata) {
            $userlist->add_user($userid);
        }
    }

    /**
     * Exportiert die Daten der angefragten Person.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        $context = null;
        foreach ($contextlist->get_contexts() as $candidate) {
            if ($candidate instanceof context_user && (int) $candidate->instanceid === $userid) {
                $context = $candidate;
                break;
            }
        }
        if ($context === null) {
            return;
        }

        $alslernende = array_values(
            $DB->get_records('local_berufsbildung_zuordnung', ['lernendeid' => $userid], 'gueltig_von ASC')
        );
        if (!empty($alslernende)) {
            writer::with_context($context)->export_data(
                [get_string('privacy:pfad_zuordnungen_lernende', 'local_berufsbildung')],
                (object) ['zuordnungen' => array_map(static fn ($z): array => [
                    'berufsbildner' => transform::user((int) $z->berufsbildnerid),
                    'beruf' => $z->beruf,
                    'rolle' => $z->rolle,
                    'gueltig_von' => transform::datetime((int) $z->gueltig_von),
                    'gueltig_bis' => $z->gueltig_bis !== null ? transform::datetime((int) $z->gueltig_bis) : null,
                ], $alslernende)]
            );
        }

        $alsberufsbildner = array_values(
            $DB->get_records('local_berufsbildung_zuordnung', ['berufsbildnerid' => $userid], 'gueltig_von ASC')
        );
        if (!empty($alsberufsbildner)) {
            writer::with_context($context)->export_data(
                [get_string('privacy:pfad_zuordnungen_berufsbildner', 'local_berufsbildung')],
                (object) ['zuordnungen' => array_map(static fn ($z): array => [
                    'lernende' => transform::user((int) $z->lernendeid),
                    'beruf' => $z->beruf,
                    'rolle' => $z->rolle,
                    'gueltig_von' => transform::datetime((int) $z->gueltig_von),
                    'gueltig_bis' => $z->gueltig_bis !== null ? transform::datetime((int) $z->gueltig_bis) : null,
                ], $alsberufsbildner)]
            );
        }

        $links = array_values($DB->get_records('local_berufsbildung_kohorten_link', ['berufsbildnerid' => $userid], 'id ASC'));
        if (!empty($links)) {
            writer::with_context($context)->export_data(
                [get_string('privacy:pfad_kohortenlinks', 'local_berufsbildung')],
                (object) ['kohorten_links' => array_map(static fn ($k): array => [
                    'cohortid' => (int) $k->cohortid,
                    'rolle' => $k->rolle,
                    'beruf' => $k->beruf,
                    'aktiv' => (bool) $k->aktiv,
                ], $links)]
            );
        }

        $einsaetze = array_values($DB->get_records('local_berufsbildung_einsatz', ['userid' => $userid], 'von ASC'));
        if (!empty($einsaetze)) {
            writer::with_context($context)->export_data(
                [get_string('privacy:pfad_einsaetze', 'local_berufsbildung')],
                (object) ['einsaetze' => array_map(static fn ($e): array => [
                    'blockid' => (int) $e->blockid,
                    'kw_von' => $e->kw_von,
                    'kw_bis' => $e->kw_bis,
                    'von' => transform::datetime((int) $e->von),
                    'bis' => transform::datetime((int) $e->bis),
                ], $einsaetze)]
            );
        }

        $importe = array_values(
            $DB->get_records('local_berufsbildung_plan_import', ['ausgefuehrt_von' => $userid], 'zeitpunkt ASC')
        );
        if (!empty($importe)) {
            writer::with_context($context)->export_data(
                [get_string('privacy:pfad_planimporte', 'local_berufsbildung')],
                (object) ['importe' => array_map(static fn ($p): array => [
                    'quelle' => $p->quelle,
                    'zeitpunkt' => transform::datetime((int) $p->zeitpunkt),
                    'status' => $p->status,
                    'zeilen_gelesen' => (int) $p->zeilen_gelesen,
                    'personen_verarbeitet' => (int) $p->personen_verarbeitet,
                ], $importe)]
            );
        }

        $aufbewahrungen = array_values(
            $DB->get_records('local_berufsbildung_aufbewahrung', ['lernendeid' => $userid], 'gueltig_von ASC')
        );
        if (!empty($aufbewahrungen)) {
            writer::with_context($context)->export_data(
                [get_string('privacy:pfad_aufbewahrung', 'local_berufsbildung')],
                (object) ['aufbewahrungen' => array_map(static fn ($a): array => [
                    'grund' => $a->grund,
                    'gueltig_von' => transform::datetime((int) $a->gueltig_von),
                    'gueltig_bis' => $a->gueltig_bis !== null ? transform::datetime((int) $a->gueltig_bis) : null,
                ], $aufbewahrungen)]
            );
        }

        $profil = $DB->get_record('local_berufsbildung_teilnahmeprofil', ['userid' => $userid]);
        if ($profil) {
            writer::with_context($context)->export_data(
                [get_string('privacy:pfad_teilnahmeart', 'local_berufsbildung')],
                (object) [
                    'teilnahmeart' => $profil->art,
                    'geaendert_am' => transform::datetime((int) $profil->timemodified),
                ]
            );
        }

        $bloecke = array_values(
            $DB->get_records('local_berufsbildung_block', ['usermodified' => $userid], 'beruf ASC, nummer ASC')
        );
        $kompetenzen = array_values($DB->get_records('local_berufsbildung_block_lk', ['usermodified' => $userid], 'blockid ASC'));
        if (!empty($bloecke) || !empty($kompetenzen)) {
            writer::with_context($context)->export_data(
                [get_string('privacy:pfad_bloecke', 'local_berufsbildung')],
                (object) [
                    'bloecke' => array_map(static fn ($b): array => [
                        'beruf' => $b->beruf,
                        'nummer' => $b->nummer,
                        'name' => $b->name,
                        'geaendert_am' => transform::datetime((int) $b->timemodified),
                    ], $bloecke),
                    'kompetenzzuordnungen' => array_map(static fn ($k): array => [
                        'blockid' => (int) $k->blockid,
                        'competencyid' => (int) $k->competencyid,
                        'geaendert_am' => transform::datetime((int) $k->timemodified),
                    ], $kompetenzen),
                ]
            );
        }
    }

    /**
     * Loescht alle Daten in diesem Kontext.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if (!$context instanceof context_user) {
            return;
        }
        $userid = (int) $context->instanceid;
        static::anonymisiere_bearbeitungsspuren($userid);
        static::zuordnung_loeschen_falls_account_geloescht($userid);
        static::zuordnung_loeschen_falls_ausbildung_beendet($userid);
    }

    /**
     * Loescht die Daten einer einzelnen Person.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && (int) $context->instanceid === $userid) {
                static::anonymisiere_bearbeitungsspuren($userid);
                static::zuordnung_loeschen_falls_account_geloescht($userid);
                static::zuordnung_loeschen_falls_ausbildung_beendet($userid);
                break;
            }
        }
    }

    /**
     * Loescht die Daten mehrerer Personen.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_user) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            $userid = (int) $userid;
            static::anonymisiere_bearbeitungsspuren($userid);
            static::zuordnung_loeschen_falls_account_geloescht($userid);
            static::zuordnung_loeschen_falls_ausbildung_beendet($userid);
        }
    }

    /**
     * Kohorten-Link und Einsatz werden absichtlich NICHT geloescht oder
     * anonymisiert: Architekturregel 5 (Versetzungsplan-Spiegel bleibt
     * unangetastet) verlangt den Erhalt dieser Historie als
     * Ausbildungsnachweis.
     *
     * Zuordnung folgt seit dem Retention-Feature einer anderen Regel als
     * frueher (Architekturregel 2): waehrend laufender Ausbildung oder mit
     * dokumentierter Aufbewahrungspflicht bleibt sie unangetastet - beides
     * pruefen die beiden folgenden Methoden.
     *
     * Nur der ausfuehrende Account eines Versetzungsplan-Imports laesst
     * sich ohne Verlust des Protokollwerts anonymisieren - wer den Import
     * ausgeloest hat, ist fuer die Aussagekraft des Protokolls unerheblich.
     * Dasselbe gilt fuer die letzte Aenderung an Ausbildungsbloecken und
     * deren Kompetenzzuordnungen: die Struktur bleibt, nur die Spur der
     * bearbeitenden Person verschwindet.
     *
     * @param int $userid
     */
    protected static function anonymisiere_bearbeitungsspuren(int $userid): void {
        global $DB;
        $DB->set_field('local_berufsbildung_plan_import', 'ausgefuehrt_von', 0, ['ausgefuehrt_von' => $userid]);
        $DB->set_field('local_berufsbildung_block', 'usermodified', 0, ['usermodified' => $userid]);
        $DB->set_field('local_berufsbildung_block_lk', 'usermodified', 0, ['usermodified' => $userid]);
    }

    /**
     * Ist der Moodle-Account bereits geloescht, verschwinden alle
     * Zuordnungen dieser Person als lernende Person sofort und unbedingt -
     * unabhaengig von Ausbildungsstand oder Aufbewahrungspflicht. Ohne
     * Ausnahme, analog zum bereits bestehenden Verhalten in
     * local_lerndokumentation.
     *
     * Als Berufsbildner/in endet ihre Zustaendigkeit dagegen nur: die
     * Zuordnungen gehoeren zur Ausbildungshistorie anderer Personen
     * (zuordnung_service::beende_fuer_geloeschtes_konto()).
     *
     * @param int $userid
     */
    protected static function zuordnung_loeschen_falls_account_geloescht(int $userid): void {
        global $DB;
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 1])) {
            return;
        }
        (new zuordnung_retention_service())->loesche_fuer_lernende($userid);
        (new zuordnung_service())->beende_fuer_geloeschtes_konto($userid);
    }

    /**
     * Fruehzeitige Loeschung auf Anfrage: ist die Ausbildung bereits
     * abgeschlossen und keine Aufbewahrungspflicht dokumentiert, wird die
     * Anfrage sofort honoriert - ohne auf den Ablauf der
     * Aufbewahrungsfrist zu warten (das uebernimmt stattdessen der
     * automatische Task task\zuordnung_retention).
     *
     * @param int $userid
     */
    protected static function zuordnung_loeschen_falls_ausbildung_beendet(int $userid): void {
        if (!api::darf_personendaten_geloescht_werden($userid)) {
            return;
        }
        (new zuordnung_retention_service())->loesche_fuer_lernende($userid);
    }
}
