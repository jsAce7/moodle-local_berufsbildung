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
 * Zurueckgehaltene Versetzungsplan-Lieferungen pruefen und freigeben oder
 * verwerfen (siehe versetzungsplan\import_service).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_berufsbildung\output\hilfe_link;
use local_berufsbildung\persistent\plan_import;
use local_berufsbildung\versetzungsplan\import_service;

admin_externalpage_setup('local_berufsbildung_planfreigabe');

$url = new moodle_url('/local/berufsbildung/plan_freigabe.php');
$PAGE->set_url($url);
$titel = get_string('planfreigabe:titel', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);
hilfe_link::fuer_seite('berufsbildung-planimport');

$service = new import_service();
$aktion = optional_param('aktion', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

if (in_array($aktion, ['freigeben', 'verwerfen'], true) && $id > 0) {
    if (optional_param('bestaetigt', 0, PARAM_BOOL) && confirm_sesskey()) {
        // Inzwischen entschieden oder ueberholt, oder die Freigabe scheitert:
        // eine Meldung auf der Uebersicht statt einer Fehlerseite.
        try {
            if ($aktion === 'freigeben') {
                $ergebnis = $service->freigeben($id, (int) $USER->id);
                $meldung = get_string('planfreigabe:freigegeben', 'local_berufsbildung', $ergebnis['einsaetze_erzeugt']);
                $meldungstyp = \core\output\notification::NOTIFY_SUCCESS;
            } else {
                $service->verwerfen($id, (int) $USER->id);
                $meldung = get_string('planfreigabe:verworfen', 'local_berufsbildung');
                $meldungstyp = \core\output\notification::NOTIFY_INFO;
            }
        } catch (moodle_exception $e) {
            redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect($url, $meldung, null, $meldungstyp);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('planfreigabe:' . $aktion . '_bestaetigung', 'local_berufsbildung'),
        new moodle_url($url, ['aktion' => $aktion, 'id' => $id, 'bestaetigt' => 1, 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->header();

$wartende = plan_import::get_records(['status' => 'zur_freigabe'], 'zeitpunkt', 'DESC');
if (empty($wartende)) {
    echo $OUTPUT->notification(get_string('planfreigabe:keine', 'local_berufsbildung'), 'info');
    echo $OUTPUT->footer();
    die();
}

echo html_writer::tag('p', get_string('planfreigabe:beschreibung', 'local_berufsbildung'));

$letzter = $service->letzter_erfolgreicher_import();

foreach ($wartende as $wartend) {
    $pruefung = $service->pruefe_wartende($wartend);
    // 0, wenn das Konto inzwischen anonymisiert wurde (privacy\provider).
    $ausgefuehrtvon = core_user::get_user((int) $wartend->get('ausgefuehrt_von'));

    echo $OUTPUT->heading(
        get_string('planfreigabe:lieferung_vom', 'local_berufsbildung', userdate((int) $wartend->get('zeitpunkt'))),
        3
    );

    $table = new html_table();
    $table->data = [
        [get_string('planfreigabe:quelle', 'local_berufsbildung'), s($wartend->get('quelle'))],
        [
            get_string('planfreigabe:ausgefuehrt_von', 'local_berufsbildung'),
            $ausgefuehrtvon ? fullname($ausgefuehrtvon) : '-',
        ],
        [get_string('planimport:zeilen_gelesen', 'local_berufsbildung'), (int) $wartend->get('zeilen_gelesen')],
        [
            get_string('planfreigabe:personen_vorher', 'local_berufsbildung'),
            $letzter !== null ? (int) $letzter->get('personen_verarbeitet') : '-',
        ],
        [get_string('planfreigabe:personen_heute', 'local_berufsbildung'), $pruefung['personen_verarbeitet']],
        [get_string('planfreigabe:einsaetze_heute', 'local_berufsbildung'), $pruefung['einsaetze_erzeugt']],
    ];
    echo html_writer::table($table);

    echo $OUTPUT->heading(
        get_string('planfreigabe:aenderungen', 'local_berufsbildung', count($pruefung['aenderungen'])),
        4
    );
    if ($pruefung['personen_unveraendert'] > 0) {
        echo html_writer::tag(
            'p',
            get_string('planfreigabe:personen_unveraendert', 'local_berufsbildung', $pruefung['personen_unveraendert'])
        );
    }
    $personen = [];
    foreach (array_keys($pruefung['aenderungen']) as $userid) {
        $person = core_user::get_user($userid);
        $personen[$userid] = $person ? fullname($person) : (string) $userid;
    }
    core_collator::asort($personen);
    $badges = [
        'entfaellt' => html_writer::span(get_string('planfreigabe:entfaellt', 'local_berufsbildung'), 'badge bg-danger'),
        'neu' => html_writer::span(get_string('planfreigabe:neu', 'local_berufsbildung'), 'badge bg-success'),
    ];
    foreach ($personen as $userid => $name) {
        echo $OUTPUT->heading(s($name), 5);
        $aenderungstabelle = new html_table();
        $aenderungstabelle->head = [
            '',
            get_string('planfreigabe:block', 'local_berufsbildung'),
            get_string('planfreigabe:zeitraum', 'local_berufsbildung'),
        ];
        foreach (['entfaellt', 'neu'] as $art) {
            foreach ($pruefung['aenderungen'][$userid][$art] as $einsatz) {
                $zeitraum = $einsatz['kw_von'] === $einsatz['kw_bis']
                    ? $einsatz['kw_von']
                    : $einsatz['kw_von'] . ' – ' . $einsatz['kw_bis'];
                $aenderungstabelle->data[] = [$badges[$art], s($einsatz['block']), s($zeitraum)];
            }
        }
        echo html_writer::table($aenderungstabelle);
    }

    if (!empty($pruefung['fehlende_personen'])) {
        echo $OUTPUT->heading(
            get_string('planfreigabe:fehlende_personen', 'local_berufsbildung', count($pruefung['fehlende_personen'])),
            4
        );
        echo html_writer::tag('p', get_string('planfreigabe:fehlende_personen_hinweis', 'local_berufsbildung'));
        $namen = [];
        foreach ($pruefung['fehlende_personen'] as $userid) {
            $person = core_user::get_user($userid);
            $namen[] = $person ? fullname($person) : (string) $userid;
        }
        core_collator::asort($namen);
        echo html_writer::alist(array_map('s', $namen));
    }

    $protokoll = array_filter(explode("\n", (string) $wartend->get('protokoll')));
    if (!empty($protokoll)) {
        echo $OUTPUT->heading(get_string('planimport:protokoll', 'local_berufsbildung'), 4);
        echo html_writer::alist(array_map('s', $protokoll));
    }

    echo html_writer::start_div('d-flex mb-4');
    echo $OUTPUT->single_button(
        new moodle_url($url, ['aktion' => 'freigeben', 'id' => $wartend->get('id')]),
        get_string('planfreigabe:freigeben', 'local_berufsbildung'),
        'get'
    );
    echo $OUTPUT->single_button(
        new moodle_url($url, ['aktion' => 'verwerfen', 'id' => $wartend->get('id')]),
        get_string('planfreigabe:verwerfen', 'local_berufsbildung'),
        'get'
    );
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
