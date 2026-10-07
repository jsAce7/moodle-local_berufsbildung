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
 * Tests fuer die Darstellung der Leistungskriterien je Block.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\service\lk_abdeckung_service;
use moodle_url;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\output\lk_abdeckung::class)]
/**
 * Tests fuer lk_abdeckung.
 *
 * @covers \local_berufsbildung\output\lk_abdeckung
 */
final class lk_abdeckung_test extends advanced_testcase {
    /**
     * Ein Rahmen mit einem zugeordneten und einem offenen LK; B1 vermittelt
     * das erste.
     *
     * @return array Ergebnis von lk_abdeckung_service::fuer_beruf()
     */
    private function abdeckung(): array {
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');

        $kompetenzen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $kompetenzen->create_framework(['idnumber' => 'au-2022']);
        $bereich = $kompetenzen->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'shortname' => 'a Entwickeln von Anlagen',
            'idnumber' => '7777BE a',
        ]);
        $hk = $kompetenzen->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'shortname' => 'Fertigungsunterlagen erstellen',
            'idnumber' => '7777BE a.01',
            'parentid' => $bereich->get('id'),
        ]);
        $zugeordnet = $kompetenzen->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'shortname' => 'AU a1 01',
            'idnumber' => 'lk-a1-01',
            'parentid' => $hk->get('id'),
        ]);
        $kompetenzen->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'shortname' => 'AU a1 02',
            'idnumber' => 'lk-a1-02',
            'parentid' => $hk->get('id'),
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $block = $generator->create_block(['nummer' => 'B1', 'name' => 'Werkstatt', 'beruf' => 'AU_EFZ']);
        $generator->create_block_competency(['blockid' => $block->get('id'), 'competencyid' => $zugeordnet->get('id')]);

        return (new lk_abdeckung_service())->fuer_beruf('AU_EFZ');
    }

    /**
     * Alle LK mit ihren Bloecken, das offene markiert.
     */
    public function test_alle_leistungskriterien(): void {
        $this->resetAfterTest();

        $html = lk_abdeckung::render(
            $this->abdeckung(),
            false,
            new moodle_url('/local/berufsbildung/lk_abdeckung.php', ['beruf' => 'AU_EFZ'])
        );

        $this->assertStringContainsString('1 of 2 performance criteria are assigned to a block, 1 to none yet.', $html);
        $this->assertStringContainsString('a.01 Fertigungsunterlagen erstellen', $html);
        $this->assertStringContainsString('AU a1 01', $html);
        $this->assertStringContainsString('B1 Werkstatt', $html);
        $this->assertStringContainsString('AU a1 02', $html);
        $this->assertSame(1, substr_count($html, 'in no block'));
    }

    /**
     * Der Filter laesst nur die LK ohne Block stehen.
     */
    public function test_nur_offene(): void {
        $this->resetAfterTest();

        $html = lk_abdeckung::render(
            $this->abdeckung(),
            true,
            new moodle_url('/local/berufsbildung/lk_abdeckung.php', ['beruf' => 'AU_EFZ'])
        );

        $this->assertStringNotContainsString('AU a1 01', $html);
        $this->assertStringNotContainsString('B1 Werkstatt', $html);
        $this->assertStringContainsString('AU a1 02', $html);
    }
}
