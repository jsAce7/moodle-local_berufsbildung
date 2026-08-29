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
 * Kompetenzabdeckung eines Ausbildungsblocks pflegen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\form\block_hk_form;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_hk;

require_login();
require_capability('local/berufsbildung:manageblocks', context_system::instance());

$id = required_param('id', PARAM_INT);
$block = new block($id);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => $id]));
$titel = get_string('blockhk:uebersicht', 'local_berufsbildung', $block->get('nummer'));
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(
    get_string('bloecke:uebersicht', 'local_berufsbildung'),
    new moodle_url('/local/berufsbildung/bloecke.php')
);
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => $id]);

$kompetenzen = [];
if (get_config('core_competency', 'enabled')) {
    foreach (\core_competency\api::list_competencies([], 'shortname', 'ASC', 0, 1000) as $kompetenz) {
        $kompetenzen[(int) $kompetenz->get('id')] = format_string($kompetenz->get('shortname'));
    }
}

$form = new block_hk_form(null, ['blockid' => $id, 'kompetenzen' => $kompetenzen]);

if (!empty($kompetenzen) && $data = $form->get_data()) {
    $verknuepfung = new block_hk(0, (object) [
        'blockid' => $id,
        'competencyid' => (int) $data->competencyid,
        'intensitaet' => $data->intensitaet,
    ]);
    $verknuepfung->create();

    redirect($returnurl, get_string('blockhk:hinzugefuegt', 'local_berufsbildung'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

$abdeckungen = block_hk::get_records(['blockid' => $id], 'id', 'ASC');

if (empty($abdeckungen)) {
    echo $OUTPUT->notification(get_string('blockhk:keine', 'local_berufsbildung'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('blockhk:kompetenz', 'local_berufsbildung'),
        get_string('blockhk:intensitaet', 'local_berufsbildung'),
        '',
    ];

    foreach ($abdeckungen as $abdeckung) {
        $kompetenzid = (int) $abdeckung->get('competencyid');
        $bezeichnung = $kompetenzen[$kompetenzid] ?? "#{$kompetenzid}";

        $intensitaet = $abdeckung->get('intensitaet') === 'teilweise'
            ? get_string('blockhk:intensitaet_teilweise', 'local_berufsbildung')
            : get_string('blockhk:intensitaet_schwerpunkt', 'local_berufsbildung');

        $entfernenurl = new moodle_url('/local/berufsbildung/block_hk_entfernen.php', [
            'id' => $abdeckung->get('id'),
            'blockid' => $id,
            'sesskey' => sesskey(),
        ]);

        $table->data[] = [
            $bezeichnung,
            $intensitaet,
            html_writer::link($entfernenurl, get_string('blockhk:entfernen', 'local_berufsbildung')),
        ];
    }

    echo html_writer::table($table);
}

if (empty($kompetenzen)) {
    echo $OUTPUT->notification(get_string('blockhk:keine_kompetenzen', 'local_berufsbildung'), 'info');
} else {
    $form->display();
}

echo html_writer::link(
    new moodle_url('/local/berufsbildung/bloecke.php'),
    get_string('blockhk:zurueck', 'local_berufsbildung')
);

echo $OUTPUT->footer();
