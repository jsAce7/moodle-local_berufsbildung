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
 * Tests fuer wahlpflicht_gruppe.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\wahlpflicht_gruppe::class)]
/**
 * Tests fuer wahlpflicht_gruppe.
 *
 * @covers \local_berufsbildung\wahlpflicht_gruppe
 */
final class wahlpflicht_gruppe_test extends advanced_testcase {
    /**
     * Ein Raster aus zwei Bereichen, a und d, mit Wahlpflicht-HK in
     * verschiedenen Staenden und je einer Pflicht-HK.
     *
     * @return raster_bereich[]
     */
    private function raster(): array {
        return [
            new raster_bereich(1, [
                new raster_kompetenz(11, raster_kompetenz::STATUS_ABGEDECKT, false),
                new raster_kompetenz(12, raster_kompetenz::STATUS_ABGEDECKT, true),
                new raster_kompetenz(13, raster_kompetenz::STATUS_EINGEPLANT, true),
            ], 'a'),
            new raster_bereich(2, [
                new raster_kompetenz(21, raster_kompetenz::STATUS_EINGEPLANT, true),
                new raster_kompetenz(22, raster_kompetenz::STATUS_OFFEN, true),
            ], 'd'),
        ];
    }

    /**
     * Gezaehlt werden nur Wahlpflicht-HK aus den Bereichen der Gruppe;
     * Pflicht-HK zaehlen nie.
     */
    public function test_stand_zaehlt_nur_wahlpflicht_der_eigenen_bereiche(): void {
        $this->assertSame(
            ['vollstaendig' => 1, 'teilweise' => 0, 'eingeplant' => 1],
            (new wahlpflicht_gruppe(['a', 'b', 'c'], 1))->stand($this->raster())
        );
        $this->assertSame(
            ['vollstaendig' => 0, 'teilweise' => 0, 'eingeplant' => 1],
            (new wahlpflicht_gruppe(['d'], 1))->stand($this->raster())
        );
        $this->assertSame(
            ['vollstaendig' => 1, 'teilweise' => 0, 'eingeplant' => 2],
            (new wahlpflicht_gruppe([], 3))->stand($this->raster())
        );
    }

    /**
     * Eine HK, von der erst ein Teil der LK vorkam, ist teilweise und
     * erfuellt die Gruppe noch nicht.
     */
    public function test_teilweise_erfuellt_noch_nicht(): void {
        $raster = [new raster_bereich(1, [
            new raster_kompetenz(12, raster_kompetenz::STATUS_ABGEDECKT, true, [
                121 => raster_kompetenz::STATUS_ABGEDECKT,
                122 => raster_kompetenz::STATUS_EINGEPLANT,
            ]),
        ], 'a')];
        $gruppe = new wahlpflicht_gruppe(['a'], 1);

        $this->assertSame(['vollstaendig' => 0, 'teilweise' => 1, 'eingeplant' => 0], $gruppe->stand($raster));
        $this->assertSame(1, $gruppe->offen($raster));
    }

    /**
     * Offen ist, was vom Verlangten noch nicht vollstaendig vorkam - eine
     * eingeplante HK zaehlt noch als offen. Randfall: mehr als
     * verlangt ergibt null, nicht eine negative Zahl.
     */
    public function test_offen_zaehlt_eingeplantes_noch_mit(): void {
        $this->assertSame(0, (new wahlpflicht_gruppe(['a'], 1))->offen($this->raster()));
        $this->assertSame(1, (new wahlpflicht_gruppe(['d'], 1))->offen($this->raster()));
        $this->assertSame(2, (new wahlpflicht_gruppe([], 3))->offen($this->raster()));

        $mehr = [new raster_bereich(1, [
            new raster_kompetenz(12, raster_kompetenz::STATUS_ABGEDECKT, true),
            new raster_kompetenz(14, raster_kompetenz::STATUS_ABGEDECKT, true),
        ], 'a')];
        $this->assertSame(0, (new wahlpflicht_gruppe(['a'], 1))->offen($mehr));
    }
}
