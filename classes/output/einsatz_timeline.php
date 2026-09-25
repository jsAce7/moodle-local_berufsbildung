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
 * Alle Einsaetze aus dem Versetzungsplan als Zeitstrahl.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use local_berufsbildung\api;
use local_berufsbildung\persistent\einsatz;

/**
 * Beantwortet "wo war ich, wo bin ich, wo komme ich hin" auf einen Blick.
 *
 * Der Versetzungsplan ist eine Verbesserung, keine Voraussetzung (siehe
 * docs/plan.md): ohne importierte Einsaetze rendert diese Klasse bewusst
 * gar nichts, statt eine leere Ueberschrift stehen zu lassen.
 */
class einsatz_timeline {
    /**
     * Der Zeitstrahl einer lernenden Person, mit den Bezeichnungen ihrer
     * Ausbildungsbloecke - die uebliche Variante fuer Seiten, die den Plan
     * einer einzelnen Person zeigen.
     *
     * @param int $lernendeid
     * @param int|null $jetzt Timestamp, null bedeutet "jetzt"
     * @return string Leerer String, wenn kein Versetzungsplan vorliegt
     */
    public static function render_fuer_lernende(int $lernendeid, ?int $jetzt = null): string {
        $einsaetze = api::get_einsaetze($lernendeid);

        $blocknamen = [];
        foreach ($einsaetze as $einzeleinsatz) {
            $blockid = (int) $einzeleinsatz->get('blockid');
            if (array_key_exists($blockid, $blocknamen)) {
                continue;
            }
            $name = api::get_block_name($blockid);
            if ($name !== null) {
                $blocknamen[$blockid] = $name;
            }
        }

        return self::render($einsaetze, $blocknamen, $jetzt, api::get_semester_grenzen($lernendeid));
    }

    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param einsatz[] $einsaetze Nach 'von' aufsteigend, siehe api::get_einsaetze()
     * @param array $blocknamen blockid => Bezeichnung, siehe api::get_block_name() Struktur: array<int, string>
     * @param int|null $jetzt Timestamp fuer die Einordnung vergangen/aktuell/kommend,
     *                        null bedeutet "jetzt"
     * @param array $semestergrenzen Semesternummer => [von, bis], Struktur: array<int, array{0: int, 1: int}>
     *        siehe api::get_semester_grenzen(). Leer (Standard) ergibt eine
     *        durchgehende Liste; gefuellt gliedert sie nach Semestern - ein
     *        Plan ueber die ganze Lehrzeit ist sonst eine Liste aus
     *        zwanzig gleich aussehenden Zeilen. Dieselben
     *        Semesterbezeichnungen wie in der Taetigkeitenliste.
     * @return string Leerer String, wenn keine Einsaetze vorliegen
     */
    public static function render(
        array $einsaetze,
        array $blocknamen,
        ?int $jetzt = null,
        array $semestergrenzen = []
    ): string {
        global $OUTPUT;

        if (empty($einsaetze)) {
            return '';
        }

        $jetzt ??= time();
        ksort($semestergrenzen);

        // Die Gruppen entstehen in der Reihenfolge, in der die Einsaetze
        // hereinkommen - die ist nach 'von' aufsteigend, also die des
        // Kalenders. Damit steht auch eine Gruppe ausserhalb der Lehrzeit
        // dort, wo sie zeitlich hingehoert.
        $gruppen = [];
        foreach ($einsaetze as $einzeleinsatz) {
            $blockid = (int) $einzeleinsatz->get('blockid');
            $von = (int) $einzeleinsatz->get('von');
            $bis = (int) $einzeleinsatz->get('bis');

            // Ein Block, dessen Nummer beim Import unbekannt war, hat hier
            // keinen Namen - der Einsatz selbst bleibt trotzdem sichtbar.
            $name = $blocknamen[$blockid] ?? get_string('einsatz:unbekannter_block', 'local_berufsbildung');

            $semester = self::finde_semester($von, $bis, $semestergrenzen);
            $schluessel = $semester ?? 0;

            if (!isset($gruppen[$schluessel])) {
                $gruppen[$schluessel] = [
                    // Ohne Semestergrenzen gibt es nichts zu beschriften -
                    // dann bleibt es die durchgehende Liste von vorher.
                    'hasname' => !empty($semestergrenzen),
                    'name' => $semester !== null
                        ? get_string('nachweis:semester', 'local_berufsbildung', $semester)
                        : get_string('nachweis:ohne_semester', 'local_berufsbildung'),
                    'eintraege' => [],
                ];
            }

            $gruppen[$schluessel]['eintraege'][] = einsatz_darstellung::zu_kontext($einzeleinsatz, $name) + [
                'istvergangen' => $bis < $jetzt,
                'istaktuell' => $von <= $jetzt && $bis >= $jetzt,
                'istkommend' => $von > $jetzt,
            ];
        }

        return $OUTPUT->render_from_template('local_berufsbildung/einsatz_timeline', [
            'gruppen' => self::falte(array_values($gruppen)),
            'titel' => get_string('einsatz:timeline_titel', 'local_berufsbildung'),
        ]);
    }

