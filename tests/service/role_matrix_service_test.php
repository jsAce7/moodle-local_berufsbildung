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
 * Tests fuer die Allow-Matrizen der Plugin-Rollen.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use context_system;

/**
 * Tests fuer role_matrix_service.
 *
 * @covers \local_berufsbildung\service\role_matrix_service
 */
final class role_matrix_service_test extends advanced_testcase {
    /**
     * Legt die Rollen an, falls sie (wie im normalen Installationsablauf
     * ueber db/install.php) noch nicht existieren, und raeumt ihre
     * Allow-Zeilen ab.
     *
     * Das Abraeumen ist der Punkt: db/install.php ruft den Service selbst
     * auf, in einer nach diesem Upgrade neu aufgebauten Testdatenbank
     * steht die Matrix also schon. Ohne Reset waeren die Zaehler davon
     * abhaengig, wann die Testdatenbank entstanden ist.
     *
     * @return array{verwaltung: int, planung: int, leitung: int, berufsbildner: int}
     */
    private function stelle_rollen_sicher(): array {
        global $DB;

        $planung = $DB->get_field('role', 'id', ['shortname' => 'berufsbildung_planung']);
        if (!$planung) {
            $planung = create_role('Ausbildungsplanung', 'berufsbildung_planung', '');
            set_role_contextlevels($planung, [CONTEXT_SYSTEM]);
        }

        $leitung = $DB->get_field('role', 'id', ['shortname' => 'berufsbildung_leitung']);
        if (!$leitung) {
            $leitung = create_role('Leitung Berufsbildung', 'berufsbildung_leitung', '');
            set_role_contextlevels($leitung, [CONTEXT_SYSTEM]);
        }

        $berufsbildner = $DB->get_field('role', 'id', ['shortname' => 'berufsbildner']);
        if (!$berufsbildner) {
            $berufsbildner = create_role('Berufsbildner/in', 'berufsbildner', '');
            set_role_contextlevels($berufsbildner, [CONTEXT_USER]);
        }

        $rollen = [
            'verwaltung' => (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST),
            'planung' => (int) $planung,
            'leitung' => (int) $leitung,
            'berufsbildner' => (int) $berufsbildner,
        ];

        foreach (['planung', 'leitung', 'berufsbildner'] as $rolle) {
            $DB->delete_records('role_allow_assign', ['allowassign' => $rollen[$rolle]]);
            $DB->delete_records('role_allow_view', ['allowview' => $rollen[$rolle]]);
        }

        return $rollen;
    }

    public function test_planungsrolle_wird_fuer_die_verwaltung_zuweisbar(): void {
        global $DB;
        $this->resetAfterTest();
        $rollen = $this->stelle_rollen_sicher();

        $ergebnis = (new role_matrix_service())->synchronisiere();

        $this->assertSame(2, $ergebnis['assign']);
        $this->assertTrue($DB->record_exists('role_allow_assign', [
            'roleid' => $rollen['verwaltung'],
            'allowassign' => $rollen['planung'],
        ]));
    }

    /**
     * Der Test, der die fachliche Entscheidung festhaelt: die
     * personenbezogene Rolle bleibt bewusst nicht von Hand vergebbar, weil
     * eine Zuweisung ohne Zuordnung nichts oeffnet - die aufsetzenden
     * Plugins pruefen zusaetzlich api::is_zustaendig().
     */
    public function test_berufsbildner_bleibt_nicht_zuweisbar_aber_sichtbar(): void {
        global $DB;
        $this->resetAfterTest();
        $rollen = $this->stelle_rollen_sicher();

        (new role_matrix_service())->synchronisiere();

        $this->assertFalse($DB->record_exists('role_allow_assign', [
            'roleid' => $rollen['verwaltung'],
            'allowassign' => $rollen['berufsbildner'],
        ]));
        $this->assertTrue($DB->record_exists('role_allow_view', [
            'roleid' => $rollen['verwaltung'],
            'allowview' => $rollen['berufsbildner'],
        ]));
    }

    /**
     * Randfall: role_allow_assign und role_allow_view haben keinen
     * Unique-Index. Ein zweiter Lauf - Upgrade auf einer Installation, die
     * die Erlaubnis schon hat - darf keine zweite Zeile anlegen.
     */
    public function test_zweiter_lauf_traegt_nichts_doppelt_ein(): void {
        global $DB;
        $this->resetAfterTest();
        $rollen = $this->stelle_rollen_sicher();

        $service = new role_matrix_service();
        $erster = $service->synchronisiere();
        $zweiter = $service->synchronisiere();

        $this->assertSame(['assign' => 2, 'view' => 3], $erster);
        $this->assertSame(['assign' => 0, 'view' => 0], $zweiter);
        $this->assertSame(1, $DB->count_records('role_allow_assign', [
            'roleid' => $rollen['verwaltung'],
            'allowassign' => $rollen['planung'],
        ]));
        $this->assertSame(1, $DB->count_records('role_allow_view', [
            'roleid' => $rollen['verwaltung'],
            'allowview' => $rollen['planung'],
        ]));
    }

