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
 * Tests fuer task\bereinige_planverlauf.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\task;

use advanced_testcase;
use local_berufsbildung\persistent\einsatz_aenderung;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\task\bereinige_planverlauf::class)]
/**
 * Tests fuer bereinige_planverlauf.
 *
 * Die Grenze prueft versetzungsplan\import_service_test. Hier nur, dass die
 * Aufgabe sie zum heutigen Zeitpunkt anwendet.
 *
 * @covers \local_berufsbildung\task\bereinige_planverlauf
 */
final class bereinige_planverlauf_test extends advanced_testcase {
    public function test_loescht_was_aelter_als_ein_jahr_ist(): void {
        global $DB;
        $this->resetAfterTest();

        $ids = [];
        foreach (['alt' => strtotime('-13 months'), 'neu' => strtotime('-11 months')] as $name => $erstellt) {
            $aenderung = new einsatz_aenderung(0, (object) [
                'importid' => 1, 'userid' => 2, 'art' => einsatz_aenderung::ENTFAELLT,
                'block' => '4', 'kw_von' => '2027-W15', 'kw_bis' => '2027-W16', 'von' => time(),
            ]);
            $aenderung->create();
            $DB->set_field(einsatz_aenderung::TABLE, 'timecreated', $erstellt, ['id' => (int) $aenderung->get('id')]);
            $ids[$name] = (int) $aenderung->get('id');
        }

        $this->expectOutputString(
            "local_berufsbildung: Versetzungsplan-Verlauf bereinigt, 1 geaenderte Einsaetze geloescht, 0 Protokolle geleert.\n"
        );
        (new bereinige_planverlauf())->execute();

        $this->assertFalse(einsatz_aenderung::record_exists($ids['alt']));
        $this->assertTrue(einsatz_aenderung::record_exists($ids['neu']));
    }
}
