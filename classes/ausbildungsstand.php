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
     * Konstruktor.
     *
     * @param string $beruf 'AU_EFZ' | 'KR_EFZ' | 'PM_EFZ'
     * @param int $jahrgang Jahr des Lehrbeginns
     * @param int $semester 1..gesamtsemester
     * @param int $lehrjahr 1..(gesamtsemester / 2)
     * @param int $semestervon Timestamp, Beginn des Semesters
     * @param int $semesterbis Timestamp, Ende des Semesters
     * @param int $gesamtsemester Lehrdauer in Semestern, je nach Beruf unterschiedlich
     *                             (siehe lehrdauer_resolver) - fuer Fortschrittsanzeigen,
     *                             nicht zur Semesterberechnung selbst noetig.
     */
    public function __construct(
        /** @var string 'AU_EFZ' | 'KR_EFZ' | 'PM_EFZ'. */
        public readonly string $beruf,
        /** @var int Jahr des Lehrbeginns. */
        public readonly int $jahrgang,
        /** @var int 1..gesamtsemester. */
        public readonly int $semester,
        /** @var int 1..(gesamtsemester / 2). */
        public readonly int $lehrjahr,
        /** @var int Timestamp, Beginn des Semesters. */
        public readonly int $semestervon,
        /** @var int Timestamp, Ende des Semesters. */
        public readonly int $semesterbis,
        /** @var int Lehrdauer in Semestern, je nach Beruf unterschiedlich. */
        public readonly int $gesamtsemester,
    ) {
    }
}
