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
use local_berufsbildung\output\nachweis_liste;

require_login();

$berufsbildnerid = (int) $USER->id;
$suchbegriff = optional_param('suche', '', PARAM_TEXT);
$berufsfilter = optional_param('beruf', '', PARAM_ALPHANUMEXT);

// Der Systemkontext zeigt keinen Avatar der betreuenden Person im Moodle-
// Seitenkopf. Die personenbezogenen Detaildaten bleiben separat geschützt.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/berufsbildung/meine_lernenden.php'));
$titel = get_string('nav:meine_lernenden', 'local_berufsbildung');
$PAGE->set_title($titel);
$PAGE->set_heading($titel);

// Der volle Datensatz bleibt stehen, nicht nur der Name: das Profilbild
// hängt daran und kostet so keine zweite Abfrage je Person.
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
        'istextern' => api::ist_uek_extern($lernendeid),
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
// Rechts in der Filterzeile und zurueckhaltend gestaltet: eine
// Nebenaktion, nicht das Erste, was man auf der Seite sieht.
$bloeckehtml = has_capability('local/berufsbildung:manageblocks', context_system::instance())
    ? html_writer::div(
        html_writer::link(
            new moodle_url('/local/berufsbildung/bloecke.php'),
            get_string('meine_lernenden:bloecke', 'local_berufsbildung'),
            ['class' => 'btn btn-outline-secondary btn-sm']
        ),
        'local-berufsbildung-roster-aktionen'
    )
    : '';

