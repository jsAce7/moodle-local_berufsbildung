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
 * filterbar nach Beruf. Jede Person ist eine Zeile ueber die volle Breite -
 * Details (Luecken-Aufschluesselung, Kompetenzraster, Taetigkeitenliste,
 * Profil-Link) stehen erst auf Wunsch (natives <details>-Element, kein
 * JavaScript). Volle Breite, weil das Kompetenzraster ein breiter Inhalt
 * ist: in einer Kachelspalte bricht es zeichenweise um.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\api;
use local_berufsbildung\nachweis\collector;
use local_berufsbildung\output\faelligkeiten_liste;
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

// Der volle Datensatz bleibt stehen, nicht nur der Name: das Profilbild
// haengt daran und kostet so keine zweite Abfrage je Person.
$nutzer = [];
$namen = [];
foreach (api::get_lernende_for($berufsbildnerid) as $lernendeid) {
    $nutzer[$lernendeid] = core_user::get_user($lernendeid, '*', MUST_EXIST);
    $namen[$lernendeid] = fullname($nutzer[$lernendeid]);
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
    $datumsformat = get_string('strftimedate', 'langconfig');

    // Die Übersicht beantwortet die Arbeitsfrage vor der Detailansicht:
    // Welche Person braucht als Nächstes Aufmerksamkeit? Die gleichen
    // Fälligkeiten erscheinen weiter unten nochmals im jeweiligen Profil,
    // dort aber ohne Namen, weil der Kontext bereits klar ist.
    $faelligkeiten = [];
    $faelligkeitenjelernende = [];
    foreach ($eintraege as $eintrag) {
        $lernendeid = $eintrag['id'];
        foreach ($collector->get_faelligkeiten($berufsbildnerid, $lernendeid) as $faelligkeit) {
            $zeile = (object) [
                'name' => $eintrag['name'],
                'bezeichnung' => $faelligkeit->bezeichnung,
                'datum' => userdate($faelligkeit->datum, $datumsformat),
                'zeitpunkt' => $faelligkeit->datum,
                'ueberfaellig' => $faelligkeit->datum < time(),
                'url' => $faelligkeit->url,
            ];
            $faelligkeiten[] = $zeile;
            $detailzeile = clone $zeile;
            unset($detailzeile->name);
            $faelligkeitenjelernende[$lernendeid][] = $detailzeile;
        }
    }
    usort($faelligkeiten, static fn (object $a, object $b): int => $a->zeitpunkt <=> $b->zeitpunkt);

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

    if (!empty($faelligkeiten)) {
        echo html_writer::div(
            html_writer::div(
                faelligkeiten_liste::render(
                    $faelligkeiten,
                    get_string('faelligkeiten:meine_lernenden', 'local_berufsbildung')
                ),
                'card-body'
            ),
            'card mb-3'
        );
    }

    // Blockbezeichnungen einmal je Block, nicht einmal je Person: in einem
    // Roster stehen typischerweise mehrere Lernende im selben ueK oder in
    // derselben Abteilung.
    $blocknamen = [];

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
            // Beides, anders als auf meine_lehre.php: die Lueckenliste
            // beantwortet die Planungsfrage "was muss ich noch einplanen"
            // als kurze Aufzaehlung, das Raster zeigt daneben die ganze
            // Karte. Fuer die lernende Person waere das doppelt - sie
            // plant nicht, sie schaut nach, wo sie steht.
            echo luecken_liste::render(api::abdeckung_aus_raster($raster[$lernendeid]), kompakt: false);
            echo html_writer::div(
                kompetenzraster::render($raster[$lernendeid], api::get_planungshorizont($lernendeid)),
                'mb-3'
            );
        }

        echo faelligkeiten_liste::render(
            $faelligkeitenjelernende[$lernendeid] ?? [],
            get_string('faelligkeiten:titel', 'local_berufsbildung')
        );

        $nachweise = $collector->get_nachweise($berufsbildnerid, $lernendeid, 0, time());
        echo nachweis_liste::render(
            $nachweise,
            $quellennamen,
            titel: get_string('nachweis:titel', 'local_berufsbildung')
        );

        $profilurl = new moodle_url('/user/profile.php', ['id' => $lernendeid]);
        echo html_writer::div(html_writer::link(
            $profilurl,
            get_string('meine_lernenden:profil_oeffnen', 'local_berufsbildung')
        ), 'mt-3');

        $detailhtml = ob_get_clean();

        // Das Profilbild nur, wenn wirklich eines hinterlegt ist. Ohne
        // Bild liefert Moodle fuer alle dieselbe graue Silhouette - vier
        // identische Silhouetten untereinander unterscheiden sich
        // schlechter als "GH / NW / NN / RS". Ohne Verlinkung, weil ein
        // Link im <summary> bei jedem Klick zugleich auf- und zuklappen
        // wuerde; der Weg ins Profil steht im Detailbereich. Nicht fuer
        // Screenreader, weil der Name unmittelbar daneben steht.
        $bildhtml = !empty($nutzer[$lernendeid]->picture)
            ? $OUTPUT->user_picture($nutzer[$lernendeid], [
                'size' => 40,
                'link' => false,
                'visibletoscreenreaders' => false,
                'class' => 'userpicture local-berufsbildung-kachel-bild',
            ])
            : null;

        // "Wo steht die Person gerade" - beim Blick auf den Roster die
        // erste Frage. Der Plan ist ein Spiegel (Architekturregel 5):
        // gezeigt wird, was zuletzt importiert wurde. Laeuft zum Stichtag
        // kein Einsatz, bleibt die Spalte leer.
        $einsatz = api::get_aktueller_einsatz($lernendeid);
        $einsatzname = null;
        if ($einsatz !== null) {
            $blockid = (int) $einsatz->get('blockid');
            if (!array_key_exists($blockid, $blocknamen)) {
                $blocknamen[$blockid] = api::get_block_name($blockid);
            }
            $einsatzname = $blocknamen[$blockid];
        }

        echo lernenden_kachel::render(
            $eintrag['name'],
            $stand,
            $anzahlluecken[$lernendeid],
            count($nachweise),
            $detailhtml,
            bildhtml: $bildhtml,
            einsatzname: $einsatzname
        );
    }

    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();
