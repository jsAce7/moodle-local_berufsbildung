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
use local_berufsbildung\persistent\block_lk;
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

        // Dreistufiger Rahmen wie in der Praxis: Handlungskompetenzbereich
        // (hkb) -> Handlungskompetenz (hk_abgedeckt/hk_luecke) ->
        // Leistungskriterium (lk). Die Luecken-Analyse wertet die
        // HK-Ebene aus, der Block wird auf LK-Ebene gepflegt.
        $competencygenerator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competencygenerator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $competencygenerator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $hkabgedeckt = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'), 'parentid' => $hkb->get('id'),
        ]);
        $hkluecke = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'), 'parentid' => $hkb->get('id'),
        ]);
        $lk = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'), 'parentid' => $hkabgedeckt->get('id'),
        ]);

        $block = new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]);
        $block->create();
        (new block_lk(0, (object) [
            'blockid' => $block->get('id'), 'competencyid' => $lk->get('id'), 'intensitaet' => 'schwerpunkt',
        ]))->create();

        (new einsatz(0, (object) [
            'userid' => $lernende->id, 'blockid' => $block->get('id'),
            'von' => strtotime('-1 month'), 'bis' => strtotime('+1 month'),
            'kw_von' => '2027-W01', 'kw_bis' => '2027-W04', 'importid' => 0,
        ]))->create();

        $luecken = (new luecken_analyse())->get_luecken((int) $lernende->id);

        $this->assertSame([(int) $hkluecke->get('id')], $luecken);
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
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $pflicht = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
            'idnumber' => '7777 a.01',
        ]);
        $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
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

    /**
     * get_abdeckung() liefert dieselbe Auswertung wie get_luecken(), aber
     * je Handlungskompetenzbereich und mit Bezugsgroesse - "eine von zwei
     * offen" statt nur eines Namens ohne Nenner.
     */
    public function test_get_abdeckung_gruppiert_nach_bereich_mit_bezugsgroesse(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $this->laufender_jahrgang(),
        ]);

        // Zwei Bereiche: im ersten ist eine von zwei HK abgedeckt, der
        // zweite ist unberuehrt.
        $competencygenerator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competencygenerator->create_framework(['idnumber' => 'au-2022']);
        $hkb1 = $competencygenerator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $hkb2 = $competencygenerator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $hkabgedeckt = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'), 'parentid' => $hkb1->get('id'),
        ]);
        $hkluecke = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'), 'parentid' => $hkb1->get('id'),
        ]);
        $hkzweiterbereich = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'), 'parentid' => $hkb2->get('id'),
        ]);
        $lk = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'), 'parentid' => $hkabgedeckt->get('id'),
        ]);

        $block = new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]);
        $block->create();
        (new block_lk(0, (object) [
            'blockid' => $block->get('id'), 'competencyid' => $lk->get('id'), 'intensitaet' => 'schwerpunkt',
        ]))->create();
        (new einsatz(0, (object) [
            'userid' => $lernende->id, 'blockid' => $block->get('id'),
            'von' => strtotime('-1 month'), 'bis' => strtotime('+1 month'),
            'kw_von' => '2027-W01', 'kw_bis' => '2027-W04', 'importid' => 0,
        ]))->create();

        $abdeckungen = [];
        foreach ((new luecken_analyse())->get_abdeckung((int) $lernende->id) as $abdeckung) {
            $abdeckungen[$abdeckung->bereichid] = $abdeckung;
        }

        $this->assertCount(2, $abdeckungen);

        $ersterbereich = $abdeckungen[(int) $hkb1->get('id')];
        $this->assertSame(2, $ersterbereich->soll);
        $this->assertSame(1, $ersterbereich->anzahl_abgedeckt());
        $this->assertSame([(int) $hkluecke->get('id')], $ersterbereich->luecken);
        $this->assertFalse($ersterbereich->ist_vollstaendig());

        $zweiterbereich = $abdeckungen[(int) $hkb2->get('id')];
        $this->assertSame(1, $zweiterbereich->soll);
        $this->assertSame(0, $zweiterbereich->anzahl_abgedeckt());
        $this->assertSame([(int) $hkzweiterbereich->get('id')], $zweiterbereich->luecken);
    }

    /**
     * Wahlpflicht-HK zaehlen weder als Luecke noch in die Bezugsgroesse -
     * sonst waere ein vollstaendig ausgebildeter Bereich nie "vollstaendig".
     * Ein Bereich, dessen HK ausschliesslich Wahlpflicht sind, entfaellt
     * ganz, statt als "0 von 0" zu erscheinen.
     */
    public function test_get_abdeckung_ohne_wahlpflicht_hk(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');
        set_config('beruf_wahlpflicht_hk', 'AU_EFZ=hk-wahl,hk-nur-wahl', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $this->laufender_jahrgang(),
        ]);

        $competencygenerator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competencygenerator->create_framework(['idnumber' => 'au-2022']);
        $hkb1 = $competencygenerator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $hkb2 = $competencygenerator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $pflicht = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $hkb1->get('id'),
            'idnumber' => 'hk-pflicht',
        ]);
        $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $hkb1->get('id'),
            'idnumber' => 'hk-wahl',
        ]);
        $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $hkb2->get('id'),
            'idnumber' => 'hk-nur-wahl',
        ]);

        $abdeckungen = (new luecken_analyse())->get_abdeckung((int) $lernende->id);

        // Nur der Bereich mit einer Pflicht-HK bleibt uebrig.
        $this->assertCount(1, $abdeckungen);
        $this->assertSame((int) $hkb1->get('id'), $abdeckungen[0]->bereichid);
        $this->assertSame(1, $abdeckungen[0]->soll);
        $this->assertSame([(int) $pflicht->get('id')], $abdeckungen[0]->luecken);
    }

    /**
     * get_luecken() ist seit der Gruppierung nur noch die flache Sicht auf
     * dieselbe Auswertung - beide duerfen nie auseinanderlaufen.
     */
    public function test_get_luecken_bleibt_konsistent_mit_get_abdeckung(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $this->laufender_jahrgang(),
        ]);

        $competencygenerator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $competencygenerator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $competencygenerator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $erste = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'), 'parentid' => $hkb->get('id'),
        ]);
        $zweite = $competencygenerator->create_competency([
            'competencyframeworkid' => $framework->get('id'), 'parentid' => $hkb->get('id'),
        ]);

        $analyse = new luecken_analyse();

        $ausabdeckung = [];
        foreach ($analyse->get_abdeckung((int) $lernende->id) as $abdeckung) {
            foreach ($abdeckung->luecken as $competencyid) {
                $ausabdeckung[] = $competencyid;
            }
        }

        $this->assertSame($ausabdeckung, $analyse->get_luecken((int) $lernende->id));
        $this->assertEqualsCanonicalizing(
            [(int) $erste->get('id'), (int) $zweite->get('id')],
            $ausabdeckung
        );
    }
}
