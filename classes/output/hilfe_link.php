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
 * Link "Hilfe" im Seitenkopf.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use html_writer;
use moodle_url;

/**
 * Fuehrt von einer Seite direkt zu ihrem Abschnitt auf hilfe.php. Steht
 * rechts neben der Seitenueberschrift (Header-Aktion), damit er auf allen
 * Seiten an derselben Stelle ist. Auch fuer aufsetzende Plugins, vor
 * $OUTPUT->header() aufzurufen:
 *
 *     hilfe_link::fuer_seite('bildungsbericht-erfassen');
 *
 * Der Anker muss zu einem Abschnitt gehoeren, den die Person auf der Seite
 * auch sieht - also zu einem, der unter derselben Bedingung gemeldet wird,
 * unter der sie die Seite oeffnen darf.
 */
final class hilfe_link {
    /**
     * Setzt den Link in den Kopf der aktuellen Seite.
     *
     * @param string $anker Anker des Abschnitts (hilfe\abschnitt::$anker)
     */
    public static function fuer_seite(string $anker): void {
        global $PAGE;

        $PAGE->add_header_action(self::render($anker));
    }

    /**
     * Der Link als HTML.
     *
     * @param string $anker Anker des Abschnitts
     * @return string
     */
    public static function render(string $anker): string {
        global $OUTPUT;

        $text = get_string('hilfe:link', 'local_berufsbildung');

        return html_writer::link(
            new moodle_url('/local/berufsbildung/hilfe.php', null, $anker),
            $OUTPUT->pix_icon('help', '', 'core', ['class' => 'me-1']) . $text,
            ['class' => 'btn btn-outline-secondary btn-sm', 'title' => get_string('hilfe:link_titel', 'local_berufsbildung')]
        );
    }
}
