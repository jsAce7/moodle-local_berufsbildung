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
 * Tests fuer die Karte des aktuellen Einsatzes.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\persistent\einsatz;

/**
 * Tests fuer einsatz_karte.
 *
 * @covers \local_berufsbildung\output\einsatz_karte
 */
final class einsatz_karte_test extends advanced_testcase {
    /**
     * Baut einen Einsatz, den die Karte ohne Datenbankzugriff rendern kann.
     *
     * @return einsatz
     */
    private function einsatz(): einsatz {
        return new einsatz(0, (object) [
            'userid' => 1,
            'blockid' => 1,
            'von' => strtotime('2027-02-01 00:00:00'),
            'bis' => strtotime('2027-02-28 23:59:59'),
            'kw_von' => '2027-W05',
            'kw_bis' => '2027-W08',
            'importid' => 1,
        ]);
    }

    public function test_verknuepfter_kurs_erscheint_als_link(): void {
        $this->resetAfterTest();

        $html = einsatz_karte::render(
            $this->einsatz(),
            'Montage',
            (object) ['id' => 42, 'fullname' => 'Montage Grundlagen']
        );

        $this->assertStringContainsString('Montage Grundlagen', $html);
        $this->assertStringContainsString('/course/view.php?id=42', $html);
        $this->assertStringContainsString(
            get_string('einsatz:kurs_oeffnen', 'local_berufsbildung', 'Montage Grundlagen'),
            $html
        );
    }

    public function test_ohne_verknuepften_kurs_erscheint_kein_link(): void {
        $this->resetAfterTest();

        $html = einsatz_karte::render($this->einsatz(), 'Montage');

        $this->assertStringNotContainsString('/course/view.php', $html);
    }
}
