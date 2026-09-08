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
 * Kohorten-Verknuepfung anlegen; synchronisiert sofort einmal, statt auf
 * den naechsten Task-Lauf zu warten.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');

use local_berufsbildung\form\kohorten_link_form;
use local_berufsbildung\persistent\kohorten_link;
use local_berufsbildung\service\kohorten_sync_service;

admin_externalpage_setup('local_berufsbildung_kohortenlinks');

$PAGE->set_url(new moodle_url('/local/berufsbildung/kohorten_link_anlegen.php'));
$titel = get_string('kohortenlink:anlegen', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(
    get_string('kohortenlink:uebersicht', 'local_berufsbildung'),
    new moodle_url('/local/berufsbildung/kohorten_links.php')
);
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/kohorten_links.php');

$cohorts = [];
foreach (cohort_get_all_cohorts(0, 0)['cohorts'] as $cohort) {
    $cohorts[(int) $cohort->id] = format_string($cohort->name);
}

$form = new kohorten_link_form(null, ['cohorts' => $cohorts]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if (!empty($cohorts) && $data = $form->get_data()) {
    $link = new kohorten_link(0, (object) [
        'cohortid' => (int) $data->cohortid,
        'berufsbildnerid' => (int) $data->berufsbildnerid,
        'rolle' => $data->rolle,
        'beruf' => $data->beruf,
        'aktiv' => true,
    ]);
    $link->create();

    $ergebnis = (new kohorten_sync_service())->synchronisiere_link($link);

    redirect(
        $returnurl,
        get_string('kohortenlink:angelegt', 'local_berufsbildung', (object) ['erzeugt' => $ergebnis['erzeugt']]),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

if (empty($cohorts)) {
    echo $OUTPUT->notification(get_string('kohortenlink:keine_kohorten', 'local_berufsbildung'), 'info');
} else {
    $form->display();
}

echo $OUTPUT->footer();
