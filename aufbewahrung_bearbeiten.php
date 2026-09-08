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
 * Aufbewahrungspflicht anlegen oder bearbeiten.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\form\aufbewahrung_form;
use local_berufsbildung\persistent\aufbewahrung;

admin_externalpage_setup('local_berufsbildung_aufbewahrung');

$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/berufsbildung/aufbewahrung_bearbeiten.php', ['id' => $id]));
$titel = $id
    ? get_string('aufbewahrung:bearbeiten', 'local_berufsbildung')
    : get_string('aufbewahrung:neu', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(
    get_string('aufbewahrung:uebersicht', 'local_berufsbildung'),
    new moodle_url('/local/berufsbildung/aufbewahrung.php')
);
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/aufbewahrung.php');
$eintrag = $id ? new aufbewahrung($id) : null;

$form = new aufbewahrung_form();

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if (!$form->is_submitted() && $eintrag !== null) {
    $form->set_data((object) [
        'id' => $id,
        'lernendeid' => $eintrag->get('lernendeid'),
        'grund' => $eintrag->get('grund'),
        'gueltig_von' => $eintrag->get('gueltig_von'),
        // date_selector mit optional => true erwartet 0 statt null fuer
        // "kein Datum" (siehe zuordnung_beenden.php).
        'gueltig_bis' => $eintrag->get('gueltig_bis') ?? 0,
    ]);
}

if ($data = $form->get_data()) {
    $gueltigbis = !empty($data->gueltig_bis) ? (int) $data->gueltig_bis : null;

    if ($eintrag === null) {
        $eintrag = new aufbewahrung(0, (object) [
            'lernendeid' => (int) $data->lernendeid,
            'grund' => $data->grund,
            'gueltig_von' => (int) $data->gueltig_von,
            'gueltig_bis' => $gueltigbis,
        ]);
        $eintrag->create();
    } else {
        $eintrag->set('lernendeid', (int) $data->lernendeid);
        $eintrag->set('grund', $data->grund);
        $eintrag->set('gueltig_von', (int) $data->gueltig_von);
        $eintrag->set('gueltig_bis', $gueltigbis);
        $eintrag->update();
    }

    redirect(
        $returnurl,
        get_string('aufbewahrung:gespeichert', 'local_berufsbildung'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
