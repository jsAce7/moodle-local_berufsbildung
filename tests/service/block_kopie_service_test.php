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
 * Tests fuer das Kopieren der Kompetenzzuordnung eines Blocks.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use core_competency\competency;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\block_kopie_service::class)]
/**
 * Tests fuer block_kopie_service.
 *
 * @covers \local_berufsbildung\service\block_kopie_service
 */
final class block_kopie_service_test extends advanced_testcase {
    /** @var competency[] Nach ID-Nummer. */
    private array $kompetenzen = [];

    /**
     * Zwei Berufe mit je eigenem Rahmen: AU mit zwei LK, PM mit einem.
     */
    private function lege_rahmen_an(): void {
        set_config('beruf_rahmen_mapping', "AU_EFZ=au-2022\nPM_EFZ=pm-2022\nAU_EBA=au-2022", 'local_berufsbildung');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        foreach (['au-2022' => ['lk-au-1', 'lk-au-2'], 'pm-2022' => ['lk-pm-1']] as $idnumber => $lks) {
            $rahmen = $generator->create_framework(['idnumber' => $idnumber]);
            foreach ($lks as $lk) {
                $this->kompetenzen[$lk] = $generator->create_competency([
                    'competencyframeworkid' => $rahmen->get('id'),
                    'idnumber' => $lk,
                ]);
            }
        }
    }

    /**
     * Legt einen Block mit Kompetenzen an.
     *
     * @param string $nummer
     * @param string $beruf
     * @param string[] $kompetenzen ID-Nummern
     */
    private function block(string $nummer, string $beruf, array $kompetenzen = []): block {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $block = $generator->create_block(['nummer' => $nummer, 'beruf' => $beruf]);
        foreach ($kompetenzen as $idnumber) {
            $generator->create_block_competency([
                'blockid' => $block->get('id'),
                'competencyid' => $this->kompetenzen[$idnumber]->get('id'),
            ]);
        }

        return $block;
    }

    /**
     * Die Kompetenzen eines Blocks als ID-Nummern, sortiert.
     *
     * @param block $block
     * @return string[]
     */
    private function kompetenzen_von(block $block): array {
        $idnumbers = [];
        foreach (block_lk::get_records(['blockid' => $block->get('id')]) as $abdeckung) {
            $idnumbers[] = (new competency((int) $abdeckung->get('competencyid')))->get('idnumber');
        }
        sort($idnumbers);

        return $idnumbers;
    }

    /**
     * Gleicher Rahmen: alles wird uebernommen, Vorhandenes nicht doppelt.
     */
    public function test_uebernimmt_alles_im_selben_rahmen(): void {
        $this->resetAfterTest();
        $this->lege_rahmen_an();
        $quelle = $this->block('B1', 'AU_EFZ', ['lk-au-1', 'lk-au-2']);
        $ziel = $this->block('B9', 'AU_EBA', ['lk-au-2']);

        $ergebnis = (new block_kopie_service())->uebernimm_kompetenzen($quelle, $ziel);

        $this->assertSame(['uebernommen' => 1, 'verworfen' => 0], $ergebnis);
        $this->assertSame(['lk-au-1', 'lk-au-2'], $this->kompetenzen_von($ziel));
        $this->assertSame(['lk-au-1', 'lk-au-2'], $this->kompetenzen_von($quelle));
    }

    /**
     * Anderer Rahmen: was dort nicht steht, wird verworfen.
     */
    public function test_verwirft_kompetenzen_aus_anderem_rahmen(): void {
        $this->resetAfterTest();
        $this->lege_rahmen_an();
        $quelle = $this->block('B1', 'AU_EFZ', ['lk-au-1', 'lk-au-2']);
        $ziel = $this->block('P9', 'PM_EFZ');

        $ergebnis = (new block_kopie_service())->uebernimm_kompetenzen($quelle, $ziel);

        $this->assertSame(['uebernommen' => 0, 'verworfen' => 2], $ergebnis);
        $this->assertSame([], $this->kompetenzen_von($ziel));
    }

    /**
     * Ohne Beruf gibt es keinen Rahmen und damit nichts zu uebernehmen;
     * ohne Kompetenzen ebenso.
     */
    public function test_ohne_beruf_oder_ohne_kompetenzen(): void {
        $this->resetAfterTest();
        $this->lege_rahmen_an();
        $service = new block_kopie_service();

        $this->assertSame(
            ['uebernommen' => 0, 'verworfen' => 1],
            $service->uebernimm_kompetenzen($this->block('B1', 'AU_EFZ', ['lk-au-1']), $this->block('X1', ''))
        );
        $this->assertSame(
            ['uebernommen' => 0, 'verworfen' => 0],
            $service->uebernimm_kompetenzen($this->block('B2', 'AU_EFZ'), $this->block('B3', 'AU_EFZ'))
        );
    }
}
