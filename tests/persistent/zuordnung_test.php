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
 * Tests fuer die Zuordnung-Persistent-Klasse.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\persistent;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\persistent\zuordnung::class)]
/**
 * Tests fuer zuordnung.
 *
 * @covers \local_berufsbildung\persistent\zuordnung
 */
final class zuordnung_test extends advanced_testcase {
    /**
     * Eine Zuordnung anlegen und unveraendert zurueckgelesen bekommen.
     */
    public function test_create_and_read(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $gueltigvon = time();

        $zuordnung = new zuordnung(0, (object) [
            'berufsbildnerid' => $berufsbildner->id,
            'lernendeid' => $lernende->id,
            'beruf' => 'AU_EFZ',
            'gueltig_von' => $gueltigvon,
        ]);
        $zuordnung->create();

        $geladen = new zuordnung((int) $zuordnung->get('id'));
        $this->assertSame((int) $berufsbildner->id, $geladen->get('berufsbildnerid'));
        $this->assertSame((int) $lernende->id, $geladen->get('lernendeid'));
        $this->assertSame('AU_EFZ', $geladen->get('beruf'));
        $this->assertSame('hauptverantwortlich', $geladen->get('rolle'));
        $this->assertSame($gueltigvon, $geladen->get('gueltig_von'));
        $this->assertNull($geladen->get('gueltig_bis'));
    }

    /**
     * Regression: manche Schulen pflegen im Beruf-Profilfeld die
     * ausgeschriebene Bezeichnung statt eines Kurzcodes - Schraegstriche,
     * Leerzeichen und Bindestriche muessen daher erlaubt sein, nicht nur
     * PARAM_ALPHANUMEXT-vertraegliche Zeichen.
     */
    public function test_create_mit_ausgeschriebener_berufsbezeichnung(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();

        $zuordnung = new zuordnung(0, (object) [
            'berufsbildnerid' => $berufsbildner->id,
            'lernendeid' => $lernende->id,
            'beruf' => 'Automatiker/in EFZ',
            'gueltig_von' => time(),
        ]);
        $zuordnung->create();

        $geladen = new zuordnung((int) $zuordnung->get('id'));
        $this->assertSame('Automatiker/in EFZ', $geladen->get('beruf'));
    }
}
