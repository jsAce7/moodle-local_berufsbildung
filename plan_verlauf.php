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
 * Verlauf der Versetzungsplan-Importe: die letzten Laeufe und je Lauf, bei
 * wem sich welche Einsaetze geaendert haben (siehe
 * versetzungsplan\import_service::aenderungen_fuer_import()).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\output\hilfe_link;
use local_berufsbildung\output\plan_aenderungen;
use local_berufsbildung\persistent\einsatz_aenderung;
use local_berufsbildung\persistent\plan_import;
use local_berufsbildung\versetzungsplan\import_service;

admin_externalpage_setup('local_berufsbildung_planverlauf');

$id = optional_param('id', 0, PARAM_INT);

$url = new moodle_url('/local/berufsbildung/plan_verlauf.php');
$PAGE->set_url($id > 0 ? new moodle_url($url, ['id' => $id]) : $url);
$titel = get_string('planverlauf:titel', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
hilfe_link::fuer_seite('berufsbildung-planimport');

// Wie viele Laeufe die Uebersicht zeigt.
$anzahl = 50;

$statusname = static function (string $status): string {
    $bekannt = ['ok', 'mit_warnungen', 'fehlgeschlagen', 'zur_freigabe', 'freigegeben', 'verworfen', 'ueberholt'];
    // Den Status 'abgewiesen' gibt es nur noch in Altdaten.
    return in_array($status, $bekannt, true)
        ? get_string('planimport:status_' . $status, 'local_berufsbildung')
        : s($status);
};

echo $OUTPUT->header();

if ($id > 0) {
    $import = new plan_import($id);

    echo $OUTPUT->heading(get_string(
        'planfreigabe:lieferung_vom',
        'local_berufsbildung',
        userdate((int) $import->get('zeitpunkt'))
    ), 3);

    $table = new html_table();
    $table->data = [
        [get_string('planfreigabe:quelle', 'local_berufsbildung'), s($import->get('quelle'))],
        [get_string('planimport:status', 'local_berufsbildung'), $statusname((string) $import->get('status'))],
        [get_string('planimport:zeilen_gelesen', 'local_berufsbildung'), (int) $import->get('zeilen_gelesen')],
        [get_string('planimport:personen_verarbeitet', 'local_berufsbildung'), (int) $import->get('personen_verarbeitet')],
        [
            get_string('planimport:ausserhalb_geltungsbereich', 'local_berufsbildung'),
            (int) $import->get('zeilen_ausserhalb_geltungsbereich'),
        ],
        [get_string('planimport:einsaetze_erzeugt', 'local_berufsbildung'), (int) $import->get('einsaetze_erzeugt')],
    ];
    echo html_writer::table($table);

    $protokoll = array_values(array_filter(explode("\n", (string) $import->get('protokoll'))));
    if (!empty($protokoll)) {
        echo $OUTPUT->heading(get_string('planimport:protokoll', 'local_berufsbildung'), 4);
        echo html_writer::alist(array_map('s', $protokoll));
    }

    $aenderungen = (new import_service())->aenderungen_fuer_import($id);
    if (empty($aenderungen)) {
        echo $OUTPUT->notification(get_string('planverlauf:keine_aenderungen', 'local_berufsbildung'), 'info');
    } else {
        echo $OUTPUT->heading(get_string(
            'planfreigabe:aenderungen',
            'local_berufsbildung',
            count(array_unique(array_column($aenderungen, 'userid')))
        ), 4);
        echo plan_aenderungen::render($aenderungen);
    }

    echo html_writer::tag('p', html_writer::link($url, get_string('planverlauf:zurueck', 'local_berufsbildung')));
    echo $OUTPUT->footer();
    die();
}

// Die ID entscheidet, wenn zwei Laeufe in dieselbe Sekunde fallen - etwa
// eine Freigabe und der Lauf, den sie ausloest.
$importe = plan_import::get_records_select('', [], 'zeitpunkt DESC, id DESC', '*', 0, $anzahl);
if (empty($importe)) {
    echo $OUTPUT->notification(get_string('planverlauf:keine', 'local_berufsbildung'), 'info');
    echo $OUTPUT->footer();
    die();
}

echo html_writer::tag('p', get_string('planverlauf:beschreibung', 'local_berufsbildung', $anzahl));

$table = new html_table();
$table->head = [
    get_string('planverlauf:zeitpunkt', 'local_berufsbildung'),
    get_string('planfreigabe:quelle', 'local_berufsbildung'),
    get_string('planimport:status', 'local_berufsbildung'),
    get_string('planimport:personen_verarbeitet', 'local_berufsbildung'),
    get_string('planimport:einsaetze_erzeugt', 'local_berufsbildung'),
    get_string('planverlauf:aenderungen', 'local_berufsbildung'),
];
foreach ($importe as $import) {
    $importid = (int) $import->get('id');
    $table->data[] = [
        html_writer::link(new moodle_url($url, ['id' => $importid]), userdate((int) $import->get('zeitpunkt'))),
        s($import->get('quelle')),
        $statusname((string) $import->get('status')),
        (int) $import->get('personen_verarbeitet'),
        (int) $import->get('einsaetze_erzeugt'),
        einsatz_aenderung::count_records(['importid' => $importid]),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
