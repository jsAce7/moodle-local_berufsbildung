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
 * Der laufende Einsatz aus dem Versetzungsplan, gerendert fuer
 * meine_lehre.php.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use local_berufsbildung\persistent\einsatz;

/**
 * "Wo bin ich gerade?" - fuer eine lernende Person die unmittelbarste
 * Information des Versetzungsplans. Der Plan ist ein Spiegel, keine
 * Wahrheit (Architekturregel 5): angezeigt wird, was zuletzt importiert
 * wurde, ohne Anspruch darauf, dass es taggenau stimmt.
 */
class einsatz_karte {
    /**
     * Baut die Daten fuer das Template zusammen.
     *
     * @param einsatz $einsatz Der laufende Einsatz, siehe api::get_aktueller_einsatz()
     * @param string $blockname Bezeichnung des Ausbildungsblocks, siehe api::get_block_name()
     */
    public static function render(einsatz $einsatz, string $blockname): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template(
            'local_berufsbildung/einsatz_karte',
            einsatz_darstellung::zu_kontext($einsatz, $blockname)
        );
    }
}
