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
 * Tests fuer die Roster-Kachel.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\nachweis\schnellaktion;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\output\lernenden_kachel::class)]
/**
 * Tests fuer lernenden_kachel.
 *
 * @covers \local_berufsbildung\output\lernenden_kachel
 */
final class lernenden_kachel_test extends advanced_testcase {
    /**
     * Die Schaltflaeche steht ausserhalb von <details> - im <summary> waere
     * sie ein Bedienelement im Umschalter, und ein Klick klappte die Kachel
     * auf.
     */
    public function test_schnellaktion_steht_ausserhalb_des_umschalters(): void {
        $this->resetAfterTest();

        $html = lernenden_kachel::render(
            'Elena Furrer',
            null,
            0,
            0,
            '<p>Detail</p>',
            lernendeid: 42,
            schnellaktionen: [new schnellaktion('testquelle', 'Notiz (3)', '/local/test/notiz.php?userid=42', 'fa-sticky-note')]
        );

        $nachdetails = substr($html, strpos($html, '</details>'));
        $this->assertStringContainsString('data-local-berufsbildung-schnellaktion="testquelle"', $nachdetails);
        $this->assertStringContainsString('data-lernendeid="42"', $nachdetails);
        $this->assertStringContainsString('Notiz (3)', $nachdetails);
        $this->assertStringContainsString('fa-sticky-note', $nachdetails);
        $this->assertStringContainsString(s(get_string('meine_lernenden:schnellaktion_fuer', 'local_berufsbildung', (object) [
            'aktion' => 'Notiz (3)',
            'name' => 'Elena Furrer',
        ])), $nachdetails);
        $this->assertStringContainsString('local-berufsbildung-kachel-mit-schnellaktionen', $html);
    }

    public function test_ohne_schnellaktion_keine_leiste(): void {
        $this->resetAfterTest();

        $html = lernenden_kachel::render('Elena Furrer', null, 0, 0, '<p>Detail</p>');

        $this->assertStringNotContainsString('data-local-berufsbildung-schnellaktion', $html);
        $this->assertStringNotContainsString('local-berufsbildung-kachel-mit-schnellaktionen', $html);
    }

    /**
     * Moodle-Strings kennen keine Mehrzahl - eine einzelne Taetigkeit
     * braucht einen eigenen String.
     */
    public function test_taetigkeiten_in_einzahl_und_mehrzahl(): void {
        $this->resetAfterTest();

        $eine = lernenden_kachel::render('Elena Furrer', null, 0, 1, '');
        $mehrere = lernenden_kachel::render('Elena Furrer', null, 0, 9, '');

        $this->assertStringContainsString(get_string('meine_lernenden:taetigkeiten_eine', 'local_berufsbildung'), $eine);
        $this->assertStringNotContainsString(
            get_string('meine_lernenden:taetigkeiten_anzahl', 'local_berufsbildung', 1),
            $eine
        );
        $this->assertStringContainsString(
            get_string('meine_lernenden:taetigkeiten_anzahl', 'local_berufsbildung', 9),
            $mehrere
        );
    }

    /**
     * Offene Wahlpflicht-HK stehen als eigenes Badge in der Kopfzeile,
     * ohne Zahl kein Badge.
     */
    public function test_wahlpflicht_offen_als_badge(): void {
        $this->resetAfterTest();
        $text = get_string('luecken:wahlpflicht_offen', 'local_berufsbildung', 2);

        $mit = lernenden_kachel::render('Elena Furrer', null, 0, 0, '', anzahlwahlpflichtoffen: 2);
        $ohne = lernenden_kachel::render('Elena Furrer', null, 0, 0, '');

        $this->assertStringContainsString($text, $mit);
        $this->assertStringNotContainsString(
            get_string('luecken:wahlpflicht_offen', 'local_berufsbildung', 0),
            $ohne
        );
    }
}
