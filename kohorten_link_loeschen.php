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
 * Confirmation page for deleting a cohort link.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\api;
use local_berufsbildung\persistent\kohorten_link;

admin_externalpage_setup('local_berufsbildung_kohortenlinks');

$id = required_param('id', PARAM_INT);
$link = new kohorten_link($id);
$returnurl = new moodle_url('/local/berufsbildung/kohorten_links.php');
$title = get_string('kohortenlink:loeschen', 'local_berufsbildung');
$PAGE->set_url(new moodle_url('/local/berufsbildung/kohorten_link_loeschen.php', ['id' => $id]));
$PAGE->set_title($title);
$PAGE->set_heading($title);

if (optional_param('bestaetigt', 0, PARAM_BOOL) && confirm_sesskey()) {
    api::loesche_kohorten_link($id);
    redirect(
        $returnurl,
        get_string('kohortenlink:geloescht', 'local_berufsbildung'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('kohortenlink:loeschen_bestaetigung', 'local_berufsbildung', $link->get('id')),
    new moodle_url('/local/berufsbildung/kohorten_link_loeschen.php', [
        'id' => $id,
        'bestaetigt' => 1,
        'sesskey' => sesskey(),
    ]),
    $returnurl
);
echo $OUTPUT->footer();
