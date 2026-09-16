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
 * Rendert die Luecken-Analyse fuer die Uebersichtsseiten
 * meine_lehre.php und meine_lernenden.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use core_competency\competency;
use local_berufsbildung\bereich_abdeckung;

/**
 * Nur ein Vorschlag fuer die Ausbildungsplanung, keine Festlegung
 * (Architekturregel 6) - siehe api::get_luecken_nach_bereich(). Deshalb
 * bewusst als dezenter Hinweis gerendert, nicht als Warnung/Fehler.
 *
 * Gruppiert nach Handlungskompetenzbereich und mit Bezugsgroesse: "vier
 * von sechs abgedeckt" sagt fuer die Planung mehr als zwei Namen ohne
 * Nenner.
 */
class luecken_liste {
    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param bereich_abdeckung[] $abdeckungen Ergebnis von api::get_luecken_nach_bereich()
     * @param bool $kompakt Nur die Zusammenfassung ohne Bereichsaufschluesselung,
     *                       fuer die Roster-Karten in meine_lernenden.php
     */
    public static function render(array $abdeckungen, bool $kompakt = false): string {
        global $OUTPUT;

        $namen = self::lade_namen($abdeckungen);

        $bereiche = [];
        $anzahlluecken = 0;
        $anzahlsoll = 0;

        foreach ($abdeckungen as $abdeckung) {
            $anzahlluecken += count($abdeckung->luecken);
            $anzahlsoll += $abdeckung->soll;

            $kompetenzen = [];
            foreach ($abdeckung->luecken as $competencyid) {
                if (isset($namen[$competencyid])) {
                    $kompetenzen[] = ['shortname' => $namen[$competencyid]];
                }
            }

            $bereiche[] = [
                'name' => $namen[$abdeckung->bereichid] ?? '',
                'abdeckung' => get_string('luecken:bereich_abdeckung', 'local_berufsbildung', (object) [
                    'abgedeckt' => $abdeckung->anzahl_abgedeckt(),
                    'soll' => $abdeckung->soll,
                ]),
                'istvollstaendig' => $abdeckung->ist_vollstaendig(),
                'hasluecken' => !empty($kompetenzen),
                'kompetenzen' => $kompetenzen,
            ];
        }

        return $OUTPUT->render_from_template('local_berufsbildung/luecken_liste', [
            'hasluecken' => $anzahlluecken > 0,
            'anzahl' => $anzahlluecken,
            'zusammenfassung' => get_string('luecken:zusammenfassung', 'local_berufsbildung', (object) [
                'abgedeckt' => $anzahlsoll - $anzahlluecken,
                'soll' => $anzahlsoll,
            ]),
            'bereiche' => $bereiche,
            'kompakt' => $kompakt,
            'titel' => get_string('luecken:titel', 'local_berufsbildung'),
            'keinetext' => get_string('luecken:keine', 'local_berufsbildung'),
        ]);
    }

    /**
     * Bezeichnungen aller angezeigten Kompetenzen - Bereiche und Luecken -
     * in einer Abfrage statt einer je Kompetenz.
     *
     * @param bereich_abdeckung[] $abdeckungen
     * @return array<int, string> competencyid => shortname
     */
    private static function lade_namen(array $abdeckungen): array {
        $ids = [];
        foreach ($abdeckungen as $abdeckung) {
            $ids[] = $abdeckung->bereichid;
            foreach ($abdeckung->luecken as $competencyid) {
                $ids[] = $competencyid;
            }
        }

        if (empty($ids)) {
            return [];
        }

        // Ausschliesslich Integer aus der API, deshalb direkt einsetzbar.
        $idliste = implode(',', array_map('intval', array_unique($ids)));

        $namen = [];
        foreach (competency::get_records_select("id IN ({$idliste})") as $kompetenz) {
            $namen[(int) $kompetenz->get('id')] = format_string($kompetenz->get('shortname'));
        }

        return $namen;
    }
}
