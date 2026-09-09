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
 * Wertobjekt fuer eine Erfassen-Aktion einer registrierten Quelle.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

/**
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert - gleiches Vorgehen
 * wie bei `nachweis`.
 */
class erfassen_aktion {

    /**
     * @param string $quelle_key Schluessel der liefernden Quelle, z.B. 'lerndokumentation'
     * @param string $label Beschriftung der Schaltflaeche, z.B. "Neuer Eintrag"
     * @param string $url Ziel-URL der Erfassen-Schaltflaeche
     */
    public function __construct(
        public readonly string $quelle_key,
        public readonly string $label,
        public readonly string $url,
    ) {
    }
}
