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
use local_berufsbildung\service\kompetenz_baum;
use local_berufsbildung\service\semester_calculator;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\api::class)]
/**
 * Tests fuer api.
 *
 * @covers \local_berufsbildung\api
 */
final class api_test extends advanced_testcase {
    /**
     * Legt eine Zuordnung an, ohne Umwege ueber die noch nicht existierende
     * set_zuordnung()-Methode.
     *
     * @param int $berufsbildnerid
     * @param int $lernendeid
     * @param int $von
     * @param int|null $bis
     * @param string $rolle
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

        $waehrendzuordnung = strtotime('-18 months');
        $this->assertTrue(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id, $waehrendzuordnung));
        $this->assertFalse(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id));
    }

    public function test_is_zustaendig_heute_oder_am_gilt_fuer_neue_und_fuer_vorherige_person(): void {
        $this->resetAfterTest();

        $vorherige = $this->getDataGenerator()->create_user();
        $neue = $this->getDataGenerator()->create_user();
        $fremde = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $wechsel = strtotime('-1 month');
        $this->lege_zuordnung_an((int) $vorherige->id, (int) $lernende->id, strtotime('-2 years'), $wechsel - 1);
        $this->lege_zuordnung_an((int) $neue->id, (int) $lernende->id, $wechsel);

        $vordemwechsel = strtotime('-3 months');
        $this->assertTrue(api::is_zustaendig_heute_oder_am((int) $vorherige->id, (int) $lernende->id, $vordemwechsel));
        $this->assertTrue(api::is_zustaendig_heute_oder_am((int) $neue->id, (int) $lernende->id, $vordemwechsel));
        $this->assertFalse(api::is_zustaendig_heute_oder_am((int) $vorherige->id, (int) $lernende->id, null));
        $this->assertFalse(api::is_zustaendig_heute_oder_am((int) $fremde->id, (int) $lernende->id, $vordemwechsel));

        // Randfall: am letzten Tag der alten Zuordnung gilt sie noch, am
        // ersten Tag der neuen nicht mehr.
        $this->assertTrue(api::is_zustaendig_heute_oder_am((int) $vorherige->id, (int) $lernende->id, $wechsel - 1));
        $this->assertFalse(api::is_zustaendig_heute_oder_am((int) $vorherige->id, (int) $lernende->id, $wechsel));
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

        $waehrendzuordnung = strtotime('-18 months');
        $this->assertSame(
            [(int) $lernende->id],
            api::get_lernende_for((int) $berufsbildner->id, $waehrendzuordnung)
        );
        $this->assertSame([], api::get_lernende_for((int) $berufsbildner->id));
    }

    public function test_get_aktive_lernende(): void {
        $this->resetAfterTest();

        $berufsbildnereins = $this->getDataGenerator()->create_user();
        $berufsbildnerzwei = $this->getDataGenerator()->create_user();
        $aktuell = $this->getDataGenerator()->create_user();
        $ebenfallsaktuell = $this->getDataGenerator()->create_user();
        $beendet = $this->getDataGenerator()->create_user();

        // Zwei gleichzeitige Zuordnungen derselben Person duerfen nicht zu
        // einem doppelten Eintrag fuehren.
        $this->lege_zuordnung_an((int) $berufsbildnereins->id, (int) $aktuell->id, strtotime('-1 year'));
        $this->lege_zuordnung_an(
            (int) $berufsbildnerzwei->id,
            (int) $aktuell->id,
            strtotime('-1 year'),
            null,
            'stellvertretung'
        );
        $this->lege_zuordnung_an((int) $berufsbildnerzwei->id, (int) $ebenfallsaktuell->id, strtotime('-1 year'));
        $this->lege_zuordnung_an(
            (int) $berufsbildnereins->id,
            (int) $beendet->id,
            strtotime('-2 years'),
            strtotime('-1 month')
        );

        $aktive = api::get_aktive_lernende();
        sort($aktive);
        $erwartet = [(int) $aktuell->id, (int) $ebenfallsaktuell->id];
        sort($erwartet);
        $this->assertSame($erwartet, $aktive);
    }

    public function test_get_aktive_lernende_stichtag(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $von = strtotime('-2 years');
        $bis = strtotime('-1 year');
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, $von, $bis);

        $waehrendzuordnung = strtotime('-18 months');
        $this->assertSame([(int) $lernende->id], api::get_aktive_lernende($waehrendzuordnung));
        $this->assertSame([], api::get_aktive_lernende());
    }

    public function test_get_aktive_lernende_ohneuekextern_schliesst_uek_externe_aus(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $regulaer = $this->getDataGenerator()->create_user();
        $uekextern = $this->getDataGenerator()->create_user();

        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $regulaer->id, strtotime('-1 year'));
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $uekextern->id, strtotime('-1 year'));
        api::set_teilnahmeart((int) $uekextern->id, api::TEILNAHMEART_UEK_EXTERN);

        $ohneparameter = api::get_aktive_lernende();
        sort($ohneparameter);
        $erwartetohneparameter = [(int) $regulaer->id, (int) $uekextern->id];
        sort($erwartetohneparameter);
        $this->assertSame($erwartetohneparameter, $ohneparameter);

        $this->assertSame([(int) $regulaer->id], api::get_aktive_lernende(null, true));
    }

    /**
     * Sind Kohorten zugewiesen, fuehren nur deren Mitglieder eine
     * Lerndokumentation, eine Mitgliedschaft genuegt; ohne Zuweisung alle
     * ausser externen uK-Teilnehmenden. Massgebend ist die Mitgliedschaft
     * heute.
     */
    public function test_ist_lerndokumentation_erforderlich_mit_kohorten(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();

        $mitglieda = $this->getDataGenerator()->create_user();
        $mitgliedb = $this->getDataGenerator()->create_user();
        $andere = $this->getDataGenerator()->create_user();
        $uekextern = $this->getDataGenerator()->create_user();
        api::set_teilnahmeart((int) $uekextern->id, api::TEILNAHMEART_UEK_EXTERN);
        $kohortea = $this->getDataGenerator()->create_cohort();
        $kohorteb = $this->getDataGenerator()->create_cohort();
        $nichtzugewiesen = $this->getDataGenerator()->create_cohort();
        cohort_add_member((int) $kohortea->id, (int) $mitglieda->id);
        cohort_add_member((int) $kohorteb->id, (int) $mitgliedb->id);
        cohort_add_member((int) $kohortea->id, (int) $uekextern->id);
        cohort_add_member((int) $nichtzugewiesen->id, (int) $andere->id);

        $this->assertTrue(api::ist_lerndokumentation_erforderlich((int) $andere->id));
        $this->assertFalse(api::ist_lerndokumentation_erforderlich((int) $uekextern->id));

        set_config('kohorten_lerndokumentation', $kohortea->id . ',' . $kohorteb->id, 'local_berufsbildung');
        $this->assertSame([(int) $kohortea->id, (int) $kohorteb->id], api::get_kohorten_lerndokumentation());
        $this->assertTrue(api::ist_lerndokumentation_erforderlich((int) $mitglieda->id));
        $this->assertTrue(api::ist_lerndokumentation_erforderlich((int) $mitgliedb->id));
        $this->assertFalse(api::ist_lerndokumentation_erforderlich((int) $andere->id));
        $this->assertFalse(api::ist_lerndokumentation_erforderlich((int) $uekextern->id));

        cohort_add_member((int) $kohorteb->id, (int) $andere->id);
        $this->assertTrue(api::ist_lerndokumentation_erforderlich((int) $andere->id));
        cohort_remove_member((int) $kohortea->id, (int) $mitglieda->id);
        $this->assertFalse(api::ist_lerndokumentation_erforderlich((int) $mitglieda->id));

        set_config('kohorten_lerndokumentation', '', 'local_berufsbildung');
        $this->assertSame([], api::get_kohorten_lerndokumentation());
        $this->assertTrue(api::ist_lerndokumentation_erforderlich((int) $mitglieda->id));
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

        $waehrendzuordnung = strtotime('-18 months');
        $this->assertSame(
            [(int) $berufsbildner->id],
            api::get_berufsbildner_for((int) $lernende->id, $waehrendzuordnung)
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

        $waehrendsemester1 = mktime(0, 0, 0, 9, 15, 2026);
        $waehrendsemester2 = mktime(0, 0, 0, 2, 15, 2027);

        $stand1 = api::get_ausbildungsstand((int) $lernende->id, $waehrendsemester1);
        $stand2 = api::get_ausbildungsstand((int) $lernende->id, $waehrendsemester2);

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

        $waehrendsemester6 = mktime(0, 0, 0, 3, 1, 2029);
        $nachlehrabschluss = mktime(0, 0, 0, 9, 1, 2029);

        $stand = api::get_ausbildungsstand((int) $lernende->id, $waehrendsemester6);
        $this->assertSame(6, $stand->semester);
        $this->assertSame(6, $stand->gesamtsemester);
        $this->assertNull(api::get_ausbildungsstand((int) $lernende->id, $nachlehrabschluss));
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

        $waehrendsemester7 = mktime(0, 0, 0, 9, 1, 2029);

        $this->assertSame(7, api::get_ausbildungsstand((int) $lernende->id, $waehrendsemester7)->semester);
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

    /**
     * Die Bloecke je Kompetenz fuer die Planungshilfe: nur die des Berufs,
     * und ohne Rahmen nichts. Einen Stichtag gibt es nicht - die
     * Kompetenzzuordnung der Bloecke hat keine Gueltigkeitsdauer.
     */
    public function test_get_bloecke_je_kompetenz(): void {
        $this->resetAfterTest();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');
        $kompetenzen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $kompetenzen->create_framework(['idnumber' => 'au-2022']);
        $bereich = $kompetenzen->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $hk = $kompetenzen->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $bereich->get('id'),
        ]);
        $lk = $kompetenzen->create_competency(['competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hk->get('id')]);

        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        foreach (['B1' => 'AU_EFZ', 'P1' => 'PM_EFZ'] as $nummer => $beruf) {
            $block = $generator->create_block(['nummer' => $nummer, 'beruf' => $beruf]);
            $generator->create_block_competency(['blockid' => $block->get('id'), 'competencyid' => $lk->get('id')]);
        }

        $bloecke = api::get_bloecke_je_kompetenz('AU_EFZ');
        $this->assertSame([(int) $lk->get('id')], array_keys($bloecke));
        $this->assertSame('B1', $bloecke[(int) $lk->get('id')][0]->get('nummer'));
        $this->assertCount(1, $bloecke[(int) $lk->get('id')]);
        $this->assertSame([], api::get_bloecke_je_kompetenz('PM_EFZ'));
    }

    /**
     * Die verlangten Wahlpflicht-HK je Beruf, nach Gruppen von Bereichen.
     * Einen Stichtag-Randfall gibt es nicht - die Angabe haengt am Beruf,
     * nicht am Datum.
     */
    public function test_get_wahlpflicht_gruppen_for_beruf_konfigurierter_beruf(): void {
        $this->resetAfterTest();
        set_config('beruf_wahlpflicht_anzahl', "AU_EFZ=a,b,c:1; d:1\nPM_EFZ=2", 'local_berufsbildung');

        $gruppen = api::get_wahlpflicht_gruppen_for_beruf('AU_EFZ');

        $this->assertCount(2, $gruppen);
        $this->assertSame(['a', 'b', 'c'], $gruppen[0]->bereiche);
        $this->assertSame(1, $gruppen[0]->anzahl);
        $this->assertSame(['d'], $gruppen[1]->bereiche);
    }

    /**
     * Randfall: ohne Eintrag fuer den Beruf, oder ganz ohne Einstellung,
     * gibt es keine Gruppen.
     */
    public function test_get_wahlpflicht_gruppen_for_beruf_ohne_eintrag_ist_leer(): void {
        $this->resetAfterTest();

        $this->assertSame([], api::get_wahlpflicht_gruppen_for_beruf('AU_EFZ'));

        set_config('beruf_wahlpflicht_anzahl', 'AU_EFZ=3', 'local_berufsbildung');
        $this->assertSame([], api::get_wahlpflicht_gruppen_for_beruf('KR_EFZ'));
    }

    /**
     * Dieselbe Nummer wie im Kompetenzraster. Einen Stichtag-Randfall gibt
     * es hier nicht - die Methode liest nur die uebergebene ID-Nummer.
     */
    public function test_get_kompetenz_kuerzel_schneidet_den_rahmenpraefix_ab(): void {
        $this->assertSame('b.07', api::get_kompetenz_kuerzel('7777BE b.07'));
        $this->assertSame(
            kompetenz_baum::kuerzel_aus_idnumber('7777BE a.01'),
            api::get_kompetenz_kuerzel('7777BE a.01')
        );
    }

    /**
     * Randfall: ohne Leerzeichen bleibt die ID-Nummer unveraendert, ohne
     * ID-Nummer gibt es kein Kuerzel statt eines Fehlers.
     */
    public function test_get_kompetenz_kuerzel_ohne_praefix_oder_leer(): void {
        $this->assertSame('b07', api::get_kompetenz_kuerzel('b07'));
        $this->assertSame('', api::get_kompetenz_kuerzel(''));
        $this->assertSame('', api::get_kompetenz_kuerzel('   '));
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

    public function test_get_block_kurs_liefert_verknuepften_kurs(): void {
        $this->resetAfterTest();

        $kurs = $this->getDataGenerator()->create_course(['fullname' => 'Montage Grundlagen']);
        $block = new block(0, (object) [
            'nummer' => '4',
            'name' => 'Abteilung Montage',
            'ist_betrieb' => true,
            'aktiv' => true,
            'courseid' => (int) $kurs->id,
        ]);
        $block->create();

        $treffer = api::get_block_kurs((int) $block->get('id'));

        $this->assertNotNull($treffer);
        $this->assertSame((int) $kurs->id, (int) $treffer->id);
        $this->assertSame('Montage Grundlagen', $treffer->fullname);
    }

    public function test_get_block_kurs_ohne_verknuepfung_ist_null(): void {
        $this->resetAfterTest();

        $block = new block(0, (object) [
            'nummer' => '4',
            'name' => 'Abteilung Montage',
            'ist_betrieb' => true,
            'aktiv' => true,
        ]);
        $block->create();

        $this->assertNull(api::get_block_kurs((int) $block->get('id')));
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

    public function test_get_aufbewahrungsende(): void {
        $this->resetAfterTest();
        set_config('retention_monate', '6', 'local_berufsbildung');
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2020',
        ]);
        $ohneprofil = $this->getDataGenerator()->create_user();

        $this->assertSame(
            strtotime('+6 months', api::get_ausbildungsende((int) $lernende->id)),
            api::get_aufbewahrungsende((int) $lernende->id)
        );
        $this->assertNull(api::get_aufbewahrungsende((int) $ohneprofil->id));
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

    /**
     * Jahrgang so waehlen, dass "jetzt" immer mitten in der Lehre liegt,
     * unabhaengig davon, in welchem Kalendermonat der Test laeuft.
     */
    private function laufender_jahrgang(): int {
        $jetzt = time();

        return (int) date('n', $jetzt) >= 8 ? (int) date('Y', $jetzt) - 1 : (int) date('Y', $jetzt) - 2;
    }

    public function test_get_ausbildungsbeginn_berechnet_ersten_semesterbeginn(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2026',
        ]);

        $erwartet = (new semester_calculator(8, 8))->semester_grenzen(2026, 1)[0];

        $this->assertSame($erwartet, api::get_ausbildungsbeginn((int) $lernende->id));
    }

    public function test_get_ausbildungsbeginn_ohne_profildaten_ist_null(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->create_user();

        $this->assertNull(api::get_ausbildungsbeginn((int) $lernende->id));
    }

    /**
     * Ohne konfiguriertes Profilfeld oder bei leerem Wert ist der
     * Lehrbeginn der berechnete Ausbildungsbeginn.
     */
    public function test_get_lehrbeginn_ohne_abweichung_ist_ausbildungsbeginn(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $lernende = $generator->create_lernende(['beruf' => 'AU_EFZ', 'jahrgang' => '2026']);
        $lernendeid = (int) $lernende->id;

        $this->assertSame(api::get_ausbildungsbeginn($lernendeid), api::get_lehrbeginn($lernendeid));

        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'lehrbeginn',
            'name' => 'Lehrbeginn',
        ]);
        set_config('profilefield_lehrbeginn', 'lehrbeginn', 'local_berufsbildung');

