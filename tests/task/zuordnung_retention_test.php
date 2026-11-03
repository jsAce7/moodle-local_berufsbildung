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
 * Tests fuer die geplante Aufgabe zur Loeschung abgelaufener Zuordnungen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\task;

use advanced_testcase;
use local_berufsbildung\persistent\aufbewahrung;
use local_berufsbildung\persistent\zuordnung;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\task\zuordnung_retention::class)]
/**
 * Tests fuer zuordnung_retention.
 *
 * Die Regeln der Loeschung prueft service\zuordnung_retention_service_test.
 * Hier geht es darum, dass die Aufgabe sie tatsaechlich anwendet - sie
 * loescht endgueltig und laeuft ohne Aufsicht.
 *
 * @covers \local_berufsbildung\task\zuordnung_retention
 */
final class zuordnung_retention_test extends advanced_testcase {
    /**
     * Legt eine lernende Person mit laufender Zuordnung an.
     *
     * @param string $jahrgang
     * @return int userid der lernenden Person
     */
    private function lege_lernende_an(string $jahrgang): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $lernende = $generator->create_lernende(['beruf' => 'AU_EFZ', 'jahrgang' => $jahrgang]);
        $berufsbildner = $this->getDataGenerator()->create_user();
        $generator->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
        ]);

        return (int) $lernende->id;
    }

    public function test_loescht_nur_abgelaufene_ohne_aufbewahrungspflicht_und_meldet_die_zahlen(): void {
        $this->resetAfterTest();

        $abgelaufen = $this->lege_lernende_an('2010');
        $aufbewahrt = $this->lege_lernende_an('2010');
        (new aufbewahrung(0, (object) [
            'lernendeid' => $aufbewahrt,
            'grund' => 'Laufendes Verfahren',
            'gueltig_von' => strtotime('-1 year'),
            'gueltig_bis' => null,
        ]))->create();
        $laufend = $this->lege_lernende_an((string) date('Y'));

        $this->expectOutputString('local_berufsbildung: 3 Person(en) geprueft, 1 geloescht, '
            . "1 wegen Aufbewahrungspflicht uebersprungen.\n");
        (new zuordnung_retention())->execute();

        $this->assertCount(0, zuordnung::get_records(['lernendeid' => $abgelaufen]));
        $this->assertCount(1, zuordnung::get_records(['lernendeid' => $aufbewahrt]));
        $this->assertCount(1, zuordnung::get_records(['lernendeid' => $laufend]));
    }

    public function test_ohne_zuordnungen_loescht_nichts(): void {
        $this->resetAfterTest();

        $this->expectOutputString('local_berufsbildung: 0 Person(en) geprueft, 0 geloescht, '
            . "0 wegen Aufbewahrungspflicht uebersprungen.\n");
        (new zuordnung_retention())->execute();
    }
}
