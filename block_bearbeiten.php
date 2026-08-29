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
 * Ausbildungsblock anlegen oder bearbeiten.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\form\block_form;
use local_berufsbildung\persistent\block;

require_login();
require_capability('local/berufsbildung:manageblocks', context_system::instance());

$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/berufsbildung/block_bearbeiten.php', ['id' => $id]));
$titel = $id
    ? get_string('block:bearbeiten', 'local_berufsbildung')
    : get_string('block:anlegen', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(
    get_string('bloecke:uebersicht', 'local_berufsbildung'),
    new moodle_url('/local/berufsbildung/bloecke.php')
);
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/bloecke.php');
$block = $id ? new block($id) : null;

$form = new block_form();

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if (!$form->is_submitted() && $block !== null) {
    $form->set_data((object) [
        'id' => $id,
        'nummer' => $block->get('nummer'),
        'name' => $block->get('name'),
        'ist_betrieb' => $block->get('ist_betrieb') ? 1 : 0,
        'aktiv' => $block->get('aktiv') ? 1 : 0,
    ]);
}

if ($data = $form->get_data()) {
    if ($block === null) {
        $block = new block(0, (object) [
            'nummer' => $data->nummer,
            'name' => $data->name,
            'ist_betrieb' => (bool) $data->ist_betrieb,
            'aktiv' => (bool) $data->aktiv,
        ]);
        $block->create();
    } else {
        $block->set('nummer', $data->nummer);
        $block->set('name', $data->name);
        $block->set('ist_betrieb', (bool) $data->ist_betrieb);
        $block->set('aktiv', (bool) $data->aktiv);
        $block->update();
    }

    redirect($returnurl, get_string('block:angelegt', 'local_berufsbildung'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