if (empty($eintraege)) {
    if ($bloeckehtml !== '') {
        echo html_writer::div(
            html_writer::div(html_writer::div($bloeckehtml, 'local-berufsbildung-roster-toolbar'), 'card-body'),
            'card mb-3 local-berufsbildung-roster-steuerung'
        );
    }
    echo $OUTPUT->notification(get_string('meine_lernenden:keine_lernenden', 'local_berufsbildung'), 'info');
} else {
    $collector = new collector();
    $quellennamen = $collector->get_quelle_namen();
    $datumsformat = get_string('strftimedate', 'langconfig');

    // Fälligkeiten gehören zur jeweiligen Person. Im geschlossenen Header
    // wird nur Überfälliges signalisiert; alle Fristen stehen im Detail.
    $faelligkeitenjelernende = [];
    $anzahlueberfaellig = [];
    foreach ($eintraege as $eintrag) {
        $lernendeid = $eintrag['id'];
        foreach ($collector->get_faelligkeiten($berufsbildnerid, $lernendeid) as $faelligkeit) {
            $zeile = (object) [
                'bezeichnung' => $faelligkeit->bezeichnung,
                'datum' => userdate($faelligkeit->datum, $datumsformat),
                'ueberfaellig' => $faelligkeit->datum < time(),
                'url' => $faelligkeit->url,
            ];
            $faelligkeitenjelernende[$lernendeid][] = $zeile;
            if ($zeile->ueberfaellig) {
                $anzahlueberfaellig[$lernendeid] = ($anzahlueberfaellig[$lernendeid] ?? 0) + 1;
            }
        }
    }

    // Kompetenzstand je Person - vorab fuer den gesamten Bestand berechnet
    // (nicht nur die aktuell gefilterte Auswahl), damit die Zusammenfassung
    // in der Toolbar unabhaengig von Suche/Filter bleibt.
    //
    // Einmal das Raster, daraus beides: die Zahl fuer die Kachel und
    // weiter unten das Raster selbst. Sonst liefe dieselbe Auswertung je
    // Person mehrfach.
    $raster = [];
    $anzahlluecken = [];
    foreach ($eintraege as $eintrag) {
        $stand = $eintrag['stand'];
        $raster[$eintrag['id']] = (!$eintrag['istextern'] && $stand !== null && api::get_kompetenzrahmen_for_beruf($stand->beruf) !== null)
            ? api::get_kompetenzraster($eintrag['id'])
            : [];

        $offen = 0;
        foreach (api::abdeckung_aus_raster($raster[$eintrag['id']]) as $abdeckung) {
            $offen += count($abdeckung->luecken);
        }
        $anzahlluecken[$eintrag['id']] = $offen;
    }
    $anzahlmitluecken = count(array_filter($anzahlluecken, static fn (int $anzahl): bool => $anzahl > 0));

    echo html_writer::start_tag('div', ['class' => 'card mb-3 local-berufsbildung-roster-steuerung']);
    echo html_writer::start_tag('div', ['class' => 'card-body']);
    echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-roster-toolbar']);

    $formurl = new moodle_url('/local/berufsbildung/meine_lernenden.php');
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $formurl->out_omit_querystring(),
        'class' => 'local-berufsbildung-roster-toolbar-filter',
    ]);
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
    // Ohne eigene Klasse: html_writer::select() setzt je nach Moodle-Version
    // custom-select (Bootstrap 4) oder form-select (Bootstrap 5) selbst.
    echo html_writer::select($berufoptions, 'beruf', $berufsfilter, false);
    // Primaerfarbe: hellgrau gefuellt wirkte die Schaltflaeche deaktiviert.
    echo html_writer::tag('button', get_string('meine_lernenden:filtern', 'local_berufsbildung'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo $bloeckehtml;
    echo html_writer::end_tag('div');
    echo html_writer::div(
        get_string('meine_lernenden:zusammenfassung', 'local_berufsbildung', (object) [
            'anzahl' => count($eintraege),
            'luecken' => $anzahlmitluecken,
        ]),
        'text-muted small local-berufsbildung-roster-toolbar-summary'
    );
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');

    // Blockbezeichnungen einmal je Block, nicht einmal je Person: in einem
    // Roster stehen typischerweise mehrere Lernende im selben ueK oder in
    // derselben Abteilung.
    $blocknamen = [];

    // AMD-Module der Schnellaktionen einmal je Seite, nicht je Person -
    // sie binden sich ueber das data-Attribut an alle ihre Schaltflaechen.
    $schnellaktionsmodule = [];

    $gefiltert = lernenden_roster::filtere($eintraege, $suchbegriff, $berufsfilter);

    if (empty($gefiltert)) {
        echo $OUTPUT->notification(get_string('meine_lernenden:keine_treffer', 'local_berufsbildung'), 'info');
    }

    echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-roster-grid']);

    foreach ($gefiltert as $eintrag) {
        $lernendeid = $eintrag['id'];
        $stand = $eintrag['stand'];

        ob_start();

        // Was die Berufsbildner/in fuer die Person als Naechstes erfasst,
        // etwa den faelligen Bildungsbericht - siehe
        // classes/nachweis/quelle_mit_zustaendigen_aktion.php. Zuoberst,
        // weil es die Handlung ist, fuer die man die Person aufklappt; ein
        // gestylter Link wie auf meine_lehre.php, der Weg dahin ist ein GET.
        $aktionen = $collector->get_zustaendigen_aktionen($berufsbildnerid, $lernendeid);
        if (!empty($aktionen)) {
            echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-roster-erfassen mb-3']);
            foreach ($aktionen as $aktion) {
                echo html_writer::start_tag('div', ['class' => 'local-berufsbildung-roster-erfassen-aktion']);
                echo html_writer::link(new moodle_url($aktion->url), $aktion->label, ['class' => 'btn btn-primary btn-sm']);
                if ($aktion->hinweis !== null) {
                    echo html_writer::tag('p', $aktion->hinweis, ['class' => 'small text-muted mt-1 mb-0']);
                }
                echo html_writer::end_tag('div');
            }
            echo html_writer::end_tag('div');
        }

        if (!empty($raster[$lernendeid])) {
            // Nur das Raster, ohne Lueckenliste daneben - wie auf
            // meine_lehre.php. Was fehlt, zeigt es selbst: die weissen
            // Zellen, und je Zelle die fehlenden LK im Dialog. Die Zahl
            // der fehlenden Pflicht-HK traegt das Badge der Kachel.
            echo html_writer::div(
                kompetenzraster::render(
                    $raster[$lernendeid],
                    api::get_planungshorizont($lernendeid),
                    wahlpflichtsoll: $stand !== null ? api::get_wahlpflicht_anzahl_for_beruf($stand->beruf) : null
                ),
                'mb-3'
            );
        }

        echo faelligkeiten_liste::render(
            $faelligkeitenjelernende[$lernendeid] ?? [],
            get_string('faelligkeiten:titel', 'local_berufsbildung'),
            kompakt: true
        );

        $nachweise = $collector->get_nachweise($berufsbildnerid, $lernendeid, 0, time());
        echo nachweis_liste::render(
            $nachweise,
            $quellennamen,
            // Externe Personen haben hier keinen Ausbildungsstand und damit
            // auch keine Semester, in die sich ein Nachweis einordnen liesse.
            $eintrag['istextern'] ? [] : api::get_semester_grenzen($lernendeid),
            get_string('nachweis:titel', 'local_berufsbildung'),
            $collector->get_zusammenfassungen($nachweise),
            $collector->get_ausstehende($berufsbildnerid, $lernendeid)
        );

        $profilurl = new moodle_url('/user/profile.php', ['id' => $lernendeid]);
        echo html_writer::div(html_writer::link(
            $profilurl,
            get_string('meine_lernenden:profil_oeffnen', 'local_berufsbildung')
        ), 'mt-3');

        // Die Teilnahmeart wird hier nicht umgestellt: sie ist Stammdatum
        // der Person, keine Handlung der Betreuung, und ein Klick neben den
        // Taetigkeiten blendet Raster und Lerndokumentation aus. Sie wird
        // in der Zuordnungsuebersicht gepflegt (teilnahmeart_setzen.php).

        $detailhtml = ob_get_clean();

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

        // Was nebenbei jederzeit fuer die Person zu tun ist, etwa eine
        // Notiz - in der Kopfzeile, ohne Aufklappen. Siehe
        // classes/nachweis/quelle_mit_schnellaktion.php.
        $schnellaktionen = $collector->get_schnellaktionen($berufsbildnerid, $lernendeid);
        foreach ($schnellaktionen as $schnellaktion) {
            if ($schnellaktion->amdmodul !== null) {
                $schnellaktionsmodule[$schnellaktion->amdmodul] = true;
            }
        }

        echo lernenden_kachel::render(
            $eintrag['name'],
            // Externe Personen haben keinen Ausbildungsstand in diesem
            // Plugin; ihr allfälliger aktueller üK-Einsatz bleibt separat
            // sichtbar, aber Semesterleiste und Lehrberuf nicht.
            $eintrag['istextern'] ? null : $stand,
            $anzahlluecken[$lernendeid],
            // Dieselbe Zaehlung wie die Liste im Detail: ein Lerndoku-Eintrag
            // mit mehreren Handlungskompetenzen ist eine Taetigkeit.
            count(nachweis_liste::eindeutige($nachweise)),
            $detailhtml,
            bildhtml: $bildhtml,
            einsatzname: $einsatzname,
            anzahlueberfaellig: $anzahlueberfaellig[$lernendeid] ?? 0,
            istextern: $eintrag['istextern'],
            lernendeid: $lernendeid,
            schnellaktionen: $schnellaktionen
        );
    }

    echo html_writer::end_tag('div');

    foreach (array_keys($schnellaktionsmodule) as $amdmodul) {
        $PAGE->requires->js_call_amd($amdmodul, 'init');
    }
}

echo $OUTPUT->footer();
