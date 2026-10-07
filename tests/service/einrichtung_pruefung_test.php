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
 * Tests fuer die Pruefung der Einrichtung je Beruf.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\einrichtung_pruefung::class)]
/**
 * Tests fuer einrichtung_pruefung.
 *
 * @covers \local_berufsbildung\service\einrichtung_pruefung
 */
final class einrichtung_pruefung_test extends advanced_testcase {
    /**
     * Nur die Codes je Beruf, ohne Stufe und Parameter.
     *
     * @param array $befunde Ergebnis von pruefe()
     * @return array Beruf => string[]
     */
    private function codes(array $befunde): array {
        return array_map(
            static fn (array $liste): array => array_column($liste, 'code'),
            $befunde
        );
    }

    /**
     * Legt einen Rahmen mit einer Kompetenz an.
     *
     * @param string $idnumber
     * @return int ID der Kompetenz
     */
    private function rahmen(string $idnumber): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => $idnumber]);

        return (int) $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')])->get('id');
    }

    /**
     * Ein vollstaendig eingerichteter Beruf hat keine Befunde.
     */
    public function test_vollstaendig_eingerichtet(): void {
        $this->resetAfterTest();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');
        set_config('beruf_wahlpflicht_hk', '', 'local_berufsbildung');
        $kompetenzid = $this->rahmen('au-2022');
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $block = $generator->create_block(['nummer' => 'B1', 'beruf' => 'AU_EFZ']);
        $generator->create_block_competency(['blockid' => $block->get('id'), 'competencyid' => $kompetenzid]);

        $this->assertSame([], (new einrichtung_pruefung())->pruefe());
    }

    /**
     * Fehlender Rahmen, fehlende Bloecke, Bloecke ohne zaehlende
     * Kompetenzen und Wahlpflicht ohne Anzahl werden je Beruf gemeldet.
     */
    public function test_befunde_je_beruf(): void {
        $this->resetAfterTest();
        set_config('beruf_rahmen_mapping', "AU_EFZ=au-2022\nPM_EFZ=pm-2022", 'local_berufsbildung');
        set_config('beruf_wahlpflicht_hk', 'AU_EFZ=a.04', 'local_berufsbildung');
        set_config('beruf_wahlpflicht_anzahl', '', 'local_berufsbildung');
        $kompetenzid = $this->rahmen('au-2022');

        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        // AU: Kompetenzen nur in einem inaktiven und einem Schulblock.
        $inaktiv = $generator->create_block(['nummer' => 'B5', 'beruf' => 'AU_EFZ', 'aktiv' => 0]);
        $schule = $generator->create_block(['nummer' => 'S1', 'beruf' => 'AU_EFZ', 'ist_betrieb' => 0]);
        foreach ([$inaktiv, $schule] as $block) {
            $generator->create_block_competency(['blockid' => $block->get('id'), 'competencyid' => $kompetenzid]);
        }
        // KV: Block ohne Rahmen. Berufsuebergreifende Bloecke werden nicht geprueft.
        $generator->create_block(['nummer' => 'K1', 'beruf' => 'KV_EFZ']);
        $generator->create_block(['nummer' => 'F1', 'beruf' => '']);

        $befunde = (new einrichtung_pruefung())->pruefe();

        $this->assertSame([
            'AU_EFZ' => ['keine_kompetenzen', 'ungenutzte_kompetenzen', 'wahlpflicht_ohne_anzahl'],
            'KV_EFZ' => ['kein_rahmen', 'keine_kompetenzen'],
            'PM_EFZ' => ['rahmen_fehlt', 'keine_bloecke'],
        ], $this->codes($befunde));
        $this->assertSame('B5, S1', $befunde['AU_EFZ'][1]['a']);
        $this->assertSame(einrichtung_pruefung::STUFE_HINWEIS, $befunde['AU_EFZ'][1]['stufe']);
        $this->assertSame('pm-2022', $befunde['PM_EFZ'][0]['a']);
        $this->assertSame(einrichtung_pruefung::STUFE_WARNUNG, $befunde['PM_EFZ'][0]['stufe']);
    }
}
