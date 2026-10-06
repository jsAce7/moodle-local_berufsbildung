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
        $erstehk = $anlegen([
            'shortname' => 'Fertigungsunterlagen erstellen oder überarbeiten',
            'idnumber' => '7777BE a.01',
            'parentid' => $bereich->get('id'),
        ]);
        $zweitehk = $anlegen([
            'shortname' => 'Netze planen und parametrieren',
            'idnumber' => '7777BE a.03',
            'parentid' => $bereich->get('id'),
        ]);

        $anlegen([
            'shortname' => 'MEM 02 02',
            'parentid' => $erstehk->get('id'),
            'description' => 'Sie dokumentieren und archivieren ihre Arbeit nachvollziehbar.',
        ]);
        $anlegen([
            'shortname' => 'AU a1 01 1-2',
            'parentid' => $erstehk->get('id'),
            'description' => 'Sie erstellen Stücklisten.',
        ]);
        $anlegen([
            'shortname' => 'MEM 07 01',
            'parentid' => $zweitehk->get('id'),
            'description' => 'Sie parametrieren Netzwerkkomponenten.',
        ]);
        $anlegen([
            'shortname' => 'AU a3 03',
            'parentid' => $zweitehk->get('id'),
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

    /**
     * IDs zu Kurznamen, als Zuordnung wie in block_kompetenzen.php.
     *
     * @param string ...$kurznamen
     * @return array<int, true>
     */
    private function zugeordnet(string ...$kurznamen): array {
        $zugeordnet = [];
        foreach ($kurznamen as $kurzname) {
            $zugeordnet[(int) $this->kompetenzen[$kurzname]->get('id')] = true;
        }

        return $zugeordnet;
    }

    /**
     * Ohne Zuordnung ist alles waehlbar und nichts ganz oder teilweise.
     */
    public function test_stand_ohne_zuordnung(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $stand = kompetenz_auswahl::stand($baum, []);

        // Zwei HK und vier LK - der Bereich selbst ist nie waehlbar.
        $this->assertCount(6, $stand['waehlbar']);
        $this->assertSame(
            ['anzahl' => 2, 'ganz' => 0, 'teilweise' => 0],
            $stand['bereiche'][(int) $this->kompetenzen['a Entwickeln von automatisierten Anlagen']->get('id')]
        );
    }

    /**
     * Eine als Ganzes zugeordnete Handlungskompetenz deckt ihre
     * Leistungskriterien ab: keines davon ist mehr waehlbar, die andere HK
     * bleibt es.
     */
    public function test_stand_ganze_hk_deckt_leistungskriterien_ab(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();
        $hk = (int) $this->kompetenzen['Fertigungsunterlagen erstellen oder überarbeiten']->get('id');

        $stand = kompetenz_auswahl::stand($baum, $this->zugeordnet('Fertigungsunterlagen erstellen oder überarbeiten'));

        $this->assertTrue($stand['handlungskompetenzen'][$hk]['direkt']);
        $this->assertTrue($stand['handlungskompetenzen'][$hk]['ganz']);
        $erwartet = array_keys($this->zugeordnet('Netze planen und parametrieren', 'MEM 07 01', 'AU a3 03'));
        $waehlbar = array_keys($stand['waehlbar']);
        sort($erwartet);
        sort($waehlbar);
        $this->assertSame($erwartet, $waehlbar);
        $this->assertSame(
            ['anzahl' => 2, 'ganz' => 1, 'teilweise' => 0],
            $stand['bereiche'][(int) $this->kompetenzen['a Entwickeln von automatisierten Anlagen']->get('id')]
        );
    }

    /**
     * Randfall: nur ein Teil der Leistungskriterien ist zugeordnet. Die HK
     * zaehlt als teilweise und bleibt als Ganzes waehlbar, ebenso das
     * noch offene Leistungskriterium.
     */
    public function test_stand_einzelne_leistungskriterien_sind_teilweise(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();
        $hk = (int) $this->kompetenzen['Fertigungsunterlagen erstellen oder überarbeiten']->get('id');

        $stand = kompetenz_auswahl::stand($baum, $this->zugeordnet('MEM 02 02'));

        $this->assertSame(
            ['direkt' => false, 'ganz' => false, 'lkanzahl' => 2, 'lkzugeordnet' => 1],
            $stand['handlungskompetenzen'][$hk]
        );
        $this->assertArrayHasKey($hk, $stand['waehlbar']);
        $this->assertArrayHasKey((int) $this->kompetenzen['AU a1 01 1-2']->get('id'), $stand['waehlbar']);
        $this->assertArrayNotHasKey((int) $this->kompetenzen['MEM 02 02']->get('id'), $stand['waehlbar']);
        $this->assertSame(
            ['anzahl' => 2, 'ganz' => 0, 'teilweise' => 1],
            $stand['bereiche'][(int) $this->kompetenzen['a Entwickeln von automatisierten Anlagen']->get('id')]
        );
    }

    /**
     * Randfall: sind alle Leistungskriterien einzeln zugeordnet, ist die HK
     * ganz zugeordnet - sie noch als Ganzes zu waehlen, legte nur eine
     * doppelte Zeile an.
     */
    public function test_stand_alle_leistungskriterien_einzeln_sind_ganz(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();
        $hk = (int) $this->kompetenzen['Fertigungsunterlagen erstellen oder überarbeiten']->get('id');

        $stand = kompetenz_auswahl::stand($baum, $this->zugeordnet('MEM 02 02', 'AU a1 01 1-2'));

        $this->assertFalse($stand['handlungskompetenzen'][$hk]['direkt']);
        $this->assertTrue($stand['handlungskompetenzen'][$hk]['ganz']);
        $this->assertArrayNotHasKey($hk, $stand['waehlbar']);
    }

    /**
     * Die Ausgabe zeigt den Stand: Bereichszaehler, "x von y LK" an der
     * teilweise zugeordneten HK und "ueber ... abgedeckt" statt einer
     * Checkbox unter der ganz zugeordneten.
     */
    public function test_render_zeigt_stand(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $html = kompetenz_auswahl::render(
            $baum,
            $this->zugeordnet('Fertigungsunterlagen erstellen oder überarbeiten', 'MEM 07 01'),
            new \moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => 7]),
            7
        );

        $this->assertStringContainsString(s(get_string(
            'blocklk:bereich_stand_teilweise',
            'local_berufsbildung',
            (object) ['ganz' => 1, 'anzahl' => 2, 'teilweise' => 1]
        )), $html);
        $this->assertStringContainsString($this->lk_stand(1, 2), $html);
        $this->assertStringContainsString(s(get_string('blocklk:abgedeckt_durch_hk', 'local_berufsbildung', 'a.01')), $html);
        $this->assertStringNotContainsString(
            'value="' . $this->kompetenzen['AU a1 01 1-2']->get('id') . '"',
            $html
        );
        $this->assertStringContainsString(
            'value="' . $this->kompetenzen['AU a3 03']->get('id') . '"',
            $html
        );
        $this->assertStringNotContainsString('intensitaet', $html);
    }

    /**
     * Randfall: bei aktiver Suche zaehlt der Stand weiter ueber die ganze
     * HK - aus "1 von 2 LK" darf nicht "1 von 1" werden, nur weil das
     * andere Leistungskriterium ausgeblendet ist.
     */
    public function test_render_stand_ignoriert_suchfilter(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $html = kompetenz_auswahl::render(
            $baum,
            $this->zugeordnet('MEM 07 01'),
            new \moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => 7]),
            7,
            'MEM 07'
        );

        $this->assertStringContainsString($this->lk_stand(1, 2), $html);
    }

    /**
     * Erwarteter Text des Teilstands, in der Sprache des Testlaufs.
     *
     * @param int $zugeordnet
     * @param int $anzahl
     */
    private function lk_stand(int $zugeordnet, int $anzahl): string {
        return s(get_string('blocklk:lk_stand', 'local_berufsbildung', (object) [
            'zugeordnet' => $zugeordnet,
            'anzahl' => $anzahl,
        ]));
    }

    /**
     * Begriffe werden an Komma, Semikolon und Zeilenumbruch getrennt, nicht
     * am Leerzeichen - ein LK-Code enthaelt selbst welche. Doppelte zaehlen
     * einmal.
     */
    public function test_suchbegriffe_trennt_listen(): void {
        $this->assertSame(
            ['AU b4 01', 'MEM 11 05 1-2', 'MEM 02 01'],
            kompetenz_auswahl::suchbegriffe(" AU b4 01, MEM 11 05 1-2;\r\nMEM 02 01,, au B4 01 ")
        );
        $this->assertSame([], kompetenz_auswahl::suchbegriffe(' , ; '));
    }

    /**
     * Eine Liste von LK-Codes, wie sie aus einer Tabelle eingefuegt wird:
     * jedes Leistungskriterium, das einen der Codes traegt, bleibt stehen,
     * unter seiner eigenen Handlungskompetenz.
     */
    public function test_liste_von_codes_findet_jeden(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $struktur = $this->struktur(kompetenz_auswahl::filtere($baum, 'MEM 02 02, AU a3 03'));

        $this->assertSame([
            'Fertigungsunterlagen erstellen oder überarbeiten' => ['MEM 02 02'],
            'Netze planen und parametrieren' => ['AU a3 03'],
        ], $struktur);
    }

    /**
     * Randfall: ein Code der Liste trifft nichts. Er wird gemeldet, die
     * anderen bleiben unbeeinflusst; Gross- und Kleinschreibung spielt
     * keine Rolle.
     */
    public function test_nicht_gefundene_codes_werden_gemeldet(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();

        $this->assertSame(
            ['XY 99 01'],
            kompetenz_auswahl::nicht_gefunden($baum, 'mem 02 02, XY 99 01, AU a3 03')
        );
        $this->assertSame([], kompetenz_auswahl::nicht_gefunden($baum, ''));
        $this->assertSame(
            ['Fertigungsunterlagen erstellen oder überarbeiten' => ['MEM 02 02']],
            $this->struktur(kompetenz_auswahl::filtere($baum, 'XY 99 01, MEM 02 02'))
        );
    }

    /**
     * Die Ausgabe nennt nicht gefundene Codes nur neben anderen Treffern -
     * trifft gar nichts, sagt das bereits die Meldung "keine Treffer".
     */
    public function test_render_meldet_nicht_gefundene_codes(): void {
        $this->resetAfterTest();
        $baum = $this->lege_rahmen_an();
        $url = new \moodle_url('/local/berufsbildung/block_kompetenzen.php', ['id' => 7]);

        $html = kompetenz_auswahl::render($baum, [], $url, 7, 'MEM 02 02, XY 99 01');
        $this->assertMatchesRegularExpression(
            '/<p class="text-warning" [^>]*data-region="berufsbildung-auswahl-nicht-gefunden"/',
            $html
        );
        $this->assertStringContainsString('XY 99 01', $html);

        $html = kompetenz_auswahl::render($baum, [], $url, 7, 'XY 99 01');
        $this->assertMatchesRegularExpression(
            '/<p class="text-warning d-none" [^>]*data-region="berufsbildung-auswahl-nicht-gefunden"/',
            $html
        );
    }
}
