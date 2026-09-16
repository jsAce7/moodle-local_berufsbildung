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
 * Gemeinsame Aufbereitung eines Einsatzes fuer die Anzeige.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use local_berufsbildung\persistent\einsatz;
use local_berufsbildung\versetzungsplan\kw_converter;

/**
 * Karte (einsatz_karte) und Zeitstrahl (einsatz_timeline) zeigen denselben
 * Einsatz unterschiedlich gross, aber gleich formatiert - deshalb liegt
 * die Aufbereitung hier und nicht doppelt in beiden Klassen.
 */
class einsatz_darstellung {
    /**
     * Wandelt einen Einsatz in den Template-Kontext um.
     *
     * @param einsatz $einsatz
     * @param string $blockname Bezeichnung des Ausbildungsblocks, siehe api::get_block_name()
     * @return array{blockname: string, zeitraum: string, haskw: bool, kw: string}
     */
    public static function zu_kontext(einsatz $einsatz, string $blockname): array {
        $datumsformat = get_string('strftimedatefullshort', 'langconfig');
        $kalenderwochen = self::kalenderwochen(
            (string) $einsatz->get('kw_von'),
            (string) $einsatz->get('kw_bis')
        );

        return [
            'blockname' => format_string($blockname),
            'zeitraum' => get_string('einsatz:zeitraum', 'local_berufsbildung', (object) [
                'von' => userdate((int) $einsatz->get('von'), $datumsformat),
                'bis' => userdate((int) $einsatz->get('bis'), $datumsformat),
            ]),
            'haskw' => $kalenderwochen !== null,
            'kw' => $kalenderwochen ?? '',
        ];
    }

    /**
     * Kalenderwochen als Spanne, oder als einzelne Woche wenn der Einsatz
     * nur eine umfasst. Null, sobald eine der beiden Wochen nicht im
     * erwarteten Format vorliegt - im Betrieb wird in Kalenderwochen
     * gedacht, aber eine unlesbare Woche ist kein Grund, das Datum
     * daneben zu verschweigen.
     *
     * @param string $kwvon
     * @param string $kwbis
     * @return string|null
     */
    private static function kalenderwochen(string $kwvon, string $kwbis): ?string {
        $converter = new kw_converter();
        $von = $converter->zu_wochennummer($kwvon);
        $bis = $converter->zu_wochennummer($kwbis);

        if ($von === null || $bis === null) {
            return null;
        }

        if ($von === $bis) {
            return get_string('einsatz:kw', 'local_berufsbildung', $von);
        }

        return get_string('einsatz:kw_spanne', 'local_berufsbildung', (object) [
            'von' => $von,
            'bis' => $bis,
        ]);
    }
}
