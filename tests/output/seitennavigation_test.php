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
 * Tests fuer die Reiterleiste unter "Meine Lehre" und "Meine Lernenden".
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\hook_callbacks;
use local_berufsbildung\navigation\menuepunkt;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\output\seitennavigation::class)]
/**
 * Welche Plugins Menuepunkte melden, haengt von der Installation ab (in der
 * CI dieses Plugins keines, in der Entwicklungsumgebung alle). Die Tests
 * pruefen deshalb gegen das, was gemeldet wird.
 *
 * @covers \local_berufsbildung\output\seitennavigation
 */
final class seitennavigation_test extends advanced_testcase {
    public function test_meine_lehre_zeigt_uebersicht_und_gemeldete_punkte(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende());

        $gemeldet = hook_callbacks::gemeldete_menuepunkte()[menuepunkt::MEINE_LEHRE] ?? [];
        $html = seitennavigation::render(menuepunkt::MEINE_LEHRE);

        if (empty($gemeldet)) {
            // Ein einzelner Reiter fuehrt nirgendwohin.
            $this->assertSame('', $html);
            return;
        }
        // Der aktive Reiter wird ohne Link gerendert: die Uebersicht hat
        // ihren Link nur, wenn ein gemeldeter Punkt aktiv ist.
        foreach ($gemeldet as $punkt) {
            $this->assertStringContainsString($punkt->url->out(), $html);
        }
        $this->assertStringNotContainsString('/local/berufsbildung/bloecke.php', $html);

        $html = seitennavigation::render(menuepunkt::MEINE_LEHRE, $gemeldet[0]->schluessel);
        $this->assertStringContainsString('/local/berufsbildung/meine_lehre.php', $html);
    }

    public function test_meine_lehre_ohne_blockverwaltung_auch_mit_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertStringNotContainsString(
            '/local/berufsbildung/bloecke.php',
            seitennavigation::render(menuepunkt::MEINE_LEHRE)
        );
    }

    public function test_meine_lernenden_mit_manageblocks_hat_reiter_ausbildungsbloecke(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Mit dem Reiter zur Blockverwaltung gibt es immer mindestens zwei.
        // Der aktive Reiter wird ohne Link gerendert, deshalb beide Seiten pruefen.
        $html = seitennavigation::render(menuepunkt::MEINE_LERNENDEN);
        $this->assertStringContainsString('/local/berufsbildung/bloecke.php', $html);
        $this->assertStringContainsString(get_string('bloecke:uebersicht', 'local_berufsbildung'), $html);

        $html = seitennavigation::render(menuepunkt::MEINE_LERNENDEN, seitennavigation::BLOECKE);
        $this->assertStringContainsString('/local/berufsbildung/meine_lernenden.php', $html);
        $this->assertStringContainsString(get_string('bloecke:uebersicht', 'local_berufsbildung'), $html);
    }

    public function test_meine_lernenden_ohne_manageblocks_kein_reiter_ausbildungsbloecke(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $gemeldet = hook_callbacks::gemeldete_menuepunkte()[menuepunkt::MEINE_LERNENDEN] ?? [];
        $html = seitennavigation::render(menuepunkt::MEINE_LERNENDEN);

        $this->assertStringNotContainsString('/local/berufsbildung/bloecke.php', $html);
        if (empty($gemeldet)) {
            $this->assertSame('', $html);
        }
    }

    public function test_berufsbildung_hat_keine_reiter(): void {
        $this->expectException(\coding_exception::class);
        seitennavigation::render(menuepunkt::BERUFSBILDUNG);
    }

    public function test_hauptleiste_nur_fuer_menues_mit_eigener_seite(): void {
        $this->expectException(\coding_exception::class);
        seitennavigation::markiere_hauptleiste(menuepunkt::BERUFSBILDUNG);
    }
}
