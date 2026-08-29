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
 * Persistent-Klasse fuer die Kompetenzabdeckung eines Ausbildungsblocks.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use core\persistent;

/**
 * Verknuepfung eines Ausbildungsblocks mit einer Handlungskompetenz aus
 * core_competency.
 */
class block_hk extends persistent {

    /** Tabellenname. */
    const TABLE = 'local_berufsbildung_block_hk';

    /**
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'blockid' => [
                'type' => PARAM_INT,
            ],
            'competencyid' => [
                'type' => PARAM_INT,
            ],
            'intensitaet' => [
                'type' => PARAM_ALPHA,
                'choices' => ['schwerpunkt', 'teilweise'],
                'default' => 'schwerpunkt',
            ],
        ];
    }
}
