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
 * Tests fuer output\plan_aenderungen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\output\plan_aenderungen::class)]
/**
 * Tests fuer plan_aenderungen.
 *
 * @covers \local_berufsbildung\output\plan_aenderungen
 */
final class plan_aenderungen_test extends advanced_testcase {
    /**
     * Nach Person gruppiert und alphabetisch, je Person in der gelieferten
     * Reihenfolge; ein Einsatz ueber eine einzige Woche zeigt sie nur einmal.
     */
    public function test_personen_gruppiert_und_alphabetisch(): void {
        $this->resetAfterTest();

        $ben = $this->getDataGenerator()->create_user(['firstname' => 'Ben', 'lastname' => 'Zwei']);
        $anna = $this->getDataGenerator()->create_user(['firstname' => 'Anna', 'lastname' => 'Eins']);

        $personen = plan_aenderungen::personen([
            ['userid' => (int) $ben->id, 'art' => 'entfaellt', 'block' => '4', 'kw_von' => '2027-W15', 'kw_bis' => '2027-W16'],
            ['userid' => (int) $ben->id, 'art' => 'neu', 'block' => '5', 'kw_von' => '2027-W20', 'kw_bis' => '2027-W20'],
            ['userid' => (int) $anna->id, 'art' => 'neu', 'block' => '4', 'kw_von' => '2027-W17', 'kw_bis' => '2027-W18'],
        ]);

        $this->assertSame([fullname($anna), fullname($ben)], array_column($personen, 'name'));
        $this->assertSame(
            [
                ['entfaellt' => true, 'block' => '4', 'zeitraum' => '2027-W15 – 2027-W16'],
                ['entfaellt' => false, 'block' => '5', 'zeitraum' => '2027-W20'],
            ],
            $personen[1]['einsaetze']
        );
    }

    public function test_ohne_aenderungen_keine_personen(): void {
        $this->assertSame([], plan_aenderungen::personen([]));
    }
}
