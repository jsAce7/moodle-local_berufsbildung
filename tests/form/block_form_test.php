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
 * Tests fuer das Formular eines Ausbildungsblocks.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\form;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\form\block_form::class)]
/**
 * Tests fuer block_form::validation(), Feld "Fachvorgesetzte".
 *
 * Erlaubt ist nur, was block_bearbeiten.php als Option mitgibt; die IDs
 * muessen dafuer keine Konten sein.
 *
 * @covers \local_berufsbildung\form\block_form
 */
final class block_form_test extends advanced_testcase {
    /**
     * Prueft die Eingaben mit den Personen 11 und 12 als Optionen.
     *
     * @param array $fachvorgesetzte Weitere Felder der Eingabe, meist nur 'fachvorgesetzte'
     * @return array Fehler je Feld
     */
    private function pruefe(array $fachvorgesetzte): array {
        $form = new block_form(new \moodle_url('/local/berufsbildung/block_bearbeiten.php'), [
            'berufe' => [],
            'kurse' => [],
            'fachvorgesetzte' => [11 => 'Anna Keller (anna@example.com)', 12 => 'Zora Meier (zora@example.com)'],
        ]);

        return $form->validation(['id' => 0, 'nummer' => 'Z99', 'ist_betrieb' => 1] + $fachvorgesetzte, []);
    }

    public function test_id_ausserhalb_der_optionen_ergibt_fehler(): void {
        $this->resetAfterTest();

        $fehler = $this->pruefe(['fachvorgesetzte' => [11, 99]]);

        $this->assertArrayHasKey('fachvorgesetzte', $fehler);
        $this->assertSame(get_string('block:fehler_fachvorgesetzte', 'local_berufsbildung'), $fehler['fachvorgesetzte']);
    }

    public function test_erlaubte_ids_ergeben_keinen_fehler(): void {
        $this->resetAfterTest();

        $this->assertArrayNotHasKey('fachvorgesetzte', $this->pruefe(['fachvorgesetzte' => ['11', '12']]));
    }

    public function test_leere_auswahl_ergibt_keinen_fehler(): void {
        $this->resetAfterTest();

        $this->assertArrayNotHasKey('fachvorgesetzte', $this->pruefe(['fachvorgesetzte' => []]));
    }

    public function test_fehlendes_feld_ergibt_keinen_fehler(): void {
        $this->resetAfterTest();

        $this->assertArrayNotHasKey('fachvorgesetzte', $this->pruefe([]));
    }
}
