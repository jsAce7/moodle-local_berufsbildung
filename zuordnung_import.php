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
 * Zuordnungen aus CSV importieren - Schritt 1: Datei hochladen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_berufsbildung\form\zuordnung_import_form;

admin_externalpage_setup('local_berufsbildung_zuordnung');

$PAGE->set_url(new moodle_url('/local/berufsbildung/zuordnung_import.php'));
$titel = get_string('import:titel', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(get_string('zuordnung:uebersicht', 'local_berufsbildung'), new moodle_url('/local/berufsbildung/zuordnung.php'));
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/zuordnung.php');

$form = new zuordnung_import_form();

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $content = $form->get_file_content('csvfile');

    $iid = csv_import_reader::get_new_iid('local_berufsbildungzuordnung');
    $cir = new csv_import_reader($iid, 'local_berufsbildungzuordnung');
    $anzahl = $cir->load_csv_content($content, $data->encoding, $data->delimiter);
    $fehler = $cir->get_error();
    $cir->close();

    if ($fehler !== null) {
        redirect(
            $PAGE->url,
            get_string('import:fehler_datei', 'local_berufsbildung', $fehler),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    redirect(new moodle_url('/local/berufsbildung/zuordnung_import_vorschau.php', ['iid' => $iid]));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
