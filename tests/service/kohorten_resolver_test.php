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
 * Tests fuer den Kohorten-Resolver.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Tests fuer kohorten_resolver.
 *
 * @covers \local_berufsbildung\service\kohorten_resolver
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\kohorten_resolver::class)]
final class kohorten_resolver_test extends advanced_testcase {
    public function test_liefert_eindeutige_mitglieder_ueber_mehrere_kohorten(): void {
        $this->resetAfterTest();

        $kohorte1 = $this->getDataGenerator()->create_cohort();
        $kohorte2 = $this->getDataGenerator()->create_cohort();
        $person1 = $this->getDataGenerator()->create_user();
        $person2 = $this->getDataGenerator()->create_user();

        cohort_add_member($kohorte1->id, $person1->id);
        cohort_add_member($kohorte2->id, $person1->id);
        cohort_add_member($kohorte2->id, $person2->id);

        $mitglieder = (new kohorten_resolver())->mitglieder([(int) $kohorte1->id, (int) $kohorte2->id]);
        sort($mitglieder);

        $erwartet = [(int) $person1->id, (int) $person2->id];
        sort($erwartet);
        $this->assertSame($erwartet, $mitglieder);
    }

    public function test_leere_kohortenliste_gibt_leeres_array(): void {
        $this->resetAfterTest();

        $this->assertSame([], (new kohorten_resolver())->mitglieder([]));
    }
}
