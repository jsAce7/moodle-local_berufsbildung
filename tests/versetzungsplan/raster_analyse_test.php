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
 * Tests fuer die Rasterauswertung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\versetzungsplan;

use advanced_testcase;
use core_competency\competency;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\block_lk;
use local_berufsbildung\persistent\einsatz;
use local_berufsbildung\raster_bereich;
use local_berufsbildung\raster_kompetenz;
use stdClass;

/**
 * Tests fuer raster_analyse.
 *
 * @covers \local_berufsbildung\versetzungsplan\raster_analyse
 */
final class raster_analyse_test extends advanced_testcase {
    /**
     * Legt die Profilfelder Beruf und Jahrgang an.
     */
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

    /**
     * Legt eine lernende Person mit laufender Lehre und konfiguriertem Rahmen an.
     *
     * @return stdClass
     */
    private function lege_lernende_an(): stdClass {
        $this->lege_profilfelder_an();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');

        return $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $this->laufender_jahrgang(),
        ]);
    }

    /**
     * Ein Einsatz, der die uebergebene Handlungskompetenz ueber ein
     * Leistungskriterium abdeckt - so, wie es in der Praxis gepflegt wird.
     *
     * @param int $lernendeid
     * @param competency $hk
     * @param int $von
     * @param int $bis
     * @return void
     */
    private function lege_einsatz_an(int $lernendeid, competency $hk, int $von, int $bis): void {
        static $nummer = 0;
        $nummer++;

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $lk = $generator->create_competency([
            'competencyframeworkid' => $hk->get('competencyframeworkid'),
            'parentid' => $hk->get('id'),
        ]);

        $block = new block(0, (object) [
            'nummer' => (string) $nummer, 'name' => (string) $nummer, 'ist_betrieb' => true, 'aktiv' => true,
        ]);
        $block->create();
        (new block_lk(0, (object) [
            'blockid' => $block->get('id'), 'competencyid' => $lk->get('id'),
        ]))->create();
        (new einsatz(0, (object) [
            'userid' => $lernendeid, 'blockid' => $block->get('id'),
            'von' => $von, 'bis' => $bis,
            'kw_von' => '2027-W01', 'kw_bis' => '2027-W04', 'importid' => 0,
        ]))->create();
    }

    /**
     * Alle Zellen eines Rasters nach competencyid.
     *
     * @param raster_bereich[] $raster
     * @return array<int, raster_kompetenz>
     */
    private function zellen(array $raster): array {
        $zellen = [];
        foreach ($raster as $bereich) {
            foreach ($bereich->kompetenzen as $kompetenz) {
                $zellen[$kompetenz->competencyid] = $kompetenz;
            }
        }

        return $zellen;
    }

    /**
     * Je Leistungskriterium ein eigener Stand: eine HK gilt schon als
     * vorgekommen, sobald eines ihrer LK vorkommt - welche fehlen, steht in
     * der LK-Liste. Eine als Ganzes zugeordnete HK deckt alle ihre LK ab.
     * Randfall am Stichtag wie bei den HK: ein Einsatz, der genau dann
     * beginnt, zaehlt bereits.
     */
    public function test_stand_je_leistungskriterium(): void {
        $this->resetAfterTest();
        $lernende = $this->lege_lernende_an();
        $stichtag = time();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $teilweise = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
        ]);
        $ganz = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
        ]);
        $lk = [];
        foreach (['vorgekommen', 'eingeplant', 'fehlt'] as $name) {
            $lk[$name] = $generator->create_competency([
                'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $teilweise->get('id'),
            ]);
        }
        $ganzlk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $ganz->get('id'),
        ]);

        $einsatz = function (array $kompetenzen, int $von) use ($lernende): void {
            static $nummer = 100;
            $nummer++;
            $block = new block(0, (object) [
                'nummer' => (string) $nummer, 'name' => (string) $nummer, 'ist_betrieb' => true, 'aktiv' => true,
            ]);
            $block->create();
            foreach ($kompetenzen as $kompetenz) {
                (new block_lk(0, (object) [
                    'blockid' => $block->get('id'), 'competencyid' => $kompetenz->get('id'),
                ]))->create();
            }
            (new einsatz(0, (object) [
                'userid' => $lernende->id, 'blockid' => $block->get('id'),
                'von' => $von, 'bis' => $von + WEEKSECS,
                'kw_von' => '2027-W01', 'kw_bis' => '2027-W02', 'importid' => 0,
            ]))->create();
        };
        $einsatz([$lk['vorgekommen'], $ganz], $stichtag);
        $einsatz([$lk['eingeplant']], $stichtag + 1);

        $zellen = $this->zellen((new raster_analyse())->get_raster((int) $lernende->id, $stichtag));

        $this->assertSame(raster_kompetenz::STATUS_ABGEDECKT, $zellen[(int) $teilweise->get('id')]->status);
        $this->assertSame([
            (int) $lk['vorgekommen']->get('id') => raster_kompetenz::STATUS_ABGEDECKT,
            (int) $lk['eingeplant']->get('id') => raster_kompetenz::STATUS_EINGEPLANT,
            (int) $lk['fehlt']->get('id') => raster_kompetenz::STATUS_OFFEN,
        ], $zellen[(int) $teilweise->get('id')]->leistungskriterien);
        $this->assertSame(
            [(int) $ganzlk->get('id') => raster_kompetenz::STATUS_ABGEDECKT],
            $zellen[(int) $ganz->get('id')]->leistungskriterien
        );
    }

    /**
     * Die Zahl der Datenbankabfragen haengt nicht davon ab, wie viele LK
     * den Bloecken zugeordnet sind: "Meine Lernenden" rechnet das Raster
     * fuer jede Person. Frueher wurde jede Kompetenz einzeln bis zur HK
     * hinauf geladen - ueber 500 Abfragen fuer eine einzige Person.
     */
    public function test_abfragen_wachsen_nicht_mit_den_leistungskriterien(): void {
        global $DB;
        $this->resetAfterTest();
        $lernende = $this->lege_lernende_an();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);

        $messen = function (int $lkjeblock) use ($DB, $lernende, $generator, $rahmen, $hkb): int {
            static $nummer = 200;
            for ($einsatz = 0; $einsatz < 3; $einsatz++) {
                $nummer++;
                $hk = $generator->create_competency([
                    'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
                ]);
                $block = new block(0, (object) [
                    'nummer' => (string) $nummer, 'name' => (string) $nummer, 'ist_betrieb' => true, 'aktiv' => true,
                ]);
                $block->create();
                for ($i = 0; $i < $lkjeblock; $i++) {
                    $lk = $generator->create_competency([
                        'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hk->get('id'),
                    ]);
                    (new block_lk(0, (object) [
                        'blockid' => $block->get('id'), 'competencyid' => $lk->get('id'),
                    ]))->create();
                }
                $von = time() + ($einsatz - 1) * 30 * DAYSECS;
                (new einsatz(0, (object) [
                    'userid' => $lernende->id, 'blockid' => $block->get('id'),
                    'von' => $von, 'bis' => $von + WEEKSECS,
                    'kw_von' => '2027-W01', 'kw_bis' => '2027-W02', 'importid' => 0,
                ]))->create();
            }

            // Einmal vorab, damit Einstellungen und Profilfelder im Cache
            // liegen und nicht beim ersten Lauf mitzaehlen.
            (new raster_analyse())->get_raster((int) $lernende->id);
            $vorher = $DB->perf_get_reads();
            (new raster_analyse())->get_raster((int) $lernende->id);

            return $DB->perf_get_reads() - $vorher;
        };

        $wenige = $messen(1);
        $viele = $messen(20);

        $this->assertSame($wenige, $viele);
    }

    /**
     * Der Kern des Rasters: drei unterscheidbare Staende. Zugleich die
     * beiden Stichtag-Randfaelle - ein Einsatz, der genau am Stichtag
     * beginnt, zaehlt bereits; einer, der eine Sekunde spaeter beginnt,
     * ist erst eingeplant.
     */
    public function test_drei_staende_am_stichtag_unterschieden(): void {
        $this->resetAfterTest();
        $lernende = $this->lege_lernende_an();
        $stichtag = time();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $abgedeckt = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
        ]);
        $eingeplant = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
        ]);
        $offen = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
        ]);

        // Beginnt exakt am Stichtag - laeuft also zum Stichtag.
        $this->lege_einsatz_an((int) $lernende->id, $abgedeckt, $stichtag, $stichtag + WEEKSECS);
        // Beginnt eine Sekunde nach dem Stichtag.
        $this->lege_einsatz_an((int) $lernende->id, $eingeplant, $stichtag + 1, $stichtag + WEEKSECS);

        $zellen = $this->zellen((new raster_analyse())->get_raster((int) $lernende->id, $stichtag));

        $this->assertSame(
            raster_kompetenz::STATUS_ABGEDECKT,
            $zellen[(int) $abgedeckt->get('id')]->status
        );
        $this->assertSame(
            raster_kompetenz::STATUS_EINGEPLANT,
            $zellen[(int) $eingeplant->get('id')]->status
        );
        $this->assertSame(
            raster_kompetenz::STATUS_OFFEN,
            $zellen[(int) $offen->get('id')]->status
        );
    }

    /**
     * Anders als die Lueckenliste zeigt das Raster auch die
     * Wahlpflicht-HK - im Bildungsplan stehen sie ja ebenfalls. Sie sind
     * als solche markiert und zaehlen nicht in die Bezugsgroesse.
     */
    public function test_wahlpflicht_ist_sichtbar_aber_nicht_teil_der_bezugsgroesse(): void {
        $this->resetAfterTest();
        set_config('beruf_wahlpflicht_hk', 'AU_EFZ=hk-wahl', 'local_berufsbildung');
        $lernende = $this->lege_lernende_an();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $pflicht = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'), 'idnumber' => 'hk-pflicht',
        ]);
        $wahl = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'), 'idnumber' => 'hk-wahl',
        ]);

        $raster = (new raster_analyse())->get_raster((int) $lernende->id);

        $this->assertCount(1, $raster);
        $this->assertCount(2, $raster[0]->kompetenzen);
        $this->assertSame(1, $raster[0]->soll());

        $zellen = $this->zellen($raster);
        $this->assertFalse($zellen[(int) $pflicht->get('id')]->istwahlpflicht);
        $this->assertTrue($zellen[(int) $wahl->get('id')]->istwahlpflicht);
    }

    /**
     * Die Einstellung beschreibt den Bildungsplan, die ID-Nummer im Rahmen
     * traegt zusaetzlich einen Rahmen-Praefix. Beides muss zusammenfinden,
     * sonst laeuft die Kennzeichnung stillschweigend ins Leere und alles
     * erscheint als Pflicht.
     *
     * @dataProvider wahlpflicht_schreibweisen
     * @param string $konfiguriert Schreibweise in der Einstellung
     */
    public function test_wahlpflicht_trifft_unabhaengig_vom_rahmenpraefix(string $konfiguriert): void {
        $this->resetAfterTest();
        set_config('beruf_wahlpflicht_hk', 'AU_EFZ=' . $konfiguriert, 'local_berufsbildung');
        $lernende = $this->lege_lernende_an();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $wahl = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
            'idnumber' => '7777BE a.04',
        ]);
        $pflicht = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
            'idnumber' => '7777BE a.05',
        ]);

        $zellen = $this->zellen((new raster_analyse())->get_raster((int) $lernende->id));

        $this->assertTrue($zellen[(int) $wahl->get('id')]->istwahlpflicht);
        $this->assertFalse($zellen[(int) $pflicht->get('id')]->istwahlpflicht);
    }

    /**
     * Schreibweisen, die alle dieselbe Handlungskompetenz meinen.
     *
     * @return array<string, string[]>
     */
    public static function wahlpflicht_schreibweisen(): array {
        return [
            'ohne Praefix' => ['a.04'],
            'mit abweichendem Praefix' => ['7777 a.04'],
            'voll ausgeschrieben' => ['7777BE a.04'],
            'abweichende Schreibweise' => ['7777be A.04'],
        ];
    }

    /**
     * Ein Bereich, dessen HK ausschliesslich Wahlpflicht sind, bleibt im
     * Raster sichtbar - sonst fehlte gegenueber dem gedruckten
     * Bildungsplan eine ganze Zeile. In der Lueckenliste entfaellt er
     * weiterhin, weil er dort keine Bezugsgroesse haette.
     */
    public function test_reiner_wahlpflicht_bereich_bleibt_im_raster_fehlt_aber_in_der_abdeckung(): void {
        $this->resetAfterTest();
        set_config('beruf_wahlpflicht_hk', 'AU_EFZ=hk-nur-wahl', 'local_berufsbildung');
        $lernende = $this->lege_lernende_an();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb1 = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $hkb2 = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb1->get('id'), 'idnumber' => 'hk-pflicht',
        ]);
        $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb2->get('id'), 'idnumber' => 'hk-nur-wahl',
        ]);

        $raster = (new raster_analyse())->get_raster((int) $lernende->id);

        $this->assertSame(
            [(int) $hkb1->get('id'), (int) $hkb2->get('id')],
            array_map(static fn (raster_bereich $bereich): int => $bereich->bereichid, $raster)
        );

        $abdeckungen = (new luecken_analyse())->aus_raster($raster);
        $this->assertCount(1, $abdeckungen);
        $this->assertSame((int) $hkb1->get('id'), $abdeckungen[0]->bereichid);
    }

    /**
     * Eine erst spaeter eingeplante HK ist zum Stichtag genauso wenig
     * ausgebildet wie eine offene - die Lueckenliste darf den feineren
     * Stand des Rasters nicht als Abdeckung missverstehen.
     */
    public function test_eingeplante_hk_zaehlt_in_der_abdeckung_als_luecke(): void {
        $this->resetAfterTest();
        $lernende = $this->lege_lernende_an();
        $stichtag = time();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $eingeplant = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
        ]);

        $this->lege_einsatz_an((int) $lernende->id, $eingeplant, $stichtag + DAYSECS, $stichtag + WEEKSECS);

        $raster = (new raster_analyse())->get_raster((int) $lernende->id, $stichtag);
        $abdeckungen = (new luecken_analyse())->aus_raster($raster);

        $this->assertSame([(int) $eingeplant->get('id')], $abdeckungen[0]->luecken);
        $this->assertSame(0, $abdeckungen[0]->anzahl_abgedeckt());
    }

    /**
     * get_abdeckung() liefert dasselbe wie die Ableitung aus dem Raster -
     * Liste und Raster duerfen nie auseinanderlaufen.
     */
    public function test_abdeckung_ist_die_ableitung_aus_dem_raster(): void {
        $this->resetAfterTest();
        $lernende = $this->lege_lernende_an();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $erste = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
        ]);
        $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
        ]);

        $this->lege_einsatz_an((int) $lernende->id, $erste, time() - WEEKSECS, time() + WEEKSECS);

        $analyse = new luecken_analyse();
        $direkt = $analyse->get_abdeckung((int) $lernende->id);
        $ueberraster = $analyse->aus_raster((new raster_analyse())->get_raster((int) $lernende->id));

        $this->assertEquals($direkt, $ueberraster);
    }

    public function test_ohne_rahmen_konfiguration_ist_das_raster_leer(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        // Keine beruf_rahmen_mapping-Konfiguration gesetzt.

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) $this->laufender_jahrgang(),
        ]);

        $this->assertSame([], (new raster_analyse())->get_raster((int) $lernende->id));
    }

    /**
     * Randfall: die Lehre hat zum Stichtag noch nicht begonnen -
     * get_ausbildungsstand() liefert null, das Raster muss das abfangen
     * statt auf einer Null-Referenz zu scheitern.
     */
    public function test_lehre_noch_nicht_begonnen_ergibt_leeres_raster(): void {
        $this->resetAfterTest();
        $this->lege_profilfelder_an();
        set_config('beruf_rahmen_mapping', 'AU_EFZ=au-2022', 'local_berufsbildung');

        $lernende = $this->getDataGenerator()->create_user([
            'profile_field_beruf' => 'AU_EFZ',
            'profile_field_jahrgang' => (string) (((int) date('Y')) + 5),
        ]);

        $this->assertSame([], (new raster_analyse())->get_raster((int) $lernende->id));
    }

    /**
     * Der Planungshorizont sagt, bis wohin der vorliegende Plan reicht -
     * ohne ihn liesse sich "nicht im Plan" nicht einordnen.
     */
    public function test_planungshorizont_ist_das_ende_des_letzten_einsatzes(): void {
        $this->resetAfterTest();
        $lernende = $this->lege_lernende_an();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $hk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hkb->get('id'),
        ]);

        $analyse = new raster_analyse();
        $this->assertNull($analyse->get_planungshorizont((int) $lernende->id));

        $frueher = time() + WEEKSECS;
        $spaeter = time() + 4 * WEEKSECS;
        $this->lege_einsatz_an((int) $lernende->id, $hk, time(), $frueher);
        $this->lege_einsatz_an((int) $lernende->id, $hk, $frueher + 1, $spaeter);

        $this->assertSame($spaeter, $analyse->get_planungshorizont((int) $lernende->id));
    }
}
