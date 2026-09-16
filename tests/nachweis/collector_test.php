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

defined('MOODLE_INTERNAL') || die();

// Die beiden Test-Provider liegen als Fixtures daneben: eine Klasse je
// Datei, und tests/fixtures/ wird nicht autogeladen.
require_once(__DIR__ . '/../fixtures/collector_test_provider.php');
require_once(__DIR__ . '/../fixtures/collector_test_erfassbarer_provider.php');

/**
 * Tests fuer collector.
 *
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
        $this->assertTrue($providera->wurdeaufgerufen);
        $this->assertTrue($providerb->wurdeaufgerufen);
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
            $this->assertFalse($provider->wurdeaufgerufen);
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

    public function test_historische_semesteransicht_prueft_zustaendigkeit_am_semesterende(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $semesterende = time() - WEEKSECS;
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => $semesterende - YEARSECS,
            'gueltig_bis' => $semesterende,
        ]);
        $provider = new collector_test_provider([new nachweis('uekkn', 'üK 2', $semesterende, '5.0', null, null)]);

        $nachweise = (new collector([$provider]))->get_nachweise(
            (int) $berufsbildner->id,
            (int) $lernende->id,
            $semesterende - (30 * DAYSECS),
            $semesterende,
            $semesterende
        );

        $this->assertCount(1, $nachweise);
        $this->assertTrue($provider->wurdeaufgerufen);
    }

    public function test_get_erfassen_aktionen_liefert_aktion_von_erfassbarer_quelle(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_erfassbarer_provider('/local/test/edit.php');
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertCount(1, $aktionen);
        $this->assertSame('testerfassbar', $aktionen[0]->quellekey);
        $this->assertSame('Neuer Test-Eintrag', $aktionen[0]->label);
        $this->assertStringContainsString('/local/test/edit.php', $aktionen[0]->url);
    }

    /**
     * Ein normaler `provider` ohne `erfassbare_quelle` liefert keine Aktion
     * und wird dafuer auch gar nicht erst gefragt.
     */
    public function test_get_erfassen_aktionen_ignoriert_provider_ohne_erfassbare_quelle(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_provider();
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
    }

    /**
     * Liefert die Quelle keine URL (z.B. weil die Berechtigung fehlt), gibt
     * es dafuer auch keine Aktion.
     */
    public function test_get_erfassen_aktionen_ohne_url_ergibt_keine_aktion(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $provider = new collector_test_erfassbarer_provider(null);
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $lernende->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
        $this->assertTrue($provider->erfassenwurdeaufgerufen);
    }

    /**
     * Randfall: eine fremde Person bekommt nie eine Erfassen-Aktion fuer
     * jemand anderen - unabhaengig von einer allfaelligen Zustaendigkeit.
     * Anders als bei get_nachweise() wird der Provider dafuer gar nicht erst
     * gefragt (Architekturregel 7).
     */
    public function test_get_erfassen_aktionen_fuer_fremde_person_bleibt_leer(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $fremder = $this->getDataGenerator()->create_user();

        $provider = new collector_test_erfassbarer_provider();
        $aktionen = (new collector([$provider]))->get_erfassen_aktionen((int) $fremder->id, (int) $lernende->id);

        $this->assertSame([], $aktionen);
        $this->assertFalse($provider->erfassenwurdeaufgerufen);
    }
}
