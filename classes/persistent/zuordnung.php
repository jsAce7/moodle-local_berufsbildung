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
 * Persistent-Klasse fuer die Zuordnungstabelle.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use core\persistent;

/**
 * Zuordnung zwischen Berufsbildner/in und Lernenden.
 *
 * Zuordnungen werden nie geloescht, nur ueber gueltig_bis beendet.
 */
class zuordnung extends persistent {

    /** Tabellenname. */
    const TABLE = 'local_berufsbildung_zuordnung';

    /**
     * Eigenschaften der Zuordnung.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'berufsbildnerid' => [
                'type' => PARAM_INT,
            ],
            'lernendeid' => [
                'type' => PARAM_INT,
            ],
            'beruf' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'rolle' => [
                'type' => PARAM_ALPHA,
                'default' => 'hauptverantwortlich',
            ],
            'gueltig_von' => [
                'type' => PARAM_INT,
            ],
            'gueltig_bis' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'kohorten_link_id' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
        ];
    }
}
