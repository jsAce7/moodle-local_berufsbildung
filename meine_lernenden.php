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
 * Uebersicht "Meine Lernenden": alle aktuell zugeordneten Lernenden mit
 * Ausbildungsstand und ihren Taetigkeiten aus allen registrierten
 * Nachweis-Quellen (siehe classes/nachweis/).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\api;
use local_berufsbildung\nachweis\collector;
use local_berufsbildung\output\nachweis_liste;

require_login();

$berufsbildnerid = (int) $USER->id;

$PAGE->set_context(context_user::instance($berufsbildnerid));
$PAGE->set_url(new moodle_url('/local/berufsbildung/meine_lernenden.php'));
$titel = get_string('nav:meine_lernenden', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

$namen = [];
foreach (api::get_lernende_for($berufsbildnerid) as $lernendeid) {
    $namen[$lernendeid] = fullname(core_user::get_user($lernendeid, '*', MUST_EXIST));
}
core_collator::asort($namen);

echo $OUTPUT->header();

if (empty($namen)) {
    echo $OUTPUT->notification(get_string('meine_lernenden:keine_lernenden', 'local_berufsbildung'), 'info');
}

$collector = new collector();

foreach ($namen as $lernendeid => $name) {
    echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-lernende']);

    $profilurl = new moodle_url('/user/profile.php', ['id' => $lernendeid]);
    echo html_writer::tag('h3', html_writer::link($profilurl, $name));

    $stand = api::get_ausbildungsstand($lernendeid);
    if ($stand !== null) {
        echo html_writer::tag('p', get_string('form:ausbildungsstand', 'local_berufsbildung', (object) [
            'beruf' => $stand->beruf,
            'lehrjahr' => $stand->lehrjahr,
            'semester' => $stand->semester,
        ]));
    }

    $nachweise = $collector->get_nachweise($berufsbildnerid, $lernendeid, 0, time());
    echo nachweis_liste::render($nachweise);

    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();
