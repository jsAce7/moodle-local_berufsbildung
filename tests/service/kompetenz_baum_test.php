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
 * Tests fuer die Blattknoten-Ermittlung im Kompetenzrahmen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;

/**
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
}
