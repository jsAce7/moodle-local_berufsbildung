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
 * Tests fuer die nach Quelle gruppierte Nachweis-Darstellung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\nachweis\nachweis;

/**
 * @covers \local_berufsbildung\output\nachweis_liste
 */
final class nachweis_liste_test extends advanced_testcase {

    public function test_ohne_nachweise_zeigt_leertext(): void {
        $this->resetAfterTest();

        $html = nachweis_liste::render([], []);

        $this->assertStringContainsString(
            get_string('form:keine_taetigkeiten', 'local_berufsbildung'),
            $html
        );
    }

    /**
     * Getrennte Darstellung je Quelle (z.B. Lerndokumentation vs. ueK) -
     * jede Quelle bekommt ihre eigene Ueberschrift, die Reihenfolge
     * innerhalb einer Gruppe bleibt wie eingegeben (bereits vom Collector
     * sortiert, hier nicht neu sortiert).
     */
    public function test_gruppiert_nach_quelle(): void {
        $this->resetAfterTest();

        $nachweise = [
            new nachweis('lerndoku', 'Neuerer Lerndoku-Eintrag', time(), null, null, null),
            new nachweis('uek', 'üK 3: Steuerungstechnik', time() - 10, '5.0', null, null),
            new nachweis('lerndoku', 'Aelterer Lerndoku-Eintrag', time() - 100, null, null, null),
        ];

        $html = nachweis_liste::render($nachweise, [
            'lerndoku' => 'Lerndokumentation',
            'uek' => 'Überbetriebliche Kurse',
        ]);

        $this->assertStringContainsString('Lerndokumentation', $html);
        $this->assertStringContainsString('Überbetriebliche Kurse', $html);
        $this->assertStringContainsString('üK 3: Steuerungstechnik', $html);

        // Reihenfolge innerhalb der Lerndoku-Gruppe bleibt neueste zuerst.
        $posneuer = strpos($html, 'Neuerer Lerndoku-Eintrag');
        $posaelter = strpos($html, 'Aelterer Lerndoku-Eintrag');
        $this->assertNotFalse($posneuer);
        $this->assertNotFalse($posaelter);
        $this->assertLessThan($posaelter, $posneuer);
    }

    public function test_ergebnis_wird_angezeigt(): void {
        $this->resetAfterTest();

        $nachweise = [new nachweis('uek', 'üK 3', time(), 'bestanden', null, null)];

        $html = nachweis_liste::render($nachweise, ['uek' => 'ÜK']);

        $this->assertStringContainsString('bestanden', $html);
    }

    /**
     * Randfall: eine Quelle ohne Eintrag im uebergebenen Namen-Mapping
     * (z.B. ein inzwischen deaktivierter Provider) faellt auf den
     * Quelle-Key zurueck, statt die Darstellung abzubrechen.
     */
    public function test_unbekannte_quelle_faellt_auf_key_zurueck(): void {
        $this->resetAfterTest();

        $nachweise = [new nachweis('unbekannt', 'Eintrag', time(), null, null, null)];

        $html = nachweis_liste::render($nachweise, []);

        $this->assertStringContainsString('unbekannt', $html);
    }
}
