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
 * Tests fuer die Faelligkeit.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\nachweis\faelligkeit::class)]
/**
 * Ueberfaellig erst ab dem Tag nach der Frist.
 *
 * @covers \local_berufsbildung\nachweis\faelligkeit
 */
final class faelligkeit_test extends advanced_testcase {
    /**
     * Ein Zeitpunkt am 22. Oktober 2026 in der Zeitzone des Servers.
     *
     * @param string $uhrzeit
     * @return int
     */
    private function am(string $uhrzeit): int {
        $this->setTimezone('Europe/Zurich');

        return (new \DateTime('2026-10-22 ' . $uhrzeit, \core_date::get_server_timezone_object()))->getTimestamp();
    }

    /**
     * Eine Faelligkeit mit Frist.
     *
     * @param int $datum
     * @return faelligkeit
     */
    private function faelligkeit(int $datum): faelligkeit {
        return new faelligkeit('test', 'Test', $datum, 'https://example.com');
    }

    public function test_frist_um_mitternacht_gilt_den_ganzen_tag(): void {
        $this->resetAfterTest();
        $frist = $this->am('00:00:00');
        $folgetag = $this->am('00:00:00') + DAYSECS;

        $this->assertSame($folgetag, faelligkeit::ueberfaellig_ab($frist));
        $this->assertFalse($this->faelligkeit($frist)->ist_ueberfaellig($this->am('12:00:00')));
        $this->assertFalse($this->faelligkeit($frist)->ist_ueberfaellig($folgetag - 1));
        $this->assertTrue($this->faelligkeit($frist)->ist_ueberfaellig($folgetag));
    }

    public function test_frist_mitten_am_tag_gilt_bis_zum_tagesende(): void {
        $this->resetAfterTest();
        $frist = $this->am('14:30:00');

        $this->assertFalse($this->faelligkeit($frist)->ist_ueberfaellig($frist + HOURSECS));
        $this->assertTrue($this->faelligkeit($frist)->ist_ueberfaellig($this->am('00:00:00') + DAYSECS));
    }

    /**
     * Semesterenden liegen auf der letzten Sekunde des Tages: sie werden
     * wie bisher eine Sekunde spaeter ueberfaellig.
     */
    public function test_frist_in_der_letzten_sekunde_unveraendert(): void {
        $this->resetAfterTest();
        $frist = $this->am('23:59:59');

        $this->assertSame($frist + 1, faelligkeit::ueberfaellig_ab($frist));
        $this->assertFalse($this->faelligkeit($frist)->ist_ueberfaellig($frist));
        $this->assertTrue($this->faelligkeit($frist)->ist_ueberfaellig($frist + 1));
    }

    /**
     * Am Tag der Zeitumstellung (25. Oktober 2026, 25 Stunden) beginnt der
     * Folgetag trotzdem um Mitternacht.
     */
    public function test_zeitumstellung(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Zurich');
        $zone = \core_date::get_server_timezone_object();
        $frist = (new \DateTime('2026-10-25 08:00:00', $zone))->getTimestamp();

        $this->assertSame(
            (new \DateTime('2026-10-26 00:00:00', $zone))->getTimestamp(),
            faelligkeit::ueberfaellig_ab($frist)
        );
    }
}
