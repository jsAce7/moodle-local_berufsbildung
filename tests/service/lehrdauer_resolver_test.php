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
 * Tests fuer die Aufloesung der Lehrdauer je Beruf.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\lehrdauer_resolver::class)]
/**
 * Tests fuer lehrdauer_resolver.
 *
 * @covers \local_berufsbildung\service\lehrdauer_resolver
 */
final class lehrdauer_resolver_test extends advanced_testcase {
    public function test_beruf_in_konfiguration_ueberschreibt_standard(): void {
        $resolver = new lehrdauer_resolver();
        $konfiguration = "AU_EFZ=8\nPM_EFZ=6";

        $this->assertSame(6, $resolver->loese_auf('PM_EFZ', $konfiguration, 8));
    }

    /**
     * Randfall: Beruf kommt in der Konfiguration nicht vor - der globale
     * Standard gilt, damit unkonfigurierte Berufe sich nicht aendern.
     */
    public function test_unbekannter_beruf_faellt_auf_standard_zurueck(): void {
        $resolver = new lehrdauer_resolver();
        $konfiguration = "AU_EFZ=8\nPM_EFZ=6";

        $this->assertSame(8, $resolver->loese_auf('KR_EFZ', $konfiguration, 8));
    }

    public function test_leere_konfiguration_faellt_auf_standard_zurueck(): void {
        $resolver = new lehrdauer_resolver();

        $this->assertSame(8, $resolver->loese_auf('AU_EFZ', '', 8));
    }

    /**
     * Fehlerhafte Zeilen (kein '=', kein numerischer Wert) werden ignoriert,
     * statt den ganzen Aufruf scheitern zu lassen - eine Tippfehler-Zeile
     * soll nicht die uebrige Konfiguration unbrauchbar machen.
     */
    public function test_fehlerhafte_zeilen_werden_uebersprungen(): void {
        $resolver = new lehrdauer_resolver();
        $konfiguration = "keine_gleichheitszeichen\nAU_EFZ=abc\nPM_EFZ=6";

        $this->assertSame(6, $resolver->loese_auf('PM_EFZ', $konfiguration, 8));
        $this->assertSame(8, $resolver->loese_auf('AU_EFZ', $konfiguration, 8));
    }

    public function test_leerzeichen_um_code_und_wert_werden_toleriert(): void {
        $resolver = new lehrdauer_resolver();

        $this->assertSame(6, $resolver->loese_auf('PM_EFZ', ' PM_EFZ = 6 ', 8));
    }
}
