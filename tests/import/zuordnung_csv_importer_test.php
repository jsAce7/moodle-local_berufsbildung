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
 * Tests fuer den Zuordnungs-CSV-Import.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\import;

use advanced_testcase;
use local_berufsbildung\api;
use local_berufsbildung\persistent\zuordnung;

/**
 * @covers \local_berufsbildung\import\zuordnung_csv_importer
 */
final class zuordnung_csv_importer_test extends advanced_testcase {

    public function test_zeile_zuordnen_liest_spalten_unabhaengig_von_reihenfolge(): void {
        $importer = new zuordnung_csv_importer();

        // Spalten in der Datei stehen in anderer Reihenfolge als erwartet.
        $spaltenindex = ['gueltig_von' => 0, 'lernende' => 1, 'berufsbildner' => 2, 'beruf' => 3];
        $rohzeile = ['2027-08-01', 'lern_user', 'bb_user', 'AU_EFZ'];

        $zeile = $importer->zeile_zuordnen($rohzeile, $spaltenindex);

        $this->assertSame([
            'berufsbildner' => 'bb_user',
            'lernende' => 'lern_user',
            'beruf' => 'AU_EFZ',
            'gueltig_von' => '2027-08-01',
        ], $zeile);
    }

    public function test_verarbeite_zeile_normalfall_ohne_anlegen(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user(['username' => 'bb_csv']);
        $lernende = $this->getDataGenerator()->create_user(['username' => 'lern_csv']);

        $importer = new zuordnung_csv_importer();
        $ergebnis = $importer->verarbeite_zeile([
            'berufsbildner' => 'bb_csv',
            'lernende' => 'lern_csv',
            'beruf' => 'AU_EFZ',
            'gueltig_von' => '2027-08-01',
        ], false);

        $this->assertNull($ergebnis['fehler']);
        $this->assertCount(0, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    public function test_verarbeite_zeile_legt_bei_anlegen_true_tatsaechlich_an(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user(['username' => 'bb_csv']);
        $lernende = $this->getDataGenerator()->create_user(['username' => 'lern_csv']);

        $importer = new zuordnung_csv_importer();
        $ergebnis = $importer->verarbeite_zeile([
            'berufsbildner' => 'bb_csv',
            'lernende' => 'lern_csv',
            'beruf' => 'AU_EFZ',
            'gueltig_von' => '2027-08-01',
        ], true);

        $this->assertNull($ergebnis['fehler']);
        $this->assertTrue(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id, strtotime('2027-08-01')));

        $zuordnungen = zuordnung::get_records(['lernendeid' => (int) $lernende->id]);
        $this->assertCount(1, $zuordnungen);
        $this->assertSame('AU_EFZ', reset($zuordnungen)->get('beruf'));
    }

    /**
     * Randfall: leerer Beruf in der CSV wird wie beim manuellen Formular aus
     * dem Profil uebernommen, nicht als Fehler behandelt.
     */
    public function test_verarbeite_zeile_leerer_beruf_wird_aus_profil_uebernommen(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'beruf', 'name' => 'Beruf']);
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'jahrgang', 'name' => 'Jahrgang']);

        $berufsbildner = $this->getDataGenerator()->create_user(['username' => 'bb_csv']);
        $lernende = $this->getDataGenerator()->create_user([
            'username' => 'lern_csv',
            'profile_field_beruf' => 'KR_EFZ',
            'profile_field_jahrgang' => '2026',
        ]);

        $importer = new zuordnung_csv_importer();
        $importer->verarbeite_zeile([
            'berufsbildner' => 'bb_csv',
            'lernende' => 'lern_csv',
            'beruf' => '',
            'gueltig_von' => '2027-08-01',
        ], true);

        $zuordnungen = zuordnung::get_records(['lernendeid' => (int) $lernende->id]);
        $this->assertSame('KR_EFZ', reset($zuordnungen)->get('beruf'));
    }

    public function test_verarbeite_zeile_unbekannter_benutzername_ergibt_fehler_ohne_absturz(): void {
        $this->resetAfterTest();

        $importer = new zuordnung_csv_importer();
        $ergebnis = $importer->verarbeite_zeile([
            'berufsbildner' => 'gibt_es_nicht',
            'lernende' => 'gibt_es_auch_nicht',
            'beruf' => 'AU_EFZ',
            'gueltig_von' => '2027-08-01',
        ], false);

        $this->assertNotNull($ergebnis['fehler']);
    }

    public function test_verarbeite_zeile_fehlender_benutzername_ergibt_fehler(): void {
        $this->resetAfterTest();

        $importer = new zuordnung_csv_importer();

        $ohnebb = $importer->verarbeite_zeile(
            ['berufsbildner' => '', 'lernende' => 'irgendwer', 'beruf' => '', 'gueltig_von' => '2027-08-01'],
            false
        );
        $this->assertNotNull($ohnebb['fehler']);

        $ohnelernende = $importer->verarbeite_zeile(
            ['berufsbildner' => 'irgendwer', 'lernende' => '', 'beruf' => '', 'gueltig_von' => '2027-08-01'],
            false
        );
        $this->assertNotNull($ohnelernende['fehler']);
    }

    public function test_verarbeite_zeile_gleiche_person_ergibt_fehler(): void {
        $this->resetAfterTest();

        $person = $this->getDataGenerator()->create_user(['username' => 'nur_eine_person']);

        $importer = new zuordnung_csv_importer();
        $ergebnis = $importer->verarbeite_zeile([
            'berufsbildner' => 'nur_eine_person',
            'lernende' => 'nur_eine_person',
            'beruf' => 'AU_EFZ',
            'gueltig_von' => '2027-08-01',
        ], false);

        $this->assertNotNull($ergebnis['fehler']);
    }

    /**
     * Randfall: ein ungueltiges Datumsformat wird abgelehnt statt
     * stillschweigend (falsch) interpretiert zu werden.
     */
    public function test_verarbeite_zeile_ungueltiges_datum_ergibt_fehler(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user(['username' => 'bb_csv']);
        $lernende = $this->getDataGenerator()->create_user(['username' => 'lern_csv']);

        $importer = new zuordnung_csv_importer();
        $ergebnis = $importer->verarbeite_zeile([
            'berufsbildner' => 'bb_csv',
            'lernende' => 'lern_csv',
            'beruf' => 'AU_EFZ',
            'gueltig_von' => '01.08.2027',
        ], false);

        $this->assertNotNull($ergebnis['fehler']);
    }
}
