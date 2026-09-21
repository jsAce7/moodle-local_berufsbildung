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
use local_berufsbildung\output\kompetenz_auswahl;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;
use local_berufsbildung\service\kompetenz_baum;

admin_externalpage_setup('local_berufsbildung_bloecke');

$id = required_param('id', PARAM_INT);
$suchbegriff = optional_param('suche', '', PARAM_TEXT);
$block = new block($id);

$bloeckeurl = new moodle_url('/local/berufsbildung/bloecke.php');
$blockurl = new moodle_url('/local/berufsbildung/block_bearbeiten.php', ['id' => $id]);

// Der Filter bleibt in der Rueckkehr-Adresse stehen: nach dem Speichern
// steht man dort weiter, wo man gesucht hat, statt wieder vor dem ganzen
// Rahmen.
$returnurl = new moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => $id]);
if (trim($suchbegriff) !== '') {
    $returnurl->param('suche', $suchbegriff);
}

$PAGE->set_url($returnurl);
$titel = get_string('blocklk:uebersicht', 'local_berufsbildung', $block->get('nummer'));
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
$PAGE->navbar->add(get_string('bloecke:uebersicht', 'local_berufsbildung'), $bloeckeurl);
$PAGE->navbar->add($titel);

// Live-Suche als Ergaenzung: ohne dieses Skript bleibt das Suchformular
// ein gewoehnlicher GET-Filter, den kompetenz_auswahl::filtere() auswertet.
// Kein AMD-Modul, weil das Plugin keine Build-Kette hat und neben amd/src
// auch ein amd/build/*.min.js noetig waere - zwei Kopien, die nichts
// synchron haelt. Die Plugin-Version als Parameter, damit der Browser nach
// einem Update nicht die alte Fassung aus dem Zwischenspeicher nimmt;
// requires->js() haengt anders als AMD von sich aus keine an.
$PAGE->requires->js(new moodle_url('/local/berufsbildung/js/kompetenz_suche.js', [
    'v' => (string) get_config('local_berufsbildung', 'version'),
]));

// Die Auswahl wird auf den Kompetenzrahmen des Berufs dieses Blocks
// eingeschraenkt - sonst stehen bei mehreren konfigurierten Berufen alle
// Rahmen gemischt beieinander (siehe raster_analyse.php fuer das gleiche
// Aufloesungsmuster: Beruf -> Rahmen-idnumber -> Framework-Datensatz).
$beruf = (string) $block->get('beruf');
$framework = null;
$baum = [];

// Alles, was sich zuordnen laesst: Handlungskompetenzen und die
// Leistungskriterien darunter. Je Eintrag zusaetzlich die zugehoerige HK,
// damit die Tabelle der Zuordnungen den Platz im Rahmen mitzeigen kann.
$auswaehlbar = [];

if (get_config('core_competency', 'enabled') && $beruf !== '') {
    $frameworkidnumber = api::get_kompetenzrahmen_for_beruf($beruf);
    $framework = $frameworkidnumber !== null ? competency_framework::get_record(['idnumber' => $frameworkidnumber]) : false;

    if ($framework) {
        // Nach 'sortorder', damit der Baum der Gliederung des
        // Bildungsplans folgt (a1, a2, ... b1, b2) und nicht dem Alphabet.
        $rahmenkompetenzen = competency::get_records(
            ['competencyframeworkid' => (int) $framework->get('id')],
            'sortorder',
            'ASC',
            0,
            1000
        );

        $baum = (new kompetenz_baum())->baum($rahmenkompetenzen);

        foreach ($baum as $zweig) {
            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                $handlungskompetenz = $eintrag['kompetenz'];
                $auswaehlbar[(int) $handlungskompetenz->get('id')] = [
                    'kompetenz' => $handlungskompetenz,
                    'handlungskompetenz' => $handlungskompetenz,
                    'isthk' => true,
                ];

                foreach ($eintrag['leistungskriterien'] as $lk) {
                    $auswaehlbar[(int) $lk->get('id')] = [
                        'kompetenz' => $lk,
                        'handlungskompetenz' => $handlungskompetenz,
                        'isthk' => false,
                    ];
                }
            }
        }
    }
}

