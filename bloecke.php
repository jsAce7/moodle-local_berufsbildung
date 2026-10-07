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
 * Uebersicht der Ausbildungsbloecke.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;
use local_berufsbildung\api;
use local_berufsbildung\service\einrichtung_pruefung;
use local_berufsbildung\service\lk_abdeckung_service;

admin_externalpage_setup('local_berufsbildung_bloecke');

$PAGE->set_url(new moodle_url('/local/berufsbildung/bloecke.php'));
$titel = get_string('bloecke:uebersicht', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('bloecke:einleitung', 'local_berufsbildung'));

echo html_writer::div($OUTPUT->single_button(
    new moodle_url('/local/berufsbildung/block_bearbeiten.php'),
    get_string('bloecke:neu', 'local_berufsbildung'),
    'get'
), 'mb-3');

// Was dem Kompetenzraster der Lernenden fehlt, steht zuoberst: von der
// lernenden Person aus ist nicht zu erkennen, ob der Rahmen, die Bloecke oder
// deren Kompetenzen fehlen.
$befunde = get_config('core_competency', 'enabled') ? (new einrichtung_pruefung())->pruefe() : [];
if (!empty($befunde)) {
    $warnung = false;
    $punkte = '';
    foreach ($befunde as $beruf => $liste) {
        $texte = [];
        foreach ($liste as $befund) {
            $warnung = $warnung || $befund['stufe'] === einrichtung_pruefung::STUFE_WARNUNG;
            $a = is_string($befund['a']) ? s($befund['a']) : null;
            $texte[] = get_string('einrichtung:' . $befund['code'], 'local_berufsbildung', $a);
        }
        $punkte .= html_writer::tag('li', html_writer::tag('strong', s((string) $beruf)) . ': ' . implode(' ', $texte));
    }
    echo $OUTPUT->notification(
        html_writer::tag('p', get_string('einrichtung:titel', 'local_berufsbildung'), ['class' => 'mb-1 local-berufsbildung-fett'])
            . html_writer::tag('ul', $punkte, ['class' => 'mb-1'])
            . html_writer::link(
                new moodle_url('/local/berufsbildung/beruf_rahmen.php'),
                get_string('einrichtung:link_rahmen', 'local_berufsbildung')
            ),
        $warnung ? 'warning' : 'info',
        false
    );
}

$bloecke = block::get_records([], 'nummer', 'ASC');

