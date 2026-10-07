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
 * Tests fuer raster_kompetenz.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\raster_kompetenz::class)]
/**
 * Tests fuer raster_kompetenz.
 *
 * @covers \local_berufsbildung\raster_kompetenz
 */
final class raster_kompetenz_test extends advanced_testcase {
    /**
     * Vollstaendig erst mit allen LK; ein Teil ergibt teilweise. Eine HK
     * ohne LK ist vollstaendig, sobald sie selbst vorkam.
     */
    public function test_anzeigestand(): void {
        $teilweise = new raster_kompetenz(1, raster_kompetenz::STATUS_ABGEDECKT, false, [
            11 => raster_kompetenz::STATUS_ABGEDECKT,
            12 => raster_kompetenz::STATUS_OFFEN,
        ]);
        $vollstaendig = new raster_kompetenz(2, raster_kompetenz::STATUS_ABGEDECKT, false, [
            21 => raster_kompetenz::STATUS_ABGEDECKT,
        ]);
        $ohnelk = new raster_kompetenz(3, raster_kompetenz::STATUS_ABGEDECKT, false);
        $eingeplant = new raster_kompetenz(4, raster_kompetenz::STATUS_EINGEPLANT, false, [
            41 => raster_kompetenz::STATUS_EINGEPLANT,
        ]);
        $offen = new raster_kompetenz(5, raster_kompetenz::STATUS_OFFEN, false);

        $this->assertSame(raster_kompetenz::ANZEIGE_TEILWEISE, $teilweise->anzeigestand());
        $this->assertSame(raster_kompetenz::ANZEIGE_VOLLSTAENDIG, $vollstaendig->anzeigestand());
        $this->assertSame(raster_kompetenz::ANZEIGE_VOLLSTAENDIG, $ohnelk->anzeigestand());
        $this->assertSame(raster_kompetenz::ANZEIGE_EINGEPLANT, $eingeplant->anzeigestand());
        $this->assertSame(raster_kompetenz::ANZEIGE_OFFEN, $offen->anzeigestand());
    }

    /**
     * Randfall: ist erst ein LK eingeplant und keines vorgekommen, bleibt
     * die Luecke bestehen - teilweise ist nur, was schon vorkam.
     */
    public function test_eingeplantes_lk_macht_noch_nicht_teilweise(): void {
        $kompetenz = new raster_kompetenz(1, raster_kompetenz::STATUS_EINGEPLANT, false, [
            11 => raster_kompetenz::STATUS_EINGEPLANT,
            12 => raster_kompetenz::STATUS_OFFEN,
        ]);

        $this->assertSame(raster_kompetenz::ANZEIGE_EINGEPLANT, $kompetenz->anzeigestand());
        $this->assertFalse($kompetenz->ist_abgedeckt());
    }
}
