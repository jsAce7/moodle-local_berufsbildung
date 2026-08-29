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
 * Tests fuer den Nachweis-Collector.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\nachweis;

use advanced_testcase;
use local_berufsbildung\persistent\zuordnung;
use moodle_exception;

/**
 * Test-Provider, der jeden Aufruf protokolliert - damit sichtbar wird, ob
 * er ueberhaupt gefragt wurde (Architekturregel 7: nie ungeprueft
 * aufrufen).
 */
final class collector_test_provider implements provider {
    public bool $wurde_aufgerufen = false;

    public function __construct(private readonly array $nachweise = []) {
    }

    public function get_nachweise(int $lernendeid, int $von, int $bis): array {
        $this->wurde_aufgerufen = true;

        return $this->nachweise;
    }

    public function get_quelle_name(): string {
        return 'Test-Quelle';
    }

    public function get_quelle_key(): string {
        return 'test';
    }
}

/**
 * @covers \local_berufsbildung\nachweis\collector
 */
final class collector_test extends advanced_testcase {

    private function lege_zuordnung_an(int $berufsbildnerid, int $lernendeid): void {
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => $berufsbildnerid,
            'lernendeid' => $lernendeid,
            'gueltig_von' => time() - YEARSECS,
        ]);
    }

    public function test_sammelt_nachweise_von_allen_registrierten_providern(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id);

        $providera = new collector_test_provider([
            new nachweis('a', 'Erster', time() - 100, null, null, null),
        ]);
        $providerb = new collector_test_provider([
            new nachweis('b', 'Zweiter', time(), null, null, null),
        ]);

        $collector = new collector([$providera, $providerb]);
        $nachweise = $collector->get_nachweise((int) $berufsbildner->id, (int) $lernende->id, 0, time() + 1);

        $this->assertCount(2, $nachweise);
        $this->assertTrue($providera->wurde_aufgerufen);
        $this->assertTrue($providerb->wurde_aufgerufen);
    }

    public function test_sortiert_neueste_zuerst(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id);

        $provider = new collector_test_provider([
            new nachweis('a', 'Aelter', time() - 1000, null, null, null),
            new nachweis('a', 'Neuer', time(), null, null, null),
        ]);

        $collector = new collector([$provider]);
        $nachweise = $collector->get_nachweise((int) $berufsbildner->id, (int) $lernende->id, 0, time() + 1);

        $this->assertSame('Neuer', $nachweise[0]->bezeichnung);
        $this->assertSame('Aelter', $nachweise[1]->bezeichnung);
    }

    /**
     * Randfall: eine fremde Person ohne Zuordnung darf gar nicht erst bei
     * den Providern nachfragen - die Pruefung passiert vorher.
     */
    public function test_fremde_person_bekommt_exception_und_provider_wird_nicht_gefragt(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $fremder = $this->getDataGenerator()->create_user();
        $provider = new collector_test_provider([new nachweis('a', 'X', time(), null, null, null)]);
        $collector = new collector([$provider]);

        $this->expectException(moodle_exception::class);

        try {
            $collector->get_nachweise((int) $fremder->id, (int) $lernende->id, 0, time());
        } finally {
            $this->assertFalse($provider->wurde_aufgerufen);
        }
    }

    public function test_eigene_person_braucht_keine_zuordnung(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $provider = new collector_test_provider([new nachweis('a', 'X', time(), null, null, null)]);
        $collector = new collector([$provider]);

        $nachweise = $collector->get_nachweise((int) $lernende->id, (int) $lernende->id, 0, time() + 1);

        $this->assertCount(1, $nachweise);
    }
}
