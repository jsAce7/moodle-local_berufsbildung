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
 * Tests fuer die geplante Aufgabe, die einen veralteten Versetzungsplan meldet.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\task;

use advanced_testcase;
use local_berufsbildung\versetzungsplan\import_service;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\task\pruefe_planalterung::class)]
/**
 * Tests fuer pruefe_planalterung.
 *
 * Die Regeln prueft versetzungsplan\alterung_service_test. Hier nur, dass
 * die Aufgabe sie zum heutigen Zeitpunkt anwendet.
 *
 * @covers \local_berufsbildung\task\pruefe_planalterung
 */
final class pruefe_planalterung_test extends advanced_testcase {
    public function test_meldet_einen_veralteten_plan_den_site_admins(): void {
        $this->resetAfterTest();
        set_config('versetzungsplan_alterung_meldung', 1, 'local_berufsbildung');
        set_config('versetzungsplan_alterung_tage', 10, 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user(['email' => 'anna@firma.ch']);
        $berufsbildner = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $generator->create_zuordnung(['berufsbildnerid' => (int) $berufsbildner->id, 'lernendeid' => (int) $lernende->id]);
        $generator->create_plan_lieferung([
            'emails' => ['anna@firma.ch'],
            'block' => '4',
            'kw_von' => '2027-W15',
            'kw_bis' => '2027-W15',
        ]);
        $import = (new import_service())->letzter_erfolgreicher_import();
        $import->set('zeitpunkt', time() - 11 * DAYSECS);
        $import->update();
        $sink = $this->redirectMessages();

        $this->expectOutputString("local_berufsbildung: veralteter Versetzungsplan an 1 Person(en) gemeldet.\n");
        (new pruefe_planalterung())->execute();

        $this->assertCount(1, $sink->get_messages());
    }

    public function test_aktueller_plan_wird_nicht_gemeldet(): void {
        $this->resetAfterTest();
        set_config('versetzungsplan_alterung_meldung', 1, 'local_berufsbildung');

        $this->expectOutputString("local_berufsbildung: veralteter Versetzungsplan an 0 Person(en) gemeldet.\n");
        (new pruefe_planalterung())->execute();
    }
}
