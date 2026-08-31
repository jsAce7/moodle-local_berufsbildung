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
 * Tests fuer die Luecken-Analyse.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use advanced_testcase;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_hk;
use local_berufsbildung\persistent\einsatz;

/**
 * @covers \local_berufsbildung\versetzungsplan\luecken_analyse
 */
final class luecken_analyse_test extends advanced_testcase {

    private function lege_profilfelder_an(): void {
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'beruf', 'name' => 'Beruf',
        ]);
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'jahrgang', 'name' => 'Jahrgang',
        ]);
    }

    /**
     * Jahrgang so waehlen, dass "jetzt" immer in der Lehre liegt,
     * unabhaengig davon, in welchem Kalendermonat der Test laeuft.
     */
    private function laufender_jahrgang(): int {
        $jetzt = time();

        return (int) date('n', $jetzt) >= 8 ? (int) date('Y', $jetzt) : (int) date('Y', $jetzt) - 1;
    }

    public function test_get_luecken_liefert_nicht_abgedeckte_kompetenzen(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $this->laufender_jahrgang(),
        ]);

        $competencygenerator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competencygenerator->create_framework(['idnumber' => 'au-2022']);
        $abgedeckt = $competencygenerator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $luecke = $competencygenerator->create_competency(['competencyframeworkid' => $framework->get('id')]);

        $block = new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]);
        $block->create();
        (new block_hk(0, (object) [
            'blockid' => $block->get('id'), 'competencyid' => $abgedeckt->get('id'), 'intensitaet' => 'schwerpunkt',
        ]))->create();

        (new einsatz(0, (object) [
            'userid' => $lernende->id, 'blockid' => $block->get('id'),
            'von' => strtotime('-1 month'), 'bis' => strtotime('+1 month'),
            'kw_von' => '2027-W01', 'kw_bis' => '2027-W04', 'importid' => 0,
        ]))->create();

        $luecken = (new luecken_analyse())->get_luecken((int) $lernende->id);

        $this->assertSame([(int) $luecke->get('id')], $luecken);
    }

    public function test_get_luecken_ohne_rahmen_konfiguration_ist_leer(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        // Keine beruf_rahmen_mapping-Konfiguration gesetzt.

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $this->laufender_jahrgang(),
        ]);

        $this->assertSame([], (new luecken_analyse())->get_luecken((int) $lernende->id));
    }

    public function test_wahlpflicht_hk_wird_nicht_als_luecke_ausgewiesen(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');
        set_config('beruf_wahlpflicht_hk', 'AU_EFZ=7777 a.04', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $this->laufender_jahrgang(),
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $pflicht = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'idnumber' => '7777 a.01',
        ]);
        $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'idnumber' => '7777 a.04',
        ]);

        $luecken = (new luecken_analyse())->get_luecken((int) $lernende->id);

        $this->assertSame([(int) $pflicht->get('id')], $luecken);
    }

    /**
     * Randfall: die Lehre hat zum Stichtag noch nicht begonnen -
     * get_ausbildungsstand() liefert null, die Luecken-Analyse muss das
     * abfangen statt auf einer Null-Referenz zu scheitern.
     */
    public function test_get_luecken_lehre_noch_nicht_begonnen_ist_leer(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) (((int) date('Y')) + 5),
        ]);

        $this->assertSame([], (new luecken_analyse())->get_luecken((int) $lernende->id));
    }

    public function test_get_luecken_konfigurierter_rahmen_existiert_nicht_ist_leer(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=existiert-nicht', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $this->laufender_jahrgang(),
        ]);

        $this->assertSame([], (new luecken_analyse())->get_luecken((int) $lernende->id));
    }
}
