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
 * Tests fuer die Kompetenzauswahl eines Ausbildungsblocks.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use core_competency\competency;
use local_berufsbildung\service\kompetenz_baum;

/**
 * Tests fuer kompetenz_auswahl.
 *
 * @covers \local_berufsbildung\output\kompetenz_auswahl
 */
final class kompetenz_auswahl_test extends advanced_testcase {
    /** @var competency[] Die angelegten Kompetenzen, nach Kurzname. */
    private array $kompetenzen = [];

    /**
     * Ein dreistufiger Rahmen wie in der Praxis: ein Bereich, zwei
     * Handlungskompetenzen, je zwei Leistungskriterien mit Code als
     * Kurzname und dem erklaerenden Text in der Beschreibung.
     *
     * @return array Baum wie von kompetenz_baum::baum()
     */
    private function lege_rahmen_an(): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $generator->create_framework(['idnumber' => 'au-2022']);

        $anlegen = function (array $daten) use ($rahmen, $generator): competency {
            $kompetenz = $generator->create_competency($daten + [
                'competencyframeworkid' => $rahmen->get('id'),
                'descriptionformat' => FORMAT_HTML,
            ]);
            $this->kompetenzen[$daten['shortname']] = $kompetenz;

            return $kompetenz;
        };

        $bereich = $anlegen([
            'shortname' => 'a Entwickeln von automatisierten Anlagen',
            'idnumber' => '7777BE a',
        ]);
        $ersteHk = $anlegen([
            'shortname' => 'Fertigungsunterlagen erstellen oder überarbeiten',
            'idnumber' => '7777BE a.01',
            'parentid' => $bereich->get('id'),
        ]);
        $zweiteHk = $anlegen([
            'shortname' => 'Netze planen und parametrieren',
            'idnumber' => '7777BE a.03',
            'parentid' => $bereich->get('id'),
        ]);

        $anlegen([
            'shortname' => 'MEM 02 02',
            'parentid' => $ersteHk->get('id'),
            'description' => 'Sie dokumentieren und archivieren ihre Arbeit nachvollziehbar.',
        ]);
        $anlegen([
            'shortname' => 'AU a1 01 1-2',
            'parentid' => $ersteHk->get('id'),
            'description' => 'Sie erstellen Stücklisten.',
        ]);
        $anlegen([
            'shortname' => 'MEM 07 01',
            'parentid' => $zweiteHk->get('id'),
            'description' => 'Sie parametrieren Netzwerkkomponenten.',
        ]);
        $anlegen([
            'shortname' => 'AU a3 03',
            'parentid' => $zweiteHk->get('id'),
            'description' => 'Sie prüfen die Verkabelung.',
        ]);

        return (new kompetenz_baum())->baum(array_values($this->kompetenzen));
    }

    /**
     * Kurznamen der Leistungskriterien einer Handlungskompetenz im
     * gefilterten Baum.
     *
     * @param array $baum
     * @return array<string, string[]> HK-Kurzname => LK-Kurznamen
     */
    private function struktur(array $baum): array {
        $struktur = [];
        foreach ($baum as $zweig) {
            foreach ($zweig['handlungskompetenzen'] as $eintrag) {
                $struktur[$eintrag['kompetenz']->get('shortname')] = array_map(
                    static fn (competency $lk): string => (string) $lk->get('shortname'),
                    $eintrag['leistungskriterien']
                );
            }
        }

        return $struktur;
    }

    /**
     * Randfall: ohne Suchbegriff bleibt der Baum unveraendert - die Suche
     * darf nichts kosten, solange niemand sucht.
     */
    public function test_leerer_suchbegriff_laesst_den_baum_unveraendert(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $this->assertSame($baum, kompetenz_auswahl::filtere($baum, ''));
        $this->assertSame($baum, kompetenz_auswahl::filtere($baum, '   '));
    }

    /**
     * Trifft eine Handlungskompetenz selbst, gehoeren alle ihre
     * Leistungskriterien dazu - und die andere HK faellt weg.
     */
    public function test_treffer_auf_handlungskompetenz_behaelt_alle_leistungskriterien(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $struktur = $this->struktur(kompetenz_auswahl::filtere($baum, 'parametrieren'));

        $this->assertSame(
            ['Netze planen und parametrieren' => ['MEM 07 01', 'AU a3 03']],
            $struktur
        );
    }

    /**
     * Trifft nur ein Leistungskriterium, bleibt von seiner
     * Handlungskompetenz genau dieses uebrig - der Platz im Rahmen bleibt
     * damit sichtbar, ohne die uebrigen mitzuschleppen.
     */
    public function test_treffer_auf_leistungskriterium_behaelt_nur_dieses(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $struktur = $this->struktur(kompetenz_auswahl::filtere($baum, 'MEM 02'));

        $this->assertSame(
            ['Fertigungsunterlagen erstellen oder überarbeiten' => ['MEM 02 02']],
            $struktur
        );
    }

    /**
     * Gesucht wird auch in der Beschreibung - bei einem Leistungskriterium
     * ist der Kurzname nur ein Code und taugt allein nicht zur Suche.
     */
    public function test_suche_findet_ueber_die_beschreibung(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $struktur = $this->struktur(kompetenz_auswahl::filtere($baum, 'stücklisten'));

        $this->assertSame(
            ['Fertigungsunterlagen erstellen oder überarbeiten' => ['AU a1 01 1-2']],
            $struktur
        );
    }

    /**
     * Gesucht wird auch in der ID-Nummer, und zwar unabhaengig von der
     * Schreibweise - wer "a.03" aus dem Bildungsplan kennt, tippt nicht
     * den Rahmen-Praefix davor.
     */
    public function test_suche_findet_ueber_die_idnummer_unabhaengig_von_schreibweise(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $this->assertSame(
            ['Netze planen und parametrieren'],
            array_keys($this->struktur(kompetenz_auswahl::filtere($baum, 'a.03')))
        );
        $this->assertSame(
            ['Netze planen und parametrieren'],
            array_keys($this->struktur(kompetenz_auswahl::filtere($baum, '7777be A.03')))
        );
    }

    /**
     * Trifft der Bereich, gehoert alles darunter dazu: wer den Namen eines
     * Handlungskompetenzbereichs sucht, meint den ganzen Bereich.
     */
    public function test_treffer_auf_bereich_behaelt_den_ganzen_zweig(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $gefiltert = kompetenz_auswahl::filtere($baum, 'Entwickeln von');

        $this->assertSame($baum, $gefiltert);
    }

    /**
     * Randfall: nichts passt. Dann bleibt ein leerer Baum uebrig, kein
     * Bereich ohne Inhalt - die Seite meldet das eigens.
     */
    public function test_ohne_treffer_bleibt_der_baum_leer(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $this->assertSame([], kompetenz_auswahl::filtere($baum, 'Hydraulik'));
    }
}
