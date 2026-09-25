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
 * Tests fuer die Semesterleiste.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;

/**
 * Tests fuer semester_stepper.
 *
 * @covers \local_berufsbildung\output\semester_stepper
 */
final class semester_stepper_test extends advanced_testcase {
    /**
     * Zaehlt die Segmente je Zustand. Das laufende traegt dieselbe
     * Primaerfarbe wie die abgeschlossenen und wird deshalb ueber seine
     * eigene Klasse gezaehlt.
     *
     * @param string $html
     * @return array{segmente: int, aktuell: int, primaer: int, kommend: int}
     */
    private function zaehle(string $html): array {
        return [
            'segmente' => substr_count($html, 'local-berufsbildung-stepper-segment '),
            'aktuell' => substr_count($html, 'local-berufsbildung-stepper-segment-aktuell'),
            'primaer' => substr_count($html, ' bg-primary'),
            'kommend' => substr_count($html, ' bg-secondary'),
        ];
    }

    public function test_ein_segment_je_semester_mit_genau_einem_laufenden(): void {
        $this->resetAfterTest();

        $zaehlung = $this->zaehle(semester_stepper::render(3, 6));

        $this->assertSame(6, $zaehlung['segmente']);
        $this->assertSame(1, $zaehlung['aktuell']);
        // Zwei abgeschlossene plus das laufende.
        $this->assertSame(3, $zaehlung['primaer']);
        $this->assertSame(3, $zaehlung['kommend']);
    }

    /**
     * Randfall am Lehrbeginn: nichts ist abgeschlossen, das erste Semester
     * laeuft.
     */
    public function test_erstes_semester_hat_kein_abgeschlossenes(): void {
        $this->resetAfterTest();

        $zaehlung = $this->zaehle(semester_stepper::render(1, 6));

        $this->assertSame(1, $zaehlung['aktuell']);
        $this->assertSame(1, $zaehlung['primaer']);
        $this->assertSame(5, $zaehlung['kommend']);
    }

    /**
     * Randfall am Lehrende: nichts kommt mehr - auch der Rueckblick nach
     * Lehrabschluss (meine_lehre.php) steht im letzten Semester.
     */
    public function test_letztes_semester_hat_kein_kommendes(): void {
        $this->resetAfterTest();

        $zaehlung = $this->zaehle(semester_stepper::render(8, 8));

        $this->assertSame(8, $zaehlung['segmente']);
        $this->assertSame(1, $zaehlung['aktuell']);
        $this->assertSame(8, $zaehlung['primaer']);
        $this->assertSame(0, $zaehlung['kommend']);
    }

    /**
     * Jedes Lehrjahr steht ausgeschrieben unter seiner Gruppe, das
     * laufende hervorgehoben. Die kompakte Variante hat dafuer keinen
     * Platz, nennt das Lehrjahr aber im Titel.
     */
    public function test_beschriftet_die_lehrjahre_ausser_in_der_kompakten_variante(): void {
        $this->resetAfterTest();

        $html = semester_stepper::render(3, 6);
        // Mit Leerzeichen: sonst zaehlte -marke-aktuell die laufende Gruppe doppelt.
        $this->assertSame(3, substr_count($html, 'local-berufsbildung-stepper-marke '));
        $this->assertSame(1, substr_count($html, 'local-berufsbildung-stepper-marke-aktuell'));
        $this->assertStringContainsString(get_string('stepper:lehrjahr', 'local_berufsbildung', 2), $html);

        $kompakt = semester_stepper::render(3, 6, true);
        $this->assertStringNotContainsString('local-berufsbildung-stepper-marke', $kompakt);
        $this->assertStringContainsString(
            'title="' . get_string('stepper:lehrjahr', 'local_berufsbildung', 2) . '"',
            $kompakt
        );
    }
}
