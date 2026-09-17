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
 * Tests fuer die Ebenen-Ermittlung im Kompetenzrahmen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use core_competency\competency;

/**
 * Tests fuer kompetenz_baum.
 *
 * @covers \local_berufsbildung\service\kompetenz_baum
 */
final class kompetenz_baum_test extends advanced_testcase {
    /**
     * Dreistufiger Rahmen wie in der Praxis: Handlungskompetenzbereich ->
     * Handlungskompetenz -> Leistungskriterium. Nur die LK duerfen bei der
     * Blockzuordnung zur Auswahl stehen.
     */
    public function test_nur_blaetter_liefert_nur_leistungskriterien(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);

        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $hk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
        ]);
        $lk1 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk->get('id'),
        ]);
        $lk2 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk->get('id'),
        ]);

        $blaetter = (new kompetenz_baum())->nur_blaetter([$hkb, $hk, $lk1, $lk2]);
        $blaetterids = array_map(static fn ($k) => (int) $k->get('id'), $blaetter);

        $this->assertEqualsCanonicalizing([(int) $lk1->get('id'), (int) $lk2->get('id')], $blaetterids);
    }

    /**
     * Randfall: ein Rahmen ohne Hierarchie (alle Kompetenzen auf oberster
     * Ebene) - dann sind alle Knoten zugleich Blaetter, sonst koennte in
     * so einem Rahmen nie etwas zugeordnet werden.
     */
    public function test_nur_blaetter_ohne_hierarchie_liefert_alle(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'flach-2022']);
        $eins = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $zwei = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);

        $blaetter = (new kompetenz_baum())->nur_blaetter([$eins, $zwei]);
        $blaetterids = array_map(static fn ($k) => (int) $k->get('id'), $blaetter);

        $this->assertEqualsCanonicalizing([(int) $eins->get('id'), (int) $zwei->get('id')], $blaetterids);
    }

    public function test_nur_blaetter_bei_leerer_liste_ist_leer(): void {
        $this->assertSame([], (new kompetenz_baum())->nur_blaetter([]));
    }

    /**
     * Der Baum fuer die Auswahl in block_kompetenzen.php: Bereiche mit
     * ihren Handlungskompetenzen und den LK darunter. Die Reihenfolge ist
     * die der uebergebenen Liste, nicht das Alphabet - nur so folgt die
     * Auswahl der Gliederung des Bildungsplans.
     */
    public function test_baum_gliedert_nach_bereich_und_handlungskompetenz(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);

        $hkb = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'shortname' => 'a Beraten',
            'idnumber' => 'a',
        ]);
        $hk1 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
            'shortname' => 'a1 Kundengespraech',
            'idnumber' => '7777BE a.01',
        ]);
        $hk2 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
            'shortname' => 'a2 Offerten',
            'idnumber' => '7777BE a.02',
        ]);
        $lk1 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk1->get('id'),
            'shortname' => 'AU a1 01 1-2',
        ]);
        $lk2 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk2->get('id'),
            'shortname' => 'AU a2 01 1-2',
        ]);

        $baum = (new kompetenz_baum())->baum([$hkb, $hk1, $hk2, $lk1, $lk2]);

        $this->assertCount(1, $baum);
        $this->assertSame((int) $hkb->get('id'), (int) $baum[0]['bereich']->get('id'));

        $handlungskompetenzen = $baum[0]['handlungskompetenzen'];
        $this->assertCount(2, $handlungskompetenzen);

        // Reihenfolge wie uebergeben, nicht alphabetisch neu sortiert.
        $this->assertSame((int) $hk1->get('id'), (int) $handlungskompetenzen[0]['kompetenz']->get('id'));
        $this->assertSame((int) $hk2->get('id'), (int) $handlungskompetenzen[1]['kompetenz']->get('id'));

        $this->assertSame(
            [(int) $lk1->get('id')],
            array_map(
                static fn ($lk): int => (int) $lk->get('id'),
                $handlungskompetenzen[0]['leistungskriterien']
            )
        );
        $this->assertSame(
            [(int) $lk2->get('id')],
            array_map(
                static fn ($lk): int => (int) $lk->get('id'),
                $handlungskompetenzen[1]['leistungskriterien']
            )
        );
    }

    /**
     * Randfall: eine Handlungskompetenz ohne Leistungskriterien darf nicht
     * verschwinden - sie bleibt als Ganzes auswaehlbar.
     */
    public function test_baum_haelt_handlungskompetenz_ohne_leistungskriterien(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $hk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
        ]);

        $baum = (new kompetenz_baum())->baum([$hkb, $hk]);

        $this->assertCount(1, $baum[0]['handlungskompetenzen']);
        $this->assertSame([], $baum[0]['handlungskompetenzen'][0]['leistungskriterien']);
    }

    /**
     * Randfall: eine Ebene mehr als die ueblichen drei. Die Blaetter
     * gehoeren dann trotzdem zu ihrer Handlungskompetenz, statt dass die
     * Zwischenebene als Leistungskriterium erscheint.
     */
    public function test_baum_holt_blaetter_auch_aus_tieferen_ebenen(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'tief-2022']);
        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $hk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
        ]);
        $zwischen = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk->get('id'),
        ]);
        $blatt = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $zwischen->get('id'),
        ]);

        $baum = (new kompetenz_baum())->baum([$hkb, $hk, $zwischen, $blatt]);

        $this->assertSame(
            [(int) $blatt->get('id')],
            array_map(
                static fn ($lk): int => (int) $lk->get('id'),
                $baum[0]['handlungskompetenzen'][0]['leistungskriterien']
            )
        );
    }

    public function test_baum_bei_leerer_liste_ist_leer(): void {
        $this->assertSame([], (new kompetenz_baum())->baum([]));
    }

    /**
     * Das Kuerzel ist der Anker zum gedruckten Bildungsplan: der
     * Rahmen-Praefix faellt weg, der Rest bleibt. Ohne Leerzeichen bleibt
     * die ID-Nummer unveraendert, ohne ID-Nummer entfaellt sie ganz.
     */
    public function test_kuerzel_schneidet_den_rahmenpraefix_ab(): void {
        // Ohne Datenbank: kuerzel() liest nur die idnumber, und eine leere
        // idnumber liesse sich ueber den Generator nicht sauber erzeugen -
        // der Unique-Index gilt je Rahmen.
        $this->assertSame(
            'b.07',
            kompetenz_baum::kuerzel(new competency(0, (object) ['idnumber' => '7777BE b.07']))
        );
        $this->assertSame(
            'b07',
            kompetenz_baum::kuerzel(new competency(0, (object) ['idnumber' => 'b07']))
        );
        $this->assertSame(
            '',
            kompetenz_baum::kuerzel(new competency(0, (object) ['idnumber' => '']))
        );
    }

    /**
     * Dreistufiger Rahmen: nur die zweite Ebene (HK) darf zurueckkommen -
     * weder die obersten Handlungskompetenzbereiche noch die LK darunter.
     */
    public function test_nur_handlungskompetenzen_liefert_zweite_ebene(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);

        $hkb = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $hk1 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
        ]);
        $hk2 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
        ]);
        $lk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk1->get('id'),
        ]);

        $hks = (new kompetenz_baum())->nur_handlungskompetenzen([$hkb, $hk1, $hk2, $lk]);
        $hkids = array_map(static fn ($k) => (int) $k->get('id'), $hks);

        $this->assertEqualsCanonicalizing([(int) $hk1->get('id'), (int) $hk2->get('id')], $hkids);
    }

    /**
     * Randfall: ein Rahmen ohne Hierarchie - dann gibt es keine zweite
     * Ebene, die Liste ist leer statt faelschlich die obersten Knoten
     * zurueckzugeben.
     */
    public function test_nur_handlungskompetenzen_ohne_hierarchie_ist_leer(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'flach-2022']);
        $eins = $generator->create_competency(['competencyframeworkid' => $rahmen->get('id')]);

        $this->assertSame([], (new kompetenz_baum())->nur_handlungskompetenzen([$eins]));
    }

    public function test_nur_handlungskompetenzen_bei_leerer_liste_ist_leer(): void {
        $this->assertSame([], (new kompetenz_baum())->nur_handlungskompetenzen([]));
    }
}
