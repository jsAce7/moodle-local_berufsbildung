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
 * Rendert die Luecken-Analyse (api::get_luecken()), gemeinsam genutzt von
 * meine_lehre.php und meine_lernenden.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use core_competency\competency;

/**
 * Nur ein Vorschlag fuer die Ausbildungsplanung, keine Festlegung
 * (Architekturregel 6) - siehe api::get_luecken(). Deshalb bewusst als
 * dezenter Hinweis gerendert, nicht als Warnung/Fehler.
 */
class luecken_liste {

    /**
     * @param int[] $competencyids Ergebnis von api::get_luecken()
     */
    public static function render(array $competencyids): string {
        global $OUTPUT;

        $kompetenzen = [];
        foreach ($competencyids as $competencyid) {
            $kompetenz = competency::get_record(['id' => $competencyid]);
            if ($kompetenz === false) {
                continue;
            }
            $kompetenzen[] = ['shortname' => format_string($kompetenz->get('shortname'))];
        }

        return $OUTPUT->render_from_template('local_berufsbildung/luecken_liste', [
            'hasluecken' => !empty($kompetenzen),
            'anzahl' => count($kompetenzen),
            'kompetenzen' => $kompetenzen,
            'titel' => get_string('luecken:titel', 'local_berufsbildung'),
            'keinetext' => get_string('luecken:keine', 'local_berufsbildung'),
        ]);
    }
}
