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
 * Wertobjekt fuer den Ausbildungsstand einer lernenden Person.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

/**
 * Beruf, Lehrjahr und laufendes Semester einer lernenden Person.
 *
 * Wird berechnet, nicht gespeichert - deshalb kein Persistent.
 *
 * Einzelne readonly-Properties statt einer readonly-Klasse, damit die
 * Klasse auch unter PHP 8.1 (Moodle 4.5) funktioniert.
 */
class ausbildungsstand {

    /**
     * @param string $beruf 'AU_EFZ' | 'KR_EFZ' | 'PM_EFZ'
     * @param int $jahrgang Jahr des Lehrbeginns
     * @param int $semester 1..8
     * @param int $lehrjahr 1..4
     * @param int $semester_von Timestamp, Beginn des Semesters
     * @param int $semester_bis Timestamp, Ende des Semesters
     */
    public function __construct(
        public readonly string $beruf,
        public readonly int $jahrgang,
        public readonly int $semester,
        public readonly int $lehrjahr,
        public readonly int $semester_von,
        public readonly int $semester_bis,
    ) {
    }
}
