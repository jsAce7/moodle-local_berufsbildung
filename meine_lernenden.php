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
 * Uebersicht "Meine Lernenden": alle aktuell zugeordneten Lernenden mit
 * Ausbildungsstand und ihren Taetigkeiten aus allen registrierten
 * Nachweis-Quellen (siehe classes/nachweis/).
 *
 * Sortiert nach laufendem Semester, dann nach Namen (siehe
 * output\lernenden_roster::sortiere()), durchsuchbar nach Namen und
 * filterbar nach Beruf. Jede Person ist eine kompakte Kachel - Details
 * (Luecken-Aufschluesselung, volle Taetigkeitenliste, Profil-Link)
 * stehen erst auf Wunsch (natives <details>-Element, kein JavaScript).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\api;
use local_berufsbildung\nachweis\collector;
use local_berufsbildung\output\kompetenzraster;
use local_berufsbildung\output\lernenden_kachel;
use local_berufsbildung\output\lernenden_roster;
use local_berufsbildung\output\luecken_liste;
use local_berufsbildung\output\nachweis_liste;

require_login();

$berufsbildnerid = (int) $USER->id;
$suchbegriff = optional_param('suche', '', PARAM_TEXT);
$berufsfilter = optional_param('beruf', '', PARAM_ALPHANUMEXT);

$PAGE->set_context(context_user::instance($berufsbildnerid));
$PAGE->set_url(new moodle_url('/local/berufsbildung/meine_lernenden.php'));
$titel = get_string('nav:meine_lernenden', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

$namen = [];
foreach (api::get_lernende_for($berufsbildnerid) as $lernendeid) {
    $namen[$lernendeid] = fullname(core_user::get_user($lernendeid, '*', MUST_EXIST));
}
core_collator::asort($namen);

$eintraege = [];
foreach ($namen as $lernendeid => $name) {
    $eintraege[] = [
        'id' => $lernendeid,
        'name' => $name,
        'stand' => api::get_ausbildungsstand($lernendeid),
    ];
}
$eintraege = lernenden_roster::sortiere($eintraege);

echo $OUTPUT->header();

// Nebeneingang zur Blockverwaltung statt eines eigenen
// Navigationseintrags: wer Lernende betreut, pflegt die Ausbildungsbloecke
// im selben Arbeitsgang, und die Leiste bleibt bei den zwei fachlichen
// Einstiegen (siehe hook_callbacks). Wer die Capability ohne eigene
// Lernende hat - eine von Hand zugewiesene Ausbildungsplanung -, erreicht
// die Seite weiterhin ueber den Admin-Baum unter Ausbildungsverwaltung.
if (has_capability('local/berufsbildung:manageblocks', context_system::instance())) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/berufsbildung/bloecke.php'),
            get_string('meine_lernenden:bloecke', 'local_berufsbildung'),
            ['class' => 'btn btn-outline-secondary btn-sm']
        ),
        'local-berufsbildung-roster-aktionen'
    );
}

if (empty($eintraege)) {
    echo $OUTPUT->notification(get_string('meine_lernenden:keine_lernenden', 'local_berufsbildung'), 'info');
} else {
    $collector = new collector();
    $quellennamen = $collector->get_quelle_namen();

    // Kompetenzstand je Person - vorab fuer den gesamten Bestand berechnet
    // (nicht nur die aktuell gefilterte Auswahl), damit die Zusammenfassung
    // in der Toolbar unabhaengig von Suche/Filter bleibt.
    //
    // Einmal das Raster, daraus beides: die Zahl fuer die Kachel und
    // weiter unten Lueckenliste wie Raster. Sonst liefe dieselbe
    // Auswertung je Person mehrfach.
    $raster = [];
    $anzahlluecken = [];
    foreach ($eintraege as $eintrag) {
        $stand = $eintrag['stand'];
        $raster[$eintrag['id']] = ($stand !== null && api::get_kompetenzrahmen_for_beruf($stand->beruf) !== null)
            ? api::get_kompetenzraster($eintrag['id'])
            : [];

        $offen = 0;
        foreach (api::abdeckung_aus_raster($raster[$eintrag['id']]) as $abdeckung) {
            $offen += count($abdeckung->luecken);
        }
        $anzahlluecken[$eintrag['id']] = $offen;
    }
    $anzahlmitluecken = count(array_filter($anzahlluecken, static fn (int $anzahl): bool => $anzahl > 0));

    $formurl = new moodle_url('/local/berufsbildung/meine_lernenden.php');
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $formurl->out_omit_querystring(),
        'class' => 'local-berufsbildung-roster-toolbar',
    ]);
    echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-roster-toolbar-filter']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'suche',
        'value' => $suchbegriff,
        'placeholder' => get_string('meine_lernenden:suche_placeholder', 'local_berufsbildung'),
        'aria-label' => get_string('meine_lernenden:suche_placeholder', 'local_berufsbildung'),
        'class' => 'form-control',
    ]);
    $berufoptions = ['' => get_string('meine_lernenden:beruf_alle', 'local_berufsbildung')];
    foreach (lernenden_roster::berufe($eintraege) as $beruf) {
        $berufoptions[$beruf] = $beruf;
    }
    echo html_writer::select($berufoptions, 'beruf', $berufsfilter, false, ['class' => 'custom-select form-select']);
    echo html_writer::tag('button', get_string('meine_lernenden:filtern', 'local_berufsbildung'), [
        'type' => 'submit',
        'class' => 'btn btn-secondary',
    ]);
    echo html_writer::end_tag('div');
    echo html_writer::div(
        get_string('meine_lernenden:zusammenfassung', 'local_berufsbildung', (object) [
            'anzahl' => count($eintraege),
            'luecken' => $anzahlmitluecken,
        ]),
        'text-muted small local-berufsbildung-roster-toolbar-summary'
    );
    echo html_writer::end_tag('form');

    $gefiltert = lernenden_roster::filtere($eintraege, $suchbegriff, $berufsfilter);

    if (empty($gefiltert)) {
        echo $OUTPUT->notification(get_string('meine_lernenden:keine_treffer', 'local_berufsbildung'), 'info');
    }

    echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-roster-grid']);

    foreach ($gefiltert as $eintrag) {
        $lernendeid = $eintrag['id'];
        $stand = $eintrag['stand'];

        ob_start();

        if (!empty($raster[$lernendeid])) {
            // Das Raster kompakt: in einer Roster-Karte ist nur Platz fuer
            // Kuerzel und Symbol, die Bezeichnung steht im Titel. Die
            // Lueckenliste bleibt hier - anders als in meine_lehre.php, wo
            // das volle Raster die Namen selbst zeigt, sind die Kuerzel
            // allein fuer die Planung zu wenig.
            echo luecken_liste::render(api::abdeckung_aus_raster($raster[$lernendeid]), kompakt: false);
            echo html_writer::div(
                kompetenzraster::render($raster[$lernendeid], api::get_planungshorizont($lernendeid), kompakt: true),
                'mb-3'
            );
        }

        $nachweise = $collector->get_nachweise($berufsbildnerid, $lernendeid, 0, time());
        echo nachweis_liste::render($nachweise, $quellennamen);

        $profilurl = new moodle_url('/user/profile.php', ['id' => $lernendeid]);
        echo html_writer::div(html_writer::link(
            $profilurl,
            get_string('meine_lernenden:profil_oeffnen', 'local_berufsbildung')
        ), 'mt-3');

        $detailhtml = ob_get_clean();

        echo lernenden_kachel::render(
            $eintrag['name'],
            $stand,
            $anzahlluecken[$lernendeid],
            count($nachweise),
            $detailhtml
        );
    }

    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();
