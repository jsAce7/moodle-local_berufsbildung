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
 * Tests fuer die oeffentliche API.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung;

use advanced_testcase;
use local_berufsbildung\persistent\aufbewahrung;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\zuordnung;
use local_berufsbildung\service\semester_calculator;

/**
 * @covers \local_berufsbildung\api
 */
final class api_test extends advanced_testcase {

    /**
     * Legt eine Zuordnung an, ohne Umwege ueber die noch nicht existierende
     * set_zuordnung()-Methode.
     */
    private function lege_zuordnung_an(
        int $berufsbildnerid,
        int $lernendeid,
        int $von,
        ?int $bis = null,
        string $rolle = 'hauptverantwortlich'
    ): zuordnung {
        return $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => $berufsbildnerid,
            'lernendeid' => $lernendeid,
            'gueltig_von' => $von,
            'gueltig_bis' => $bis,
            'rolle' => $rolle,
        ]);
    }

    public function test_is_zustaendig_laufende_zuordnung_gilt(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $this->assertTrue(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id));
    }

    public function test_is_zustaendig_beendete_zuordnung_gilt_am_frueheren_stichtag_nicht_heute(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $von = strtotime('-2 years');
        $bis = strtotime('-1 year');
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, $von, $bis);

        $waehrend_zuordnung = strtotime('-18 months');
        $this->assertTrue(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id, $waehrend_zuordnung));
        $this->assertFalse(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id));
    }

    public function test_is_zustaendig_noch_nicht_begonnene_zuordnung_gilt_nicht(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('+1 year'));

        $this->assertFalse(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id));
    }

    public function test_is_zustaendig_fremde_person_gilt_nie(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $fremder = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $this->assertFalse(api::is_zustaendig((int) $fremder->id, (int) $lernende->id));
        $this->assertFalse(api::is_zustaendig((int) $berufsbildner->id, (int) $fremder->id));
    }

    /**
     * Stichtag-Randfall: Zuordnung endet genau am Stichtag (gilt noch),
     * beginnt genau am Stichtag (gilt schon).
     */
    public function test_is_zustaendig_stichtag_grenzen(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $von = strtotime('2026-08-01 00:00:00');
        $bis = strtotime('2027-01-31 23:59:59');
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, $von, $bis);

        $this->assertTrue(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id, $von));
        $this->assertFalse(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id, $von - 1));

        $this->assertTrue(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id, $bis));
        $this->assertFalse(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id, $bis + 1));
    }

    public function test_get_lernende_for(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $aktuell = $this->getDataGenerator()->create_user();
        $beendet = $this->getDataGenerator()->create_user();
        $andererberufsbildner = $this->getDataGenerator()->create_user();
        $fremd = $this->getDataGenerator()->create_user();

        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $aktuell->id, strtotime('-1 year'));
        $this->lege_zuordnung_an(
            (int) $berufsbildner->id,
            (int) $beendet->id,
            strtotime('-2 years'),
            strtotime('-1 month')
        );
        $this->lege_zuordnung_an((int) $andererberufsbildner->id, (int) $fremd->id, strtotime('-1 year'));

        $this->assertSame([(int) $aktuell->id], api::get_lernende_for((int) $berufsbildner->id));
    }

    public function test_get_lernende_for_stichtag(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $von = strtotime('-2 years');
        $bis = strtotime('-1 year');
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, $von, $bis);

        $waehrend_zuordnung = strtotime('-18 months');
        $this->assertSame(
            [(int) $lernende->id],
            api::get_lernende_for((int) $berufsbildner->id, $waehrend_zuordnung)
        );
        $this->assertSame([], api::get_lernende_for((int) $berufsbildner->id));
    }

    public function test_get_berufsbildner_for(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();
        $aktuell = $this->getDataGenerator()->create_user();
        $beendet = $this->getDataGenerator()->create_user();
        $andererlernender = $this->getDataGenerator()->create_user();
        $fremd = $this->getDataGenerator()->create_user();

        $this->lege_zuordnung_an((int) $aktuell->id, (int) $lernende->id, strtotime('-1 year'));
        $this->lege_zuordnung_an(
            (int) $beendet->id,
            (int) $lernende->id,
            strtotime('-2 years'),
            strtotime('-1 month')
        );
        $this->lege_zuordnung_an((int) $fremd->id, (int) $andererlernender->id, strtotime('-1 year'));

        $this->assertSame([(int) $aktuell->id], api::get_berufsbildner_for((int) $lernende->id));
    }

    /**
     * Stichtag-Randfall: waehrend der beendeten Zuordnung gilt sie noch,
     * danach nicht mehr.
     */
    public function test_get_berufsbildner_for_stichtag(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $von = strtotime('-2 years');
        $bis = strtotime('-1 year');
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, $von, $bis);

        $waehrend_zuordnung = strtotime('-18 months');
        $this->assertSame(
            [(int) $berufsbildner->id],
            api::get_berufsbildner_for((int) $lernende->id, $waehrend_zuordnung)
        );
        $this->assertSame([], api::get_berufsbildner_for((int) $lernende->id));
    }

    /**
     * Legt die beiden Profilfelder an, die per Default konfiguriert sind.
     */
    private function lege_profilfelder_an(): void {
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'beruf',
            'name' => 'Beruf',
        ]);
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'jahrgang',
            'name' => 'Jahrgang',
        ]);
    }

    public function test_get_ausbildungsstand(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();

        // Jahrgang so waehlen, dass "jetzt" immer in Semester 1 faellt,
        // unabhaengig davon, in welchem Kalendermonat der Test laeuft.
        $jetzt = time();
        $jahrgang = (int) date('n', $jetzt) >= 8 ? (int) date('Y', $jetzt) : (int) date('Y', $jetzt) - 1;

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $jahrgang,
        ]);

        $stand = api::get_ausbildungsstand((int) $lernende->id);

        $this->assertNotNull($stand);
        $this->assertSame('AU_EFZ', $stand->beruf);
        $this->assertSame($jahrgang, $stand->jahrgang);
        $this->assertSame(1, $stand->semester);
        $this->assertSame(1, $stand->lehrjahr);
        $this->assertSame(8, $stand->gesamtsemester);
    }

    /**
     * Das Jahrgang-Profilfeld darf auch ein kombiniertes Feld sein, das
     * zusaetzlich den Beruf enthaelt (z.B. "AU 2026" fuer die automatische
     * Kursgruppierung) - die Jahreszahl wird daraus extrahiert, der Beruf
     * kommt trotzdem aus dem separat konfigurierten Profilfeld.
     */
    public function test_get_ausbildungsstand_mit_kombiniertem_jahrgangsfeld(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();

        $jetzt = time();
        $jahrgang = (int) date('n', $jetzt) >= 8 ? (int) date('Y', $jetzt) : (int) date('Y', $jetzt) - 1;

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => 'AU ' . $jahrgang,
        ]);

        $stand = api::get_ausbildungsstand((int) $lernende->id);

        $this->assertNotNull($stand);
        $this->assertSame('AU_EFZ', $stand->beruf);
        $this->assertSame($jahrgang, $stand->jahrgang);
        $this->assertSame(1, $stand->semester);
    }

    /**
     * Randfall: enthaelt das Jahrgang-Profilfeld keine erkennbare
     * vierstellige Jahreszahl, gilt der Ausbildungsstand als nicht
     * aufloesbar - fail-safe, kein Fehler.
     */
    public function test_get_ausbildungsstand_jahrgangsfeld_ohne_erkennbare_jahreszahl_ist_null(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => 'AU',
        ]);

        $this->assertNull(api::get_ausbildungsstand((int) $lernende->id));
    }

    public function test_get_ausbildungsstand_ohne_profildaten_ist_null(): void {
        $this->resetAfterTest();

        $lernende = $this->getDataGenerator()->create_user();

        $this->assertNull(api::get_ausbildungsstand((int) $lernende->id));
    }

    public function test_get_ausbildungsstand_lehre_noch_nicht_begonnen_ist_null(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) ((int) date('Y') + 5),
        ]);

        $this->assertNull(api::get_ausbildungsstand((int) $lernende->id));
    }

    /**
     * Stichtag-Randfall: derselbe Jahrgang loest je nach uebergebenem
     * Stichtag ein anderes Semester auf als "jetzt".
     */
    public function test_get_ausbildungsstand_mit_stichtag_weicht_von_jetzt_ab(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => '2026',
        ]);

        $waehrend_semester1 = mktime(0, 0, 0, 9, 15, 2026);
        $waehrend_semester2 = mktime(0, 0, 0, 2, 15, 2027);

        $stand1 = api::get_ausbildungsstand((int) $lernende->id, $waehrend_semester1);
        $stand2 = api::get_ausbildungsstand((int) $lernende->id, $waehrend_semester2);

        $this->assertSame(1, $stand1->semester);
        $this->assertSame(2, $stand2->semester);
    }

    /**
     * Verschiedene Berufe koennen unterschiedlich lange dauern: PM_EFZ ist
     * ueber die Einstellung "beruf_dauer" auf sechs statt acht Semester
     * verkuerzt.
     */
    public function test_get_ausbildungsstand_beruf_mit_abweichender_lehrdauer(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_dauer', 'PM_EFZ=6', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'PM_EFZ',
            'profile_field_jahrgang' => '2026',
        ]);

        $waehrend_semester6 = mktime(0, 0, 0, 3, 1, 2029);
        $nach_lehrabschluss = mktime(0, 0, 0, 9, 1, 2029);

        $stand = api::get_ausbildungsstand((int) $lernende->id, $waehrend_semester6);
        $this->assertSame(6, $stand->semester);
        $this->assertSame(6, $stand->gesamtsemester);
        $this->assertNull(api::get_ausbildungsstand((int) $lernende->id, $nach_lehrabschluss));
    }

    /**
     * Randfall: ein Beruf, der in "beruf_dauer" nicht aufgefuehrt ist,
     * verwendet weiterhin die globale Lehrdauer - auch wenn fuer einen
     * anderen Beruf eine Abweichung konfiguriert ist.
     */
    public function test_get_ausbildungsstand_unkonfigurierter_beruf_verwendet_globale_lehrdauer(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_dauer', 'PM_EFZ=6', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => '2026',
        ]);

        $waehrend_semester7 = mktime(0, 0, 0, 9, 1, 2029);

        $this->assertSame(7, api::get_ausbildungsstand((int) $lernende->id, $waehrend_semester7)->semester);
    }

    public function test_get_kompetenzrahmen_for_beruf_konfigurierter_beruf(): void {
        $this->resetAfterTest();
        set_config('beruf_rahmen_mapping', "AU_EFZ=au-2022\nPM_EFZ=pm-2022", 'local_berufsbildung');

        $this->assertSame('pm-2022', api::get_kompetenzrahmen_for_beruf('PM_EFZ'));
    }

    /**
     * Randfall: ein Beruf ohne konfigurierten Rahmen liefert null, nicht
     * den Rahmen eines anderen Berufs oder einen Fehler.
     */
    public function test_get_kompetenzrahmen_for_beruf_unkonfigurierter_beruf_ist_null(): void {
        $this->resetAfterTest();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');

        $this->assertNull(api::get_kompetenzrahmen_for_beruf('KR_EFZ'));
    }

    public function test_get_block_name_bekannter_block(): void {
        $this->resetAfterTest();

        $block = new block(0, (object) ['nummer' => '4', 'name' => 'Abteilung Montage', 'ist_betrieb' => true, 'aktiv' => true]);
        $block->create();

        $this->assertSame('Abteilung Montage', api::get_block_name((int) $block->get('id')));
    }

    /**
     * Randfall: eine unbekannte blockid liefert null statt eines Fehlers
     * oder eines leeren Strings, der leicht mit "kein Name gesetzt"
     * verwechselt werden koennte.
     */
    public function test_get_block_name_unbekannter_block_ist_null(): void {
        $this->resetAfterTest();

        $this->assertNull(api::get_block_name(999999));
    }

    public function test_set_zuordnung_legt_neue_zuordnung_an(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $von = strtotime('2027-08-01 00:00:00');

        $zuordnung = api::set_zuordnung((int) $berufsbildner->id, (int) $lernende->id, 'AU_EFZ', $von);

        $this->assertSame((int) $berufsbildner->id, $zuordnung->get('berufsbildnerid'));
        $this->assertSame((int) $lernende->id, $zuordnung->get('lernendeid'));
        $this->assertSame('AU_EFZ', $zuordnung->get('beruf'));
        $this->assertSame('hauptverantwortlich', $zuordnung->get('rolle'));
        $this->assertSame($von, $zuordnung->get('gueltig_von'));
        $this->assertNull($zuordnung->get('gueltig_bis'));
    }

    /**
     * Randfall: die/der Lernende hat bereits eine laufende Zuordnung bei
     * einer/einem anderen Berufsbildner/in - die wird automatisch zum
     * Vortag der neuen beendet, nicht stillschweigend ueberschrieben.
     */
    public function test_set_zuordnung_beendet_bestehende_laufende_zuordnung_automatisch(): void {
        $this->resetAfterTest();

        $alterberufsbildner = $this->getDataGenerator()->create_user();
        $neuerberufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $alte = $this->lege_zuordnung_an((int) $alterberufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $von = strtotime('2027-08-01 00:00:00');
        api::set_zuordnung((int) $neuerberufsbildner->id, (int) $lernende->id, 'AU_EFZ', $von);

        $alte->read();
        $this->assertSame($von - 1, $alte->get('gueltig_bis'));
    }

    /**
     * Mehrere Berufsbildner/innen gleichzeitig fuer dieselbe Person: erlaubt,
     * solange sie verschiedene Rollen haben. Nur dieselbe Rolle ist pro
     * Lernende/r eindeutig.
     */
    public function test_set_zuordnung_andere_rolle_beendet_bestehende_zuordnung_nicht(): void {
        $this->resetAfterTest();

        $hauptverantwortlich = $this->getDataGenerator()->create_user();
        $stellvertretung = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $bestehende = $this->lege_zuordnung_an(
            (int) $hauptverantwortlich->id,
            (int) $lernende->id,
            strtotime('-1 year'),
            null,
            'hauptverantwortlich'
        );

        api::set_zuordnung(
            (int) $stellvertretung->id,
            (int) $lernende->id,
            'AU_EFZ',
            strtotime('-1 month'),
            'stellvertretung'
        );

        $bestehende->read();
        $this->assertNull($bestehende->get('gueltig_bis'));
        $this->assertTrue(api::is_zustaendig((int) $hauptverantwortlich->id, (int) $lernende->id));
        $this->assertTrue(api::is_zustaendig((int) $stellvertretung->id, (int) $lernende->id));
    }

    public function test_set_zuordnung_ohne_bestehende_zuordnung_beendet_nichts(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();

        // Wirft nicht und erzeugt keine unerwartete zweite Zuordnung.
        api::set_zuordnung((int) $berufsbildner->id, (int) $lernende->id, 'AU_EFZ', strtotime('-1 year'));

        $this->assertCount(1, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    public function test_beende_zuordnung_setzt_gueltig_bis(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $zuordnung = $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $bis = strtotime('2027-07-31 23:59:59');
        api::beende_zuordnung((int) $zuordnung->get('id'), $bis);

        $zuordnung->read();
        $this->assertSame($bis, $zuordnung->get('gueltig_bis'));
    }

    public function test_beende_zuordnung_lehnt_enddatum_vor_beginn_ab(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $von = strtotime('2027-08-01 00:00:00');
        $zuordnung = $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, $von);

        $this->expectException(\moodle_exception::class);
        api::beende_zuordnung((int) $zuordnung->get('id'), $von - 1);
    }

    public function test_loesche_zuordnung_entfernt_falschen_datensatz(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $zuordnung = $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, time());

        api::loesche_zuordnung((int) $zuordnung->get('id'));
        $this->assertFalse(zuordnung::record_exists((int) $zuordnung->get('id')));
    }

    public function test_set_zuordnung_leerer_beruf_wird_aus_profil_uebernommen(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'KR_EFZ',
            'profile_field_jahrgang' => '2026',
        ]);

        $zuordnung = api::set_zuordnung((int) $berufsbildner->id, (int) $lernende->id, '', strtotime('2026-08-01 00:00:00'));

        $this->assertSame('KR_EFZ', $zuordnung->get('beruf'));
    }

    public function test_set_zuordnung_leerer_beruf_wird_fuer_zukuenftigen_start_uebernommen(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'KR_EFZ',
            'profile_field_jahrgang' => '2027',
        ]);

        $zuordnung = api::set_zuordnung(
            (int) $berufsbildner->id,
            (int) $lernende->id,
            '',
            strtotime('2027-08-01 00:00:00')
        );

        $this->assertSame('KR_EFZ', $zuordnung->get('beruf'));
    }

    /**
     * Randfall: Profil (noch) ohne Beruf - kein Absturz, Feld bleibt leer
     * statt mit einem falschen Wert gefuellt zu werden.
     */
    public function test_set_zuordnung_leerer_beruf_ohne_profildaten_bleibt_leer(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();

        $zuordnung = api::set_zuordnung((int) $berufsbildner->id, (int) $lernende->id, '', strtotime('2026-08-01 00:00:00'));

        $this->assertSame('', $zuordnung->get('beruf'));
    }

    /**
     * Randfall: gueltig_bis = null macht eine beendete Zuordnung wieder
     * laufend - zum Korrigieren eines falsch gesetzten Enddatums, ohne die
     * Zuordnung neu anzulegen.
     */
    public function test_beende_zuordnung_mit_null_macht_wieder_laufend(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $zuordnung = $this->lege_zuordnung_an(
            (int) $berufsbildner->id,
            (int) $lernende->id,
            strtotime('-1 year'),
            strtotime('-1 month')
        );

        api::beende_zuordnung((int) $zuordnung->get('id'), null);

        $zuordnung->read();
        $this->assertNull($zuordnung->get('gueltig_bis'));
        $this->assertTrue(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id));
    }

    public function test_get_ausbildungsende_berechnet_letztes_semesterende(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2026',
        ]);

        $erwartet = (new semester_calculator(8, 8))->semester_grenzen(2026, 8)[1];

        $this->assertSame($erwartet, api::get_ausbildungsende((int) $lernende->id));
    }

    public function test_get_ausbildungsende_ohne_profildaten_ist_null(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->create_user();

        $this->assertNull(api::get_ausbildungsende((int) $lernende->id));
    }

    /**
     * Wie test_get_ausbildungsstand_beruf_mit_abweichender_lehrdauer(), nur
     * fuer get_ausbildungsende(): eine abweichend konfigurierte Lehrdauer
     * muss auch das berechnete Ausbildungsende verschieben.
     */
    public function test_get_ausbildungsende_beruf_mit_abweichender_lehrdauer(): void {
        $this->resetAfterTest();
        set_config('beruf_dauer', 'PM_EFZ=6', 'local_berufsbildung');
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'PM_EFZ',
            'jahrgang' => '2026',
        ]);

        $erwartet = (new semester_calculator(8, 6))->semester_grenzen(2026, 6)[1];

        $this->assertSame($erwartet, api::get_ausbildungsende((int) $lernende->id));
    }

    /**
     * Randfall: die Grenze ist exklusiv - der letzte Tag der Ausbildung
     * selbst gilt noch nicht als "beendet", der Tag danach schon.
     */
    public function test_ist_ausbildung_beendet_randfall_exakt_am_ende(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2026',
        ]);
        $ende = api::get_ausbildungsende((int) $lernende->id);

        $this->assertFalse(api::ist_ausbildung_beendet((int) $lernende->id, $ende));
        $this->assertTrue(api::ist_ausbildung_beendet((int) $lernende->id, $ende + 1));
    }

    /**
     * Ein nicht aufloesbarer Ausbildungsstand (fehlende Profildaten) gilt
     * nie als "beendet" - eine automatische Loeschung darf nie auf einem
     * unklaren Stand basieren, egal wie weit in der Zukunft der Stichtag
     * liegt.
     */
    public function test_ist_ausbildung_beendet_ohne_resolvable_stand_ist_false(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->create_user();

        $this->assertFalse(api::ist_ausbildung_beendet((int) $lernende->id, strtotime('+50 years')));
    }

    public function test_hat_aufbewahrungspflicht_randfall_gueltig_von_und_bis(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->create_user();

        $von = strtotime('-30 days');
        $bis = strtotime('+30 days');
        (new aufbewahrung(0, (object) [
            'lernendeid' => (int) $lernende->id,
            'grund' => 'Laufendes Verfahren',
            'gueltig_von' => $von,
            'gueltig_bis' => $bis,
        ]))->create();

        $this->assertTrue(api::hat_aufbewahrungspflicht((int) $lernende->id, $von));
        $this->assertTrue(api::hat_aufbewahrungspflicht((int) $lernende->id, $bis));
        $this->assertFalse(api::hat_aufbewahrungspflicht((int) $lernende->id, $von - 1));
        $this->assertFalse(api::hat_aufbewahrungspflicht((int) $lernende->id, $bis + DAYSECS));
    }

    public function test_darf_personendaten_geloescht_werden_kombinationen(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2020',
        ]);
        $ende = api::get_ausbildungsende((int) $lernende->id);
        $nachabschluss = $ende + DAYSECS;
        $waehrendausbildung = $ende - YEARSECS;

        // Beendet, keine Aufbewahrungspflicht -> darf geloescht werden, auch
        // ohne dass die Retention-Frist bereits abgelaufen waere.
        $this->assertTrue(api::darf_personendaten_geloescht_werden((int) $lernende->id, $nachabschluss));

        // Noch nicht beendet -> nie, unabhaengig von einer Aufbewahrungspflicht.
        $this->assertFalse(api::darf_personendaten_geloescht_werden((int) $lernende->id, $waehrendausbildung));

        // Beendet, aber mit dokumentierter Aufbewahrungspflicht -> nicht loeschen.
        (new aufbewahrung(0, (object) [
            'lernendeid' => (int) $lernende->id,
            'grund' => 'Laufendes Verfahren',
            'gueltig_von' => $ende,
            'gueltig_bis' => null,
        ]))->create();
        $this->assertFalse(api::darf_personendaten_geloescht_werden((int) $lernende->id, $nachabschluss));
    }

    /**
     * Randfall: die Frist ist inklusiv - genau am Ablauftag bereits
     * abgelaufen, einen Tag davor noch nicht.
     */
    public function test_aufbewahrungsfrist_abgelaufen_randfall_frist_grenze(): void {
        $this->resetAfterTest();
        set_config('retention_monate', '12', 'local_berufsbildung');
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2020',
        ]);
        $ende = api::get_ausbildungsende((int) $lernende->id);
        $ablauf = strtotime('+12 months', $ende);

        $this->assertFalse(api::aufbewahrungsfrist_abgelaufen((int) $lernende->id, $ablauf - 1));
        $this->assertTrue(api::aufbewahrungsfrist_abgelaufen((int) $lernende->id, $ablauf));

        // Mit dokumentierter Aufbewahrungspflicht bleibt sie auch nach
        // Fristablauf false.
        (new aufbewahrung(0, (object) [
            'lernendeid' => (int) $lernende->id,
            'grund' => 'Laufendes Verfahren',
            'gueltig_von' => $ende,
            'gueltig_bis' => null,
        ]))->create();
        $this->assertFalse(api::aufbewahrungsfrist_abgelaufen((int) $lernende->id, $ablauf));
    }
}
