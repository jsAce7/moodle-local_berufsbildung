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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\wahlpflicht_resolver::class)]
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
     * Gruppen von Bereichen mit ihrer Anzahl - Kuerzel klein geschrieben,
     * Leerzeichen egal.
     */
    public function test_gruppen_nach_bereichen(): void {
        $gruppen = (new wahlpflicht_resolver())->gruppen('AU_EFZ', "PM_EFZ=2\r\nAU_EFZ = A, b ,c : 1 ; d:2");

        $this->assertCount(2, $gruppen);
        $this->assertSame(['a', 'b', 'c'], $gruppen[0]->bereiche);
        $this->assertSame(1, $gruppen[0]->anzahl);
        $this->assertSame(['d'], $gruppen[1]->bereiche);
        $this->assertSame(2, $gruppen[1]->anzahl);
        $this->assertTrue($gruppen[0]->umfasst('B'));
        $this->assertFalse($gruppen[0]->umfasst('d'));
    }

    /**
     * Ohne Bereiche gilt die Anzahl fuer den ganzen Beruf - die Gruppe
     * umfasst jeden Bereich.
     */
    public function test_gruppe_ohne_bereiche_gilt_fuer_den_ganzen_beruf(): void {
        $gruppen = (new wahlpflicht_resolver())->gruppen('AU_EFZ', 'AU_EFZ=3');

        $this->assertCount(1, $gruppen);
        $this->assertSame([], $gruppen[0]->bereiche);
        $this->assertSame(3, $gruppen[0]->anzahl);
        $this->assertTrue($gruppen[0]->umfasst('x'));
    }

    /**
     * Randfall: ohne Eintrag, mit leerem, nicht-numerischem oder Null-Wert
     * gibt es keine Gruppe - eine verlangte Null waere keine Aussage. Eine
     * ungueltige Gruppe faellt weg, die gueltigen daneben bleiben.
     *
     * @dataProvider ungueltige_gruppen
     * @param string $konfiguration
     * @param int $erwartet Anzahl gueltiger Gruppen
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ungueltige_gruppen')]
    public function test_ungueltige_gruppen_fallen_weg(string $konfiguration, int $erwartet): void {
        $this->assertCount($erwartet, (new wahlpflicht_resolver())->gruppen('AU_EFZ', $konfiguration));
    }

    /**
     * Konfigurationen mit ungueltigen Gruppen fuer AU_EFZ.
     *
     * @return array
     */
    public static function ungueltige_gruppen(): array {
        return [
            'leer' => ['', 0],
            'anderer beruf' => ['PM_EFZ=2', 0],
            'ohne wert' => ['AU_EFZ=', 0],
            'keine zahl' => ['AU_EFZ=drei', 0],
            'null' => ['AU_EFZ=a:0', 0],
            'negativ' => ['AU_EFZ=-1', 0],
            'eine von zwei gueltig' => ['AU_EFZ=a,b:x; d:1', 1],
        ];
    }
}
