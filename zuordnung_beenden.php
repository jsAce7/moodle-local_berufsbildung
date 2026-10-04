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
 * Gueltig-bis-Datum einer Zuordnung bearbeiten: setzen (beendet sie) oder
 * leer lassen (macht eine beendete Zuordnung wieder laufend).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\api;
use local_berufsbildung\form\zuordnung_beenden_form;
use local_berufsbildung\persistent\zuordnung;

admin_externalpage_setup('local_berufsbildung_zuordnung');
require_capability('local/berufsbildung:managezuordnung', context_system::instance());

$id = required_param('id', PARAM_INT);
$zuordnung = new zuordnung($id);

$PAGE->set_url(new moodle_url('/local/berufsbildung/zuordnung_beenden.php', ['id' => $id]));
$titel = get_string('zuordnung:beenden', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(get_string('zuordnung:uebersicht', 'local_berufsbildung'), new moodle_url('/local/berufsbildung/zuordnung.php'));
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/zuordnung.php');

$form = new zuordnung_beenden_form(null, ['gueltig_von' => (int) $zuordnung->get('gueltig_von')]);
// date_selector mit optional => true erwartet 0 statt null fuer "kein Datum".
$form->set_data((object) [
    'id' => $id,
    'gueltig_bis' => $zuordnung->get('gueltig_bis') ?? 0,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $gueltigbis = !empty($data->gueltig_bis) ? (int) $data->gueltig_bis : null;
    api::beende_zuordnung((int) $data->id, $gueltigbis);

    $meldung = $gueltigbis !== null
        ? get_string('zuordnung:beendet_erfolgreich', 'local_berufsbildung')
        : get_string('zuordnung:wiedereroeffnet', 'local_berufsbildung');
    redirect($returnurl, $meldung, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

$beschreibung = (object) [
    'lernende' => fullname(core_user::get_user((int) $zuordnung->get('lernendeid'))),
    'berufsbildner' => fullname(core_user::get_user((int) $zuordnung->get('berufsbildnerid'))),
];
echo html_writer::tag('p', get_string('zuordnung:beenden_beschreibung', 'local_berufsbildung', $beschreibung));

$form->display();

echo $OUTPUT->footer();
