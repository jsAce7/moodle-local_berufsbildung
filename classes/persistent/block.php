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
 * Persistent-Klasse fuer den Ausbildungsblock.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use core\persistent;

/**
 * Ausbildungsblock aus dem Versetzungsplan. Die Zeitachse (Einsaetze)
 * kommt aus dem Import; Name und Kompetenzabdeckung werden in Moodle
 * gepflegt (siehe block_lk).
 *
 * 'beruf' wird beim manuellen Anlegen gesetzt (Freitext, wie
 * zuordnung::beruf) und steuert, welcher Kompetenzrahmen bei der
 * LK-Zuordnung zur Auswahl steht (siehe api::get_kompetenzrahmen_for_beruf()).
 * Leer = berufsuebergreifend, typischerweise fuer ist_betrieb=0-Bloecke
 * wie Schule, ueK, Ferien oder Militaer.
 */
class block extends persistent {

    /** Tabellenname. */
    const TABLE = 'local_berufsbildung_block';

    /**
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'nummer' => [
                'type' => PARAM_TEXT,
            ],
            'name' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'beruf' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'ist_betrieb' => [
                'type' => PARAM_BOOL,
                'default' => true,
            ],
            'aktiv' => [
                'type' => PARAM_BOOL,
                'default' => true,
            ],
        ];
    }
}
