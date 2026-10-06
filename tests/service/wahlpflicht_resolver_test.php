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
 * Tests fuer wahlpflicht_resolver.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;

/**
 * Tests fuer wahlpflicht_resolver.
 *
 * @covers \local_berufsbildung\service\wahlpflicht_resolver
 */
final class wahlpflicht_resolver_test extends advanced_testcase {
    /**
     * Die Wahlpflicht-HK des Berufs, nicht die eines anderen.
     */
    public function test_loese_auf_liefert_die_hk_des_berufs(): void {
        $this->assertSame(
            ['a.04', 'a.05'],
            (new wahlpflicht_resolver())->loese_auf('PM_EFZ', "AU_EFZ=a.01\nPM_EFZ= a.04 , a.05 ")
        );
    }

    /**
     * Die verlangte Anzahl des Berufs.
     */
    public function test_anzahl_des_berufs(): void {
        $this->assertSame(3, (new wahlpflicht_resolver())->anzahl('AU_EFZ', "PM_EFZ=2\r\nAU_EFZ = 3"));
    }

    /**
     * Randfall: ohne Eintrag, mit leerem, nicht-numerischem oder Null-Wert
     * gibt es keine Anzahl - eine verlangte Null waere keine Aussage.
     *
     * @dataProvider ungueltige_anzahl
     * @param string $konfiguration
     */
    public function test_anzahl_ohne_gueltigen_eintrag_ist_null(string $konfiguration): void {
        $this->assertNull((new wahlpflicht_resolver())->anzahl('AU_EFZ', $konfiguration));
    }

    /**
     * Konfigurationen ohne gueltige Anzahl fuer AU_EFZ.
     *
     * @return array
     */
    public static function ungueltige_anzahl(): array {
        return [
            'leer' => [''],
            'anderer beruf' => ['PM_EFZ=2'],
            'ohne wert' => ['AU_EFZ='],
            'kein zahl' => ['AU_EFZ=drei'],
            'null' => ['AU_EFZ=0'],
            'negativ' => ['AU_EFZ=-1'],
        ];
    }
}
