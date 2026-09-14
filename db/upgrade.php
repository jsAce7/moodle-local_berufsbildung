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
 * Upgrade steps.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_berufsbildung_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082801) {
        // Define table local_berufsbildung_zuordnung to be created.
        $table = new xmldb_table('local_berufsbildung_zuordnung');

        // Adding fields to table local_berufsbildung_zuordnung.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('berufsbildnerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lernendeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('beruf', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rolle', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'hauptverantwortlich');
        $table->add_field('gueltig_von', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('gueltig_bis', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table local_berufsbildung_zuordnung.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key(
            'bb_lernende_gueltigvon',
            XMLDB_KEY_UNIQUE,
            ['berufsbildnerid', 'lernendeid', 'gueltig_von']
        );

        // Adding indexes to table local_berufsbildung_zuordnung.
        $table->add_index('lernendeid_gueltigbis', XMLDB_INDEX_NOTUNIQUE, ['lernendeid', 'gueltig_bis']);
        $table->add_index('berufsbildnerid_gueltigbis', XMLDB_INDEX_NOTUNIQUE, ['berufsbildnerid', 'gueltig_bis']);

        // Conditionally launch create table for local_berufsbildung_zuordnung.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Berufsbildung savepoint reached.
        upgrade_plugin_savepoint(true, 2026082801, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026082802) {
        // Rolle 'berufsbildner' nachziehen fuer Installationen, die vor
        // db/install.php entstanden sind - siehe xmldb_local_berufsbildung_install().
        if (!$DB->record_exists('role', ['shortname' => 'berufsbildner'])) {
            $roleid = create_role(
                get_string('role:berufsbildner', 'local_berufsbildung'),
                'berufsbildner',
                get_string('role:berufsbildner_desc', 'local_berufsbildung')
            );
            set_role_contextlevels($roleid, [CONTEXT_USER]);
        }

        // Berufsbildung savepoint reached.
        upgrade_plugin_savepoint(true, 2026082802, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026082902) {
        // Define table local_berufsbildung_kohorten_link to be created.
        $table = new xmldb_table('local_berufsbildung_kohorten_link');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('berufsbildnerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rolle', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'hauptverantwortlich');
        $table->add_field('beruf', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('aktiv', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('cohort_bb_rolle', XMLDB_KEY_UNIQUE, ['cohortid', 'berufsbildnerid', 'rolle']);

        $table->add_index('aktiv', XMLDB_INDEX_NOTUNIQUE, ['aktiv']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define field kohorten_link_id to be added to local_berufsbildung_zuordnung.
        $table = new xmldb_table('local_berufsbildung_zuordnung');
        $field = new xmldb_field(
            'kohorten_link_id',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            null,
            null,
            null,
            'gueltig_bis'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $key = new xmldb_key('kohortenlinkid', XMLDB_KEY_FOREIGN, ['kohorten_link_id'], 'local_berufsbildung_kohorten_link', ['id']);
        $dbman->add_key($table, $key);

        // Berufsbildung savepoint reached.
        upgrade_plugin_savepoint(true, 2026082902, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026083000) {
        // Define table local_berufsbildung_block to be created.
        $table = new xmldb_table('local_berufsbildung_block');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nummer', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ist_betrieb', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('aktiv', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('nummer', XMLDB_KEY_UNIQUE, ['nummer']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_berufsbildung_block_hk to be created.
        $table = new xmldb_table('local_berufsbildung_block_hk');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('blockid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('competencyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('intensitaet', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'schwerpunkt');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('blockid', XMLDB_KEY_FOREIGN, ['blockid'], 'local_berufsbildung_block', ['id']);
        $table->add_key('block_competency', XMLDB_KEY_UNIQUE, ['blockid', 'competencyid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_berufsbildung_plan_import to be created.
        $table = new xmldb_table('local_berufsbildung_plan_import');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('quelle', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('daten_hash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('zeitpunkt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ausgefuehrt_von', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('zeilen_gelesen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('personen_verarbeitet', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('zeilen_ausserhalb_geltungsbereich', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('einsaetze_erzeugt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'ok');
        $table->add_field('protokoll', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('ausgefuehrtvon', XMLDB_KEY_FOREIGN, ['ausgefuehrt_von'], 'user', ['id']);
        $table->add_index('status_zeitpunkt', XMLDB_INDEX_NOTUNIQUE, ['status', 'zeitpunkt']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_berufsbildung_einsatz to be created.
        $table = new xmldb_table('local_berufsbildung_einsatz');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('blockid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('von', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('bis', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kw_von', XMLDB_TYPE_CHAR, '8', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kw_bis', XMLDB_TYPE_CHAR, '8', null, XMLDB_NOTNULL, null, null);
        $table->add_field('importid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('blockid', XMLDB_KEY_FOREIGN, ['blockid'], 'local_berufsbildung_block', ['id']);
        $table->add_key('importid', XMLDB_KEY_FOREIGN, ['importid'], 'local_berufsbildung_plan_import', ['id']);
        $table->add_index('userid_von', XMLDB_INDEX_NOTUNIQUE, ['userid', 'von']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Berufsbildung savepoint reached.
        upgrade_plugin_savepoint(true, 2026083000, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026083002) {
        // Define table local_berufsbildung_aufbewahrung to be created.
        $table = new xmldb_table('local_berufsbildung_aufbewahrung');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lernendeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('grund', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('gueltig_von', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('gueltig_bis', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_index('lernendeid_gueltigbis', XMLDB_INDEX_NOTUNIQUE, ['lernendeid', 'gueltig_bis']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Berufsbildung savepoint reached.
        upgrade_plugin_savepoint(true, 2026083002, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026083003) {
        // Beruf-Feld verbreitern: reale Profilfelder enthalten oft die
        // ausgeschriebene Berufsbezeichnung (z.B. "Automatiker/in EFZ")
        // statt eines Kurzcodes - 50 Zeichen waren dafuer zu knapp.
        $table = new xmldb_table('local_berufsbildung_zuordnung');
        $field = new xmldb_field('beruf', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        $table = new xmldb_table('local_berufsbildung_kohorten_link');
        $field = new xmldb_field('beruf', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        // Berufsbildung savepoint reached.
        upgrade_plugin_savepoint(true, 2026083003, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026083100) {
        $table = new xmldb_table('local_berufsbildung_zuordnung');
        $oldindex = new xmldb_index('bb_lernende_gueltigvon', XMLDB_INDEX_UNIQUE, ['berufsbildnerid', 'lernendeid', 'gueltig_von']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $newindex = new xmldb_index(
            'bb_lernende_rolle_gueltigvon',
            XMLDB_INDEX_UNIQUE,
            ['berufsbildnerid', 'lernendeid', 'rolle', 'gueltig_von']
        );
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026083100, 'local', 'berufsbildung');
    }

    // Repariert Installationen, die 2026083100 mit der historischen
    // KEY-API ausgefuehrt haben; UNIQUE-Keys sind in Moodle Indizes.
    if ($oldversion < 2026083102) {
        $table = new xmldb_table('local_berufsbildung_zuordnung');
        $oldindex = new xmldb_index('bb_lernende_gueltigvon', XMLDB_INDEX_UNIQUE, ['berufsbildnerid', 'lernendeid', 'gueltig_von']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $newindex = new xmldb_index('bb_lernende_rolle_gueltigvon', XMLDB_INDEX_UNIQUE, [
            'berufsbildnerid', 'lernendeid', 'rolle', 'gueltig_von',
        ]);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026083102, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026083115) {
        // Blocknummern sind berufsspezifisch (ausser bei berufsuebergreifenden
        // Bloecken wie Schule/ueK/Ferien/Militaer, dort bleibt beruf leer) -
        // steuert die Rahmen-Filterung bei der LK-Zuordnung.
        $table = new xmldb_table('local_berufsbildung_block');
        $field = new xmldb_field('beruf', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026083115, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026083116) {
        // local_berufsbildung_block_hk speichert eigentlich die einzelnen
        // Leistungskriterien (LK) je Block - unterste Ebene des Rahmens
        // (Handlungskompetenzbereich -> Handlungskompetenz ->
        // Leistungskriterium), nicht die Handlungskompetenzen selbst. Siehe
        // luecken_analyse.php, wo genau diese Unterscheidung fuer die
        // Lueckenanalyse gebraucht wird. Der Tabellenname war irrefuehrend,
        // deshalb Umbenennung. Guard, weil ein frischer Install ueber
        // db/install.xml die Tabelle bereits korrekt benannt anlegt und
        // diese Tabelle dann gar nie existiert.
        $altetabelle = new xmldb_table('local_berufsbildung_block_hk');
        if ($dbman->table_exists($altetabelle)) {
            $dbman->rename_table($altetabelle, 'local_berufsbildung_block_lk');
        }

        upgrade_plugin_savepoint(true, 2026083116, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026090902) {
        // Systemweite Planungsrolle nachziehen - siehe
        // xmldb_local_berufsbildung_install(). Sie oeffnet die
        // Blockverwaltung fuer Berufsbildner/innen; zugewiesen wird sie vom
        // role_sync_service an jede Person mit laufender Zuordnung.
        if (!$DB->record_exists('role', ['shortname' => 'berufsbildung_planung'])) {
            $roleid = create_role(
                get_string('role:planung', 'local_berufsbildung'),
                'berufsbildung_planung',
                get_string('role:planung_desc', 'local_berufsbildung')
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
            assign_capability(
                'local/berufsbildung:manageblocks',
                CAP_ALLOW,
                $roleid,
                context_system::instance()->id,
                true
            );
        }

        // Berufsbildung savepoint reached.
        upgrade_plugin_savepoint(true, 2026090902, 'local', 'berufsbildung');
    }

    if ($oldversion < 2026091400) {
        // Beide Rollen in die Allow-Matrizen nachtragen - siehe
        // xmldb_local_berufsbildung_install(). create_role() legt dort
        // nichts an, deshalb konnte bisher nur eine Administratorin die
        // Planungsrolle von Hand vergeben, obwohl die README das der
        // Ausbildungsleitung zuschreibt.
        (new \local_berufsbildung\service\role_matrix_service())->synchronisiere();

        // Berufsbildung savepoint reached.
        upgrade_plugin_savepoint(true, 2026091400, 'local', 'berufsbildung');
    }

    return true;
}
