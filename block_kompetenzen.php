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
 * Kompetenzabdeckung eines Ausbildungsblocks pflegen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_competency\competency;
use core_competency\competency_framework;
use local_berufsbildung\api;
use local_berufsbildung\form\block_lk_form;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;
use local_berufsbildung\service\kompetenz_baum;

admin_externalpage_setup('local_berufsbildung_bloecke');

$id = required_param('id', PARAM_INT);
$block = new block($id);

$bloeckeurl = new moodle_url('/local/berufsbildung/bloecke.php');
$blockurl = new moodle_url('/local/berufsbildung/block_bearbeiten.php', ['id' => $id]);
$returnurl = new moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => $id]);

$PAGE->set_url($returnurl);
$titel = get_string('blocklk:uebersicht', 'local_berufsbildung', $block->get('nummer'));
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(get_string('bloecke:uebersicht', 'local_berufsbildung'), $bloeckeurl);
$PAGE->navbar->add($titel);

// Die LK-Auswahl wird auf den Kompetenzrahmen des Berufs dieses Blocks
// eingeschraenkt - sonst stehen bei mehreren konfigurierten Berufen alle
// Rahmen gemischt in einem Dropdown (siehe luecken_analyse.php fuer das
// gleiche Aufloesungsmuster: Beruf -> Rahmen-idnumber -> Framework-Datensatz).
$beruf = (string) $block->get('beruf');
$framework = null;
$alle = [];

if (get_config('core_competency', 'enabled') && $beruf !== '') {
    $frameworkidnumber = api::get_kompetenzrahmen_for_beruf($beruf);
    $framework = $frameworkidnumber !== null ? competency_framework::get_record(['idnumber' => $frameworkidnumber]) : false;

    if ($framework) {
        $rahmenkompetenzen = competency::get_records(['competencyframeworkid' => (int) $framework->get('id')], 'shortname', 'ASC', 0, 1000);

        foreach ((new kompetenz_baum())->blatt_beschriftungen($rahmenkompetenzen) as $kompetenzid => $beschriftung) {
            $alle[$kompetenzid] = format_string($beschriftung);
        }
    }
}

$zugeordnet = [];
foreach (block_lk::get_records(['blockid' => $id], 'id', 'ASC') as $abdeckung) {
    $zugeordnet[(int) $abdeckung->get('competencyid')] = $abdeckung;
}

// Bereits zugeordnete LK stehen nicht mehr zur Auswahl - besser gar nicht
// anbieten, als hinterher die Duplikat-Fehlermeldung zu zeigen.
$offen = array_diff_key($alle, $zugeordnet);

$form = new block_lk_form(null, ['blockid' => $id, 'kompetenzen' => $offen]);

