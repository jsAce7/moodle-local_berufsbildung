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
 * Uebersicht der Kohorten-Verknuepfungen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\persistent\kohorten_link;
use local_berufsbildung\service\kohorten_resolver;

admin_externalpage_setup('local_berufsbildung_kohortenlinks');

$PAGE->set_url(new moodle_url('/local/berufsbildung/kohorten_links.php'));
$titel = get_string('kohortenlink:uebersicht', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

echo $OUTPUT->header();

echo html_writer::div($OUTPUT->single_button(
    new moodle_url('/local/berufsbildung/kohorten_link_anlegen.php'),
    get_string('kohortenlink:neu', 'local_berufsbildung')
), 'mb-3');

$links = kohorten_link::get_records([], 'timecreated', 'DESC');

if (empty($links)) {
    echo $OUTPUT->notification(get_string('kohortenlink:keine_links', 'local_berufsbildung'), 'info');
} else {
    $cohortids = array_map(static fn (kohorten_link $link): int => (int) $link->get('cohortid'), $links);
    $kohortennamen = (new kohorten_resolver())->namen(array_unique($cohortids));

    $table = new html_table();
    $table->head = [
        get_string('kohortenlink:kohorte', 'local_berufsbildung'),
        get_string('zuordnung:berufsbildner', 'local_berufsbildung'),
        get_string('zuordnung:rolle', 'local_berufsbildung'),
        get_string('zuordnung:beruf', 'local_berufsbildung'),
        get_string('kohortenlink:aktiv', 'local_berufsbildung'),
        '',
    ];

    foreach ($links as $link) {
        $cohortid = (int) $link->get('cohortid');
        $berufsbildner = core_user::get_user((int) $link->get('berufsbildnerid'));
        $aktiv = (bool) $link->get('aktiv');

        $toggleurl = new moodle_url('/local/berufsbildung/kohorten_link_umschalten.php', [
            'id' => $link->get('id'),
            'sesskey' => sesskey(),
        ]);
        $toggletext = $aktiv
            ? get_string('kohortenlink:deaktivieren', 'local_berufsbildung')
            : get_string('kohortenlink:aktivieren', 'local_berufsbildung');
        $loeschenurl = new moodle_url('/local/berufsbildung/kohorten_link_loeschen.php', [
            'id' => $link->get('id'),
        ]);

        $table->data[] = [
            format_string($kohortennamen[$cohortid] ?? '-'),
            fullname($berufsbildner),
            s($link->get('rolle')),
            $link->get('beruf') !== '' ? s($link->get('beruf')) : '-',
            $aktiv
                ? get_string('kohortenlink:status_aktiv', 'local_berufsbildung')
                : get_string('kohortenlink:status_inaktiv', 'local_berufsbildung'),
            html_writer::link($toggleurl, $toggletext) . ' | ' .
                html_writer::link($loeschenurl, get_string('kohortenlink:loeschen', 'local_berufsbildung')),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
