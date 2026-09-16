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
 * Persistent-Klasse fuer eine dokumentierte Aufbewahrungspflicht.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use core\persistent;
use lang_string;

/**
 * Dokumentierte Ausnahme von der automatischen Retention-Loeschung einer
 * lernenden Person - gleiches Intervall-Prinzip wie zuordnung
 * (gueltig_von/gueltig_bis), damit eine Ausnahme von selbst ablaeuft statt
 * vergessen auf ewig zu blockieren.
 */
class aufbewahrung extends persistent {
    /** Tabellenname. */
    const TABLE = 'local_berufsbildung_aufbewahrung';

    /**
     * Eigenschaften der Aufbewahrungspflicht.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'lernendeid' => [
                'type' => PARAM_INT,
            ],
            'grund' => [
                'type' => PARAM_RAW,
            ],
            'gueltig_von' => [
                'type' => PARAM_INT,
            ],
            'gueltig_bis' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
        ];
    }

    /**
     * Eine Aufbewahrungspflicht ohne Begruendung ist nicht nachvollziehbar.
     *
     * @param string $value
     * @return true|lang_string
     */
    protected function validate_grund($value) {
        if (trim($value) === '') {
            return new lang_string('error:aufbewahrunggrundleer', 'local_berufsbildung');
        }

        return true;
    }
}
