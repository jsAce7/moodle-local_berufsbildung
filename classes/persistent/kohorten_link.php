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
 * Persistent-Klasse fuer die Kohorten-Verknuepfung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use core\persistent;

/**
 * Laufende Verknuepfung zwischen einer Kohorte und einer/einem
 * Berufsbildner/in - wird vom Scheduled Task laufend nachgefuehrt.
 *
 * Wird nie geloescht, nur ueber aktiv=0 stillgelegt - dieselbe Logik wie bei
 * der Zuordnung selbst: die Herkunft bereits erzeugter Zuordnungen muss
 * nachvollziehbar bleiben (siehe zuordnung.kohorten_link_id).
 */
class kohorten_link extends persistent {
    /** Tabellenname. */
    const TABLE = 'local_berufsbildung_kohorten_link';

    /**
     * Eigenschaften des Links.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'cohortid' => [
                'type' => PARAM_INT,
            ],
            'berufsbildnerid' => [
                'type' => PARAM_INT,
            ],
            'rolle' => [
                'type' => PARAM_ALPHA,
                'default' => 'hauptverantwortlich',
            ],
            'beruf' => [
                // Freitext, siehe zuordnung::beruf - leer = je Person aus
                // dem Profil uebernehmen.
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'aktiv' => [
                'type' => PARAM_BOOL,
                'default' => true,
            ],
        ];
    }
}
