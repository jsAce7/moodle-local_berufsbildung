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
 * Tests fuer die Darstellung einer zurueckgehaltenen Versetzungsplan-Lieferung.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\output;

use advanced_testcase;
use local_berufsbildung\persistent\plan_import;
use local_berufsbildung\versetzungsplan\import_service;
use moodle_url;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\output\plan_freigabe::class)]
/**
 * Tests fuer plan_freigabe.
 *
 * @covers \local_berufsbildung\output\plan_freigabe
 */
final class plan_freigabe_test extends advanced_testcase {
    /**
     * Legt Anna, Ben und Carla mit laufender Zuordnung an, liefert alle drei
     * und dann nur noch Anna in einem spaeteren Zeitraum - deutlicher
     * Rueckgang, die zweite Lieferung wartet.
     *
     * @return plan_import die wartende Lieferung
     */
    private function wartende_lieferung(): plan_import {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $berufsbildner = $this->getDataGenerator()->create_user();
        $personen = [['Carla', 'Drei'], ['Anna', 'Eins'], ['Ben', 'Zwei']];
        foreach ($personen as [$vorname, $nachname]) {
            $lernende = $this->getDataGenerator()->create_user([
                'firstname' => $vorname,
                'lastname' => $nachname,
                'email' => strtolower($vorname) . '@firma.ch',
            ]);
            $generator->create_zuordnung([
                'berufsbildnerid' => (int) $berufsbildner->id,
                'lernendeid' => (int) $lernende->id,
            ]);
        }

        $generator->create_plan_lieferung([
            'emails' => ['anna@firma.ch', 'ben@firma.ch', 'carla@firma.ch'],
            'block' => '4',
            'kw_von' => '2027-W15',
            'kw_bis' => '2027-W16',
        ]);
        $ergebnis = $generator->create_plan_lieferung([
            'emails' => ['anna@firma.ch'],
            'block' => '4',
            'kw_von' => '2027-W20',
            'kw_bis' => '2027-W20',
        ]);
        $this->assertSame('zur_freigabe', $ergebnis['status']);

        return plan_import::get_record(['status' => 'zur_freigabe']);
    }

    public function test_daten_zeigen_aenderungen_und_fehlende_alphabetisch(): void {
        $this->resetAfterTest();

        $wartend = $this->wartende_lieferung();
        $service = new import_service();
        $url = new moodle_url('/local/berufsbildung/plan_freigabe.php');

        $daten = plan_freigabe::daten(
            $wartend,
            $service->pruefe_wartende($wartend),
            $service->letzter_erfolgreicher_import(),
            $url
        );

        $this->assertSame((int) $wartend->get('id'), $daten['id']);
        $this->assertSame($url->out(false), $daten['url']);
        $this->assertSame(fullname(get_admin()), $daten['ausgefuehrtvon']);
        $this->assertSame('3', $daten['personenvorher']);
        $this->assertSame(1, $daten['personenheute']);
        $this->assertSame(1, $daten['anzahlaenderungen']);
        $this->assertFalse($daten['haspersonenunveraendert']);
        // Eine Kalenderwoche wird nicht als Spanne geschrieben.
        $this->assertSame(
            [['name' => 'Anna Eins', 'einsaetze' => [['entfaellt' => false, 'block' => '4', 'zeitraum' => '2027-W20']]]],
            $daten['personen']
        );
        $this->assertTrue($daten['hasfehlende']);
        $this->assertSame(2, $daten['anzahlfehlende']);
        $this->assertSame([['name' => 'Ben Zwei'], ['name' => 'Carla Drei']], $daten['fehlende']);
    }

    /**
     * Ohne letzten erfolgreichen Import gibt es keinen Vergleichswert, und
     * eine inzwischen geloeschte ausfuehrende Person erscheint als "-".
     */
    public function test_daten_ohne_vergleichswert_und_ohne_ausfuehrende_person(): void {
        $this->resetAfterTest();

        $wartend = $this->wartende_lieferung();
        $wartend->set('ausgefuehrt_von', 0);
        $service = new import_service();

        $daten = plan_freigabe::daten(
            $wartend,
            $service->pruefe_wartende($wartend),
            null,
            new moodle_url('/local/berufsbildung/plan_freigabe.php')
        );

        $this->assertSame('-', $daten['personenvorher']);
        $this->assertSame('-', $daten['ausgefuehrtvon']);
    }

    public function test_render_enthaelt_namen_und_beide_knoepfe(): void {
        $this->resetAfterTest();

        $wartend = $this->wartende_lieferung();
        $service = new import_service();

        $html = plan_freigabe::render(
            $wartend,
            $service->pruefe_wartende($wartend),
            $service->letzter_erfolgreicher_import(),
            new moodle_url('/local/berufsbildung/plan_freigabe.php')
        );

        $this->assertStringContainsString('Anna Eins', $html);
        $this->assertStringContainsString('Ben Zwei', $html);
        $this->assertStringContainsString('name="aktion" value="freigeben"', $html);
        $this->assertStringContainsString('name="aktion" value="verwerfen"', $html);
        $this->assertStringContainsString('name="id" value="' . (int) $wartend->get('id') . '"', $html);
    }
}
