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
 * Tests fuer die Eintraege in der primaeren Navigation.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use local_berufsbildung\navigation\menuepunkt;

/**
 * Welche Plugins Menuepunkte melden, haengt von der Installation ab (in der
 * CI dieses Plugins keines, in der Entwicklungsumgebung alle). Die Tests
 * pruefen deshalb, dass der Aufbau zu dem passt, was gemeldet wird.
 *
 * @covers \local_berufsbildung\hook_callbacks
 * @covers \local_berufsbildung\navigation\menuepunkt
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Die primaere Navigation der angemeldeten Person.
     *
     * @return \core\navigation\views\primary
     */
    private function primaere_navigation(): \core\navigation\views\primary {
        global $PAGE;

        $PAGE = new \moodle_page();
        $PAGE->set_url('/');
        $PAGE->set_context(\context_system::instance());

        $primary = new \core\navigation\views\primary($PAGE);
        $primary->initialise();

        return $primary;
    }

    /**
     * Die Schluessel der Kinder eines Knotens, in ihrer Reihenfolge.
     *
     * @param \navigation_node $knoten
     * @return string[]
     */
    private function kinder(\navigation_node $knoten): array {
        $schluessel = [];
        foreach ($knoten->children as $kind) {
            $schluessel[] = (string) $kind->key;
        }

        return $schluessel;
    }

    public function test_meine_lehre_ist_aufklappbar_genau_dann_wenn_punkte_gemeldet_sind(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende();
        $this->setUser($lernende);

        $gemeldet = hook_callbacks::gemeldete_menuepunkte()[menuepunkt::MEINE_LEHRE] ?? [];
        $knoten = $this->primaere_navigation()->find('local_berufsbildung_meine_lehre', null);

        $this->assertNotFalse($knoten);
        if (empty($gemeldet)) {
            $this->assertSame([], $this->kinder($knoten));
            $this->assertStringContainsString('/local/berufsbildung/meine_lehre.php', $knoten->action->out(false));
            return;
        }

        // Erster Punkt: die bisherige Seite, danach die gemeldeten.
        $erwartet = array_merge(
            ['local_berufsbildung_meine_lehre_seite'],
            array_map(static fn(menuepunkt $p): string => $p->schluessel, $gemeldet)
        );
        $this->assertSame($erwartet, $this->kinder($knoten));
        $erster = $knoten->get('local_berufsbildung_meine_lehre_seite');
        $this->assertStringContainsString('/local/berufsbildung/meine_lehre.php', $erster->action->out(false));
    }

    public function test_menue_berufsbildung_nur_mit_gemeldeten_punkten(): void {
        global $DB;
        $this->resetAfterTest();

        // Mit der Leitungsrolle melden die aufsetzenden Plugins ihre
        // Uebersichten, sofern sie installiert sind.
        $leitung = $this->getDataGenerator()->create_user();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'berufsbildung_leitung'], MUST_EXIST);
        role_assign($roleid, (int) $leitung->id, \context_system::instance()->id);
        $this->setUser($leitung);

        $gemeldet = hook_callbacks::gemeldete_menuepunkte()[menuepunkt::BERUFSBILDUNG] ?? [];
        $knoten = $this->primaere_navigation()->find('local_berufsbildung_berufsbildung', null);

        if (empty($gemeldet)) {
            $this->assertFalse($knoten);
            return;
        }
        $this->assertNotFalse($knoten);
        $this->assertSame(
            array_map(static fn(menuepunkt $p): string => $p->schluessel, $gemeldet),
            $this->kinder($knoten)
        );
    }

    public function test_ohne_lehre_und_lernende_kein_eigener_eintrag(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $primary = $this->primaere_navigation();
        $this->assertFalse($primary->find('local_berufsbildung_meine_lehre', null));
        $this->assertFalse($primary->find('local_berufsbildung_meine_lernenden', null));
    }

    public function test_unbekanntes_menue_wird_abgelehnt(): void {
        $this->expectException(\coding_exception::class);
        new menuepunkt('irgendwo', 'Text', new \moodle_url('/'), 'schluessel');
    }
}