if (empty($bloecke)) {
    echo $OUTPUT->notification(get_string('bloecke:keine_bloecke', 'local_berufsbildung'), 'info');
} else {
    // Eine Abfrage fuer alle Zuordnungen statt einer je Block.
    $anzahlkompetenzen = [];
    foreach (block_lk::get_records() as $abdeckung) {
        $blockid = (int) $abdeckung->get('blockid');
        $anzahlkompetenzen[$blockid] = ($anzahlkompetenzen[$blockid] ?? 0) + 1;
    }

    // Je Beruf eine Tabelle, die berufsuebergreifenden Bloecke (Schule,
    // ueK, Ferien) zuletzt. Innerhalb eines Berufs nach Nummer, natuerlich
    // sortiert: B2 vor B10.
    $nachberuf = [];
    foreach ($bloecke as $block) {
        $nachberuf[(string) $block->get('beruf')][] = $block;
    }
    uksort($nachberuf, static function ($a, $b): int {
        if ((string) $a === '' || (string) $b === '') {
            return ((string) $a === '') <=> ((string) $b === '');
        }

        return strnatcasecmp((string) $a, (string) $b);
    });

    $abdeckungservice = new lk_abdeckung_service();
    $kompetenzenan = (bool) get_config('core_competency', 'enabled');

    foreach ($nachberuf as $beruf => $berufsbloecke) {
        $beruf = (string) $beruf;
        usort($berufsbloecke, static fn (block $a, block $b): int => strnatcasecmp(
            (string) $a->get('nummer'),
            (string) $b->get('nummer')
        ));

        $kopf = $beruf !== '' ? s($beruf) : get_string('block:beruf_leer_label', 'local_berufsbildung');
        if ($beruf !== '' && $kompetenzenan) {
            // Der Link fuehrt zur Sicht vom Rahmen her: welches LK in
            // welchem Block vorkommt und welches noch in keinem.
            $abdeckung = $abdeckungservice->fuer_beruf($beruf);
            if ($abdeckung !== null) {
                $kopf .= html_writer::link(
                    new moodle_url('/local/berufsbildung/lk_abdeckung.php', ['beruf' => $beruf]),
                    get_string('lkabdeckung:link', 'local_berufsbildung'),
                    ['class' => 'btn btn-sm btn-secondary local-berufsbildung-abstand-links']
                );
                $kopf .= html_writer::span(
                    $abdeckung['offen'] > 0
                        ? get_string('lkabdeckung:offen_badge', 'local_berufsbildung', $abdeckung['offen'])
                        : get_string('lkabdeckung:alle_badge', 'local_berufsbildung'),
                    'badge local-berufsbildung-abstand-links '
                        . ($abdeckung['offen'] > 0 ? 'bg-warning text-dark' : 'bg-success text-white')
                );
            }
        }
        echo html_writer::tag('h3', $kopf, ['class' => 'h4 mt-4']);

        $table = new html_table();
        $table->head = [
            get_string('block:nummer', 'local_berufsbildung'),
            get_string('block:name', 'local_berufsbildung'),
            get_string('block:ist_betrieb', 'local_berufsbildung'),
            get_string('block:aktiv', 'local_berufsbildung'),
            get_string('block:kurs', 'local_berufsbildung'),
            get_string('block:kompetenzen', 'local_berufsbildung'),
            get_string('block:aktionen', 'local_berufsbildung'),
        ];

        foreach ($berufsbloecke as $block) {
            $blockid = (int) $block->get('id');
            $anzahl = $anzahlkompetenzen[$blockid] ?? 0;

            $bearbeitenurl = new moodle_url('/local/berufsbildung/block_bearbeiten.php', ['id' => $blockid]);
            $kompetenzenurl = new moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => $blockid]);

            $kurs = api::get_block_kurs($blockid);

            // Der Zugang zur Kompetenzzuordnung ist ein beschrifteter Button in
            // der Aktionsspalte, nicht die Zahl in der LK-Spalte: auf eine "0"
            // klickt niemand, und ein neu angelegter Block hat immer eine.
            $aktionen = html_writer::div(
                html_writer::link(
                    $kompetenzenurl,
                    get_string('blocklk:zuordnen', 'local_berufsbildung'),
                    ['class' => 'btn btn-sm btn-secondary']
                ) . html_writer::link(
                    $bearbeitenurl,
                    get_string('block:bearbeiten', 'local_berufsbildung'),
                    ['class' => 'btn btn-sm btn-secondary']
                ) . html_writer::link(
                    new moodle_url('/local/berufsbildung/block_bearbeiten.php', ['kopie' => $blockid]),
                    get_string('block:kopieren', 'local_berufsbildung'),
                    ['class' => 'btn btn-sm btn-secondary']
                ),
                'local-berufsbildung-tabelle-aktionen'
            );

            $table->data[] = [
                s($block->get('nummer')),
                s($block->get('name')),
                $block->get('ist_betrieb') ? get_string('yes') : get_string('no'),
                $block->get('aktiv') ? get_string('yes') : get_string('no'),
                $kurs !== null
                    ? html_writer::link(
                        new moodle_url('/course/view.php', ['id' => (int) $kurs->id]),
                        format_string($kurs->fullname)
                    )
                    : html_writer::span(get_string('block:kein_kurs', 'local_berufsbildung'), 'text-muted'),
                html_writer::span(
                    (string) $anzahl,
                    'badge ' . ($anzahl > 0 ? 'bg-primary text-white' : 'bg-light text-dark border')
                ),
                $aktionen,
            ];
        }

        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