        $this->assertSame(api::get_ausbildungsbeginn($lernendeid), api::get_lehrbeginn($lernendeid));
    }

    /**
     * Ein Datumsfeld verschiebt nur den Lehrbeginn, nicht die Semester.
     */
    public function test_get_lehrbeginn_aus_datumsfeld(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'datetime',
            'shortname' => 'lehrbeginn',
            'name' => 'Lehrbeginn',
            'param1' => 2000,
            'param2' => 2050,
        ]);
        set_config('profilefield_lehrbeginn', 'lehrbeginn', 'local_berufsbildung');

        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $lernende = $generator->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2026',
            'profile_field_lehrbeginn' => mktime(0, 0, 0, 8, 15, 2026),
        ]);
        $lernendeid = (int) $lernende->id;

        $this->assertSame(mktime(0, 0, 0, 8, 15, 2026), api::get_lehrbeginn($lernendeid));
        $this->assertSame(mktime(0, 0, 0, 8, 1, 2026), api::get_ausbildungsbeginn($lernendeid));
    }

    /**
     * Ein Textfeld wird in beiden Schreibweisen gelesen, Unlesbares faellt
     * auf den berechneten Ausbildungsbeginn zurueck.
     */
    public function test_get_lehrbeginn_aus_textfeld(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text',
            'shortname' => 'lehrbeginn',
            'name' => 'Lehrbeginn',
        ]);
        set_config('profilefield_lehrbeginn', 'lehrbeginn', 'local_berufsbildung');
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');

        $faelle = [
            '01.02.2027' => mktime(0, 0, 0, 2, 1, 2027),
            '2026-10-15' => mktime(0, 0, 0, 10, 15, 2026),
            '31.02.2027' => mktime(0, 0, 0, 8, 1, 2026),
            'Oktober' => mktime(0, 0, 0, 8, 1, 2026),
        ];
        foreach ($faelle as $wert => $erwartet) {
            $lernende = $generator->create_lernende([
                'beruf' => 'AU_EFZ',
                'jahrgang' => '2026',
                'profile_field_lehrbeginn' => $wert,
            ]);
            $this->assertSame($erwartet, api::get_lehrbeginn((int) $lernende->id), $wert);
        }
    }

    public function test_get_ausbildungsphase_laufende_lehre(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => (string) $this->laufender_jahrgang(),
        ]);

        $this->assertSame(api::PHASE_LAUFEND, api::get_ausbildungsphase((int) $lernende->id));
    }

    /**
     * Ohne Beruf/Jahrgang im Profil ist die Phase unbekannt - nie
     * stillschweigend "beendet". Die Unterscheidung ist der Grund, warum
     * es diese Methode neben get_ausbildungsstand() ueberhaupt gibt.
     */
    public function test_get_ausbildungsphase_ohne_profildaten_ist_unbekannt(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->create_user();

        $this->assertSame(api::PHASE_UNBEKANNT, api::get_ausbildungsphase((int) $lernende->id));
    }

    /**
     * Der Stichtag-Randfall: die Lehre gilt am ersten Tag und noch in der
     * letzten Sekunde als laufend, eine Sekunde vor dem Beginn bzw. nach
     * dem Ende dagegen nicht mehr.
     */
    public function test_get_ausbildungsphase_stichtag_grenzen(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2026',
        ]);
        $lernendeid = (int) $lernende->id;

        $calculator = new semester_calculator(8, 8);
        [$beginn, ] = $calculator->semester_grenzen(2026, 1);
        [, $ende] = $calculator->semester_grenzen(2026, 8);

        $this->assertSame(api::PHASE_VOR_BEGINN, api::get_ausbildungsphase($lernendeid, $beginn - 1));
        $this->assertSame(api::PHASE_LAUFEND, api::get_ausbildungsphase($lernendeid, $beginn));
        $this->assertSame(api::PHASE_LAUFEND, api::get_ausbildungsphase($lernendeid, $ende));
        $this->assertSame(api::PHASE_BEENDET, api::get_ausbildungsphase($lernendeid, $ende + 1));
    }

    /**
     * Die Phase richtet sich nach der Lehrdauer des Berufs, nicht nach der
     * globalen: nach sechs Semestern ist eine dreijaehrige Lehre beendet,
     * waehrend die globale Einstellung noch acht Semester vorsieht.
     */
    public function test_get_ausbildungsphase_beruf_mit_abweichender_lehrdauer(): void {
        $this->resetAfterTest();
        set_config('beruf_dauer', 'PM_EFZ=6', 'local_berufsbildung');
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'PM_EFZ',
            'jahrgang' => '2026',
        ]);

        [, $ende] = (new semester_calculator(8, 6))->semester_grenzen(2026, 6);

        $this->assertSame(api::PHASE_LAUFEND, api::get_ausbildungsphase((int) $lernende->id, $ende));
        $this->assertSame(api::PHASE_BEENDET, api::get_ausbildungsphase((int) $lernende->id, $ende + 1));
    }

    /**
     * Die Grenzen decken die Lehrzeit lueckenlos ab und keine ueberschreitet
     * die Grenze zum naechsten Semester (1. Februar / 1. August).
     */
    public function test_get_semester_grenzen_deckt_lehrzeit_lueckenlos_ab(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2026',
        ]);
        $lernendeid = (int) $lernende->id;

        $grenzen = api::get_semester_grenzen($lernendeid);

        $this->assertCount(8, $grenzen);
        $this->assertSame(api::get_ausbildungsbeginn($lernendeid), $grenzen[1][0]);
        $this->assertSame(api::get_ausbildungsende($lernendeid), $grenzen[8][1]);

        foreach ($grenzen as $semester => [$von, $bis]) {
            $this->assertLessThan($bis, $von);
            // Beide Grenzen gehoeren noch zu diesem Semester, nicht zum naechsten.
            $this->assertSame($semester, api::get_ausbildungsstand($lernendeid, $von)->semester);
            $this->assertSame($semester, api::get_ausbildungsstand($lernendeid, $bis)->semester);

            if ($semester > 1) {
                $this->assertSame($grenzen[$semester - 1][1] + 1, $von);
            }
        }
    }

    public function test_get_semester_grenzen_ohne_profildaten_ist_leer(): void {
        $this->resetAfterTest();
        $lernende = $this->getDataGenerator()->create_user();

        $this->assertSame([], api::get_semester_grenzen((int) $lernende->id));
    }

    public function test_get_semester_grenzen_beruf_mit_abweichender_lehrdauer(): void {
        $this->resetAfterTest();
        set_config('beruf_dauer', 'PM_EFZ=6', 'local_berufsbildung');
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'PM_EFZ',
            'jahrgang' => '2026',
        ]);

        $this->assertCount(6, api::get_semester_grenzen((int) $lernende->id));
    }
}
