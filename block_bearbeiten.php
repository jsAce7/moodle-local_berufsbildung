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
 * Ausbildungsblock anlegen oder bearbeiten.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\form\block_form;
use local_berufsbildung\persistent\block;
use local_berufsbildung\service\block_kopie_service;
use local_berufsbildung\service\block_kurs_service;
use local_berufsbildung\service\rahmen_resolver;

admin_externalpage_setup('local_berufsbildung_bloecke');

$id = optional_param('id', 0, PARAM_INT);
// Kopie eines bestehenden Blocks: ein neuer Block, vorbelegt mit dessen
// Angaben; nach dem Speichern kommen seine Kompetenzen dazu.
$kopie = $id ? 0 : optional_param('kopie', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/berufsbildung/block_bearbeiten.php', $kopie ? ['kopie' => $kopie] : ['id' => $id]));
if ($id) {
    $titel = get_string('block:bearbeiten', 'local_berufsbildung');
} else if ($kopie) {
    $titel = get_string('block:kopieren', 'local_berufsbildung');
} else {
    $titel = get_string('block:anlegen', 'local_berufsbildung');
}
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(
    get_string('bloecke:uebersicht', 'local_berufsbildung'),
    new moodle_url('/local/berufsbildung/bloecke.php')
);
$PAGE->navbar->add($titel);

$returnurl = new moodle_url('/local/berufsbildung/bloecke.php');
$block = $id ? new block($id) : null;
$quelle = $kopie ? new block($kopie) : null;

$konfiguration = get_config('local_berufsbildung', 'beruf_rahmen_mapping');
$berufe = (new rahmen_resolver())->alle_codes($konfiguration !== false ? (string) $konfiguration : '');

$form = new block_form(null, [
    'berufe' => $berufe,
    'kurse' => (new block_kurs_service())->get_kursauswahl(),
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if (!$form->is_submitted() && $block !== null) {
    $form->set_data((object) [
        'id' => $id,
        'nummer' => $block->get('nummer'),
        'name' => $block->get('name'),
        'beruf' => $block->get('beruf'),
        'ist_betrieb' => $block->get('ist_betrieb') ? 1 : 0,
        'aktiv' => $block->get('aktiv') ? 1 : 0,
        'courseid' => $block->get('courseid') ?? 0,
    ]);
}

// Die Nummer bleibt leer: sie muss eindeutig sein, und eine vorbelegte
// wuerde nur die Fehlermeldung "besteht bereits" ausloesen.
if (!$form->is_submitted() && $quelle !== null) {
    $form->set_data((object) [
        'kopie' => $kopie,
        'name' => $quelle->get('name'),
        'beruf' => $quelle->get('beruf'),
        'ist_betrieb' => $quelle->get('ist_betrieb') ? 1 : 0,
        'aktiv' => $quelle->get('aktiv') ? 1 : 0,
        'courseid' => $quelle->get('courseid') ?? 0,
    ]);
}

if ($data = $form->get_data()) {
    if ($block === null) {
        $block = new block(0, (object) [
            'nummer' => $data->nummer,
            'name' => $data->name,
            'beruf' => $data->beruf,
            'ist_betrieb' => (bool) $data->ist_betrieb,
            'aktiv' => (bool) $data->aktiv,
            'courseid' => !empty($data->courseid) ? (int) $data->courseid : null,
        ]);
        $block->create();

        if ($quelle !== null) {
            $ergebnis = (new block_kopie_service())->uebernimm_kompetenzen($quelle, $block);
            $meldung = get_string('block:kopiert', 'local_berufsbildung', (object) $ergebnis);
            if ($ergebnis['verworfen'] > 0) {
                $meldung .= ' ' . get_string('block:kopiert_verworfen', 'local_berufsbildung', $ergebnis['verworfen']);
            }
            redirect($returnurl, $meldung, null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } else {
        $block->set('nummer', $data->nummer);
        $block->set('name', $data->name);
        $block->set('beruf', $data->beruf);
        $block->set('ist_betrieb', (bool) $data->ist_betrieb);
        $block->set('aktiv', (bool) $data->aktiv);
        $block->set('courseid', !empty($data->courseid) ? (int) $data->courseid : null);
        $block->update();
    }

    redirect($returnurl, get_string('block:angelegt', 'local_berufsbildung'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