    /**
     * Randfall: die Planungsrolle existiert noch nicht, weil der
     * Upgrade-Schritt, der sie anlegt, in einer anderen Reihenfolge lief.
     * Dann bleibt nur die personenbezogene Rolle uebrig, und der Service
     * darf nicht mit MUST_EXIST abbrechen.
     */
    public function test_fehlende_planungsrolle_wird_uebersprungen(): void {
        global $DB;
        $this->resetAfterTest();
        $this->stelle_rollen_sicher();
        $DB->delete_records('role', ['shortname' => 'berufsbildung_planung']);

        $ergebnis = (new role_matrix_service())->synchronisiere();

        $this->assertSame(['assign' => 1, 'view' => 2], $ergebnis);
    }

    /**
     * Die Leitungsrolle vergibt die Verwaltung von Hand, automatisch weist
     * sie niemand zu.
     */
    public function test_leitungsrolle_wird_fuer_die_verwaltung_zuweisbar(): void {
        global $DB;
        $this->resetAfterTest();
        $rollen = $this->stelle_rollen_sicher();

        (new role_matrix_service())->synchronisiere();

        $this->assertTrue($DB->record_exists('role_allow_assign', [
            'roleid' => $rollen['verwaltung'],
            'allowassign' => $rollen['leitung'],
        ]));
        $this->assertTrue($DB->record_exists('role_allow_view', [
            'roleid' => $rollen['verwaltung'],
            'allowview' => $rollen['leitung'],
        ]));
    }

    /**
     * Der Upgrade-Schritt, der die Leitungsrolle anlegt, traegt nur sie
     * nach: eine von Hand entfernte Erlaubnis fuer die Planungsrolle kommt
     * dadurch nicht zurueck.
     */
    public function test_nachtrag_einer_rolle_laesst_die_anderen_unberuehrt(): void {
        global $DB;
        $this->resetAfterTest();
        $rollen = $this->stelle_rollen_sicher();

        $ergebnis = (new role_matrix_service())->synchronisiere(['berufsbildung_leitung']);

        $this->assertSame(['assign' => 1, 'view' => 1], $ergebnis);
        $this->assertFalse($DB->record_exists('role_allow_assign', ['allowassign' => $rollen['planung']]));
        $this->assertFalse($DB->record_exists('role_allow_view', ['allowview' => $rollen['planung']]));
        $this->assertFalse($DB->record_exists('role_allow_view', ['allowview' => $rollen['berufsbildner']]));
    }

    /**
     * Randfall: eine Installation ohne Rolle 'manager'. Ohne Grantor gibt
     * es niemanden einzutragen - der Service darf dann nichts tun statt zu
     * werfen.
     */
    public function test_ohne_verwaltungsrolle_passiert_nichts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->stelle_rollen_sicher();
        $DB->set_field('role', 'shortname', 'verwaltung_umbenannt', ['shortname' => 'manager']);

        $ergebnis = (new role_matrix_service())->synchronisiere();

        $this->assertSame(['assign' => 0, 'view' => 0], $ergebnis);
        $this->assertSame(0, $DB->count_records('role_allow_assign', [
            'allowassign' => (int) $DB->get_field('role', 'id', ['shortname' => 'berufsbildung_planung'], MUST_EXIST),
        ]));
    }

    /**
     * Die Matrix ist Mittel zum Zweck: eine Person mit der
     * Verwaltungsrolle - und ohne Admin-Rechte, die die Matrix umgehen
     * wuerden - muss die Planungsrolle im Systemkontext tatsaechlich
     * angeboten bekommen.
     */
    public function test_verwaltung_bekommt_die_planungsrolle_angeboten(): void {
        $this->resetAfterTest();
        $rollen = $this->stelle_rollen_sicher();

        $verwalterin = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        role_assign($rollen['verwaltung'], (int) $verwalterin->id, $context->id);
        $this->setUser($verwalterin);

        $this->assertArrayNotHasKey($rollen['planung'], get_assignable_roles($context));

        (new role_matrix_service())->synchronisiere();

        $this->assertArrayHasKey($rollen['planung'], get_assignable_roles($context));
    }
}
