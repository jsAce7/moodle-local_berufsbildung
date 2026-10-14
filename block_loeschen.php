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
 * Loescht einen Ausbildungsblock nach Rueckfrage.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\persistent\block;
use local_berufsbildung\service\block_loesch_service;

admin_externalpage_setup('local_berufsbildung_bloecke');

$id = required_param('id', PARAM_INT);
$bestaetigt = optional_param('bestaetigt', 0, PARAM_BOOL);

$PAGE->set_url(new moodle_url('/local/berufsbildung/block_loeschen.php', ['id' => $id]));
$titel = get_string('block:loeschen', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(
    get_string('bloecke:uebersicht', 'local_berufsbildung'),
    new moodle_url('/local/berufsbildung/bloecke.php')
);
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/bloecke.php');
$block = block::get_record(['id' => $id]);
if (!$block) {
    // Schon geloescht, etwa durch einen zweiten Klick.
    redirect($returnurl);
}

$service = new block_loesch_service();
if (!$service->kann_loeschen($block)) {
    redirect(
        $returnurl,
        get_string('block:loeschen_begonnen', 'local_berufsbildung'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$a = (object) ([
    'nummer' => s($block->get('nummer')),
    'name' => s($block->get('name')),
] + $service->umfang($block));

if ($bestaetigt) {
    require_sesskey();
    $geloescht = $service->loesche($block);
    redirect(
        $returnurl,
        get_string('block:geloescht', 'local_berufsbildung', (object) (['nummer' => $a->nummer] + $geloescht)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Geplante Einsaetze gehen mit - das soll vor dem Klick dastehen, nicht erst
// in der Erfolgsmeldung.
$frage = get_string('block:loeschen_bestaetigen', 'local_berufsbildung', $a);
if ($a->einsaetze > 0) {
    $frage .= ' ' . get_string('block:loeschen_bestaetigen_einsaetze', 'local_berufsbildung', $a);
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    $frage,
    new single_button(
        new moodle_url('/local/berufsbildung/block_loeschen.php', ['id' => $id, 'bestaetigt' => 1, 'sesskey' => sesskey()]),
        get_string('delete'),
        'post'
    ),
    new single_button($returnurl, get_string('cancel'), 'get')
);
echo $OUTPUT->footer();
