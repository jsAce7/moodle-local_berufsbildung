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
 * Leistungskriterien eines Berufs mit den Bloecken, die sie vermitteln.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\output\lk_abdeckung;
use local_berufsbildung\service\lk_abdeckung_service;

admin_externalpage_setup('local_berufsbildung_bloecke');

$beruf = required_param('beruf', PARAM_TEXT);
$nuroffen = optional_param('nuroffen', 0, PARAM_BOOL);

$bloeckeurl = new moodle_url('/local/berufsbildung/bloecke.php');
$seitenurl = new moodle_url('/local/berufsbildung/lk_abdeckung.php', ['beruf' => $beruf]);

$PAGE->set_url(new moodle_url($seitenurl, $nuroffen ? ['nuroffen' => 1] : []));
// Titel und Navigation escapen selbst, die Ueberschrift nicht.
$titel = get_string('lkabdeckung:titel', 'local_berufsbildung', $beruf);
$PAGE->set_title($titel);
$PAGE->set_heading(get_string('lkabdeckung:titel', 'local_berufsbildung', s($beruf)));
$PAGE->navbar->add(get_string('bloecke:uebersicht', 'local_berufsbildung'), $bloeckeurl);
$PAGE->navbar->add($titel);

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('lkabdeckung:einleitung', 'local_berufsbildung'));

$abdeckung = get_config('core_competency', 'enabled') ? (new lk_abdeckung_service())->fuer_beruf($beruf) : null;

if (!get_config('core_competency', 'enabled')) {
    echo $OUTPUT->notification(get_string('blocklk:kompetenzen_aus', 'local_berufsbildung'), 'info');
} else if ($abdeckung === null) {
    echo $OUTPUT->notification(get_string('blocklk:kein_rahmen', 'local_berufsbildung', s($beruf)), 'info');
} else {
    echo lk_abdeckung::render($abdeckung, (bool) $nuroffen, $seitenurl);
}

echo html_writer::div(html_writer::link($bloeckeurl, get_string('blocklk:zurueck', 'local_berufsbildung')), 'mt-3');

echo $OUTPUT->footer();
