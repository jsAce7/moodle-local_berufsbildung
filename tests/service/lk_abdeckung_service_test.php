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
 * Tests fuer die Abdeckung der Leistungskriterien eines Berufs.
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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\lk_abdeckung_service::class)]
/**
 * Tests fuer lk_abdeckung_service.
 *
 * @covers \local_berufsbildung\service\lk_abdeckung_service
 */
final class lk_abdeckung_service_test extends advanced_testcase {
    /** @var competency[] Die angelegten Kompetenzen, nach ID-Nummer. */
    private array $kompetenzen = [];

    /**
     * Ein Bereich mit zwei Handlungskompetenzen: a.01 mit zwei LK, a.02
     * ohne LK.
     */
    private function lege_rahmen_an(): void {
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);

        $anlegen = function (string $idnumber, string $elternteil = '') use ($rahmen, $generator): void {
            $this->kompetenzen[$idnumber] = $generator->create_competency([
                'competencyframeworkid' => $rahmen->get('id'),
                'shortname' => $idnumber,
                'idnumber' => $idnumber,
                'parentid' => $elternteil !== '' ? $this->kompetenzen[$elternteil]->get('id') : 0,
            ]);
        };

        $anlegen('7777BE a');
        $anlegen('7777BE a.01', '7777BE a');
        $anlegen('lk-a1-01', '7777BE a.01');
        $anlegen('lk-a1-02', '7777BE a.01');
        $anlegen('7777BE a.02', '7777BE a');
    }

    /**
     * Legt einen Block an und ordnet ihm Kompetenzen zu.
     *
     * @param array $daten Felder des Blocks
     * @param string[] $kompetenzen ID-Nummern
     */
    private function block(array $daten, array $kompetenzen): block {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $block = $generator->create_block($daten + ['beruf' => 'AU_EFZ']);
        foreach ($kompetenzen as $idnumber) {
            $generator->create_block_competency([
                'blockid' => $block->get('id'),
                'competencyid' => $this->kompetenzen[$idnumber]->get('id'),
            ]);
        }

        return $block;
    }

    /**
     * Die Zeilen nach Kurzname, je mit den Nummern der Bloecke.
     *
     * @param array $abdeckung Ergebnis von fuer_beruf()
     * @return array shortname => ['zugeordnet' => bool, 'bloecke' => string[]]
     */
    private function zeilen(array $abdeckung): array {
        $zeilen = [];
        foreach ($abdeckung['bereiche'] as $bereich) {
            foreach ($bereich['handlungskompetenzen'] as $hk) {
                foreach ($hk['leistungskriterien'] as $lk) {
                    $zeilen[$lk['kompetenz']->get('shortname')] = [
                        'zugeordnet' => $lk['zugeordnet'],
                        'bloecke' => array_map(
                            static fn (array $eintrag): string => $eintrag['block']->get('nummer')
                                . ($eintrag['ueberhk'] ? ' (HK)' : ''),
                            $lk['bloecke']
                        ),
                    ];
                }
            }
        }

        return $zeilen;
    }

    /**
     * Ein LK steht bei den Bloecken, die es selbst oder ueber seine ganze
     * HK abdecken; eine HK ohne LK steht als ihre eigene Zeile da.
     */
    public function test_bloecke_je_leistungskriterium(): void {
        $this->resetAfterTest();
        $this->lege_rahmen_an();
        $this->block(['nummer' => 'B10'], ['lk-a1-01']);
        $this->block(['nummer' => 'B2'], ['7777BE a.01']);

        $abdeckung = (new lk_abdeckung_service())->fuer_beruf('AU_EFZ');

        $this->assertSame([
            'lk-a1-01' => ['zugeordnet' => true, 'bloecke' => ['B2 (HK)', 'B10']],
            'lk-a1-02' => ['zugeordnet' => true, 'bloecke' => ['B2 (HK)']],
            '7777BE a.02' => ['zugeordnet' => false, 'bloecke' => []],
        ], $this->zeilen($abdeckung));
        $this->assertSame(3, $abdeckung['anzahl']);
        $this->assertSame(1, $abdeckung['offen']);
        $this->assertSame(1, $abdeckung['bereiche'][0]['offen']);
    }

    /**
     * Inaktive und nicht betriebliche Bloecke stehen da, zaehlen aber
     * nicht - wie im Kompetenzraster.
     */
    public function test_inaktive_und_nicht_betriebliche_bloecke_zaehlen_nicht(): void {
        $this->resetAfterTest();
        $this->lege_rahmen_an();
        $this->block(['nummer' => 'B1', 'aktiv' => 0], ['lk-a1-01']);
        $this->block(['nummer' => 'S1', 'ist_betrieb' => 0], ['lk-a1-02']);

        $abdeckung = (new lk_abdeckung_service())->fuer_beruf('AU_EFZ');
        $zeilen = $this->zeilen($abdeckung);

        $this->assertSame(['zugeordnet' => false, 'bloecke' => ['B1']], $zeilen['lk-a1-01']);
        $this->assertSame(['zugeordnet' => false, 'bloecke' => ['S1']], $zeilen['lk-a1-02']);
        $this->assertSame(3, $abdeckung['offen']);
    }

    /**
     * Bloecke eines anderen Berufs und berufsuebergreifende Bloecke
     * gehoeren nicht dazu.
     */
    public function test_nur_bloecke_des_berufs(): void {
        $this->resetAfterTest();
        $this->lege_rahmen_an();
        $this->block(['nummer' => 'P1', 'beruf' => 'PM_EFZ'], ['lk-a1-01']);
        $this->block(['nummer' => 'X1', 'beruf' => ''], ['lk-a1-02']);

        $abdeckung = (new lk_abdeckung_service())->fuer_beruf('AU_EFZ');

        $this->assertSame(3, $abdeckung['offen']);
        $this->assertSame([], $this->zeilen($abdeckung)['lk-a1-01']['bloecke']);
    }

    /**
     * Ohne konfigurierten oder vorhandenen Rahmen gibt es keine Abdeckung.
     */
    public function test_ohne_rahmen_null(): void {
        $this->resetAfterTest();
        $this->lege_rahmen_an();

        $service = new lk_abdeckung_service();
        $this->assertNull($service->fuer_beruf(''));
        $this->assertNull($service->fuer_beruf('PM_EFZ'));

        set_config('beruf_rahmen_mapping', 'AU_EFZ=gibt-es-nicht', 'local_berufsbildung');
        $this->assertNull($service->fuer_beruf('AU_EFZ'));
    }
}
