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
 * "Meine Lehre": eigener Ausbildungsstand und eigene Taetigkeiten aus allen
 * registrierten Nachweis-Quellen (siehe classes/nachweis/).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\api;
use local_berufsbildung\nachweis\collector;
use local_berufsbildung\output\luecken_liste;
use local_berufsbildung\output\nachweis_liste;

require_login();

$lernendeid = (int) $USER->id;

$PAGE->set_context(context_user::instance($lernendeid));
$PAGE->set_url(new moodle_url('/local/berufsbildung/meine_lehre.php'));
$titel = get_string('nav:meine_lehre', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

echo $OUTPUT->header();

$stand = api::get_ausbildungsstand($lernendeid);

if ($stand === null) {
    echo $OUTPUT->notification(get_string('meine_lehre:kein_ausbildungsstand', 'local_berufsbildung'), 'info');
} else {
    echo html_writer::tag('p', get_string('form:ausbildungsstand', 'local_berufsbildung', (object) [
        'beruf' => $stand->beruf,
        'lehrjahr' => $stand->lehrjahr,
        'semester' => $stand->semester,
    ]));

    if (api::get_kompetenzrahmen_for_beruf($stand->beruf) !== null) {
        echo luecken_liste::render(api::get_luecken($lernendeid));
    }

    $collector = new collector();
    $nachweise = $collector->get_nachweise($lernendeid, $lernendeid, 0, time());
    echo nachweis_liste::render($nachweise);
}

echo $OUTPUT->footer();
