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
 * Rendert eine Liste von Nachweisen, gemeinsam genutzt von meine_lehre.php
 * und meine_lernenden.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use html_writer;
use local_berufsbildung\nachweis\nachweis;

class nachweis_liste {

    /**
     * @param nachweis[] $nachweise
     */
    public static function render(array $nachweise): string {
        if (empty($nachweise)) {
            return html_writer::tag('p', get_string('form:keine_taetigkeiten', 'local_berufsbildung'));
        }

        $zeilen = [];
        foreach ($nachweise as $einzelnachweis) {
            $text = format_string($einzelnachweis->bezeichnung)
                . ' — ' . userdate($einzelnachweis->datum, get_string('strftimedate', 'langconfig'));

            if ($einzelnachweis->ergebnis !== null && $einzelnachweis->ergebnis !== '') {
                $text .= ' (' . s($einzelnachweis->ergebnis) . ')';
            }

            if ($einzelnachweis->url !== null) {
                $text = html_writer::link($einzelnachweis->url, $text);
            }

            $zeilen[] = html_writer::tag('li', $text);
        }

        return html_writer::tag('ul', implode('', $zeilen));
    }
}
