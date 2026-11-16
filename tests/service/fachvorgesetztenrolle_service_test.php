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
 * Tests fuer die Wiederherstellung der Fachvorgesetztenrolle.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use context_system;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\fachvorgesetztenrolle_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\observer::class)]
/**
 * Tests fuer fachvorgesetztenrolle_service::stelle_sicher() und den Observer
 * auf capability_unassigned.
 *
 * Ohne Argument stellt stelle_sicher() nur die Capabilities wieder her, die
 * aufsetzende Plugins ueber den Callback melden - in der CI dieses Plugins
 * keines. Wie in leitungsrolle_service_test dient deshalb eine Capability
 * dieses Plugins als Beispiel fuer den expliziten Aufruf, und fuer den
 * Observer zeigt das Kontextlevel, dass er gelaufen ist.
 *
 * @covers \local_berufsbildung\service\fachvorgesetztenrolle_service
 * @covers \local_berufsbildung\observer
 */
final class fachvorgesetztenrolle_service_test extends advanced_testcase {
    /** @var string Beispiel-Capability, die kein Plugin fuer die Rolle meldet. */
    private const CAPABILITY = 'local/berufsbildung:viewzuordnung';

    /**
     * Die ID der Fachvorgesetztenrolle, die db/install.php angelegt hat.
     *
     * @return int
     */
    private function rolle(): int {
        $roleid = (new fachvorgesetztenrolle_service())->rolle_id();
        $this->assertNotNull($roleid, 'db/install.php legt die Rolle an');

        return $roleid;
    }

    /**
     * Der Wert der Capability in der Rolle, null wenn nicht gesetzt.
     *
     * @param int $roleid
     * @param string $capability
     * @return int|null
     */
    private function wert(int $roleid, string $capability): ?int {
        global $DB;

        $wert = $DB->get_field('role_capabilities', 'permission', [
            'roleid' => $roleid,
            'contextid' => context_system::instance()->id,
            'capability' => $capability,
        ]);

        return $wert === false ? null : (int) $wert;
    }

    /**
     * Ob die Rolle global zuweisbar ist.
     *
     * @param int $roleid
     * @return bool
     */
    private function ist_global_zuweisbar(int $roleid): bool {
        return in_array(CONTEXT_SYSTEM, array_map('intval', array_values(get_role_contextlevels($roleid))), true);
    }

    public function test_stelle_sicher_setzt_fehlende_capability(): void {
        $this->resetAfterTest();
        $roleid = $this->rolle();
        $service = new fachvorgesetztenrolle_service();

        $this->assertGreaterThanOrEqual(1, $service->stelle_sicher([self::CAPABILITY]));

        $this->assertSame(CAP_ALLOW, $this->wert($roleid, self::CAPABILITY));
        $this->assertSame(0, $service->stelle_sicher([self::CAPABILITY]));
    }

    /**
     * Der Observer laeuft bei jeder entzogenen Capability der Rolle und macht
     * sie wieder global zuweisbar. Eine Capability, die kein Plugin meldet,
     * bleibt entzogen.
     */
    public function test_observer_laeuft_nach_entzogener_capability(): void {
        $this->resetAfterTest();
        $roleid = $this->rolle();
        $systemid = context_system::instance()->id;
        $this->assertNotContains(self::CAPABILITY, (new fachvorgesetztenrolle_service())->gemeldete_capabilities());
        assign_capability(self::CAPABILITY, CAP_ALLOW, $roleid, $systemid);
        set_role_contextlevels($roleid, []);

        unassign_capability(self::CAPABILITY, $roleid, $systemid);

        $this->assertTrue($this->ist_global_zuweisbar($roleid));
        $this->assertNull($this->wert($roleid, self::CAPABILITY));
    }

    /**
     * Eine gemeldete Capability ist nach dem Entziehen sofort wieder da.
     * Braucht ein aufsetzendes Plugin, das Capabilities fuer die Rolle meldet.
     */
    public function test_observer_stellt_gemeldete_capability_wieder_her(): void {
        $this->resetAfterTest();
        $roleid = $this->rolle();
        $systemid = context_system::instance()->id;
        $gemeldet = array_values(array_filter(
            (new fachvorgesetztenrolle_service())->gemeldete_capabilities(),
            static fn(string $capability): bool => (bool) get_capability_info($capability)
        ));
        if (empty($gemeldet)) {
            $this->markTestSkipped('Kein installiertes Plugin meldet Capabilities fuer die Fachvorgesetztenrolle.');
        }
        assign_capability($gemeldet[0], CAP_ALLOW, $roleid, $systemid, true);

        unassign_capability($gemeldet[0], $roleid, $systemid);

        $this->assertSame(CAP_ALLOW, $this->wert($roleid, $gemeldet[0]));
    }

    /**
     * Der Observer laesst die Fachvorgesetztenrolle bei anderen Rollen in Ruhe.
     */
    public function test_andere_rolle_loest_nichts_aus(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = $this->rolle();
        set_role_contextlevels($roleid, []);
        $manager = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

        unassign_capability(self::CAPABILITY, $manager);

        $this->assertFalse($this->ist_global_zuweisbar($roleid));
    }
}
