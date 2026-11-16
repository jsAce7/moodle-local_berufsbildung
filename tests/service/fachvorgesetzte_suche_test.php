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
 * Tests fuer die Personensuche im Feld "Fachvorgesetzte".
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\fachvorgesetzte_service::class)]
/**
 * Tests fuer fachvorgesetzte_service::suche_personen().
 *
 * Jedes ausgeschlossene Konto hat genau einen Grund, ausgeschlossen zu
 * sein; daneben steht jeweils ein aktives Konto, das die Suche findet.
 *
 * @covers \local_berufsbildung\service\fachvorgesetzte_service
 */
final class fachvorgesetzte_suche_test extends advanced_testcase {
    /**
     * Die IDs der Treffer.
     *
     * @param string $suche
     * @return int[]
     */
    private function treffer(string $suche): array {
        return array_column((new fachvorgesetzte_service())->suche_personen($suche), 'id');
    }

    public function test_geloeschtes_konto_erscheint_nicht(): void {
        global $DB;
        $this->resetAfterTest();
        $aktiv = (int) $this->getDataGenerator()->create_user(['lastname' => 'Loeschtest'])->id;
        $geloescht = (int) $this->getDataGenerator()->create_user(['lastname' => 'Loeschtest'])->id;

        // Nur als geloescht markiert: delete_user() aenderte auch die Mailadresse.
        $DB->set_field('user', 'deleted', 1, ['id' => $geloescht]);

        $this->assertSame([$aktiv], $this->treffer('Loeschtest'));
    }

    public function test_unbestaetigtes_konto_erscheint_nicht(): void {
        $this->resetAfterTest();
        $aktiv = (int) $this->getDataGenerator()->create_user(['lastname' => 'Bestaetigtest'])->id;
        $this->getDataGenerator()->create_user(['lastname' => 'Bestaetigtest', 'confirmed' => 0]);

        $this->assertSame([$aktiv], $this->treffer('Bestaetigtest'));
    }

    public function test_gastkonto_erscheint_nicht(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $gast = $DB->get_record('user', ['id' => $CFG->siteguest], '*', MUST_EXIST);
        // Ausgeschlossen nur, weil es das Gastkonto ist.
        $this->assertSame(0, (int) $gast->deleted);
        $this->assertSame(0, (int) $gast->suspended);
        $this->assertSame(1, (int) $gast->confirmed);
        $this->assertGreaterThanOrEqual(2, \core_text::strlen(trim($gast->firstname)));
        $kontrolle = (int) $this->getDataGenerator()->create_user([
            'firstname' => $gast->firstname,
            'lastname' => 'Gastkontrolle',
        ])->id;

        $this->assertSame([$kontrolle], $this->treffer(trim($gast->firstname)));
    }

    public function test_hoechstens_zwanzig_treffer(): void {
        $this->resetAfterTest();
        for ($i = 0; $i < 21; $i++) {
            $this->getDataGenerator()->create_user(['lastname' => 'Massentest']);
        }

        $this->assertCount(20, $this->treffer('Massentest'));
    }

    public function test_prozentzeichen_wird_woertlich_gesucht(): void {
        $this->resetAfterTest();
        $woertlich = (int) $this->getDataGenerator()->create_user(['lastname' => 'Zins%satz'])->id;
        // Passte, wenn % als Platzhalter wirkte.
        $this->getDataGenerator()->create_user(['lastname' => 'Zinsxxsatz']);

        $this->assertSame([$woertlich], $this->treffer('Zins%satz'));
    }

    public function test_unterstrich_wird_woertlich_gesucht(): void {
        $this->resetAfterTest();
        $woertlich = (int) $this->getDataGenerator()->create_user(['lastname' => 'Unter_strich'])->id;
        // Passte, wenn _ als Platzhalter wirkte.
        $this->getDataGenerator()->create_user(['lastname' => 'Unterxstrich']);

        $this->assertSame([$woertlich], $this->treffer('Unter_strich'));
    }
}
