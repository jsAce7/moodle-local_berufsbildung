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
 * Tests fuer die Zeitleiste der Stationen und ihre Spalte in der Kachel.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\nachweis\erfassen_aktion;
use local_berufsbildung\nachweis\station;
use local_berufsbildung\nachweis\stationen;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\output\stationen_leiste::class)]
/**
 * Tests fuer stationen_leiste und die Spalte in lernenden_kachel.
 *
 * @covers \local_berufsbildung\output\stationen_leiste
 * @covers \local_berufsbildung\output\lernenden_kachel
 */
final class stationen_leiste_test extends advanced_testcase {
    /**
     * Drei Stationen: freigegeben, in Arbeit (aktuell), geplant.
     *
     * @return stationen
     */
    private function stationen(): stationen {
        return new stationen('bildungsbericht', 'Bildungsbericht', [
            new station(
                'bildungsbericht',
                'Bildungsbericht 1. Semester',
                station::STATUS_ERLEDIGT,
                'freigegeben',
                'Gespräch am 2. Februar 2026',
                '/local/test/ansicht.php?id=1',
                false,
                '1. Semester'
            ),
            new station(
                'bildungsbericht',
                'Bildungsbericht 2. Semester',
                station::STATUS_OFFEN,
                'in Arbeit',
                'Fällig bis 31. August 2026',
                '/local/test/erfassen.php?id=2',
                true,
                '2. Semester'
            ),
            new station(
                'bildungsbericht',
                'Bildungsbericht 3. Semester',
                station::STATUS_GEPLANT,
                'geplant',
                kurzbezeichnung: '3. Semester'
            ),
        ], '/local/test/verlauf.php');
    }

    public function test_zeitleiste_mit_aktueller_station_und_aktion(): void {
        $this->resetAfterTest();

        $html = stationen_leiste::render(
            $this->stationen(),
            new erfassen_aktion('bildungsbericht', 'Semester 2 weiter erfassen', '/local/test/erfassen.php?id=2')
        );

        $this->assertStringContainsString('Bildungsbericht', $html);
        // In der Zeitleiste die Kurzbezeichnung, darunter der Statustext.
        $this->assertStringContainsString('3. Semester', $html);
        $this->assertStringContainsString('geplant', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        // Die aktuelle Station mit Stand und der Aktion der Quelle.
        $this->assertStringContainsString('Fällig bis 31. August 2026', $html);
        $this->assertStringContainsString('Semester 2 weiter erfassen', $html);
        // Erledigt: Stand und Link in der Zeitleiste selbst, keine zweite
        // Liste; der Stand offener Stationen nur bei der aktuellen.
        $this->assertStringContainsString('Gespräch am 2. Februar 2026', $html);
        $this->assertSame(1, substr_count($html, '/local/test/ansicht.php?id=1'));
        $this->assertSame(1, substr_count($html, 'Fällig bis 31. August 2026'));
        $this->assertStringContainsString('/local/test/verlauf.php', $html);
        $this->assertStringContainsString(get_string('stationen:verlauf', 'local_berufsbildung'), $html);
    }

    /**
     * Ohne Abgeschlossenes, Verlauf und aktuelle Station bleibt die
     * Schaltflaeche trotzdem erreichbar.
     */
    public function test_ohne_aktuelle_station_steht_die_aktion_trotzdem(): void {
        $this->resetAfterTest();

        $html = stationen_leiste::render(
            new stationen('bildungsbericht', 'Bildungsbericht', [
                new station('bildungsbericht', 'Bildungsbericht 1. Semester', station::STATUS_GEPLANT, 'geplant'),
            ]),
            new erfassen_aktion('bildungsbericht', 'Semester 1 anlegen', '/local/test/anlegen.php')
        );

        $this->assertStringContainsString('Semester 1 anlegen', $html);
        $this->assertStringNotContainsString(get_string('stationen:verlauf', 'local_berufsbildung'), $html);
        $this->assertStringNotContainsString('aria-current', $html);
    }

    public function test_kachel_zeigt_aktuelle_station_in_eigener_spalte(): void {
        $this->resetAfterTest();

        $html = lernenden_kachel::render(
            'Elena Furrer',
            null,
            0,
            0,
            '<p>Detail</p>',
            stationen: [
                new station(
                    'bildungsbericht',
                    'Probezeitbericht',
                    station::STATUS_UEBERFAELLIG,
                    'überfällig',
                    'Überfällig seit gestern'
                ),
            ]
        );

        $summary = substr($html, 0, strpos($html, '</summary>'));
        $this->assertStringContainsString('local-berufsbildung-kachel-stationen', $summary);
        $this->assertStringContainsString('Probezeitbericht', $summary);
        $this->assertStringContainsString('überfällig', $summary);
        $this->assertStringContainsString('Überfällig seit gestern', $summary);
        $this->assertStringContainsString('border-danger', $summary);
        // Kein Link im Umschalter (WCAG 4.1.2).
        $this->assertStringNotContainsString('<a ', $summary);
    }

    /**
     * Ohne Quelle mit Stationen (null) keine Spalte; leer haelt sie frei.
     */
    public function test_kachel_ohne_stationen(): void {
        $this->resetAfterTest();

        $ohne = lernenden_kachel::render('Elena Furrer', null, 0, 0, '');
        $leer = lernenden_kachel::render('Elena Furrer', null, 0, 0, '', stationen: []);

        $this->assertStringNotContainsString('local-berufsbildung-kachel-stationen', $ohne);
        $this->assertStringContainsString('local-berufsbildung-kachel-stationen', $leer);
        $this->assertStringNotContainsString('local-berufsbildung-kachel-station ', $leer);
    }
}
