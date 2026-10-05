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
 * Tests fuer die Absicherung der Leitungsrolle.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use context_system;

/**
 * Tests fuer leitungsrolle_service und den Observer, der ihn ausloest.
 *
 * Als Beispiel dient eine Capability dieses Plugins: ein aufsetzendes Plugin
 * ist in seiner CI nicht installiert.
 *
 * @covers \local_berufsbildung\service\leitungsrolle_service
 * @covers \local_berufsbildung\observer
 */
final class leitungsrolle_service_test extends advanced_testcase {
    /** @var string Beispiel-Capability. */
    private const CAPABILITY = 'local/berufsbildung:viewzuordnung';

    /**
     * Die ID der Leitungsrolle, die db/install.php angelegt hat.
     *
     * @return int
     */
    private function rolle(): int {
        $roleid = (new leitungsrolle_service())->rolle_id();
        $this->assertNotNull($roleid, 'db/install.php legt die Rolle an');

        return $roleid;
    }

    /**
     * Der Wert der Capability in der Rolle, null wenn nicht gesetzt.
     *
     * @param int $roleid
     * @return int|null
     */
    private function wert(int $roleid): ?int {
        global $DB;

        $wert = $DB->get_field('role_capabilities', 'permission', [
            'roleid' => $roleid,
            'contextid' => context_system::instance()->id,
            'capability' => self::CAPABILITY,
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

    public function test_stellt_kontextlevel_und_capability_wieder_her(): void {
        $this->resetAfterTest();
        $roleid = $this->rolle();
        set_role_contextlevels($roleid, []);

        $anzahl = (new leitungsrolle_service())->stelle_sicher([self::CAPABILITY]);

        $this->assertSame(2, $anzahl);
        $this->assertTrue($this->ist_global_zuweisbar($roleid));
        $this->assertSame(CAP_ALLOW, $this->wert($roleid));
    }

    /**
     * Ein bewusst entzogenes Recht ("Verhindern") bleibt entzogen.
     */
    public function test_verhindern_bleibt_stehen(): void {
        $this->resetAfterTest();
        $roleid = $this->rolle();
        assign_capability(self::CAPABILITY, CAP_PREVENT, $roleid, context_system::instance()->id, true);

        $anzahl = (new leitungsrolle_service())->stelle_sicher([self::CAPABILITY]);

        $this->assertSame(0, $anzahl);
        $this->assertSame(CAP_PREVENT, $this->wert($roleid));
    }

    /**
     * Randfall: die Capability eines deinstallierten Plugins.
     */
    public function test_unbekannte_capability_wird_uebersprungen(): void {
        $this->resetAfterTest();
        $this->rolle();

        $this->assertSame(0, (new leitungsrolle_service())->stelle_sicher(['local/gibtesnicht:sehen']));
    }

    /**
     * Randfall: die Rolle fehlt oder hat einen anderen Kurznamen bekommen.
     */
    public function test_ohne_rolle_passiert_nichts(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->set_field('role', 'shortname', 'umbenannt', ['id' => $this->rolle()]);

        $this->assertSame(0, (new leitungsrolle_service())->stelle_sicher([self::CAPABILITY]));
    }

    /**
     * "Rolle zuruecksetzen" in der Rollenverwaltung: Moodle speichert dann
     * leere Kontextlevel und setzt jede Capability auf "Nicht gesetzt" - in
     * dieser Reihenfolge, wie core_role_define_role_table_advanced::save_changes().
     * Der Observer macht die Rolle danach wieder global zuweisbar.
     */
    public function test_zuruecksetzen_macht_die_rolle_wieder_zuweisbar(): void {
        $this->resetAfterTest();
        $roleid = $this->rolle();
        $systemid = context_system::instance()->id;
        assign_capability(self::CAPABILITY, CAP_ALLOW, $roleid, $systemid);

        set_role_contextlevels($roleid, []);
        assign_capability(self::CAPABILITY, CAP_INHERIT, $roleid, $systemid, true);

        $this->assertTrue($this->ist_global_zuweisbar($roleid));
    }

    /**
     * Der Observer laesst andere Rollen in Ruhe.
     */
    public function test_andere_rollen_bleiben_unberuehrt(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = $this->rolle();
        set_role_contextlevels($roleid, []);
        $manager = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

        unassign_capability(self::CAPABILITY, $manager);

        $this->assertFalse($this->ist_global_zuweisbar($roleid));
    }
}
