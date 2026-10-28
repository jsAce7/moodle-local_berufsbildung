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
 * Tests fuer die Tabelle der Stationen ueber alle Lernenden.
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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\output\stationen_tabelle::class)]
/**
 * Tests fuer stationen_tabelle.
 *
 * @covers \local_berufsbildung\output\stationen_tabelle
 */
final class stationen_tabelle_test extends advanced_testcase {
    /**
     * Stationen mit den angegebenen Kurzbezeichnungen, alle geplant.
     *
     * @param string[] $kurz
     * @return stationen
     */
    private function geplant(array $kurz): stationen {
        $eintraege = [];
        foreach ($kurz as $name) {
            $eintraege[] = new station('bericht', 'Bericht ' . $name, station::STATUS_GEPLANT, 'geplant', kurzbezeichnung: $name);
        }

        return new stationen('bericht', 'Bildungsbericht', $eintraege);
    }

    public function test_spalten_fuehren_alle_personen_zusammen(): void {
        $spalten = stationen_tabelle::spalten([
            $this->geplant(['Probezeit', '1. Semester', '2. Semester']),
            // Drei Lehrjahre mehr Semester, dazu eine Verlaengerung der Probezeit.
            $this->geplant(['Probezeit', 'Probezeit, Verlängerung 1', '1. Semester', '2. Semester', '3. Semester']),
            $this->geplant(['1. Semester', '2. Semester']),
        ]);

        $this->assertSame(
            ['Probezeit', 'Probezeit, Verlängerung 1', '1. Semester', '2. Semester', '3. Semester'],
            $spalten
        );
    }

    public function test_spalten_ohne_kurzbezeichnung_nehmen_die_bezeichnung(): void {
        $stationen = new stationen('q', 'Quelle', [
            new station('q', 'Erster Schritt', station::STATUS_ERLEDIGT, 'erledigt'),
            new station('q', 'Zweiter Schritt', station::STATUS_GEPLANT, 'geplant', kurzbezeichnung: 'Zwei'),
        ]);

        $this->assertSame(['Erster Schritt', 'Zwei'], stationen_tabelle::spalten([$stationen]));
        $this->assertSame([], stationen_tabelle::spalten([]));
    }

    public function test_render_zeigt_zeilen_spalten_aktion_und_legende(): void {
        $this->resetAfterTest();

        $anna = new stationen('bericht', 'Bildungsbericht', [
            new station(
                'bericht',
                'Probezeitbericht',
                station::STATUS_ERLEDIGT,
                'freigegeben',
                'Gespräch am 2. Oktober 2026',
                '/ansicht.php?id=1',
                kurzbezeichnung: 'Probezeit'
            ),
            new station(
                'bericht',
                'Bildungsbericht 1. Semester',
                station::STATUS_UEBERFAELLIG,
                'überfällig',
                'Fällig seit 11. Oktober 2026',
                '/erfassen.php?id=2',
                true,
                '1. Semester'
            ),
        ]);
        $ben = new stationen('bericht', 'Bildungsbericht', [
            new station(
                'bericht',
                'Bildungsbericht 1. Semester',
                station::STATUS_GEPLANT,
                'geplant',
                'Fällig bis 28. Februar 2027',
                null,
                true,
                '1. Semester'
            ),
        ]);

        $html = stationen_tabelle::render('Bildungsbericht', [
            [
                'name' => 'Anna Muster',
                'stand' => 'AU_EFZ, 1. Lehrjahr (Semester 1)',
                'stationen' => $anna,
                'aktion' => new erfassen_aktion('bericht', '1. Semester weiter erfassen', '/erfassen.php?id=2'),
            ],
            ['name' => 'Ben Keller', 'stand' => null, 'stationen' => $ben, 'aktion' => null],
        ]);

        $this->assertStringContainsString('Anna Muster', $html);
        $this->assertStringContainsString('AU_EFZ, 1. Lehrjahr (Semester 1)', $html);
        $this->assertStringContainsString('>Probezeit</th>', $html);
        $this->assertStringContainsString('>1. Semester</th>', $html);
        // Zelle mit Link und vollem Titel; bei Erledigtem samt Stand.
        $this->assertStringContainsString('href="/ansicht.php?id=1"', $html);
        $this->assertStringContainsString('Probezeitbericht: freigegeben · Gespräch am 2. Oktober 2026', $html);
        $this->assertStringContainsString('Bildungsbericht 1. Semester: überfällig', $html);
        $this->assertStringContainsString('local-berufsbildung-station-punkt-aktuell', $html);
        // Ben hat keine Probezeit: die Zelle bleibt leer, mit Text fuer Screenreader.
        $this->assertStringContainsString(get_string('stationen:tabelle_keine', 'local_berufsbildung'), $html);
        // Naechster Schritt: Aktion mit Stand, ohne Aktion die aktuelle Station.
        $this->assertStringContainsString('href="/erfassen.php?id=2" class="btn btn-primary btn-sm"', $html);
        $this->assertStringContainsString('Fällig seit 11. Oktober 2026', $html);
        $this->assertStringContainsString('1. Semester: <span class="">geplant</span>', $html);
        // Legende nur mit vorkommenden Status.
        $this->assertStringContainsString(get_string('stationen:status_erledigt', 'local_berufsbildung'), $html);
        $this->assertStringContainsString(get_string('stationen:status_ueberfaellig', 'local_berufsbildung'), $html);
        $this->assertStringNotContainsString(get_string('stationen:status_entfaellt', 'local_berufsbildung'), $html);
        $this->assertStringNotContainsString(get_string('stationen:status_offen', 'local_berufsbildung'), $html);
    }
}
