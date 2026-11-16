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
 * Tests fuer die geplante Aufgabe sync_role_assignments, Teil Fachvorgesetzte.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\task;

use advanced_testcase;
use context_system;
use local_berufsbildung\service\fachvorgesetztenrolle_service;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\task\sync_role_assignments::class)]
/**
 * Tests fuer sync_role_assignments.
 *
 * Die Aufgabe ist das Sicherheitsnetz fuer Aenderungen ohne Event: was
 * fehlt, wird hier ohne Observer entfernt (Event-Umleitung bzw. ein
 * Kontextlevel, dessen Aenderung kein Event ausloest).
 *
 * @covers \local_berufsbildung\task\sync_role_assignments
 */
final class sync_role_assignments_test extends advanced_testcase {
    public function test_stellt_fachvorgesetztenrolle_wieder_her(): void {
        global $DB;
        $this->resetAfterTest();
        $service = new fachvorgesetztenrolle_service();
        $roleid = $service->rolle_id();
        $this->assertNotNull($roleid);
        $systemid = context_system::instance()->id;
        $gemeldet = array_values(array_filter(
            $service->gemeldete_capabilities(),
            static fn(string $capability): bool => (bool) get_capability_info($capability)
        ));

        // Alles entfernen, ohne dass der Observer es gleich wieder setzt.
        set_role_contextlevels($roleid, []);
        $sink = $this->redirectEvents();
        foreach ($gemeldet as $capability) {
            unassign_capability($capability, $roleid, $systemid);
        }
        $sink->close();
        foreach ($gemeldet as $capability) {
            $this->assertFalse($DB->record_exists('role_capabilities', ['roleid' => $roleid, 'capability' => $capability]));
        }

        $this->expectOutputRegex('/Fachvorgesetztenrolle bei 0 Person\(en\) zugewiesen, bei 0 entzogen\./');
        (new sync_role_assignments())->execute();

        $this->assertContains(CONTEXT_SYSTEM, array_map('intval', array_values(get_role_contextlevels($roleid))));
        foreach ($gemeldet as $capability) {
            $this->assertSame(CAP_ALLOW, (int) $DB->get_field('role_capabilities', 'permission', [
                'roleid' => $roleid,
                'contextid' => $systemid,
                'capability' => $capability,
            ]));
        }
    }

    public function test_weist_fehlende_fachvorgesetztenrolle_zu(): void {
        global $DB;
        $this->resetAfterTest();
        $blockid = (int) $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')
            ->create_block(['nummer' => 'A1', 'ist_betrieb' => true])->get('id');
        $fv = (int) $this->getDataGenerator()->create_user()->id;
        // Eintrag ohne Service: die Rolle fehlt.
        $DB->insert_record('local_berufsbildung_block_fv', (object) [
            'blockid' => $blockid, 'userid' => $fv, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->expectOutputRegex('/Fachvorgesetztenrolle bei 1 Person\(en\) zugewiesen, bei 0 entzogen\./');
        (new sync_role_assignments())->execute();

        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => (new fachvorgesetztenrolle_service())->rolle_id(),
            'userid' => $fv,
            'contextid' => context_system::instance()->id,
            'component' => 'local_berufsbildung',
        ]));
    }
}
