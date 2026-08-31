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
 * Entfernt eine LK-Zuordnung von einem Ausbildungsblock.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\persistent\block_lk;

require_login();
require_capability('local/berufsbildung:manageblocks', context_system::instance());
require_sesskey();

$id = required_param('id', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/berufsbildung/block_lk_entfernen.php', ['id' => $id, 'blockid' => $blockid]));

$verknuepfung = new block_lk($id);
if ((int) $verknuepfung->get('blockid') === $blockid) {
    $verknuepfung->delete();
}

redirect(
    new moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => $blockid]),
    get_string('blocklk:entfernt', 'local_berufsbildung'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
