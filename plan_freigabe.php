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
use local_berufsbildung\output\plan_freigabe;
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
    echo plan_freigabe::render($wartend, $service->pruefe_wartende($wartend), $letzter, $url);
}

echo $OUTPUT->footer();
