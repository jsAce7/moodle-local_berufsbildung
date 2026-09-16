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
     * Die Auswahlliste in block_kompetenzen.php braucht die
     * Handlungskompetenz als Praefix und die idnumber dahinter, sonst sind
     * gleich benannte LK aus verschiedenen HK nicht unterscheidbar. Die
     * Reihenfolge kommt aus der Beschriftung, nicht aus der Eingabeliste.
     */
    public function test_blatt_beschriftungen_haben_hk_praefix_und_idnumber(): void {
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
            'idnumber' => 'a1',
        ]);
        $hk2 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hkb->get('id'),
            'shortname' => 'a2 Offerten',
            'idnumber' => 'a2',
        ]);
        $lk2 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk2->get('id'),
            'shortname' => 'Angebot erstellen',
            'idnumber' => 'a2.1',
        ]);
        $lk1 = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk1->get('id'),
            'shortname' => 'Bedarf klaeren',
            'idnumber' => 'a1.1',
        ]);

        // Absichtlich in der "falschen" Reihenfolge uebergeben.
        $beschriftungen = (new kompetenz_baum())->blatt_beschriftungen([$hkb, $hk1, $hk2, $lk2, $lk1]);

        $this->assertSame([
            (int) $lk1->get('id') => 'a1 Kundengespraech: Bedarf klaeren (a1.1)',
            (int) $lk2->get('id') => 'a2 Offerten: Angebot erstellen (a2.1)',
        ], $beschriftungen);
    }

    /**
     * Randfall: Blatt auf oberster Ebene - es gibt keine Handlungskompetenz
     * davor, die Beschriftung darf deswegen nicht mit einem Trenner
     * beginnen.
     */
    public function test_blatt_beschriftungen_ohne_eltern_ohne_praefix(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'flach-2022']);
        $eins = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'shortname' => 'Werkzeuge instand halten',
            'idnumber' => 'w1',
        ]);

        $this->assertSame(
            [(int) $eins->get('id') => 'Werkzeuge instand halten (w1)'],
            (new kompetenz_baum())->blatt_beschriftungen([$eins])
        );
    }

    /**
     * Randfall: der Elternknoten ist nicht Teil der uebergebenen Liste.
     * Dann fehlt der Praefix, statt dass die Beschriftung ausfaellt.
     */
    public function test_blatt_beschriftungen_bei_unbekanntem_eltern_ohne_praefix(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);
        $hk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'shortname' => 'a1 Kundengespraech',
            'idnumber' => 'a1',
        ]);
        $lk = $generator->create_competency([
            'competencyframeworkid' => $rahmen->get('id'),
            'parentid' => $hk->get('id'),
            'shortname' => 'Bedarf klaeren',
            'idnumber' => 'a1.1',
        ]);

        $this->assertSame(
            [(int) $lk->get('id') => 'Bedarf klaeren (a1.1)'],
            (new kompetenz_baum())->blatt_beschriftungen([$lk])
        );
    }

    public function test_blatt_beschriftungen_bei_leerer_liste_ist_leer(): void {
        $this->assertSame([], (new kompetenz_baum())->blatt_beschriftungen([]));
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
