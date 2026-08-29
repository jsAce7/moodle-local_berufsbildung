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
 * Tests fuer die Aufloesung des Kompetenzrahmens je Beruf.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;

/**
 * @covers \local_berufsbildung\service\rahmen_resolver
 */
final class rahmen_resolver_test extends advanced_testcase {

    public function test_beruf_in_konfiguration_liefert_framework_idnumber(): void {
        $resolver = new rahmen_resolver();
        $konfiguration = "AU_EFZ=au-2022\nPM_EFZ=pm-2022";

        $this->assertSame('pm-2022', $resolver->loese_auf('PM_EFZ', $konfiguration));
    }

    /**
     * Randfall: Beruf kommt in der Konfiguration nicht vor - null statt
     * eines geratenen Rahmens, damit Aufrufer das explizit als
     * "nicht konfiguriert" erkennen koennen.
     */
    public function test_unbekannter_beruf_liefert_null(): void {
        $resolver = new rahmen_resolver();
        $konfiguration = "AU_EFZ=au-2022";

        $this->assertNull($resolver->loese_auf('KR_EFZ', $konfiguration));
    }

    public function test_leere_konfiguration_liefert_null(): void {
        $resolver = new rahmen_resolver();

        $this->assertNull($resolver->loese_auf('AU_EFZ', ''));
    }

    public function test_fehlerhafte_zeilen_werden_uebersprungen(): void {
        $resolver = new rahmen_resolver();
        $konfiguration = "keine_gleichheitszeichen\nAU_EFZ=\nPM_EFZ=pm-2022";

        $this->assertSame('pm-2022', $resolver->loese_auf('PM_EFZ', $konfiguration));
        $this->assertNull($resolver->loese_auf('AU_EFZ', $konfiguration));
    }

    public function test_leerzeichen_um_code_und_wert_werden_toleriert(): void {
        $resolver = new rahmen_resolver();

        $this->assertSame('pm-2022', $resolver->loese_auf('PM_EFZ', ' PM_EFZ = pm-2022 '));
    }
}
