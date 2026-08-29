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
 * Tests fuer den Zuordnungs-Retention-Service.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use local_berufsbildung\api;
use local_berufsbildung\persistent\aufbewahrung;
use local_berufsbildung\persistent\zuordnung;

/**
 * @covers \local_berufsbildung\service\zuordnung_retention_service
 */
final class zuordnung_retention_service_test extends advanced_testcase {

    private function lege_zuordnung_an(int $berufsbildnerid, int $lernendeid, ?int $gueltigbis = null): zuordnung {
        $zuordnung = new zuordnung(0, (object) [
            'berufsbildnerid' => $berufsbildnerid,
            'lernendeid' => $lernendeid,
            'beruf' => 'AU_EFZ',
            'rolle' => 'hauptverantwortlich',
            'gueltig_von' => strtotime('-4 years'),
            'gueltig_bis' => $gueltigbis,
        ]);
        $zuordnung->create();

        return $zuordnung;
    }

    public function test_bereinige_abgelaufene_loescht_ohne_aufbewahrungspflicht(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2010',
        ]);
        // Eine noch laufende Zuordnung - die Loeschung darf sich nicht nur
        // auf bereits beendete Zeilen beschraenken (docs/plan.md §13.4).
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, null);

        $ergebnis = (new zuordnung_retention_service())->bereinige_abgelaufene();

        $this->assertSame(1, $ergebnis['geloescht']);
        $this->assertSame(0, $ergebnis['uebersprungen_aufbewahrung']);
        $this->assertCount(0, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    public function test_bereinige_abgelaufene_behaelt_mit_aufbewahrungspflicht(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2010',
        ]);
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, null);
        (new aufbewahrung(0, (object) [
            'lernendeid' => (int) $lernende->id,
            'grund' => 'Laufendes Verfahren',
            'gueltig_von' => strtotime('-1 year'),
            'gueltig_bis' => null,
        ]))->create();

        $ergebnis = (new zuordnung_retention_service())->bereinige_abgelaufene();

        $this->assertSame(0, $ergebnis['geloescht']);
        $this->assertSame(1, $ergebnis['uebersprungen_aufbewahrung']);
        $this->assertCount(1, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    public function test_bereinige_abgelaufene_behaelt_innerhalb_der_frist(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        // Jahrgang so gewaehlt, dass die Ausbildung genau am letzten
        // 31. Juli endete - das ist unabhaengig vom Kalendermonat, in dem
        // der Test laeuft, immer weniger als 12 Monate her (derselbe
        // Kniff wie in api_test::test_get_ausbildungsstand()).
        $jetzt = time();
        $jahrgangbasis = (int) date('n', $jetzt) >= 8 ? (int) date('Y', $jetzt) : (int) date('Y', $jetzt) - 1;
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => (string) ($jahrgangbasis - 4),
        ]);
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, null);

        $ergebnis = (new zuordnung_retention_service())->bereinige_abgelaufene();

        $this->assertSame(0, $ergebnis['geloescht']);
        $this->assertCount(1, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    public function test_bereinige_abgelaufene_ueberspringt_unresolvable_stand(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-5 years'));

        $ergebnis = (new zuordnung_retention_service())->bereinige_abgelaufene();

        $this->assertSame(0, $ergebnis['geloescht']);
        $this->assertCount(1, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    public function test_loesche_fuer_lernende_entfernt_abgelaufene_aufbewahrung(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 month'));
        (new aufbewahrung(0, (object) [
            'lernendeid' => (int) $lernende->id,
            'grund' => 'Abgelaufene Ausnahme',
            'gueltig_von' => strtotime('-2 years'),
            'gueltig_bis' => strtotime('-1 year'),
        ]))->create();

        $anzahl = (new zuordnung_retention_service())->loesche_fuer_lernende((int) $lernende->id);

        $this->assertSame(1, $anzahl);
        $this->assertCount(0, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
        $this->assertCount(0, aufbewahrung::get_records(['lernendeid' => (int) $lernende->id]));
    }
}
