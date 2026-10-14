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
 * Hilfe zur Berufsbildung: Anleitungen aller Plugins der Berufsbildung,
 * nach Zielgruppe gegliedert und auf das beschraenkt, was die angemeldete
 * Person oeffnen kann (siehe classes/hilfe/).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\hilfe\sammlung;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/berufsbildung/hilfe.php'));
$PAGE->set_pagelayout('standard');
$titel = get_string('hilfe:titel', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

$gruppen = [];
foreach (sammlung::je_zielgruppe() as $zielgruppe => $abschnitte) {
    $gruppe = [
        'anker' => 'zielgruppe-' . $zielgruppe,
        'titel' => get_string('hilfe:zielgruppe_' . $zielgruppe, 'local_berufsbildung'),
        'abschnitte' => [],
    ];
    foreach ($abschnitte as $abschnitt) {
        $gruppe['abschnitte'][] = [
            'anker' => $abschnitt->anker,
            'titel' => $abschnitt->titel,
            'inhalt' => $OUTPUT->render_from_template($abschnitt->template, $abschnitt->kontext),
        ];
    }
    $gruppen[] = $gruppe;
}

echo $OUTPUT->header();

if (empty($gruppen)) {
    echo $OUTPUT->notification(get_string('hilfe:keine', 'local_berufsbildung'), 'info');
} else {
    echo $OUTPUT->render_from_template('local_berufsbildung/hilfe_seite', [
        'gruppen' => $gruppen,
        'mehrere' => count($gruppen) > 1,
    ]);
}

echo $OUTPUT->footer();
