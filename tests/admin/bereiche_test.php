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
 * Tests fuer die Bereiche unter Website-Administration ▸ Berufsbildung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\admin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Die Bereiche stehen immer in derselben Reihenfolge, egal in welcher
 * Reihenfolge die Plugins sie anlegen.
 *
 * @covers \local_berufsbildung\admin\bereiche
 */
final class bereiche_test extends \advanced_testcase {
    /**
     * Ein leerer Admin-Baum mit der Kategorie, vor der "Berufsbildung" steht.
     *
     * @return \admin_root
     */
    private function leerer_baum(): \admin_root {
        $admin = new \admin_root(true);
        $admin->add('root', new \admin_category('ai', 'KI'));
        return $admin;
    }

    /**
     * Die Namen der Kinder einer Kategorie in ihrer Reihenfolge.
     *
     * @param \admin_root $admin
     * @param string $kategorie
     * @return string[]
     */
    private function kinder(\admin_root $admin, string $kategorie): array {
        return array_values(array_map(
            static fn(\part_of_admin_tree $kind): string => $kind->name,
            $admin->locate($kategorie)->get_children()
        ));
    }

    /**
     * Data provider: Ladereihenfolgen der Plugins.
     *
     * @return array
     */
    public static function ladereihenfolgen(): array {
        $alle = [
            bereiche::ALLGEMEIN,
            bereiche::ZUORDNUNGEN,
            bereiche::PLANUNG,
            bereiche::BILDUNGSBERICHT,
            bereiche::LERNDOKUMENTATION,
            bereiche::UEK,
        ];
        return [
            'vorgesehene Reihenfolge' => [$alle],
            'umgekehrt' => [array_reverse($alle)],
            'Basisplugin zuletzt' => [[
                bereiche::UEK,
                bereiche::BILDUNGSBERICHT,
                bereiche::LERNDOKUMENTATION,
                bereiche::ALLGEMEIN,
                bereiche::ZUORDNUNGEN,
                bereiche::PLANUNG,
            ]],
        ];
    }

    /**
     * Unabhaengig von der Ladereihenfolge stehen die Bereiche gleich.
     *
     * @dataProvider ladereihenfolgen
     * @param string[] $reihenfolge Reihenfolge, in der die Bereiche angelegt werden
     */
    public function test_reihenfolge_unabhaengig_vom_laden(array $reihenfolge): void {
        $admin = $this->leerer_baum();
        foreach ($reihenfolge as $name) {
            bereiche::anlegen($admin, $name, $name);
        }

        $this->assertSame([
            bereiche::ALLGEMEIN,
            bereiche::ZUORDNUNGEN,
            bereiche::PLANUNG,
            bereiche::BILDUNGSBERICHT,
            bereiche::LERNDOKUMENTATION,
            bereiche::UEK,
        ], $this->kinder($admin, bereiche::OBERKATEGORIE));
        $this->assertSame([bereiche::OBERKATEGORIE, 'ai'], $this->kinder($admin, 'root'));
    }

    /**
     * Fehlen einzelne Plugins, bleibt die Reihenfolge der uebrigen.
     */
    public function test_teilmenge(): void {
        $admin = $this->leerer_baum();
        bereiche::anlegen($admin, bereiche::UEK, 'uek');
        bereiche::anlegen($admin, bereiche::ALLGEMEIN, 'allgemein');
        bereiche::anlegen($admin, bereiche::BILDUNGSBERICHT, 'bildungsbericht');

        $this->assertSame(
            [bereiche::ALLGEMEIN, bereiche::BILDUNGSBERICHT, bereiche::UEK],
            $this->kinder($admin, bereiche::OBERKATEGORIE)
        );
    }

    /**
     * Ein zweiter Aufruf legt den Bereich nicht doppelt an.
     */
    public function test_zweiter_aufruf_ohne_wirkung(): void {
        $admin = $this->leerer_baum();
        bereiche::anlegen($admin, bereiche::PLANUNG, 'erster');
        bereiche::anlegen($admin, bereiche::PLANUNG, 'zweiter');

        $this->assertSame([bereiche::PLANUNG], $this->kinder($admin, bereiche::OBERKATEGORIE));
        $this->assertSame('erster', $admin->locate(bereiche::PLANUNG)->visiblename);
    }

    /**
     * Ein unbekannter Bereich ist ein Programmierfehler.
     */
    public function test_unbekannter_bereich(): void {
        $this->expectException(\coding_exception::class);
        bereiche::anlegen($this->leerer_baum(), 'ausbildungsverwaltung_lernbegleitung', 'alt');
    }
}
