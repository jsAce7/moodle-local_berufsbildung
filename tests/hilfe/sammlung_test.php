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
 * Tests der Hilfeseite.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\hilfe;

use core_user\hook\extend_user_menu;
use local_berufsbildung\hook_callbacks;
use local_berufsbildung\output\hilfe_link;

/**
 * Welche Plugins Abschnitte melden, haengt von der Installation ab. Die
 * Tests zur Sichtbarkeit geben die gemeldeten Abschnitte deshalb leer vor
 * und pruefen nur die Abschnitte dieses Plugins.
 *
 * @covers \local_berufsbildung\hilfe\sammlung
 * @covers \local_berufsbildung\hilfe\abschnitt
 * @covers \local_berufsbildung\output\hilfe_link
 * @covers \local_berufsbildung\hook_callbacks
 */
final class sammlung_test extends \advanced_testcase {
    /**
     * Die Anker je Zielgruppe.
     *
     * @param array<string, abschnitt[]> $je
     * @return array<string, string[]>
     */
    private function anker(array $je): array {
        return array_map(
            static fn(array $abschnitte): array => array_map(static fn(abschnitt $a): string => $a->anker, $abschnitte),
            $je
        );
    }

    public function test_ohne_lehre_und_lernende_keine_abschnitte(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertSame([], sammlung::je_zielgruppe([]));
    }

    public function test_gast_bekommt_nichts(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->assertSame([], sammlung::je_zielgruppe([]));
    }

    public function test_lernende_sehen_grundlagen_und_meine_lehre(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende());

