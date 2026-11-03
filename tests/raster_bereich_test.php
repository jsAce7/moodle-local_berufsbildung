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
 * Tests fuer raster_bereich.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\raster_bereich::class)]
/**
 * Tests fuer raster_bereich.
 *
 * @covers \local_berufsbildung\raster_bereich
 */
final class raster_bereich_test extends advanced_testcase {
    /**
     * Nicht im Plan ist nur, was weder vorkam noch eingeplant ist - anders
     * als luecken(), das Eingeplantes mitzaehlt. Wahlpflicht zaehlt nie.
     */
    public function test_nicht_im_plan_ohne_eingeplante_und_wahlpflicht(): void {
        $bereich = new raster_bereich(1, [
            new raster_kompetenz(11, raster_kompetenz::STATUS_ABGEDECKT, false),
            new raster_kompetenz(12, raster_kompetenz::STATUS_EINGEPLANT, false),
            new raster_kompetenz(13, raster_kompetenz::STATUS_OFFEN, false),
            new raster_kompetenz(14, raster_kompetenz::STATUS_OFFEN, true),
        ], 'a');

        $this->assertSame([13], $bereich->nicht_im_plan());
        $this->assertSame([12, 13], $bereich->luecken());
    }
}
