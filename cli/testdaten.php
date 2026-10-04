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
 * Testdaten fuer den manuellen Testlauf.
 *
 * Legt an, sofern nicht schon vorhanden:
 * - die Profilfelder 'beruf' und 'jahrgang'
 * - drei Testnutzer: test_bb, test_lernende (Beruf AU_EFZ, Jahrgang 2026),
 *   test_extern (bewusst OHNE Zuordnung)
 * - eine laufende Zuordnung zwischen test_bb und test_lernende
 * - die Rolle 'berufsbildner' im Nutzerkontext von test_lernende, zugewiesen
 *   an test_bb (von Hand, kein Sync in diesem Schnitt)
 *
 * Wiederholt aufrufbar, ohne Duplikate anzulegen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

use local_berufsbildung\api;
use local_berufsbildung\persistent\zuordnung;

/**
 * Legt ein Textfeld im Nutzerprofil an, sofern es nicht bereits existiert.
 *
 * @param string $shortname
 * @param string $name
 * @param string $kategorie
 */
function local_berufsbildung_testdaten_profilfeld(string $shortname, string $name, string $kategorie): void {
    global $DB;

    if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
        return;
    }

    $categoryid = $DB->get_field('user_info_category', 'id', ['name' => $kategorie]);
    if (!$categoryid) {
        $categoryid = $DB->insert_record('user_info_category', (object) [
            'name' => $kategorie,
            'sortorder' => (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}') + 1,
        ]);
    }

    $sortorder = (int) $DB->get_field_sql(
        'SELECT MAX(sortorder) FROM {user_info_field} WHERE categoryid = ?',
        [$categoryid]
    ) + 1;

    $DB->insert_record('user_info_field', (object) [
        'shortname' => $shortname,
        'name' => $name,
        'datatype' => 'text',
        'categoryid' => $categoryid,
        'sortorder' => $sortorder,
        'description' => '',
        'descriptionformat' => 0,
        'required' => 0,
        'locked' => 0,
        'visible' => PROFILE_VISIBLE_ALL,
        'forceunique' => 0,
        'signup' => 0,
        'defaultdata' => '',
        'defaultdataformat' => 0,
        'param1' => 30,
        'param2' => 2048,
        'param3' => '',
        'param4' => '',
        'param5' => '',
    ]);

    cli_writeln("Profilfeld angelegt: $shortname");
}

/**
 * Legt einen Testnutzer an, sofern er nicht bereits existiert.
 *
 * @param string $username
 * @param string $vorname
 * @param string $nachname
 */
function local_berufsbildung_testdaten_nutzer(string $username, string $vorname, string $nachname): stdClass {
    global $CFG, $DB;

    if ($vorhanden = $DB->get_record('user', ['username' => $username])) {
        cli_writeln("vorhanden: $username (id {$vorhanden->id})");
        return $vorhanden;
    }

    $user = new stdClass();
    $user->username = $username;
    $user->password = 'Test1234!';
    $user->firstname = $vorname;
    $user->lastname = $nachname;
    $user->email = $username . '@example.local';
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->lang = 'de';

    $userid = user_create_user($user, true, false);
    cli_writeln("angelegt:  $username (id $userid)");

    return $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
}

cli_heading('local_berufsbildung: Testdaten');

// Die Testkonten haben ein bekanntes Passwort. Auf einem Produktivsystem waeren
// sie eine offene Tuer, deshalb nur mit Debug-Stufe DEVELOPER, wie sie die
// Entwicklungsumgebung setzt.
if (empty($CFG->debugdeveloper)) {
    cli_error('Nur auf Entwicklungssystemen: legt Konten mit bekanntem Passwort an. '
        . 'Abbruch, weil die Debug-Meldungen nicht auf DEVELOPER stehen.');
}

local_berufsbildung_testdaten_profilfeld('beruf', 'Beruf', 'Berufsbildung');
local_berufsbildung_testdaten_profilfeld('jahrgang', 'Jahrgang', 'Berufsbildung');

$bb = local_berufsbildung_testdaten_nutzer('test_bb', 'Beat', 'Berufsbildner');
$lernende = local_berufsbildung_testdaten_nutzer('test_lernende', 'Anna', 'Muster');
$extern = local_berufsbildung_testdaten_nutzer('test_extern', 'Erwin', 'Extern');

profile_save_data((object) [
    'id' => $lernende->id,
    'profile_field_beruf' => 'AU_EFZ',
    'profile_field_jahrgang' => '2026',
]);

if (api::is_zustaendig((int) $bb->id, (int) $lernende->id)) {
    cli_writeln('Zuordnung besteht bereits: test_bb <-> test_lernende');
} else {
    $zuordnung = new zuordnung(0, (object) [
        'berufsbildnerid' => $bb->id,
        'lernendeid' => $lernende->id,
        'beruf' => 'AU_EFZ',
        'gueltig_von' => strtotime('-1 year'),
    ]);
    $zuordnung->create();
    cli_writeln('Zuordnung angelegt: test_bb <-> test_lernende');
}

$roleid = $DB->get_field('role', 'id', ['shortname' => 'berufsbildner'], MUST_EXIST);
$context = context_user::instance($lernende->id);
if (user_has_role_assignment((int) $bb->id, $roleid, $context->id)) {
    cli_writeln('Rolle bereits zugewiesen: berufsbildner an test_bb im Kontext von test_lernende');
} else {
    role_assign($roleid, $bb->id, $context->id);
    cli_writeln('Rolle zugewiesen: berufsbildner an test_bb im Kontext von test_lernende');
}

cli_writeln('');
cli_writeln('Testnutzer, Passwort jeweils Test1234! :');
cli_writeln('  test_bb        Berufsbildner');
cli_writeln('  test_lernende  Lernende Person, Beruf AU_EFZ, Jahrgang 2026');
cli_writeln('  test_extern    ohne Zuordnung - muss das Plugin NICHT sehen');
cli_writeln('');
cli_writeln('Fertig.');
