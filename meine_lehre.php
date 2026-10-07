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
use local_berufsbildung\output\faelligkeiten_liste;
use local_berufsbildung\output\kompetenzraster;
use local_berufsbildung\output\nachweis_liste;
use local_berufsbildung\output\semester_stepper;

require_login();

$lernendeid = (int) $USER->id;

$PAGE->set_context(context_user::instance($lernendeid));
$PAGE->set_url(new moodle_url('/local/berufsbildung/meine_lehre.php'));
$titel = get_string('nav:meine_lehre', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

// Der Direktaufruf bleibt harmlos, obwohl externe üK-Teilnehmende keinen
// Navigationseintrag erhalten: Für sie verwaltet dieses Plugin keine Lehre.
if (api::ist_uek_extern($lernendeid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('meine_lehre:uek_extern', 'local_berufsbildung'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Ein Format fuer ausgeschriebene Daten im Fliesstext, eines fuer die
// kompakten Listen (siehe einsatz_darstellung) - mehr braucht die Seite
// nicht. Bewusst ohne Wochentag: bei "Ihre Lehre beginnt am ..." ist das
// Datum die Auskunft, der Wochentag nur Laenge.
$datumsformat = get_string('strftimedate', 'langconfig');
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

// Ein Seitenabschnitt als eigene Karte. Die Seite bestand aus einer
// einzigen Karte, in der Einordnung, Raster, Zeitstrahl und Taetigkeiten
// ohne Abstand aufeinander folgten - vier Themen in einer Textwand. Jedes
// Thema bekommt jetzt seine eigene Flaeche mit Abstand darum; die
// Ueberschrift bringt der Inhalt selbst mit. Leerer Inhalt ergibt keine
// leere Karte.
$abschnitt = static function (string $inhalt): string {
    return trim($inhalt) === ''
        ? ''
        : html_writer::div(html_writer::div($inhalt, 'card-body'), 'card mb-3');
};

if ($stand !== null) {
    $collector = new collector();

    // Kopfkarte: wer bin ich, wo stehe ich, was kann ich jetzt tun.
    ob_start();

    echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-herokarte-info']);

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
        // Wo bin ich gerade? Die unmittelbarste Information des Plans,
        // und nur solange die Lehre laeuft ueberhaupt eine Frage.
        $einsatz = api::get_aktueller_einsatz($lernendeid);
        if ($einsatz !== null) {
            $blockname = api::get_block_name((int) $einsatz->get('blockid'));
            if ($blockname !== null) {
                echo einsatz_karte::render(
                    $einsatz,
                    $blockname,
                    api::get_block_kurs((int) $einsatz->get('blockid'))
                );
            }
        }
    }

    echo html_writer::end_tag('div');

    if (!$istbeendet) {
        // Direktlinks zum Erfassen, pro registrierter Quelle, die eine
        // eigene Erfassung anbietet - siehe classes/nachweis/erfassbare_quelle.php.
        // Das ist die einzige Handlung der Seite und steht deshalb als
        // Primaerschaltflaeche oben rechts, nicht als graue Schaltflaeche
        // im Lesefluss. Ein gestylter Link statt single_button(): der Weg
        // dahin ist ein GET, und die Klasse des Buttons laesst sich hier
        // direkt setzen, ohne dass sie auf dem umschliessenden Block-
        // Element landet und zum vollflaechigen Balken wird.
        $aktionen = $collector->get_erfassen_aktionen($lernendeid, $lernendeid);
        if (!empty($aktionen)) {
            echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-herokarte-aktionen']);
            foreach ($aktionen as $aktion) {
                echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-herokarte-aktion']);
                echo html_writer::link(
                    new moodle_url($aktion->url),
                    $aktion->label,
                    ['class' => 'btn btn-primary']
                );
                // Der Hinweis der Quelle, z.B. bis wann der naechste Eintrag
                // faellig ist. Er steht unter der Schaltflaeche, zu der er
                // gehoert - bei mehreren Quellen waere sonst nicht erkennbar,
                // welche gemeint ist. Formuliert hat ihn die Quelle, dieses
                // Plugin gibt ihn unveraendert aus (quelle_mit_hinweis).
                if ($aktion->hinweis !== null) {
                    echo html_writer::tag('p', $aktion->hinweis, ['class' => 'small text-muted mt-1 mb-0']);
                }
                echo html_writer::end_tag('div');
            }
            echo html_writer::end_tag('div');
        }
    }

    echo html_writer::div(
        html_writer::div(ob_get_clean(), 'card-body local-berufsbildung-herokarte-body'),
        'card local-berufsbildung-herokarte mb-3'
    );

    // Fälligkeiten stehen bei der eigenen Lehre, nicht als dritter Einstieg
    // daneben. Die Erfassungsaktion führt unmittelbar zur zuständigen
    // Quelle; die Liste zeigt zusätzlich auch Fristen von Quellen ohne
    // eigene Schaltfläche. Aufgaben der Berufsbildner/in (etwa einen
    // Bildungsbericht schreiben) sind keine Frist der lernenden Person.
    $faelligkeiten = [];
    foreach ($collector->get_faelligkeiten($lernendeid, $lernendeid) as $faelligkeit) {
        if (!$faelligkeit->fuer_lernende) {
            continue;
        }
        $faelligkeiten[] = (object) [
            'bezeichnung' => $faelligkeit->eigene_bezeichnung,
            'datum' => userdate($faelligkeit->datum, $datumsformat),
            'ueberfaellig' => $faelligkeit->datum < time(),
            'url' => $faelligkeit->url,
        ];
    }
    echo $abschnitt(faelligkeiten_liste::render(
        $faelligkeiten,
        get_string('faelligkeiten:titel', 'local_berufsbildung')
    ));

    // Die Kompetenzuebersicht ist ein Planungsinstrument fuer die laufende
    // Ausbildung. Nach dem Abschluss waere sie keine Planung mehr, sondern
    // ein Urteil - und das ist nicht Sache dieses Plugins.
    //
    // Nur das Raster, ohne Lueckenliste daneben: beide zeigen denselben
    // Stand, die Liste nur ohne die Kuerzel, ueber die man sie im Raster
    // wiederfinden wuerde. Die Bezugsgroesse ("x von y abgedeckt") bringt
    // das Raster selbst mit, welche Kompetenzen offen sind, steht in seinen
    // Zellen. Ebenso auf meine_lernenden.php.
    if (!$istbeendet && api::get_kompetenzrahmen_for_beruf($stand->beruf) !== null) {
        echo $abschnitt(kompetenzraster::render(
            api::get_kompetenzraster($lernendeid),
            api::get_planungshorizont($lernendeid),
            wahlpflichtgruppen: api::get_wahlpflicht_gruppen_for_beruf($stand->beruf),
            vorschlaege: api::get_bloecke_je_kompetenz($stand->beruf)
        ));
    }

    echo $abschnitt(einsatz_timeline::render_fuer_lernende($lernendeid));

    // Nach Quelle gruppiert, das Semester steht je Zeile - jede Art von
    // Nachweis bringt ihre eigene Zusammenfassung mit, etwa den ueK-Schnitt.
    // Die Zusammenfassung rechnet ueber dieselben Nachweise, die hier
    // angezeigt werden, also nur ueber die bereits besprochenen. Darunter
    // steht je Quelle, was noch aussteht - etwa die kommenden ueK. Nach
    // Lehrabschluss steht nichts mehr aus, was sich noch planen liesse.
    $nachweise = $collector->get_nachweise($lernendeid, $lernendeid, 0, time());
    echo $abschnitt(nachweis_liste::render(
        $nachweise,
        $collector->get_quelle_namen(),
        api::get_semester_grenzen($lernendeid),
        get_string('nachweis:titel', 'local_berufsbildung'),
        $collector->get_zusammenfassungen($nachweise),
        $istbeendet ? [] : $collector->get_ausstehende($lernendeid, $lernendeid)
    ));
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
    echo $abschnitt(einsatz_timeline::render_fuer_lernende($lernendeid));
} else {
    // Beruf oder Jahrgang fehlen im Profil - ohne beides ist hier nichts
    // aufloesbar, auch kein Versetzungsplan. Dieselbe Meldung deckt den
    // Fall ab, dass sich die Ausbildungskonfiguration waehrend des
    // Seitenaufbaus aendert und der Stand deshalb entfaellt.
    echo $OUTPUT->notification(get_string('meine_lehre:kein_ausbildungsstand', 'local_berufsbildung'), 'info');
}

echo $OUTPUT->footer();
