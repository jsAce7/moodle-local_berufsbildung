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
 * Tests fuer die Semesterberechnung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;

/**
 * @covers \local_berufsbildung\service\semester_calculator
 */
final class semester_calculator_test extends advanced_testcase {

    /**
     * Pruefbeispiele fuer Jahrgang 2026 aus docs/schnitt1.md.
     *
     * Reihenfolge je Zeile: Stichtag-Jahr, -Monat, -Tag, erwartetes Semester.
     *
     * @return array
     */
    public static function berechne_semester_provider(): array {
        return [
            '25.08.2026 -> Semester 1' => [2026, 8, 25, 1],
            '15.01.2027 -> Semester 1' => [2027, 1, 15, 1],
            '01.02.2027 -> Semester 2' => [2027, 2, 1, 2],
            '01.08.2027 -> Semester 3' => [2027, 8, 1, 3],
            '31.07.2030 -> Semester 8' => [2030, 7, 31, 8],
            '01.08.2030 -> null (Lehre beendet)' => [2030, 8, 1, null],
            '01.07.2026 -> null (Lehre noch nicht begonnen)' => [2026, 7, 1, null],
        ];
    }

    /**
     * @dataProvider berechne_semester_provider
     */
    public function test_berechne_semester(int $stichtagjahr, int $stichtagmonat, int $stichtagtag, ?int $erwartet): void {
        $calculator = new semester_calculator();
        $jahrgang = 2026;
        $stichtag = mktime(12, 0, 0, $stichtagmonat, $stichtagtag, $stichtagjahr);

        $this->assertSame($erwartet, $calculator->berechne_semester($jahrgang, $stichtag));
    }

    /**
     * semester_grenzen(2026, 3) darf die Grenze zum naechsten Semester nicht
     * ueberschreiten - insbesondere nicht bis 31.7.2028 statt 31.1.2028.
     */
    public function test_semester_grenzen(): void {
        $calculator = new semester_calculator();

        [$von, $bis] = $calculator->semester_grenzen(2026, 3);

        $this->assertSame('2027-08-01 00:00:00', date('Y-m-d H:i:s', $von));
        $this->assertSame('2028-01-31 23:59:59', date('Y-m-d H:i:s', $bis));
    }

    /**
     * Konfigurierbarer Startmonat und konfigurierbare Lehrdauer, z. B. fuer
     * EBA-Berufe mit vier statt acht Semestern.
     */
    public function test_konfigurierbare_lehrdauer(): void {
        $calculator = new semester_calculator(startmonat: 8, lehrdauer_semester: 4);

        $this->assertSame(4, $calculator->berechne_semester(2026, mktime(12, 0, 0, 4, 1, 2028)));
        $this->assertNull($calculator->berechne_semester(2026, mktime(12, 0, 0, 8, 1, 2028)));
    }
}
