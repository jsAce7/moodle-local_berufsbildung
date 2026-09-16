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
 * Persistent-Klasse fuer den betrieblichen Einsatz.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use core\persistent;

/**
 * Einsatz einer lernenden Person in einem Ausbildungsblock ueber einen
 * zusammenhaengenden Kalenderwochen-Zeitraum. Wird komplett durch den
 * naechsten Import fuer dieselbe Person ersetzt, nie einzeln editiert.
 */
class einsatz extends persistent {
    /** Tabellenname. */
    const TABLE = 'local_berufsbildung_einsatz';

    /**
     * Feldliste dieses Persistent inklusive Validierung.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'userid' => [
                'type' => PARAM_INT,
            ],
            'blockid' => [
                'type' => PARAM_INT,
            ],
            'von' => [
                'type' => PARAM_INT,
            ],
            'bis' => [
                'type' => PARAM_INT,
            ],
            'kw_von' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'kw_bis' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'importid' => [
                'type' => PARAM_INT,
            ],
        ];
    }
}
