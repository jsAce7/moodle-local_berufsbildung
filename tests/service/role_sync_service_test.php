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
 * Tests fuer den Rollen-Sync-Service.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use context_system;
use context_user;

/**
 * Tests fuer role_sync_service.
 *
 * @covers \local_berufsbildung\service\role_sync_service
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\role_sync_service::class)]
final class role_sync_service_test extends advanced_testcase {
    /**
     * Legt die Rolle 'berufsbildner' an, falls sie (wie im normalen
     * Installationsablauf ueber db/install.php) noch nicht existiert.
     *
     * @return int
     */
    private function stelle_rolle_sicher(): int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'berufsbildner']);
        if ($roleid) {
            return (int) $roleid;
        }

        $roleid = create_role('Berufsbildner/in', 'berufsbildner', '');
        set_role_contextlevels($roleid, [CONTEXT_USER]);

        return (int) $roleid;
    }

    /**
     * Gegenstueck fuer die systemweite Planungsrolle, inklusive der
     * Capability - sonst prueft der Test die Rollenzuweisung, aber nicht
     * das, was sie oeffnen soll.
     *
     * @return int
     */
    private function stelle_planungsrolle_sicher(): int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'berufsbildung_planung']);
        if (!$roleid) {
            $roleid = create_role('Ausbildungsplanung', 'berufsbildung_planung', '');
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        }

        assign_capability(
            'local/berufsbildung:manageblocks',
            CAP_ALLOW,
            $roleid,
            context_system::instance()->id,
            true
        );

        return (int) $roleid;
    }

    /**
     * Legt eine Zuordnung zwischen Berufsbildner/in und Lernender an.
     *
     * @param int $berufsbildnerid
     * @param int $lernendeid
     * @param int $von
     * @param int|null $bis
     */
    private function lege_zuordnung_an(int $berufsbildnerid, int $lernendeid, int $von, ?int $bis = null): void {
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_zuordnung([
            'berufsbildnerid' => $berufsbildnerid,
            'lernendeid' => $lernendeid,
            'gueltig_von' => $von,
            'gueltig_bis' => $bis,
        ]);
    }

    public function test_aktive_zuordnung_ohne_rolle_wird_zugewiesen(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = $this->stelle_rolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $ergebnis = (new role_sync_service())->synchronisiere();

        $this->assertSame(1, $ergebnis['zugewiesen']);
        $this->assertSame(0, $ergebnis['entzogen']);

        $context = context_user::instance((int) $lernende->id);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => (int) $berufsbildner->id,
            'contextid' => $context->id,
            'component' => 'local_berufsbildung',
        ]));
    }

    /**
     * Beendete Zuordnungen behalten ihre Rolle fuer historische,
     * weiterhin stichtagsgepruefte Einsicht.
     */
    public function test_beendete_zuordnung_behaelt_rolle_fuer_historische_pruefung(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = $this->stelle_rolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $service = new role_sync_service();
        $service->synchronisiere();

        $zuordnung = \local_berufsbildung\persistent\zuordnung::get_record([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
        ]);
        \local_berufsbildung\api::beende_zuordnung((int) $zuordnung->get('id'), strtotime('-1 day'));

        $ergebnis = $service->synchronisiere();

        $this->assertSame(0, $ergebnis['zugewiesen']);
        $this->assertSame(0, $ergebnis['entzogen']);

        $context = context_user::instance((int) $lernende->id);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => (int) $berufsbildner->id,
            'contextid' => $context->id,
            'component' => 'local_berufsbildung',
        ]));
    }

    /**
     * Randfall (Architekturregel/Plan Abschnitt 7): eine von Hand vergebene
     * Rolle (component = '') wird nie angefasst, auch nicht entzogen, wenn
     * keine Zuordnung dahintersteht.
     */
    public function test_manuell_vergebene_rolle_bleibt_unangetastet(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = $this->stelle_rolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $fremde = $this->getDataGenerator()->create_user();
        $context = context_user::instance((int) $fremde->id);
        // Manuell zugewiesen, kein component - keine Zuordnung dahinter.
        role_assign($roleid, (int) $berufsbildner->id, $context->id);

        $ergebnis = (new role_sync_service())->synchronisiere();

        $this->assertSame(0, $ergebnis['entzogen']);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => (int) $berufsbildner->id,
            'contextid' => $context->id,
            'component' => '',
        ]));
    }

    public function test_unveraenderte_zuordnung_wird_beim_zweiten_lauf_nicht_erneut_gezaehlt(): void {
        $this->resetAfterTest();
        $this->stelle_rolle_sicher();
        $this->stelle_planungsrolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $service = new role_sync_service();
        $service->synchronisiere();
        $ergebnis = $service->synchronisiere();

        $this->assertSame(0, $ergebnis['zugewiesen']);
        $this->assertSame(0, $ergebnis['entzogen']);
        $this->assertSame(0, $ergebnis['planung_zugewiesen']);
        $this->assertSame(0, $ergebnis['planung_entzogen']);
    }

    /**
     * Das eigentliche Versprechen: wer Berufsbildner/in ist, darf die
     * Ausbildungsbloecke verwalten - geprueft an der Capability, nicht nur
     * an der Rollenzuweisung.
     */
    public function test_zuordnung_oeffnet_die_blockverwaltung(): void {
        $this->resetAfterTest();
        $this->stelle_rolle_sicher();
        $this->stelle_planungsrolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $ergebnis = (new role_sync_service())->synchronisiere();

        $this->assertSame(1, $ergebnis['planung_zugewiesen']);

        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(
            'local/berufsbildung:manageblocks',
            context_system::instance(),
            (int) $berufsbildner->id
        ));
    }

    /**
     * Gegenstueck zu test_beendete_zuordnung_behaelt_rolle_fuer_historische_pruefung:
     * die personenbezogene Rolle bleibt fuer die stichtagsgepruefte
     * Einsicht bestehen, die systemweite Planungsrolle wird entzogen. Wer
     * niemanden mehr betreut, darf die Stammdaten nicht weiter aendern.
     */
    public function test_beendete_zuordnung_entzieht_die_planungsrolle(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = $this->stelle_rolle_sicher();
        $planungsroleid = $this->stelle_planungsrolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $service = new role_sync_service();
        $service->synchronisiere();

        $zuordnung = \local_berufsbildung\persistent\zuordnung::get_record([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
        ]);
        \local_berufsbildung\api::beende_zuordnung((int) $zuordnung->get('id'), strtotime('-1 day'));

        // Die API entzieht die Planungsrolle sofort, noch vor dem naechsten Task-Lauf.
        $this->assertFalse($DB->record_exists('role_assignments', [
            'roleid' => $planungsroleid,
            'userid' => (int) $berufsbildner->id,
            'contextid' => context_system::instance()->id,
        ]));

        $ergebnis = $service->synchronisiere();

        $this->assertSame(0, $ergebnis['planung_entzogen']);
        $this->assertFalse($DB->record_exists('role_assignments', [
            'roleid' => $planungsroleid,
            'userid' => (int) $berufsbildner->id,
            'contextid' => context_system::instance()->id,
        ]));

        // Die personenbezogene Rolle bleibt - der Unterschied ist Absicht.
        $this->assertSame(0, $ergebnis['entzogen']);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => (int) $berufsbildner->id,
            'contextid' => context_user::instance((int) $lernende->id)->id,
            'component' => 'local_berufsbildung',
        ]));

        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability(
            'local/berufsbildung:manageblocks',
            context_system::instance(),
            (int) $berufsbildner->id
        ));
    }

    /**
     * Auch das endgueltige Loeschen einer Zuordnung entzieht die
     * Planungsrolle sofort - zuordnung_service::loeschen() gleicht ab.
     */
    public function test_loeschen_der_zuordnung_entzieht_die_planungsrolle(): void {
        global $DB;
        $this->resetAfterTest();
        $this->stelle_rolle_sicher();
        $planungsroleid = $this->stelle_planungsrolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        (new role_sync_service())->synchronisiere();

        $zuordnung = \local_berufsbildung\persistent\zuordnung::get_record([
            'berufsbildnerid' => (int) $berufsbildner->id,
            'lernendeid' => (int) $lernende->id,
        ]);
        \local_berufsbildung\api::loesche_zuordnung((int) $zuordnung->get('id'));

        $this->assertFalse($DB->record_exists('role_assignments', [
            'roleid' => $planungsroleid,
            'userid' => (int) $berufsbildner->id,
            'contextid' => context_system::instance()->id,
        ]));
    }

    /**
     * Eine noch laufende zweite Zuordnung haelt die Planungsrolle: entzogen
     * wird erst, wenn die letzte beendet ist.
     */
    public function test_zweite_laufende_zuordnung_haelt_die_planungsrolle(): void {
        $this->resetAfterTest();
        $this->stelle_rolle_sicher();
        $this->stelle_planungsrolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $eine = $this->getDataGenerator()->create_user();
        $andere = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $eine->id, strtotime('-1 year'), strtotime('-1 day'));
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $andere->id, strtotime('-1 year'));

        $ergebnis = (new role_sync_service())->synchronisiere();

        $this->assertSame(1, $ergebnis['planung_zugewiesen']);
        $this->assertSame(0, $ergebnis['planung_entzogen']);

        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(
            'local/berufsbildung:manageblocks',
            context_system::instance(),
            (int) $berufsbildner->id
        ));
    }

    /**
     * Randfall: die Zuordnung endet genau jetzt. Sie gilt am Stichtag noch
     * (gueltig_bis >= Stichtag, wie in api::is_zustaendig()), die Rolle
     * bleibt also bis zum Ablauf bestehen.
     */
    public function test_zuordnung_endet_genau_jetzt_haelt_die_planungsrolle(): void {
        $this->resetAfterTest();
        $this->stelle_rolle_sicher();
        $this->stelle_planungsrolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'), time() + 60);

        $ergebnis = (new role_sync_service())->synchronisiere();

        $this->assertSame(1, $ergebnis['planung_zugewiesen']);
    }

    /**
     * Die Planungsrolle ist systemweit - eine Person ohne jede Zuordnung
     * darf sie nicht bekommen, sonst haette jeder Account Schreibzugriff
     * auf die Stammdaten.
     */
    public function test_fremde_person_erhaelt_keine_planungsrolle(): void {
        $this->resetAfterTest();
        $this->stelle_rolle_sicher();
        $this->stelle_planungsrolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $fremde = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        (new role_sync_service())->synchronisiere();

        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability(
            'local/berufsbildung:manageblocks',
            context_system::instance(),
            (int) $fremde->id
        ));
    }

    /**
     * Eine gerade angelegte Zuordnung darf nicht bis zum stuendlichen Task
     * warten - synchronisiere_paar() zieht die Planungsrolle sofort mit.
     */
    public function test_synchronisiere_paar_setzt_planungsrolle_sofort(): void {
        global $DB;
        $this->resetAfterTest();
        $this->stelle_rolle_sicher();
        $roleid = $this->stelle_planungsrolle_sicher();

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 day'));

        (new role_sync_service())->synchronisiere_paar((int) $berufsbildner->id, (int) $lernende->id);

        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => (int) $berufsbildner->id,
            'contextid' => context_system::instance()->id,
            'component' => 'local_berufsbildung',
        ]));
    }

    /**
     * Randfall wie bei der personenbezogenen Rolle: eine von Hand global
     * vergebene Planungsrolle (component = '') wird nie entzogen - etwa die
     * Ausbildungsleitung, die selbst keine Lernenden betreut.
     */
    public function test_manuell_vergebene_planungsrolle_bleibt_unangetastet(): void {
        global $DB;
        $this->resetAfterTest();
        $this->stelle_rolle_sicher();
        $roleid = $this->stelle_planungsrolle_sicher();

        $leitung = $this->getDataGenerator()->create_user();
        role_assign($roleid, (int) $leitung->id, context_system::instance()->id);

        $ergebnis = (new role_sync_service())->synchronisiere();

        $this->assertSame(0, $ergebnis['planung_entzogen']);
        $this->assertTrue($DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => (int) $leitung->id,
            'contextid' => context_system::instance()->id,
            'component' => '',
        ]));
    }
}
