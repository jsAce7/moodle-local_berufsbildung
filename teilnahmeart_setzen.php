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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Ändert die Teilnahmeart einer lernenden Person, nach Bestätigung.
 *
 * Erreichbar als Zeilenaktion der Zuordnungsübersicht. Die Bestätigung
 * steht davor, weil die Umstellung auf "extern" Ausbildungsstand,
 * Kompetenzraster und Lerndokumentation der Person ausblendet.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\api;

admin_externalpage_setup('local_berufsbildung_zuordnung');
require_capability('local/berufsbildung:managezuordnung', context_system::instance());

$userid = required_param('userid', PARAM_INT);
$art = required_param('art', PARAM_ALPHANUMEXT);
if (!in_array($art, [api::TEILNAHMEART_LEHRE, api::TEILNAHMEART_UEK_EXTERN], true)) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$nutzer = core_user::get_user($userid, '*', MUST_EXIST);

$returnurl = new moodle_url('/local/berufsbildung/zuordnung.php');
$titel = get_string(
    $art === api::TEILNAHMEART_UEK_EXTERN ? 'teilnahmeart:uek_extern_setzen' : 'teilnahmeart:lehre_setzen',
    'local_berufsbildung'
);
$PAGE->set_url(new moodle_url('/local/berufsbildung/teilnahmeart_setzen.php', ['userid' => $userid, 'art' => $art]));
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

if (optional_param('bestaetigt', 0, PARAM_BOOL) && confirm_sesskey()) {
    api::set_teilnahmeart($userid, $art);
    redirect(
        $returnurl,
        get_string('teilnahmeart:gespeichert', 'local_berufsbildung'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string(
        $art === api::TEILNAHMEART_UEK_EXTERN ? 'teilnahmeart:uek_extern_bestaetigung' : 'teilnahmeart:lehre_bestaetigung',
        'local_berufsbildung',
        fullname($nutzer)
    ),
    new moodle_url('/local/berufsbildung/teilnahmeart_setzen.php', [
        'userid' => $userid,
        'art' => $art,
        'bestaetigt' => 1,
        'sesskey' => sesskey(),
    ]),
    $returnurl
);
echo $OUTPUT->footer();
