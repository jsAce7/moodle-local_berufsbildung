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
 * Uebersicht aller Zuordnungen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core_reportbuilder\system_report_factory;
use local_berufsbildung\reportbuilder\local\systemreports\zuordnungen;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/berufsbildung/zuordnung.php'));
$titel = get_string('zuordnung:uebersicht', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

echo $OUTPUT->header();

if (has_capability('local/berufsbildung:managezuordnung', $context)) {
    echo $OUTPUT->single_button(
        new moodle_url('/local/berufsbildung/zuordnung_anlegen.php'),
        get_string('zuordnung:neue_zuordnung', 'local_berufsbildung')
    );
    echo $OUTPUT->single_button(
        new moodle_url('/local/berufsbildung/zuordnung_import.php'),
        get_string('import:titel', 'local_berufsbildung')
    );
}

$report = system_report_factory::create(zuordnungen::class, $context);
echo $report->output();

echo $OUTPUT->footer();
