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
 * Tests fuer die Kalenderwochen-Umrechnung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use advanced_testcase;
use coding_exception;

/**
 * @covers \local_berufsbildung\versetzungsplan\kw_converter
 */
final class kw_converter_test extends advanced_testcase {

    /**
     * Abnahmekriterium aus docs/plan.md: 2027-W03 -> 18. bis 24. Januar 2027.
     */
    public function test_2027_w03_wird_korrekt_abgebildet(): void {
        $converter = new kw_converter();

        [$von, $bis] = $converter->zu_zeitraum('2027-W03');

        $this->assertSame('2027-01-18 00:00:00', date('Y-m-d H:i:s', $von));
        $this->assertSame('2027-01-24 23:59:59', date('Y-m-d H:i:s', $bis));
    }

    /**
     * Randfall: Woche 53, die nur in manchen Jahren existiert, wird
     * akzeptiert (siehe docs/schnittstelle_versetzungsplan.md Abschnitt 2).
     */
    public function test_woche_53_wird_akzeptiert(): void {
        $converter = new kw_converter();

        // 2026 hat eine ISO-Woche 53.
        [$von, $bis] = $converter->zu_zeitraum('2026-W53');

        $this->assertSame('2026-12-28 00:00:00', date('Y-m-d H:i:s', $von));
        $this->assertSame('2027-01-03 23:59:59', date('Y-m-d H:i:s', $bis));
    }

    public function test_ungueltiges_format_wirft(): void {
        $converter = new kw_converter();

        $this->expectException(coding_exception::class);
        $converter->zu_zeitraum('KW3');
    }

    public function test_ungueltiges_format_ohne_jahresbezug_wirft(): void {
        $converter = new kw_converter();

        $this->expectException(coding_exception::class);
        $converter->zu_zeitraum('3/2027');
    }

    public function test_sortierschluessel_ist_aufsteigend_vergleichbar(): void {
        $converter = new kw_converter();

        $this->assertLessThan($converter->sortierschluessel('2027-W02'), $converter->sortierschluessel('2027-W01'));
        $this->assertLessThan($converter->sortierschluessel('2028-W01'), $converter->sortierschluessel('2027-W52'));
    }
}
