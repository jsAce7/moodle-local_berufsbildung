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
 * Uebersicht der dokumentierten Aufbewahrungspflichten.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\persistent\aufbewahrung;

require_login();
require_capability('local/berufsbildung:manageaufbewahrung', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/berufsbildung/aufbewahrung.php'));
$titel = get_string('aufbewahrung:uebersicht', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

echo $OUTPUT->header();

echo html_writer::div($OUTPUT->single_button(
    new moodle_url('/local/berufsbildung/aufbewahrung_bearbeiten.php'),
    get_string('aufbewahrung:neu', 'local_berufsbildung')
), 'mb-3');

$eintraege = aufbewahrung::get_records([], 'gueltig_von', 'DESC');

if (empty($eintraege)) {
    echo $OUTPUT->notification(get_string('aufbewahrung:keine_eintraege', 'local_berufsbildung'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('aufbewahrung:lernende', 'local_berufsbildung'),
        get_string('aufbewahrung:grund', 'local_berufsbildung'),
        get_string('aufbewahrung:gueltig_von', 'local_berufsbildung'),
        get_string('aufbewahrung:gueltig_bis', 'local_berufsbildung'),
        '',
    ];

    foreach ($eintraege as $eintrag) {
        $eintragid = (int) $eintrag->get('id');
        $lernende = core_user::get_user((int) $eintrag->get('lernendeid'));
        $gueltigbis = $eintrag->get('gueltig_bis');

        $bearbeitenurl = new moodle_url('/local/berufsbildung/aufbewahrung_bearbeiten.php', ['id' => $eintragid]);

        $table->data[] = [
            $lernende !== false ? fullname($lernende) : '-',
            shorten_text(s($eintrag->get('grund')), 80),
            userdate((int) $eintrag->get('gueltig_von'), get_string('strftimedate', 'langconfig')),
            $gueltigbis !== null
                ? userdate((int) $gueltigbis, get_string('strftimedate', 'langconfig'))
                : get_string('aufbewahrung:unbefristet', 'local_berufsbildung'),
            html_writer::link($bearbeitenurl, get_string('aufbewahrung:bearbeiten', 'local_berufsbildung')),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
