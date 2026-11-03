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
 * Tests fuer die Webservice-Funktion des Versetzungsplan-Imports.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\external;

use advanced_testcase;
use context_system;
use core_external\external_api;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\einsatz;
use local_berufsbildung\persistent\plan_import;
use stdClass;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\external\import_versetzungsplan::class)]
/**
 * Tests fuer import_versetzungsplan.
 *
 * Die Verarbeitung selbst prueft versetzungsplan\import_service_test. Hier
 * geht es um das, was nur der Webservice tut: Capability, Base64 und die
 * Weitergabe der Parameter und des Ergebnisses.
 *
 * @covers \local_berufsbildung\external\import_versetzungsplan
 */
final class import_versetzungsplan_test extends advanced_testcase {
    /**
     * Meldet ein Dienstkonto an, das wie das Konto des Versetzungsplan-
     * Skripts nur importplan hat.
     *
     * @return stdClass
     */
    private function melde_dienstkonto_an(): stdClass {
        $dienstkonto = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/berufsbildung:importplan', CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, (int) $dienstkonto->id, context_system::instance()->id);
        $this->setUser($dienstkonto);

        return $dienstkonto;
    }

    /**
     * Legt Lernende person0@firma.ch, person1@firma.ch, ... mit laufender
     * Zuordnung an.
     *
     * @param int $anzahl
     */
    private function lege_lernende_an(int $anzahl): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        for ($i = 0; $i < $anzahl; $i++) {
            $lernende = $this->getDataGenerator()->create_user(['email' => "person{$i}@firma.ch"]);
            $berufsbildner = $this->getDataGenerator()->create_user();
            $generator->create_zuordnung([
                'berufsbildnerid' => (int) $berufsbildner->id,
                'lernendeid' => (int) $lernende->id,
                'gueltig_von' => strtotime('-1 year'),
            ]);
        }
    }

    /**
     * Eine Lieferung mit je einem Einsatz fuer person0 bis person($anzahl - 1),
     * so wie das Skript sie sendet: Base64-kodiert.
     *
     * @param int $anzahl
     * @param string $kwvon
     * @param string $kwbis
     * @return string
     */
    private function lieferung(int $anzahl, string $kwvon = '2027-W15', string $kwbis = '2027-W16'): string {
        return base64_encode("email;block;kw_von;kw_bis\n"
            . implode("\n", array_map(static fn ($i) => "person{$i}@firma.ch;4;{$kwvon};{$kwbis}", range(0, $anzahl - 1)))
            . "\n");
    }

    /**
     * Ruft den Webservice auf und prueft das Ergebnis gegen execute_returns(),
     * wie es der Webservice-Server tut.
     *
     * @param string $csvdaten
     * @param bool $testlauf
     * @param bool $rueckgangbestaetigt
     * @return array
     */
    private function rufe_auf(string $csvdaten, bool $testlauf = false, bool $rueckgangbestaetigt = false): array {
        $ergebnis = import_versetzungsplan::execute($csvdaten, 'skript', $testlauf, $rueckgangbestaetigt);

        return external_api::clean_returnvalue(import_versetzungsplan::execute_returns(), $ergebnis);
    }

    public function test_dienstkonto_importiert_und_wird_als_ausfuehrend_festgehalten(): void {
        $this->resetAfterTest();

        $dienstkonto = $this->melde_dienstkonto_an();
        $this->lege_lernende_an(1);
        // Damit der Block schon bekannt ist und der Status 'ok' bleibt.
        (new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]))->create();

        $ergebnis = $this->rufe_auf($this->lieferung(1));

        $this->assertSame('ok', $ergebnis['status']);
        $this->assertFalse($ergebnis['unveraendert']);
        $this->assertSame(1, $ergebnis['personen_verarbeitet']);
        $this->assertSame(1, $ergebnis['einsaetze_erzeugt']);
        $this->assertSame([], $ergebnis['protokoll']);
        $this->assertCount(1, einsatz::get_records([]));

        $importe = plan_import::get_records([]);
        $this->assertCount(1, $importe);
        $import = reset($importe);
        $this->assertSame('skript', $import->get('quelle'));
        $this->assertSame((int) $dienstkonto->id, (int) $import->get('ausgefuehrt_von'));
    }

    /**
     * importplan hat keinen Archetyp: auch eine Person, die sonst alles in
     * der Berufsbildung verwalten darf, kann ohne die Capability nichts
     * einspielen.
     */
    public function test_ohne_capability_wird_abgewiesen_und_nichts_geschrieben(): void {
        $this->resetAfterTest();

        $this->lege_lernende_an(1);
        $this->setUser($this->getDataGenerator()->create_user());

        try {
            $this->rufe_auf($this->lieferung(1));
            $this->fail('Ohne local/berufsbildung:importplan haette der Aufruf abgewiesen werden muessen.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('local/berufsbildung:importplan'), $e->getMessage());
        }

        $this->assertCount(0, einsatz::get_records([]));
        $this->assertCount(0, plan_import::get_records([]));
    }

    public function test_ungueltiges_base64_wird_abgewiesen(): void {
        $this->resetAfterTest();

        $this->melde_dienstkonto_an();

        $this->expectException(\invalid_parameter_exception::class);
        $this->rufe_auf('email;block;kw_von;kw_bis');
    }

    public function test_testlauf_wird_weitergegeben_und_schreibt_nichts(): void {
        $this->resetAfterTest();

        $this->melde_dienstkonto_an();
        $this->lege_lernende_an(1);

        $ergebnis = $this->rufe_auf($this->lieferung(1), true);

        $this->assertSame(1, $ergebnis['einsaetze_erzeugt']);
        $this->assertCount(0, einsatz::get_records([]));
        $this->assertCount(0, plan_import::get_records([]));
    }

    /**
     * Ein deutlicher Rueckgang kommt als 'zur_freigabe' beim Skript an. Mit
     * rueckgang_bestaetigt wird dieselbe Lieferung verarbeitet.
     */
    public function test_rueckgang_wartet_auf_freigabe_ausser_er_ist_bestaetigt(): void {
        $this->resetAfterTest();

        $this->melde_dienstkonto_an();
        $this->lege_lernende_an(5);
        $this->redirectMessages();
        $this->rufe_auf($this->lieferung(5));

        $zurueckgehalten = $this->rufe_auf($this->lieferung(1, '2027-W20', '2027-W21'));
        $this->assertSame('zur_freigabe', $zurueckgehalten['status']);
        $this->assertSame(0, $zurueckgehalten['einsaetze_erzeugt']);

        $bestaetigt = $this->rufe_auf($this->lieferung(1, '2027-W20', '2027-W21'), false, true);
        $this->assertNotSame('zur_freigabe', $bestaetigt['status']);
        $this->assertSame(1, $bestaetigt['einsaetze_erzeugt']);
    }
}