        $this->assertSame([
            abschnitt::GRUNDLAGEN => [
                'berufsbildung-ausbildungsstand',
                'berufsbildung-versetzungsplan',
                'berufsbildung-kompetenzraster',
            ],
            abschnitt::LERNENDE => ['berufsbildung-meine-lehre'],
        ], $this->anker(sammlung::je_zielgruppe([])));
    }

    public function test_berufsbildner_sehen_meine_lernenden_aber_nicht_meine_lehre(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $berufsbildner = $this->getDataGenerator()->create_user();
        $generator->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $generator->create_lernende()->id,
        ]);
        $this->setUser($berufsbildner);

        $je = $this->anker(sammlung::je_zielgruppe([]));

        $this->assertSame(['berufsbildung-meine-lernenden'], $je[abschnitt::BERUFSBILDNER]);
        $this->assertArrayHasKey(abschnitt::GRUNDLAGEN, $je);
        $this->assertArrayNotHasKey(abschnitt::LERNENDE, $je);
        $this->assertArrayNotHasKey(abschnitt::LEITUNG, $je);
        $this->assertArrayNotHasKey(abschnitt::ADMINISTRATION, $je);
    }

    public function test_admin_sieht_verwaltung_in_fester_reihenfolge(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $je = sammlung::je_zielgruppe([]);

        $this->assertSame(
            [abschnitt::GRUNDLAGEN, abschnitt::PLANUNG, abschnitt::LEITUNG, abschnitt::ADMINISTRATION],
            array_keys($je)
        );
        $this->assertSame([
            'berufsbildung-ausbildungsbloecke',
            'berufsbildung-kompetenzrahmen',
            'berufsbildung-planimport',
        ], $this->anker($je)[abschnitt::PLANUNG]);
        $this->assertSame([
            'berufsbildung-zuordnungen',
            'berufsbildung-globale-gruppen',
            'berufsbildung-aufbewahrung',
        ], $this->anker($je)[abschnitt::LEITUNG]);
        $this->assertTrue($je[abschnitt::LEITUNG][0]->kontext['darfaendern']);
    }

    public function test_nur_lesen_der_zuordnungen_ohne_aenderungsanleitung(): void {
        $this->resetAfterTest();
        $nutzer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/berufsbildung:viewzuordnung', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, (int) $nutzer->id, \context_system::instance()->id);
        $this->setUser($nutzer);

        $je = sammlung::je_zielgruppe([]);

        $this->assertSame(['berufsbildung-zuordnungen'], $this->anker($je)[abschnitt::LEITUNG]);
        $this->assertFalse($je[abschnitt::LEITUNG][0]->kontext['darfaendern']);
    }

    public function test_gemeldete_abschnitte_folgen_den_eigenen_ihrer_zielgruppe(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende());

        $je = $this->anker(sammlung::je_zielgruppe([
            new abschnitt(abschnitt::LEITUNG, 'fremd-leitung', 'Leitung', 'local_fremd/hilfe/leitung'),
            new abschnitt(abschnitt::LERNENDE, 'fremd-erfassen', 'Erfassen', 'local_fremd/hilfe/erfassen'),
        ]));

        $this->assertSame([abschnitt::GRUNDLAGEN, abschnitt::LERNENDE, abschnitt::LEITUNG], array_keys($je));
        $this->assertSame(['berufsbildung-meine-lehre', 'fremd-erfassen'], $je[abschnitt::LERNENDE]);
        $this->assertSame(['fremd-leitung'], $je[abschnitt::LEITUNG]);
    }

    public function test_reihenfolge_ordnet_innerhalb_der_zielgruppe(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende());

        $je = $this->anker(sammlung::je_zielgruppe([
            new abschnitt(abschnitt::LERNENDE, 'uek', 'üK', 'local_fremd/hilfe/uek', [], 300),
            new abschnitt(abschnitt::KURSLEITUNG, 'kurs', 'Kurs', 'local_fremd/hilfe/kurs', [], 300),
            new abschnitt(abschnitt::LERNENDE, 'lerndoku-a', 'A', 'local_fremd/hilfe/a', [], 100),
            new abschnitt(abschnitt::LERNENDE, 'lerndoku-b', 'B', 'local_fremd/hilfe/b', [], 100),
        ]));

        $this->assertSame(['berufsbildung-meine-lehre', 'lerndoku-a', 'lerndoku-b', 'uek'], $je[abschnitt::LERNENDE]);
        $this->assertSame(
            [abschnitt::GRUNDLAGEN, abschnitt::LERNENDE, abschnitt::KURSLEITUNG],
            array_keys($je)
        );
    }

    public function test_doppelter_anker_wird_verworfen(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende());

        $je = $this->anker(sammlung::je_zielgruppe([
            new abschnitt(abschnitt::LERNENDE, 'berufsbildung-meine-lehre', 'Doppelt', 'local_fremd/hilfe/doppelt'),
        ]));

        $this->assertDebuggingCalled('Hilfe-Anker doppelt: berufsbildung-meine-lehre');
        $this->assertSame(['berufsbildung-meine-lehre'], $je[abschnitt::LERNENDE]);
    }

    public function test_unbekannte_zielgruppe_wird_abgelehnt(): void {
        $this->expectException(\coding_exception::class);
        new abschnitt('irgendwer', 'anker', 'Titel', 'local_fremd/hilfe/x');
    }

    /**
     * Anker, die als Sprungmarke in einer URL nicht taugen.
     *
     * @return array
     */
    public static function ungueltige_anker(): array {
        return [
            'leer' => [''],
            'gross' => ['Meine-Lehre'],
            'unterstrich' => ['meine_lehre'],
            'leerzeichen' => ['meine lehre'],
            'endet mit strich' => ['meine-'],
            'raute' => ['#meine-lehre'],
        ];
    }

    /**
     * Ein Anker, der als Sprungmarke nicht taugt, wird abgelehnt.
     *
     * @dataProvider ungueltige_anker
     * @param string $anker
     */
    public function test_ungueltiger_anker_wird_abgelehnt(string $anker): void {
        $this->expectException(\coding_exception::class);
        new abschnitt(abschnitt::LERNENDE, $anker, 'Titel', 'local_fremd/hilfe/x');
    }

    public function test_alle_eigenen_abschnitte_rendern(): void {
        global $OUTPUT, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/local/berufsbildung/hilfe.php');
        $PAGE->set_context(\context_system::instance());

        // Der Admin sieht alles ausser den Seiten, die eigene Lehre oder
        // eigene Lernende voraussetzen; die rendern ohne Daten genauso.
        $templates = ['local_berufsbildung/hilfe/meine_lehre', 'local_berufsbildung/hilfe/meine_lernenden'];
        $kontexte = [[], []];
        foreach (sammlung::je_zielgruppe([]) as $abschnitte) {
            foreach ($abschnitte as $abschnitt) {
                $templates[] = $abschnitt->template;
                $kontexte[] = $abschnitt->kontext;
            }
        }

        $this->assertCount(12, $templates);
        foreach ($templates as $i => $template) {
            $html = $OUTPUT->render_from_template($template, $kontexte[$i]);
            $this->assertNotSame('', trim($html), $template);
        }
        $this->assertStringContainsString(
            'Neue Zuordnung',
            $OUTPUT->render_from_template('local_berufsbildung/hilfe/zuordnungen', ['darfaendern' => true])
        );
        $this->assertStringNotContainsString(
            'Neue Zuordnung',
            $OUTPUT->render_from_template('local_berufsbildung/hilfe/zuordnungen', ['darfaendern' => false])
        );

        // Ohne Blockverwaltung gibt es deren Abschnitt nicht - also auch keinen Link dorthin.
        $this->assertStringContainsString(
            '#berufsbildung-ausbildungsbloecke',
            $OUTPUT->render_from_template('local_berufsbildung/hilfe/meine_lernenden', ['bloecke' => true])
        );
        $this->assertStringNotContainsString(
            '#berufsbildung-ausbildungsbloecke',
            $OUTPUT->render_from_template('local_berufsbildung/hilfe/meine_lernenden', ['bloecke' => false])
        );
    }

    public function test_link_fuehrt_zum_abschnitt(): void {
        $this->resetAfterTest();

        $html = hilfe_link::render('berufsbildung-meine-lehre');

        $this->assertStringContainsString('/local/berufsbildung/hilfe.php#berufsbildung-meine-lehre', $html);
    }

    public function test_benutzermenue_nur_mit_abschnitten(): void {
        $this->resetAfterTest();

        $this->setUser($this->getDataGenerator()->create_user());
        $hook = new extend_user_menu();
        hook_callbacks::extend_user_menu($hook);
        $ohne = $hook->get_navitems();

        $this->setUser($this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende());
        $hook = new extend_user_menu();
        hook_callbacks::extend_user_menu($hook);
        $mit = $hook->get_navitems();

        // Ohne Lehre und Lernende koennen nur aufsetzende Plugins etwas
        // melden - in der CI dieses Plugins keines.
        if (empty(get_plugins_with_function('berufsbildung_hilfe'))) {
            $this->assertSame([], $ohne);
        }
        $this->assertCount(1, $mit);
        $this->assertSame('link', $mit[0]->itemtype);
        $this->assertStringContainsString('/local/berufsbildung/hilfe.php', $mit[0]->url->out(false));
    }
}
