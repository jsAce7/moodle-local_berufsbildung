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
use context_user;

/**
 * @covers \local_berufsbildung\service\role_sync_service
 */
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
     * Randfall: die Zuordnung ist beendet - die vom Task selbst vergebene
     * Rolle wird wieder entzogen.
     */
    public function test_beendete_zuordnung_entzieht_selbst_vergebene_rolle(): void {
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
        $this->assertSame(1, $ergebnis['entzogen']);

        $context = context_user::instance((int) $lernende->id);
        $this->assertFalse($DB->record_exists('role_assignments', [
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

        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        $this->lege_zuordnung_an((int) $berufsbildner->id, (int) $lernende->id, strtotime('-1 year'));

        $service = new role_sync_service();
        $service->synchronisiere();
        $ergebnis = $service->synchronisiere();

        $this->assertSame(0, $ergebnis['zugewiesen']);
        $this->assertSame(0, $ergebnis['entzogen']);
    }
}
