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
 * Rendert Nachweise gruppiert nach Quelle, gemeinsam genutzt von
 * meine_lehre.php und meine_lernenden.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use local_berufsbildung\nachweis\nachweis;

class nachweis_liste {

    /**
     * @param nachweis[] $nachweise Bereits nach Datum sortiert (siehe
     *                               collector::get_nachweise()) - die
     *                               Reihenfolge je Gruppe bleibt erhalten,
     *                               es wird nicht neu sortiert.
     * @param array<string, string> $quellennamen Quelle-Key => Anzeigename,
     *                                              siehe collector::get_quelle_namen()
     */
    public static function render(array $nachweise, array $quellennamen): string {
        global $OUTPUT;

        $nachweisenachquelle = [];
        foreach ($nachweise as $einzelnachweis) {
            $nachweisenachquelle[$einzelnachweis->quelle_key][] = $einzelnachweis;
        }

        $gruppen = [];
        foreach ($nachweisenachquelle as $quellekey => $einzelnachweise) {
            $gruppen[] = [
                'name' => format_string($quellennamen[$quellekey] ?? $quellekey),
                'nachweise' => array_map(static function (nachweis $einzelnachweis): array {
                    $ergebnis = $einzelnachweis->ergebnis;
                    return [
                        'bezeichnung' => format_string($einzelnachweis->bezeichnung),
                        'datum' => userdate($einzelnachweis->datum, get_string('strftimedate', 'langconfig')),
                        'hasergebnis' => $ergebnis !== null && $ergebnis !== '',
                        'ergebnis' => $ergebnis !== null ? s($ergebnis) : '',
                        'hasurl' => $einzelnachweis->url !== null,
                        'url' => $einzelnachweis->url ?? '',
                    ];
                }, $einzelnachweise),
            ];
        }

        return $OUTPUT->render_from_template('local_berufsbildung/nachweis_liste', [
            'gruppen' => $gruppen,
            'hasgruppen' => !empty($gruppen),
            'leertext' => get_string('form:keine_taetigkeiten', 'local_berufsbildung'),
        ]);
    }
}