    /**
     * Legt fest, welche Semestergruppe offen dasteht und welche zugeklappt
     * bleibt.
     *
     * Ueber die ganze Lehrzeit hat ein Versetzungsplan rund sechzig
     * Einsaetze. Vollstaendig ausgeklappt ist das ein Referenzdokument,
     * kein Ueberblick - und die Fragen an die Liste ("wo bin ich, was kommt
     * als Naechstes") betreffen immer nur ein Semester. Zugeklappt heisst
     * dabei nicht versteckt: der Plan bleibt mit einem Klick je Semester
     * vollstaendig erreichbar (Architekturregel 5).
     *
     * Offen ist das Semester, in dem gerade ein Einsatz laeuft. Faellt
     * "jetzt" in keinen Einsatz - etwa zwischen zwei Bloecken -, oeffnet
     * das Semester mit dem naechsten kommenden. Liegt der ganze Plan in
     * der Vergangenheit, bleibt das letzte offen, damit nie eine Liste aus
     * lauter zugeklappten Zeilen dasteht.
     *
     * @param array $gruppen Template-Kontext der Gruppen, chronologisch
     * @return array Dieselben Gruppen mit istoffen und anzahl
     */
    private static function falte(array $gruppen): array {
        $offen = null;
        $naechste = null;

        foreach ($gruppen as $index => $gruppe) {
            foreach ($gruppe['eintraege'] as $eintrag) {
                if ($eintrag['istaktuell']) {
                    $offen ??= $index;
                } else if ($eintrag['istkommend']) {
                    $naechste ??= $index;
                }
            }
        }

        $offen ??= $naechste ?? (count($gruppen) - 1);

        foreach ($gruppen as $index => $gruppe) {
            $anzahl = count($gruppe['eintraege']);
            $gruppen[$index]['istoffen'] = $index === $offen;
            $gruppen[$index]['anzahl'] = $anzahl === 1
                ? get_string('einsatz:gruppe_anzahl_eins', 'local_berufsbildung')
                : get_string('einsatz:gruppe_anzahl', 'local_berufsbildung', $anzahl);
        }

        return $gruppen;
    }

    /**
     * Das Semester, in das ein Einsatz faellt.
     *
     * Ueber Ueberschneidung, nicht ueber Enthaltensein: ein Einsatz, der
     * ueber den 1. Februar oder 1. August laeuft, gehoert sonst in keines
     * der beiden Semester und verschwaende in "ausserhalb der Lehrzeit".
     * Bei Ueberschneidung mit zweien gewinnt das fruehere - dort hat der
     * Einsatz begonnen.
     *
     * @param int $von Timestamp
     * @param int $bis Timestamp
     * @param array $semestergrenzen Aufsteigend sortiert, Struktur: array<int, array{0: int, 1: int}>
     * @return int|null Semesternummer, null ausserhalb der Lehrzeit
     */
    private static function finde_semester(int $von, int $bis, array $semestergrenzen): ?int {
        foreach ($semestergrenzen as $semester => [$semestervon, $semesterbis]) {
            if ($von <= $semesterbis && $bis >= $semestervon) {
                return (int) $semester;
            }
        }

        return null;
    }
}