$zugeordnet = [];
foreach (block_lk::get_records(['blockid' => $id], 'id', 'ASC') as $abdeckung) {
    $zugeordnet[(int) $abdeckung->get('competencyid')] = $abdeckung;
}

// Bereits Zugeordnetes steht nicht mehr zur Auswahl - besser gar nicht
// anbieten, als hinterher die Duplikat-Fehlermeldung zu zeigen.
$offen = array_diff_key($auswaehlbar, $zugeordnet);

if (optional_param('speichern', 0, PARAM_BOOL)) {
    require_sesskey();

    // Gegen die angebotenen Eintraege filtern: was nicht im Rahmen dieses
    // Berufs steht oder bereits zugeordnet ist, darf auch ueber ein
    // manipuliertes POST nicht hereinkommen. Damit ist zugleich der
    // Unique-Index abgedeckt, wenn zwei Formulare sich ueberholen.
    $auswahl = array_intersect(
        optional_param_array('competencyids', [], PARAM_INT),
        array_keys($offen)
    );

    $intensitaet = optional_param('intensitaet', 'schwerpunkt', PARAM_ALPHA);
    if (!in_array($intensitaet, ['schwerpunkt', 'teilweise'], true)) {
        $intensitaet = 'schwerpunkt';
    }

    if (empty($auswahl)) {
        redirect(
            $returnurl,
            get_string('blocklk:fehler_keine_auswahl', 'local_berufsbildung'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    foreach ($auswahl as $competencyid) {
        (new block_lk(0, (object) [
            'blockid' => $id,
            'competencyid' => $competencyid,
            'intensitaet' => $intensitaet,
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

// Anzeigereihenfolge aus dem Rahmen uebernehmen; die Einfuegereihenfolge
// der Zuordnungen sagt nichts aus. Je Zeile steht der Platz im Rahmen
// dabei - ein LK-Kuerzel wie "AU b1 01 1-2" haengt unter einem Dutzend
// Handlungskompetenzen und ist ohne diese Angabe nicht zuzuordnen.
$zeilen = [];
foreach ($auswaehlbar as $kompetenzid => $eintrag) {
    if (!isset($zugeordnet[$kompetenzid])) {
        continue;
    }

    $handlungskompetenz = $eintrag['handlungskompetenz'];
    $code = kompetenz_baum::kuerzel($handlungskompetenz);
    $codebadge = $code !== ''
        ? html_writer::span(format_string($code), 'badge bg-light text-dark border mr-1') . ' '
        : '';

    if ($eintrag['isthk']) {
        $zeilen[$kompetenzid] = $codebadge
            . html_writer::span(format_string($handlungskompetenz->get('shortname')), 'font-weight-bold')
            . html_writer::div(
                get_string('blocklk:ganze_hk', 'local_berufsbildung'),
                'small text-muted'
            );
        continue;
    }

    $zeilen[$kompetenzid] = format_string($eintrag['kompetenz']->get('shortname'))
        . html_writer::div(
            $codebadge . format_string($handlungskompetenz->get('shortname')),
            'small text-muted'
        );
}

// Zuordnungen, die nicht (mehr) im Rahmen dieses Berufs stehen - etwa weil
// der Beruf des Blocks nachtraeglich geaendert wurde. Sie bleiben sichtbar
// und entfernbar, statt stillschweigend zu verschwinden.
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

if (empty($auswaehlbar)) {
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
    echo html_writer::tag('p', get_string('blocklk:auswahl_hinweis', 'local_berufsbildung'), ['class' => 'text-muted']);
    echo kompetenz_auswahl::render(
        kompetenz_auswahl::filtere($baum, $suchbegriff),
        $zugeordnet,
        $returnurl,
        $id,
        $suchbegriff
    );
}

echo html_writer::link($bloeckeurl, get_string('blocklk:zurueck', 'local_berufsbildung'));

echo $OUTPUT->footer();
