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
 * Manueller Versetzungsplan-Upload - Rueckfallweg zum Webservice, mit
 * derselben Verarbeitungslogik (siehe docs/plan.md §5.4).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\form\plan_import_form;
use local_berufsbildung\persistent\plan_import;
use local_berufsbildung\versetzungsplan\import_service;

admin_externalpage_setup('local_berufsbildung_importplan');

$PAGE->set_url(new moodle_url('/local/berufsbildung/import_plan.php'));
$titel = get_string('planimport:titel', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

$form = new plan_import_form();
$ergebnis = null;

if ($data = $form->get_data()) {
    $inhalt = $form->get_file_content('csvdatei');
    $ergebnis = (new import_service())->verarbeiten(
        (string) $inhalt,
        'upload',
        (int) $USER->id,
        (bool) $data->testlauf,
        (bool) $data->rueckgang_bestaetigt
    );
}

echo $OUTPUT->header();

$letzter = plan_import::get_records_select("status IN ('ok', 'mit_warnungen')", [], 'zeitpunkt DESC', '*', 0, 1);
$letzter = !empty($letzter) ? reset($letzter) : null;

echo html_writer::start_tag('p');
echo get_string('planimport:letzter_import', 'local_berufsbildung') . ': ';
echo $letzter !== null ? userdate((int) $letzter->get('zeitpunkt')) : get_string('planimport:noch_nie', 'local_berufsbildung');
echo html_writer::end_tag('p');

if ($letzter !== null) {
    $alterungtage = get_config('local_berufsbildung', 'versetzungsplan_alterung_tage');
    $alterungtage = $alterungtage !== false ? (int) $alterungtage : 10;

    if ($letzter->get('zeitpunkt') < time() - ($alterungtage * DAYSECS)) {
        echo $OUTPUT->notification(
            get_string('planimport:alterung_hinweis', 'local_berufsbildung', $alterungtage),
            'warning'
        );
    }
}

if ($ergebnis !== null) {
    $statusstrings = [
        'ok' => 'planimport:status_ok',
        'mit_warnungen' => 'planimport:status_mit_warnungen',
        'abgewiesen' => 'planimport:status_abgewiesen',
        'fehlgeschlagen' => 'planimport:status_fehlgeschlagen',
    ];

    echo $OUTPUT->heading(get_string('planimport:ergebnis', 'local_berufsbildung'), 3);

    if ($ergebnis['unveraendert']) {
        echo $OUTPUT->notification(get_string('planimport:unveraendert', 'local_berufsbildung'), 'info');
    } else {
        $notiftyp = $ergebnis['status'] === 'ok' ? 'success' : ($ergebnis['status'] === 'mit_warnungen' ? 'warning' : 'error');
        echo $OUTPUT->notification(
            get_string('planimport:status', 'local_berufsbildung') . ': '
                . get_string($statusstrings[$ergebnis['status']], 'local_berufsbildung'),
            $notiftyp
        );

        if ($ergebnis['status'] === 'abgewiesen') {
            echo $OUTPUT->notification(get_string('planimport:abgewiesen_hinweis', 'local_berufsbildung'), 'info');
        }

        $table = new html_table();
        $table->data = [
            [get_string('planimport:zeilen_gelesen', 'local_berufsbildung'), $ergebnis['zeilen_gelesen']],
            [get_string('planimport:personen_verarbeitet', 'local_berufsbildung'), $ergebnis['personen_verarbeitet']],
            [get_string('planimport:ausserhalb_geltungsbereich', 'local_berufsbildung'), $ergebnis['zeilen_ausserhalb_geltungsbereich']],
            [get_string('planimport:einsaetze_erzeugt', 'local_berufsbildung'), $ergebnis['einsaetze_erzeugt']],
        ];
        echo html_writer::table($table);

        if (!empty($ergebnis['protokoll'])) {
            echo $OUTPUT->heading(get_string('planimport:protokoll', 'local_berufsbildung'), 4);
            echo html_writer::alist(array_map('s', $ergebnis['protokoll']));
        }
    }
}

$form->display();

echo $OUTPUT->footer();
