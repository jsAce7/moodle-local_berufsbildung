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
 * Uebersicht der Ausbildungsbloecke.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;
use local_berufsbildung\api;

admin_externalpage_setup('local_berufsbildung_bloecke');

$PAGE->set_url(new moodle_url('/local/berufsbildung/bloecke.php'));
$titel = get_string('bloecke:uebersicht', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('bloecke:einleitung', 'local_berufsbildung'));

echo html_writer::div($OUTPUT->single_button(
    new moodle_url('/local/berufsbildung/block_bearbeiten.php'),
    get_string('bloecke:neu', 'local_berufsbildung'),
    'get'
), 'mb-3');

$bloecke = block::get_records([], 'nummer', 'ASC');

if (empty($bloecke)) {
    echo $OUTPUT->notification(get_string('bloecke:keine_bloecke', 'local_berufsbildung'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('block:nummer', 'local_berufsbildung'),
        get_string('block:name', 'local_berufsbildung'),
        get_string('block:beruf', 'local_berufsbildung'),
        get_string('block:ist_betrieb', 'local_berufsbildung'),
        get_string('block:aktiv', 'local_berufsbildung'),
        get_string('block:kurs', 'local_berufsbildung'),
        get_string('block:kompetenzen', 'local_berufsbildung'),
        get_string('block:aktionen', 'local_berufsbildung'),
    ];

    foreach ($bloecke as $block) {
        $blockid = (int) $block->get('id');
        $anzahlkompetenzen = block_lk::count_records(['blockid' => $blockid]);

        $bearbeitenurl = new moodle_url('/local/berufsbildung/block_bearbeiten.php', ['id' => $blockid]);
        $kompetenzenurl = new moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => $blockid]);

        $beruf = (string) $block->get('beruf');
        $kurs = api::get_block_kurs($blockid);

        // Der Zugang zur Kompetenzzuordnung ist ein beschrifteter Button in
        // der Aktionsspalte, nicht die Zahl in der LK-Spalte: auf eine "0"
        // klickt niemand, und ein neu angelegter Block hat immer eine.
        $aktionen = html_writer::div(
            html_writer::link(
                $kompetenzenurl,
                get_string('blocklk:zuordnen', 'local_berufsbildung'),
                ['class' => 'btn btn-sm btn-secondary']
            ) . html_writer::link(
                $bearbeitenurl,
                get_string('block:bearbeiten', 'local_berufsbildung'),
                ['class' => 'btn btn-sm btn-secondary']
            ),
            'local-berufsbildung-tabelle-aktionen'
        );

        $table->data[] = [
            s($block->get('nummer')),
            s($block->get('name')),
            $beruf !== ''
                ? s($beruf)
                : html_writer::span(get_string('block:beruf_leer_label', 'local_berufsbildung'), 'text-muted'),
            $block->get('ist_betrieb') ? get_string('yes') : get_string('no'),
            $block->get('aktiv') ? get_string('yes') : get_string('no'),
            $kurs !== null
                ? html_writer::link(
                    new moodle_url('/course/view.php', ['id' => (int) $kurs->id]),
                    format_string($kurs->fullname)
                )
                : html_writer::span(get_string('block:kein_kurs', 'local_berufsbildung'), 'text-muted'),
            html_writer::span(
                (string) $anzahlkompetenzen,
                'badge ' . ($anzahlkompetenzen > 0 ? 'bg-primary text-white' : 'bg-light text-dark border')
            ),
            $aktionen,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
