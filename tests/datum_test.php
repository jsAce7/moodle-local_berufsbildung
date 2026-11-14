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
 * Tests fuer die Umrechnung zwischen Datumsfeld und «gueltig bis».
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\datum::class)]
/**
 * Tests fuer datum.
 *
 * @covers \local_berufsbildung\datum
 */
final class datum_test extends advanced_testcase {
    /**
     * «Gueltig bis 31.07.» gilt den ganzen 31. Juli: die Zuordnung zaehlt
     * am Abend noch, am 1. August nicht mehr.
     */
    public function test_tagesende_zaehlt_den_ganzen_tag(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Zurich');

        $mitternacht = make_timestamp(2027, 7, 31);
        $ende = datum::tagesende($mitternacht);

        $this->assertSame(make_timestamp(2027, 8, 1) - 1, $ende);
        $this->assertSame($mitternacht, datum::tagesbeginn($ende));
    }

    /**
     * Randfall: am Tag der Zeitumstellung hat der Tag 25 Stunden - ein
     * festes DAYSECS endete eine Stunde zu frueh.
     */
    public function test_tagesende_am_tag_der_zeitumstellung(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Zurich');

        $ende = datum::tagesende(make_timestamp(2026, 10, 25));

        $this->assertSame(make_timestamp(2026, 10, 26) - 1, $ende);
        $this->assertSame(25 * HOURSECS - 1, $ende - make_timestamp(2026, 10, 25));
    }

    /**
     * Ein Zeitpunkt mitten am Tag fuehrt zum selben Tagesende.
     */
    public function test_tagesende_von_einem_zeitpunkt_mitten_am_tag(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Zurich');

        $this->assertSame(
            make_timestamp(2027, 1, 16) - 1,
            datum::tagesende(make_timestamp(2027, 1, 15, 14, 30))
        );
    }
}
