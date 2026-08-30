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

use html_writer;
use core_competency\competency;

/**
 * Nur ein Vorschlag fuer die Ausbildungsplanung, keine Festlegung
 * (Architekturregel 6) - siehe api::get_luecken().
 */
class luecken_liste {

    /**
     * @param int[] $competencyids Ergebnis von api::get_luecken()
     */
    public static function render(array $competencyids): string {
        $zeilen = [];
        foreach ($competencyids as $competencyid) {
            $kompetenz = competency::get_record(['id' => $competencyid]);
            if ($kompetenz === false) {
                continue;
            }
            $zeilen[] = html_writer::tag('li', format_string($kompetenz->get('shortname')));
        }

        if (empty($zeilen)) {
            return html_writer::tag('p', get_string('luecken:keine', 'local_berufsbildung'));
        }

        return html_writer::tag('p', get_string('luecken:titel', 'local_berufsbildung'))
            . html_writer::tag('ul', implode('', $zeilen));
    }
}