if (!empty($offen) && $data = $form->get_data()) {
    // Gegen die angebotenen Optionen filtern: was nicht im Rahmen dieses
    // Berufs steht, darf auch ueber ein manipuliertes POST nicht hereinkommen.
    $auswahl = array_intersect(array_map('intval', (array) ($data->competencyids ?? [])), array_keys($offen));

    foreach ($auswahl as $competencyid) {
        (new block_lk(0, (object) [
            'blockid' => $id,
            'competencyid' => $competencyid,
            'intensitaet' => $data->intensitaet,
        ]))->create();
    }

    redirect(
        $returnurl,
        get_string('blocklk:hinzugefuegt', 'local_berufsbildung', count($auswahl)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('blocklk:einleitung', 'local_berufsbildung'));

// Kontextzeile: ohne sie muesste man fuer Beruf und Rahmen dieses Blocks auf
// die Blockliste zurueck - genau die Angaben, die hier ueber die Auswahl
// entscheiden.
$kontext = [];

$blockname = (string) $block->get('name');
if ($blockname !== '') {
    $kontext[] = s($blockname);
}

$kontext[] = get_string('block:beruf', 'local_berufsbildung') . ': '
    . ($beruf !== '' ? s($beruf) : get_string('block:beruf_leer', 'local_berufsbildung'));

if ($framework) {
    $kontext[] = get_string('berufrahmen:rahmen', 'local_berufsbildung') . ': ' . format_string($framework->get('shortname'));
}

echo html_writer::div(
    implode(' · ', $kontext) . ' · ' . html_writer::link($blockurl, get_string('blocklk:block_bearbeiten', 'local_berufsbildung')),
    'text-muted mb-3'
);

if (!$block->get('ist_betrieb')) {
    echo $OUTPUT->notification(get_string('blocklk:kein_betrieb', 'local_berufsbildung'), 'warning');
}

// Anzeigereihenfolge aus $alle uebernehmen (nach Handlungskompetenz
// gruppiert); die Einfuegereihenfolge der Zuordnungen sagt nichts aus.
$zeilen = [];
foreach ($alle as $kompetenzid => $beschriftung) {
    if (isset($zugeordnet[$kompetenzid])) {
        $zeilen[$kompetenzid] = $beschriftung;
    }
}

// LK, die nicht (mehr) im Rahmen dieses Berufs stehen - etwa weil der Beruf
// des Blocks nachtraeglich geaendert wurde. Sie bleiben sichtbar und
// entfernbar, statt stillschweigend zu verschwinden.
foreach ($zugeordnet as $kompetenzid => $abdeckung) {
    if (!isset($zeilen[$kompetenzid])) {
        $zeilen[$kompetenzid] = '#' . $kompetenzid;
    }
}

if (empty($zeilen)) {
    echo $OUTPUT->notification(get_string('blocklk:keine', 'local_berufsbildung'), 'info');
} else {
    echo html_writer::tag('h3', get_string('blocklk:zugeordnete', 'local_berufsbildung', count($zeilen)));

    $table = new html_table();
    $table->head = [
        get_string('blocklk:kompetenz', 'local_berufsbildung'),
        get_string('blocklk:intensitaet', 'local_berufsbildung'),
        get_string('block:aktionen', 'local_berufsbildung'),
    ];

    foreach ($zeilen as $kompetenzid => $beschriftung) {
        $abdeckung = $zugeordnet[$kompetenzid];
        $istteilweise = $abdeckung->get('intensitaet') === 'teilweise';

        $intensitaet = html_writer::span(
            $istteilweise
                ? get_string('blocklk:intensitaet_teilweise', 'local_berufsbildung')
                : get_string('blocklk:intensitaet_schwerpunkt', 'local_berufsbildung'),
            'badge ' . ($istteilweise ? 'bg-light text-dark border' : 'bg-primary text-white')
        );

        $entfernenurl = new moodle_url('/local/berufsbildung/block_lk_entfernen.php', [
            'id' => $abdeckung->get('id'),
            'blockid' => $id,
            'sesskey' => sesskey(),
        ]);

        $table->data[] = [
            $beschriftung,
            $intensitaet,
            html_writer::link($entfernenurl, get_string('blocklk:entfernen', 'local_berufsbildung')),
        ];
    }

    echo html_writer::table($table);
}

if (empty($alle)) {
    // Sackgassen aufloesen: jeder Hinweis verlinkt die Seite, auf der das
    // Fehlende nachgetragen wird. Durchweg 'get' als Methode - der Default
    // 'post' laesst admin/settings.php in die sesskey-Pruefung laufen,
    // statt die Seite nur zu oeffnen.
    if (!get_config('core_competency', 'enabled')) {
        echo $OUTPUT->notification(get_string('blocklk:kompetenzen_aus', 'local_berufsbildung'), 'info');
        echo html_writer::div($OUTPUT->single_button(
            new moodle_url('/admin/settings.php', ['section' => 'optionalsubsystems']),
            get_string('blocklk:kompetenzen_einschalten', 'local_berufsbildung'),
            'get'
        ), 'mb-3');
    } else if ($beruf === '') {
        echo $OUTPUT->notification(get_string('blocklk:kein_beruf', 'local_berufsbildung'), 'info');
        echo html_writer::div($OUTPUT->single_button(
            $blockurl,
            get_string('blocklk:beruf_setzen', 'local_berufsbildung'),
            'get'
        ), 'mb-3');
    } else if (!$framework) {
        echo $OUTPUT->notification(get_string('blocklk:kein_rahmen', 'local_berufsbildung', s($beruf)), 'info');
        echo html_writer::div($OUTPUT->single_button(
            new moodle_url('/local/berufsbildung/beruf_rahmen.php'),
            get_string('berufrahmen:uebersicht', 'local_berufsbildung'),
            'get'
        ), 'mb-3');
    } else {
        echo $OUTPUT->notification(
            get_string('blocklk:rahmen_leer', 'local_berufsbildung', format_string($framework->get('shortname'))),
            'info'
        );
    }
} else if (empty($offen)) {
    echo $OUTPUT->notification(get_string('blocklk:alle_zugeordnet', 'local_berufsbildung'), 'info');
} else {
    echo html_writer::tag('h3', get_string('blocklk:hinzufuegen', 'local_berufsbildung'));
    $form->display();
}

echo html_writer::link($bloeckeurl, get_string('blocklk:zurueck', 'local_berufsbildung'));

echo $OUTPUT->footer();
