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
 * "Meine Lehre": eigener Ausbildungsstand, eigener Versetzungsplan und
 * eigene Taetigkeiten aus allen registrierten Nachweis-Quellen (siehe
 * classes/nachweis/).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\api;
use local_berufsbildung\nachweis\collector;
use local_berufsbildung\output\einsatz_karte;
use local_berufsbildung\output\einsatz_timeline;
use local_berufsbildung\output\luecken_liste;
use local_berufsbildung\output\nachweis_liste;
use local_berufsbildung\output\semester_stepper;

require_login();

$lernendeid = (int) $USER->id;

$PAGE->set_context(context_user::instance($lernendeid));
$PAGE->set_url(new moodle_url('/local/berufsbildung/meine_lehre.php'));
$titel = get_string('nav:meine_lehre', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

$datumsformat = get_string('strftimedaydate', 'langconfig');
$phase = api::get_ausbildungsphase($lernendeid);

// Nach Lehrabschluss loest sich der Ausbildungsstand zum Stichtag "jetzt"
// bewusst nicht mehr auf. Der Stand des letzten Semesters bleibt aber
// abrufbar (Architekturregel 3) - genau das macht den Rueckblick moeglich,
// statt die Seite am Tag des Abschlusses leer werden zu lassen.
$istbeendet = $phase === api::PHASE_BEENDET;
$ausbildungsende = $istbeendet ? api::get_ausbildungsende($lernendeid) : null;
$stand = null;

if ($phase === api::PHASE_LAUFEND) {
    $stand = api::get_ausbildungsstand($lernendeid);
} else if ($istbeendet && $ausbildungsende !== null) {
    $stand = api::get_ausbildungsstand($lernendeid, $ausbildungsende);
}

echo $OUTPUT->header();

if ($stand !== null) {
    echo html_writer::start_tag('div', ['class' => 'card local-berufsbildung-herokarte']);
    echo html_writer::start_tag('div', ['class' => 'card-body']);

    $collector = new collector();

    if ($istbeendet) {
        echo html_writer::tag('p', get_string('meine_lehre:abgeschlossen', 'local_berufsbildung', (object) [
            'beruf' => $stand->beruf,
            'datum' => userdate((int) $ausbildungsende, $datumsformat),
        ]), ['class' => 'text-muted mb-0']);
    } else {
        echo html_writer::tag('p', get_string('form:ausbildungsstand', 'local_berufsbildung', (object) [
            'beruf' => $stand->beruf,
            'lehrjahr' => $stand->lehrjahr,
            'semester' => $stand->semester,
        ]), ['class' => 'text-muted mb-0']);
    }

    echo semester_stepper::render($stand->semester, $stand->gesamtsemester);

    if (!$istbeendet) {
        // Direktlinks zum Erfassen, pro registrierter Quelle, die eine
        // eigene Erfassung anbietet - siehe classes/nachweis/erfassbare_quelle.php.
        // Kein 'class' im vierten Parameter von single_button(): das ist die
        // Klasse des umschliessenden <div> (Default 'singlebutton'), nicht die
        // des Buttons. Ein 'btn-*' landet dort auf einem Block-Element und
        // wird zum vollflaechigen farbigen Balken. Die Button-Variante selbst
        // haengt an $type, siehe \core\output\single_button.
        foreach ($collector->get_erfassen_aktionen($lernendeid, $lernendeid) as $aktion) {
            echo html_writer::div($OUTPUT->single_button(
                new moodle_url($aktion->url),
                $aktion->label,
                'get'
            ), 'mb-3');
        }

        // "Wo bin ich gerade" - die unmittelbarste Information des Plans,
        // und nur solange die Lehre laeuft ueberhaupt eine Frage.
        $einsatz = api::get_aktueller_einsatz($lernendeid);
        if ($einsatz !== null) {
            $blockname = api::get_block_name((int) $einsatz->get('blockid'));
            if ($blockname !== null) {
                echo einsatz_karte::render($einsatz, $blockname);
            }
        }

        // Die Lueckenanalyse ist ein Planungsinstrument fuer die laufende
        // Ausbildung. Nach dem Abschluss waere sie keine Planung mehr,
        // sondern ein Urteil - und das ist nicht Sache dieses Plugins.
        if (api::get_kompetenzrahmen_for_beruf($stand->beruf) !== null) {
            echo luecken_liste::render(api::get_luecken_nach_bereich($lernendeid));
        }
    }

    echo einsatz_timeline::render_fuer_lernende($lernendeid);

    $nachweise = $collector->get_nachweise($lernendeid, $lernendeid, 0, time());
    echo nachweis_liste::render(
        $nachweise,
        $collector->get_quelle_namen(),
        api::get_semester_grenzen($lernendeid)
    );

    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
} else if ($phase === api::PHASE_VOR_BEGINN) {
    // Die Lehre beginnt erst - kein Semester, aber der Versetzungsplan
    // kann bereits vorliegen und beantwortet "wo fange ich an".
    echo $OUTPUT->notification(
        get_string(
            'meine_lehre:vor_beginn',
            'local_berufsbildung',
            userdate((int) api::get_ausbildungsbeginn($lernendeid), $datumsformat)
        ),
        'info'
    );
    echo einsatz_timeline::render_fuer_lernende($lernendeid);
} else {
    // Beruf oder Jahrgang fehlen im Profil - ohne beides ist hier nichts
    // aufloesbar, auch kein Versetzungsplan. Dieselbe Meldung deckt den
    // Fall ab, dass sich die Ausbildungskonfiguration waehrend des
    // Seitenaufbaus aendert und der Stand deshalb entfaellt.
    echo $OUTPUT->notification(get_string('meine_lehre:kein_ausbildungsstand', 'local_berufsbildung'), 'info');
}

echo $OUTPUT->footer();
