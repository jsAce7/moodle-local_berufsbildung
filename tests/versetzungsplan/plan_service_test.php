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
 * Tests fuer die Versetzungsplan-Abfrage.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use advanced_testcase;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;
use local_berufsbildung\persistent\einsatz;

/**
 * Tests fuer plan_service.
 *
 * @covers \local_berufsbildung\versetzungsplan\plan_service
 */
final class plan_service_test extends advanced_testcase {
    private function lege_block_an(string $nummer, bool $istbetrieb = true): block {
        $block = new block(0, (object) ['nummer' => $nummer, 'name' => $nummer, 'ist_betrieb' => $istbetrieb, 'aktiv' => true]);
        $block->create();

        return $block;
    }

    private function lege_einsatz_an(int $userid, int $blockid, int $von, int $bis): einsatz {
        $einsatz = new einsatz(0, (object) [
            'userid' => $userid, 'blockid' => $blockid, 'von' => $von, 'bis' => $bis,
            'kw_von' => '2027-W01', 'kw_bis' => '2027-W02', 'importid' => 0,
        ]);
        $einsatz->create();

        return $einsatz;
    }

    public function test_get_einsaetze_liefert_nur_die_eigenen_sortiert(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $andere = $this->getDataGenerator()->create_user();
        $block = $this->lege_block_an('4');

        $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), 2000, 3000);
        $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), 1000, 1500);
        $this->lege_einsatz_an((int) $andere->id, (int) $block->get('id'), 1000, 3000);

        $einsaetze = (new plan_service())->get_einsaetze((int) $lernende->id);

        $this->assertCount(2, $einsaetze);
        $werte = array_values(array_map(static fn (einsatz $e) => $e->get('von'), $einsaetze));
        $this->assertSame([1000, 2000], $werte);
    }

    /**
     * Randfall: ein Einsatz, der sich nur teilweise mit dem Zeitraum
     * ueberschneidet, zaehlt trotzdem mit - explizites Abnahmekriterium
     * aus docs/konzept.md §5.6.
     */
    public function test_get_einsaetze_teilweise_ueberschneidung_zaehlt_randfall(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $block = $this->lege_block_an('4');

        // Beginnt vor dem Zeitraum, endet mitten drin.
        $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), 1000, 1500);
        // Beginnt mitten im Zeitraum, endet danach.
        $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), 2500, 4000);
        // Ausserhalb - keine Ueberschneidung.
        $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), 5000, 6000);

        $einsaetze = (new plan_service())->get_einsaetze((int) $lernende->id, 1400, 2600);

        $this->assertCount(2, $einsaetze);
    }

    public function test_get_ausgebildete_kompetenzen_vereinigt_nur_betriebliche_bloecke(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $betrieb = $this->lege_block_an('4', true);
        $schule = $this->lege_block_an('uek', false);

        (new block_lk(0, (object) ['blockid' => $betrieb->get('id'), 'competencyid' => 10, 'intensitaet' => 'schwerpunkt']))->create();
        (new block_lk(0, (object) ['blockid' => $betrieb->get('id'), 'competencyid' => 20, 'intensitaet' => 'teilweise']))->create();
        (new block_lk(0, (object) ['blockid' => $schule->get('id'), 'competencyid' => 99, 'intensitaet' => 'schwerpunkt']))->create();

        $this->lege_einsatz_an((int) $lernende->id, (int) $betrieb->get('id'), 1000, 2000);
        $this->lege_einsatz_an((int) $lernende->id, (int) $schule->get('id'), 2100, 3000);

        $kompetenzen = (new plan_service())->get_ausgebildete_kompetenzen((int) $lernende->id, 900, 3100);

        sort($kompetenzen);
        $this->assertSame([10, 20], $kompetenzen);
    }

    public function test_get_ausgebildete_kompetenzen_ohne_einsatz_leer(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $this->assertSame([], (new plan_service())->get_ausgebildete_kompetenzen((int) $lernende->id, 1000, 2000));
    }

    public function test_lernkompetenz_deckt_uebergeordnete_handlungskompetenz_ab(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $lernende = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-lk-test']);
        $hk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'idnumber' => '7777 a.01',
        ]);
        $lk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk->get('id'),
            'idnumber' => 'AU a1 01',
        ]);
        $block = $this->lege_block_an('4');
        (new block_lk(0, (object) [
            'blockid' => $block->get('id'),
            'competencyid' => $lk->get('id'),
            'intensitaet' => 'schwerpunkt',
        ]))->create();
        $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), 1000, 2000);

        $kompetenzen = (new plan_service())->get_ausgebildete_kompetenzen((int) $lernende->id, 1000, 2000);
        sort($kompetenzen);

        $this->assertSame([(int) $hk->get('id'), (int) $lk->get('id')], $kompetenzen);
    }

    public function test_get_aktueller_einsatz_normalfall(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $block = $this->lege_block_an('4');
        $jetzt = time();
        $aktuell = $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), $jetzt - 1000, $jetzt + 1000);
        $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), $jetzt - 5000, $jetzt - 2000);

        $treffer = (new plan_service())->get_aktueller_einsatz((int) $lernende->id);

        $this->assertNotNull($treffer);
        $this->assertSame((int) $aktuell->get('id'), (int) $treffer->get('id'));
    }

    /**
     * Randfall: genau am Ende des Einsatzes gilt er noch, eine Sekunde
     * danach nicht mehr - inklusive Grenze, analog zu gueltig_bis bei der
     * Zuordnung.
     */
    public function test_get_aktueller_einsatz_grenze_inklusiv(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $block = $this->lege_block_an('4');
        $jetzt = time();
        $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), $jetzt - 1000, $jetzt);

        $this->assertNotNull((new plan_service())->get_aktueller_einsatz((int) $lernende->id));

        // Fallweise pruefen: knapp ausserhalb der Grenze.
        foreach (einsatz::get_records(['userid' => (int) $lernende->id]) as $bestehend) {
            $bestehend->delete();
        }
        $this->lege_einsatz_an((int) $lernende->id, (int) $block->get('id'), $jetzt - 1000, $jetzt - 1);

        $this->assertNull((new plan_service())->get_aktueller_einsatz((int) $lernende->id));
    }
}
