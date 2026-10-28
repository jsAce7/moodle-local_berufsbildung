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
 * Reiter unter "Meine Lernenden": die Stationen einer Quelle (etwa die
 * Bildungsberichte, siehe nachweis\quelle_mit_stationen) fuer alle
 * aktuell zugeordneten Lernenden als eine Tabelle.
 *
 * Dieselben Stationen wie in der Spalte und der Zeitleiste auf
 * meine_lernenden.php, nur nebeneinander: wer ist wo, wer haengt
 * hinterher. Sortiert wie dort nach Handlungsbedarf - hier nur aus dieser
 * Quelle -, dann nach laufendem Semester und Namen. Personen ohne
 * Stationen dieser Quelle (etwa externe ueK-Teilnehmende) fehlen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_berufsbildung\api;
use local_berufsbildung\nachweis\collector;
use local_berufsbildung\navigation\menuepunkt;
use local_berufsbildung\output\hilfe_link;
use local_berufsbildung\output\lernenden_roster;
use local_berufsbildung\output\seitennavigation;
use local_berufsbildung\output\stationen_tabelle;

require_login();

$berufsbildnerid = (int) $USER->id;
$quellekey = required_param('quelle', PARAM_ALPHANUMEXT);

$collector = new collector();
$quellen = $collector->get_stationen_quellen();
if (!array_key_exists($quellekey, $quellen)) {
    // Etwa ein Lesezeichen auf ein inzwischen deinstalliertes Plugin.
    redirect(new moodle_url('/local/berufsbildung/meine_lernenden.php'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/berufsbildung/stationen.php', ['quelle' => $quellekey]));
$titel = get_string('nav:meine_lernenden', 'local_berufsbildung');
$PAGE->set_title($quellen[$quellekey] . ' – ' . $titel);
$PAGE->set_heading($titel);
seitennavigation::markiere_hauptleiste(menuepunkt::MEINE_LERNENDEN);

$namen = [];
foreach (api::get_lernende_for($berufsbildnerid) as $lernendeid) {
    $namen[$lernendeid] = fullname(core_user::get_user($lernendeid, '*', MUST_EXIST));
}
core_collator::asort($namen);

if (!empty($namen)) {
    hilfe_link::fuer_seite('berufsbildung-meine-lernenden');
}

$eintraege = [];
foreach ($namen as $lernendeid => $name) {
    $stationen = $collector->get_stationen($berufsbildnerid, $lernendeid)[$quellekey] ?? null;
    if ($stationen === null) {
        continue;
    }
    $eintraege[] = [
        'id' => $lernendeid,
        'name' => $name,
        'stand' => api::get_ausbildungsstand($lernendeid),
        'istextern' => api::ist_uek_extern($lernendeid),
        'stationen' => $stationen,
        'handlungsbedarf' => lernenden_roster::handlungsbedarf_aus_stationen([$stationen]),
    ];
}
$eintraege = lernenden_roster::nach_handlungsbedarf(lernenden_roster::sortiere($eintraege));

echo $OUTPUT->header();
echo seitennavigation::render(menuepunkt::MEINE_LERNENDEN, seitennavigation::stationen_schluessel($quellekey));

if (empty($namen)) {
    echo $OUTPUT->notification(get_string('meine_lernenden:keine_lernenden', 'local_berufsbildung'), 'info');
} else if (empty($eintraege)) {
    echo $OUTPUT->notification(get_string('stationen:tabelle_leer', 'local_berufsbildung', $quellen[$quellekey]), 'info');
} else {
    $zeilen = [];
    foreach ($eintraege as $eintrag) {
        $aktion = null;
        foreach ($collector->get_zustaendigen_aktionen($berufsbildnerid, $eintrag['id']) as $einzelaktion) {
            if ($einzelaktion->quellekey === $quellekey) {
                $aktion = $einzelaktion;
                break;
            }
        }

        $stand = $eintrag['stand'];
        $zeilen[] = [
            'name' => $eintrag['name'],
            'stand' => $stand !== null ? get_string('form:ausbildungsstand', 'local_berufsbildung', (object) [
                'beruf' => $stand->beruf,
                'lehrjahr' => $stand->lehrjahr,
                'semester' => $stand->semester,
            ]) : null,
            'stationen' => $eintrag['stationen'],
            'aktion' => $aktion,
        ];
    }

    $anzahlhandlungsbedarf = count(array_filter(
        $eintraege,
        static fn (array $eintrag): bool => $eintrag['handlungsbedarf'] !== lernenden_roster::HANDLUNGSBEDARF_KEINER
    ));
    echo html_writer::tag('p', get_string('stationen:tabelle_einleitung', 'local_berufsbildung', (object) [
        'anzahl' => count($eintraege),
        'handlungsbedarf' => $anzahlhandlungsbedarf,
    ]), ['class' => 'text-muted']);

    echo stationen_tabelle::render($quellen[$quellekey], $zeilen);
}

echo $OUTPUT->footer();
