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
 * Zuordnungen aus CSV importieren - Schritt 2: Vorschau, dann auf
 * Bestaetigung tatsaechlich anlegen. Zeilen mit Fehlern werden einzeln
 * gemeldet und uebersprungen, statt den ganzen Import abzubrechen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_berufsbildung\import\zuordnung_csv_importer;

admin_externalpage_setup('local_berufsbildung_zuordnung');

$iid = required_param('iid', PARAM_INT);
$bestaetigt = optional_param('bestaetigt', 0, PARAM_BOOL);

$PAGE->set_url(new moodle_url('/local/berufsbildung/zuordnung_import_vorschau.php', ['iid' => $iid]));
$titel = get_string('import:vorschau', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(get_string('zuordnung:uebersicht', 'local_berufsbildung'), new moodle_url('/local/berufsbildung/zuordnung.php'));
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/zuordnung.php');
$uploadurl = new moodle_url('/local/berufsbildung/zuordnung_import.php');

$cir = new csv_import_reader($iid, 'local_berufsbildungzuordnung');
$columns = $cir->get_columns();

if (empty($columns)) {
    redirect($uploadurl, get_string('import:fehler_abgelaufen', 'local_berufsbildung'), null, \core\output\notification::NOTIFY_ERROR);
}

$spaltenindex = [];
foreach ($columns as $i => $name) {
    $spaltenindex[core_text::strtolower(trim($name))] = $i;
}

$fehlendespalten = array_diff(zuordnung_csv_importer::ERWARTETE_SPALTEN, array_keys($spaltenindex));
if (!empty($fehlendespalten)) {
    redirect(
        $uploadurl,
        get_string('import:fehlende_spalten', 'local_berufsbildung', implode(', ', $fehlendespalten)),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$importer = new zuordnung_csv_importer();

if ($bestaetigt) {
    require_sesskey();

    $cir->init();
    $angelegt = 0;
    $fehlerzeilen = [];
    $zeilennummer = 0;

    while ($rohzeile = $cir->next()) {
        $zeilennummer++;
        $zeile = $importer->zeile_zuordnen($rohzeile, $spaltenindex);
        $ergebnis = $importer->verarbeite_zeile($zeile, true);

        if ($ergebnis['fehler'] !== null) {
            $fehlerzeilen[] = ['zeile' => $zeilennummer] + $ergebnis;
        } else {
            $angelegt++;
        }
    }
    $cir->close();
    $cir->cleanup();

    echo $OUTPUT->header();

    echo $OUTPUT->notification(
        get_string('import:abgeschlossen', 'local_berufsbildung', (object) [
            'angelegt' => $angelegt,
            'fehler' => count($fehlerzeilen),
        ]),
        empty($fehlerzeilen) ? 'success' : 'warning'
    );

    if (!empty($fehlerzeilen)) {
        $table = new html_table();
        $table->head = [
            get_string('import:zeile', 'local_berufsbildung'),
            get_string('zuordnung:berufsbildner', 'local_berufsbildung'),
            get_string('zuordnung:lernende', 'local_berufsbildung'),
            get_string('import:fehler', 'local_berufsbildung'),
        ];
        foreach ($fehlerzeilen as $zeile) {
            $table->data[] = [
                $zeile['zeile'],
                s($zeile['berufsbildner']),
                s($zeile['lernende']),
                s($zeile['fehler']),
            ];
        }
        echo html_writer::table($table);
    }

    echo html_writer::link($returnurl, get_string('zuordnung:uebersicht', 'local_berufsbildung'));

    echo $OUTPUT->footer();
} else {
    $cir->init();
    $vorschauzeilen = [];
    $zeilennummer = 0;
    $fehleranzahl = 0;

    while ($rohzeile = $cir->next()) {
        $zeilennummer++;
        $zeile = $importer->zeile_zuordnen($rohzeile, $spaltenindex);
        $ergebnis = $importer->verarbeite_zeile($zeile, false);

        if ($ergebnis['fehler'] !== null) {
            $fehleranzahl++;
        }

        $vorschauzeilen[] = ['zeile' => $zeilennummer] + $ergebnis;
    }
    $cir->close();

    echo $OUTPUT->header();

    if (empty($vorschauzeilen)) {
        echo $OUTPUT->notification(get_string('import:keine_zeilen', 'local_berufsbildung'), 'warning');
        echo html_writer::link($uploadurl, get_string('import:titel', 'local_berufsbildung'));
    } else {
        if ($fehleranzahl > 0) {
            echo $OUTPUT->notification(
                get_string('import:vorschau_fehlerhinweis', 'local_berufsbildung', $fehleranzahl),
                'warning'
            );
        }

        $table = new html_table();
        $table->head = [
            get_string('import:zeile', 'local_berufsbildung'),
            get_string('zuordnung:berufsbildner', 'local_berufsbildung'),
            get_string('zuordnung:lernende', 'local_berufsbildung'),
            get_string('zuordnung:beruf', 'local_berufsbildung'),
            get_string('zuordnung:gueltig_von', 'local_berufsbildung'),
            get_string('import:status', 'local_berufsbildung'),
        ];
        foreach ($vorschauzeilen as $zeile) {
            $status = $zeile['fehler'] !== null
                ? html_writer::span(s($zeile['fehler']), 'text-danger')
                : html_writer::span(get_string('import:zeile_ok', 'local_berufsbildung'), 'text-success');

            $table->data[] = [
                $zeile['zeile'],
                s($zeile['berufsbildner']),
                s($zeile['lernende']),
                s($zeile['beruf']),
                s($zeile['gueltig_von']),
                $status,
            ];
        }
        echo html_writer::table($table);

        $bestaetigenurl = new moodle_url('/local/berufsbildung/zuordnung_import_vorschau.php', [
            'iid' => $iid,
            'bestaetigt' => 1,
            'sesskey' => sesskey(),
        ]);
        echo html_writer::div(
            $OUTPUT->single_button($bestaetigenurl, get_string('import:bestaetigen', 'local_berufsbildung'))
            . html_writer::link($uploadurl, get_string('import:abbrechen', 'local_berufsbildung'), ['class' => 'ml-2']),
            'mt-3'
        );
    }

    echo $OUTPUT->footer();
}
