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
 * Tests fuer den Beruf-Katalog.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;

/**
 * @covers \local_berufsbildung\service\beruf_katalog
 */
final class beruf_katalog_test extends advanced_testcase {

    /**
     * Legt das Beruf-Profilfeld an.
     *
     * @param string $datatype 'text' oder 'menu'
     * @param string $param1 Optionen bei 'menu', eine je Zeile
     * @return int Feld-id
     */
    private function feld_anlegen(string $datatype, string $param1 = ''): int {
        $feld = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => $datatype,
            'shortname' => 'beruf',
            'name' => 'Beruf',
            'param1' => $param1,
        ]);

        return (int) $feld->id;
    }

    /**
     * Hinterlegt den Wert bei einer neuen Person direkt in der Tabelle -
     * profile_save_data() erwartet bei einem Auswahlfeld den Options-Index
     * statt des Werts, hier soll aber genau der gespeicherte Text geprueft
     * werden.
     *
     * @param int $fieldid
     * @param string $wert
     */
    private function wert_setzen(int $fieldid, string $wert): void {
        global $DB;

        $person = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_info_data', (object) [
            'userid' => $person->id,
            'fieldid' => $fieldid,
            'data' => $wert,
            'dataformat' => 0,
        ]);
    }

    public function test_menu_optionen_stehen_auch_ohne_lernende_zur_auswahl(): void {
        $this->resetAfterTest();

        $this->feld_anlegen('menu', "PM_EFZ\nAU_EFZ");

        $this->assertSame(['AU_EFZ', 'PM_EFZ'], (new beruf_katalog())->alle_codes('beruf'));
    }

    public function test_textfeld_liefert_die_hinterlegten_werte_eindeutig(): void {
        $this->resetAfterTest();

        $fieldid = $this->feld_anlegen('text');
        $this->wert_setzen($fieldid, 'PM_EFZ');
        $this->wert_setzen($fieldid, 'AU_EFZ');
        $this->wert_setzen($fieldid, 'PM_EFZ');

        $this->assertSame(['AU_EFZ', 'PM_EFZ'], (new beruf_katalog())->alle_codes('beruf'));
    }

    /**
     * Randfall: die Menu-Option wurde entfernt, eine lernende Person steht
     * aber noch auf dem Wert - der Beruf darf nicht aus der Verwaltung
     * verschwinden, sonst laesst sich sein Rahmen nicht mehr pflegen.
     */
    public function test_gespeicherter_wert_ohne_menu_option_bleibt_enthalten(): void {
        $this->resetAfterTest();

        $fieldid = $this->feld_anlegen('menu', 'AU_EFZ');
        $this->wert_setzen($fieldid, 'KR_EFZ');

        $this->assertSame(['AU_EFZ', 'KR_EFZ'], (new beruf_katalog())->alle_codes('beruf'));
    }

    public function test_leere_werte_und_leerzeichen_werden_uebergangen(): void {
        $this->resetAfterTest();

        $fieldid = $this->feld_anlegen('menu', "AU_EFZ\n\n  ");
        $this->wert_setzen($fieldid, '');
        $this->wert_setzen($fieldid, ' AU_EFZ ');

        $this->assertSame(['AU_EFZ'], (new beruf_katalog())->alle_codes('beruf'));
    }

    /**
     * Randfall: das konfigurierte Feld existiert nicht (umbenannt oder noch
     * nicht angelegt) - leere Liste statt eines Datenbankfehlers, die Seite
     * zeigt dann ihren Hinweis.
     */
    public function test_unbekanntes_profilfeld_liefert_leere_liste(): void {
        $this->resetAfterTest();

        $this->feld_anlegen('menu', 'AU_EFZ');

        $this->assertSame([], (new beruf_katalog())->alle_codes('taetigkeit'));
    }

    public function test_leerer_feldname_liefert_leere_liste(): void {
        $this->resetAfterTest();

        $this->assertSame([], (new beruf_katalog())->alle_codes(''));
    }
}
