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
 * Tests fuer den Privacy-Provider.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\privacy;

use context_user;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_berufsbildung\persistent\aufbewahrung;
use local_berufsbildung\persistent\block;
use local_berufsbildung\persistent\einsatz;
use local_berufsbildung\persistent\kohorten_link;
use local_berufsbildung\persistent\plan_import;
use local_berufsbildung\persistent\zuordnung;

/**
 * Tests fuer provider.
 *
 * @covers \local_berufsbildung\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_berufsbildung'));

        $tabellen = array_map(
            static fn ($item) => method_exists($item, 'get_name') ? $item->get_name() : null,
            $collection->get_collection()
        );

        $this->assertContains('local_berufsbildung_zuordnung', $tabellen);
        $this->assertContains('local_berufsbildung_kohorten_link', $tabellen);
        $this->assertContains('local_berufsbildung_einsatz', $tabellen);
        $this->assertContains('local_berufsbildung_plan_import', $tabellen);
    }

    public function test_get_contexts_for_userid_findet_beide_rollen(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $fremd = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => strtotime('-1 year'),
        ]);

        $lernendekontexte = provider::get_contexts_for_userid((int) $lernende->id);
        $this->assertEquals([context_user::instance((int) $lernende->id)->id], $lernendekontexte->get_contextids());

        $berufsbildnerkontexte = provider::get_contexts_for_userid((int) $berufsbildner->id);
        $this->assertEquals([context_user::instance((int) $berufsbildner->id)->id], $berufsbildnerkontexte->get_contextids());

        $fremdekontexte = provider::get_contexts_for_userid((int) $fremd->id);
        $this->assertCount(0, $fremdekontexte->get_contextids());
    }

    public function test_export_user_data_enthaelt_beide_rollen_und_planimport(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => strtotime('-1 year'),
        ]);

        (new kohorten_link(0, (object) [
            'cohortid' => 1, 'berufsbildnerid' => (int) $berufsbildner->id,
            'rolle' => 'hauptverantwortlich', 'beruf' => '', 'aktiv' => true,
        ]))->create();

        $import = new plan_import(0, (object) [
            'quelle' => 'upload', 'daten_hash' => str_repeat('a', 64), 'zeitpunkt' => time(),
            'ausgefuehrt_von' => (int) $berufsbildner->id, 'status' => 'ok', 'protokoll' => '',
        ]);
        $import->create();

        $block = new block(0, (object) ['nummer' => '4', 'name' => '4', 'ist_betrieb' => true, 'aktiv' => true]);
        $block->create();
        (new einsatz(0, (object) [
            'userid' => (int) $lernende->id, 'blockid' => (int) $block->get('id'),
            'von' => strtotime('-1 month'), 'bis' => strtotime('+1 month'),
            'kw_von' => '2027-W01', 'kw_bis' => '2027-W04', 'importid' => (int) $import->get('id'),
        ]))->create();

        // Export fuer die lernende Person: eigene Zuordnung und eigener Einsatz.
        $this->export_all_data_for_user((int) $lernende->id, 'local_berufsbildung');
        $lernendewriter = writer::with_context(context_user::instance((int) $lernende->id));
        $this->assertTrue($lernendewriter->has_any_data());
        $zuordnungsdaten = $lernendewriter->get_data([get_string('privacy:pfad_zuordnungen_lernende', 'local_berufsbildung')]);
        $this->assertCount(1, $zuordnungsdaten->zuordnungen);
        $einsatzdaten = $lernendewriter->get_data([get_string('privacy:pfad_einsaetze', 'local_berufsbildung')]);
        $this->assertCount(1, $einsatzdaten->einsaetze);

        writer::reset();

        // Export fuer die/den Berufsbildner/in: eigene Zuordnung, Kohorten-Link, Planimport.
        $this->export_all_data_for_user((int) $berufsbildner->id, 'local_berufsbildung');
        $berufsbildnerwriter = writer::with_context(context_user::instance((int) $berufsbildner->id));
        $this->assertTrue($berufsbildnerwriter->has_any_data());
        $zuordnungsdaten = $berufsbildnerwriter->get_data([
            get_string('privacy:pfad_zuordnungen_berufsbildner', 'local_berufsbildung'),
        ]);
        $this->assertCount(1, $zuordnungsdaten->zuordnungen);
        $linkdaten = $berufsbildnerwriter->get_data([get_string('privacy:pfad_kohortenlinks', 'local_berufsbildung')]);
        $this->assertCount(1, $linkdaten->kohorten_links);
        $importdaten = $berufsbildnerwriter->get_data([get_string('privacy:pfad_planimporte', 'local_berufsbildung')]);
        $this->assertCount(1, $importdaten->importe);
    }

    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $fremd = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => strtotime('-1 year'),
        ]);

        $userlist = new userlist(context_user::instance((int) $lernende->id), 'local_berufsbildung');
        provider::get_users_in_context($userlist);
        $this->assertEquals([(int) $lernende->id], $userlist->get_userids());

        $userlist = new userlist(context_user::instance((int) $fremd->id), 'local_berufsbildung');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist->get_userids());
    }

    /**
     * Zentraler Architektur-Test: eine Loeschanfrage anonymisiert nur den
     * ausfuehrenden Account eines Versetzungsplan-Imports, laesst aber
     * Zuordnung und Einsatz unangetastet (Architekturregel 2 und 5 -
     * siehe provider::anonymisiere_bearbeitungsspuren()).
     */
    public function test_delete_data_for_user_anonymisiert_nur_planimport(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();

        $zuordnung = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
            'gueltig_von' => strtotime('-1 year'),
        ]);

        $import = new plan_import(0, (object) [
            'quelle' => 'upload', 'daten_hash' => str_repeat('a', 64), 'zeitpunkt' => time(),
            'ausgefuehrt_von' => (int) $berufsbildner->id, 'status' => 'ok', 'protokoll' => '',
        ]);
        $import->create();

        $contextlist = provider::get_contexts_for_userid((int) $berufsbildner->id);
        $approved = new approved_contextlist(
            \core_user::get_user((int) $berufsbildner->id),
            'local_berufsbildung',
            $contextlist->get_contextids()
        );
        provider::delete_data_for_user($approved);

        // Planimport wurde anonymisiert ...
        $import->read();
        $this->assertSame(0, (int) $import->get('ausgefuehrt_von'));

        // ... aber die Zuordnung besteht unveraendert weiter.
        $zuordnung->read();
        $this->assertSame((int) $berufsbildner->id, (int) $zuordnung->get('berufsbildnerid'));
        $this->assertNull($zuordnung->get('gueltig_bis'));
    }

    /**
     * Baut eine genehmigte Loeschanfrage fuer eine lernende Person.
     *
     * @param stdClass $lernende
     * @return approved_contextlist
     */
    private function loeschanfrage_fuer(\stdClass $lernende): approved_contextlist {
        $contextlist = provider::get_contexts_for_userid((int) $lernende->id);

        return new approved_contextlist(
            \core_user::get_user((int) $lernende->id, '*', MUST_EXIST),
            'local_berufsbildung',
            $contextlist->get_contextids()
        );
    }

    /**
     * Fruehzeitige Loeschung auf Anfrage: die Ausbildung ist abgeschlossen,
     * keine Aufbewahrungspflicht dokumentiert - die Zuordnung wird sofort
     * geloescht, ohne auf den automatischen Task zu warten.
     */
    public function test_delete_data_for_user_loescht_zuordnung_nach_abschluss_ohne_aufbewahrungspflicht(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2010',
        ]);
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
        ]);

        provider::delete_data_for_user($this->loeschanfrage_fuer($lernende));

        $this->assertCount(0, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    /**
     * Regression: waehrend die Ausbildung noch laeuft, bleibt die
     * Zuordnung auch bei einer eigenen Loeschanfrage unangetastet - mit
     * einem tatsaechlich aufloesbaren Ausbildungsstand, nicht nur, weil das
     * Test-Fixture zufaellig keinen hat.
     */
    public function test_delete_data_for_user_behaelt_zuordnung_bei_aktiver_ausbildung(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $jetzt = time();
        $jahrgangbasis = (int) date('n', $jetzt) >= 8 ? (int) date('Y', $jetzt) : (int) date('Y', $jetzt) - 1;
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => (string) $jahrgangbasis,
        ]);
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
        ]);

        provider::delete_data_for_user($this->loeschanfrage_fuer($lernende));

        $this->assertCount(1, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    /**
     * Abgeschlossene Ausbildung, aber mit dokumentierter
     * Aufbewahrungspflicht - die Zuordnung bleibt trotz Loeschanfrage
     * erhalten.
     */
    public function test_delete_data_for_user_behaelt_zuordnung_mit_aufbewahrungspflicht(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2010',
        ]);
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
        ]);
        (new aufbewahrung(0, (object) [
            'lernendeid' => (int) $lernende->id,
            'grund' => 'Laufendes Verfahren',
            'gueltig_von' => strtotime('-1 year'),
            'gueltig_bis' => null,
        ]))->create();

        provider::delete_data_for_user($this->loeschanfrage_fuer($lernende));

        $this->assertCount(1, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    /**
     * Eine Account-Loeschung entfernt die Zuordnung sofort und unbedingt -
     * auch waehrend die Ausbildung noch aktiv laeuft, ohne
     * Aufbewahrungspflicht-Pruefung. Analog zum bestehenden Verhalten in
     * local_lerndokumentation.
     */
    public function test_delete_data_for_user_loescht_zuordnung_sofort_bei_account_geloescht(): void {
        $this->resetAfterTest();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $jetzt = time();
        $jahrgangbasis = (int) date('n', $jetzt) >= 8 ? (int) date('Y', $jetzt) : (int) date('Y', $jetzt) - 1;
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => (string) $jahrgangbasis,
        ]);
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
        ]);

        delete_user($lernende);
        provider::delete_data_for_user($this->loeschanfrage_fuer($lernende));

        $this->assertCount(0, zuordnung::get_records(['lernendeid' => (int) $lernende->id]));
    }

    /**
     * Wird das Konto einer Berufsbildner/in geloescht, endet ihre laufende
     * Zuordnung, eine noch nicht begonnene verschwindet, eine bereits beendete
     * bleibt unveraendert, und ihr Kohorten-Link legt keine neuen Zuordnungen
     * mehr an.
     */
    public function test_account_loeschung_beendet_zustaendigkeit_der_berufsbildnerin(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung');
        $berufsbildner = $this->getDataGenerator()->create_user();
        $jetzt = time();
        $laufend = $generator->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $this->getDataGenerator()->create_user()->id,
        ]);
        $kuenftig = $generator->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $this->getDataGenerator()->create_user()->id,
            'gueltig_von' => $jetzt + WEEKSECS,
        ]);
        $beendet = $generator->create_zuordnung([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $this->getDataGenerator()->create_user()->id,
            'gueltig_bis' => $jetzt - WEEKSECS,
        ]);
        $link = new kohorten_link(0, (object) [
            'cohortid' => 1, 'berufsbildnerid' => (int) $berufsbildner->id,
            'rolle' => 'hauptverantwortlich', 'beruf' => '', 'aktiv' => true,
        ]);
        $link->create();

        delete_user($berufsbildner);
        provider::delete_data_for_user($this->loeschanfrage_fuer($berufsbildner));

        $laufendnachher = new zuordnung((int) $laufend->get('id'));
        $this->assertNotNull($laufendnachher->get('gueltig_bis'));
        $this->assertLessThanOrEqual(time(), (int) $laufendnachher->get('gueltig_bis'));
        $this->assertFalse(zuordnung::record_exists((int) $kuenftig->get('id')));
        $this->assertSame($jetzt - WEEKSECS, (int) (new zuordnung((int) $beendet->get('id')))->get('gueltig_bis'));
        $this->assertFalse((bool) (new kohorten_link((int) $link->get('id')))->get('aktiv'));
    }
}
