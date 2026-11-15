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
 * Personensuche fuer das Feld "Fachvorgesetzte" eines Ausbildungsblocks.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_berufsbildung\service\fachvorgesetzte_service;

/**
 * Nur fuer die Blockverwaltung (local/berufsbildung:manageblocks), hoechstens
 * 20 Treffer mit Name und Mailadresse.
 */
class fachvorgesetzte_suchen extends external_api {
    /**
     * Parameter.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'suche' => new external_value(PARAM_TEXT, 'Teil von Name oder Mailadresse, mindestens zwei Zeichen'),
        ]);
    }

    /**
     * Sucht.
     *
     * @param string $suche
     * @return array
     */
    public static function execute(string $suche): array {
        ['suche' => $suche] = self::validate_parameters(self::execute_parameters(), ['suche' => $suche]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/berufsbildung:manageblocks', $context);

        return (new fachvorgesetzte_service())->suche_personen($suche);
    }

    /**
     * Rueckgabe.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'User-ID'),
            'name' => new external_value(PARAM_TEXT, 'Voller Name'),
            'email' => new external_value(PARAM_TEXT, 'Mailadresse'),
        ]));
    }
}
