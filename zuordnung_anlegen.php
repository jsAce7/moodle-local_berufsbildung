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
 * Zuordnung fuer eine oder mehrere Lernende anlegen, wahlweise ueber eine
 * oder mehrere Kohorten (globale Gruppen) statt Einzelauswahl.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

use local_berufsbildung\api;
use local_berufsbildung\form\zuordnung_form;
use local_berufsbildung\service\kohorten_resolver;

admin_externalpage_setup('local_berufsbildung_zuordnung');

$PAGE->set_url(new moodle_url('/local/berufsbildung/zuordnung_anlegen.php'));
$titel = get_string('zuordnung:anlegen', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(get_string('zuordnung:uebersicht', 'local_berufsbildung'), new moodle_url('/local/berufsbildung/zuordnung.php'));
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/zuordnung.php');

$cohorts = [];
foreach (cohort_get_all_cohorts(0, 0)['cohorts'] as $cohort) {
    $cohorts[(int) $cohort->id] = format_string($cohort->name);
}

$form = new zuordnung_form(null, ['cohorts' => $cohorts]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $lernendeids = !empty($data->lernendeids) ? array_map('intval', $data->lernendeids) : [];
    $cohortids = !empty($data->cohortids) ? array_map('intval', $data->cohortids) : [];

    $mitglieder = (new kohorten_resolver())->mitglieder($cohortids);
    $alle = array_unique(array_merge($lernendeids, $mitglieder));

    // Sich selbst zuzuordnen ist kein sinnvoller Fall - kann nur ueber eine
    // Kohorte passieren, das Formular prueft die Einzelauswahl bereits.
    $alle = array_diff($alle, [(int) $data->berufsbildnerid]);

    foreach ($alle as $lernendeid) {
        api::set_zuordnung(
            (int) $data->berufsbildnerid,
            (int) $lernendeid,
            $data->beruf,
            (int) $data->gueltig_von,
            $data->rolle
        );
    }

    redirect(
        $returnurl,
        get_string('zuordnung:angelegt', 'local_berufsbildung', count($alle)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
