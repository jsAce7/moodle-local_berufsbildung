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
 * Tests fuer den Kohorten-Sync-Service.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use local_berufsbildung\api;
use local_berufsbildung\persistent\kohorten_link;
use local_berufsbildung\persistent\zuordnung;

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * @covers \local_berufsbildung\service\kohorten_sync_service
 */
final class kohorten_sync_service_test extends advanced_testcase {

    private function lege_link_an(int $cohortid, int $berufsbildnerid, string $rolle = 'hauptverantwortlich'): kohorten_link {
        $link = new kohorten_link(0, (object) [
            'cohortid' => $cohortid,
            'berufsbildnerid' => $berufsbildnerid,
            'rolle' => $rolle,
            'beruf' => '',
            'aktiv' => true,
        ]);
        $link->create();

        return $link;
    }

    public function test_neues_kohortenmitglied_erhaelt_zuordnung(): void {
        $this->resetAfterTest();

        $kohorte = $this->getDataGenerator()->create_cohort();
        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        cohort_add_member($kohorte->id, $lernende->id);

        $link = $this->lege_link_an((int) $kohorte->id, (int) $berufsbildner->id);
        $ergebnis = (new kohorten_sync_service())->synchronisiere_link($link);

        $this->assertSame(1, $ergebnis['erzeugt']);
        $this->assertSame(0, $ergebnis['beendet']);
        $this->assertTrue(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id));

        $zuordnungen = zuordnung::get_records(['kohorten_link_id' => (int) $link->get('id')]);
        $this->assertCount(1, $zuordnungen);
    }

    /**
     * Randfall: Mitglied hat die Kohorte verlassen - die zugehoerige
     * Zuordnung wird beendet, nie geloescht.
     */
    public function test_ausgeschiedenes_mitglied_wird_beendet_nicht_geloescht(): void {
        $this->resetAfterTest();

        $kohorte = $this->getDataGenerator()->create_cohort();
        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        cohort_add_member($kohorte->id, $lernende->id);

        $link = $this->lege_link_an((int) $kohorte->id, (int) $berufsbildner->id);
        $service = new kohorten_sync_service();
        $service->synchronisiere_link($link);

        cohort_remove_member($kohorte->id, $lernende->id);
        $ergebnis = $service->synchronisiere_link($link);

        $this->assertSame(0, $ergebnis['erzeugt']);
        $this->assertSame(1, $ergebnis['beendet']);

        $zuordnungen = zuordnung::get_records(['kohorten_link_id' => (int) $link->get('id')]);
        $this->assertCount(1, $zuordnungen);
        $gueltigbis = reset($zuordnungen)->get('gueltig_bis');
        $this->assertNotNull($gueltigbis);

        // gueltig_bis ist inklusiv (Architekturregel 3) - "jetzt beendet"
        // gilt also noch in derselben Sekunde, aber nicht mehr danach.
        $this->assertTrue(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id, $gueltigbis));
        $this->assertFalse(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id, $gueltigbis + 1));
    }

    public function test_unveraendertes_mitglied_wird_nicht_erneut_angefasst(): void {
        $this->resetAfterTest();

        $kohorte = $this->getDataGenerator()->create_cohort();
        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        cohort_add_member($kohorte->id, $lernende->id);

        $link = $this->lege_link_an((int) $kohorte->id, (int) $berufsbildner->id);
        $service = new kohorten_sync_service();
        $service->synchronisiere_link($link);
        $ergebnis = $service->synchronisiere_link($link);

        $this->assertSame(0, $ergebnis['erzeugt']);
        $this->assertSame(0, $ergebnis['beendet']);
        $this->assertCount(1, zuordnung::get_records(['kohorten_link_id' => (int) $link->get('id')]));
    }

    public function test_inaktiver_link_wird_bei_synchronisiere_alle_uebersprungen(): void {
        $this->resetAfterTest();

        $kohorte = $this->getDataGenerator()->create_cohort();
        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->create_user();
        cohort_add_member($kohorte->id, $lernende->id);

        $link = $this->lege_link_an((int) $kohorte->id, (int) $berufsbildner->id);
        $link->set('aktiv', false);
        $link->update();

        $ergebnis = (new kohorten_sync_service())->synchronisiere_alle();

        $this->assertSame(0, $ergebnis['erzeugt']);
        $this->assertFalse(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id));
    }

    /**
     * Randfall: die/der Berufsbildner/in ist zufaellig auch Mitglied der
     * verknuepften Kohorte - sich selbst zuzuordnen ist kein sinnvoller
     * Fall und wird uebersprungen.
     */
    public function test_berufsbildner_als_kohortenmitglied_wird_uebersprungen(): void {
        $this->resetAfterTest();

        $kohorte = $this->getDataGenerator()->create_cohort();
        $berufsbildner = $this->getDataGenerator()->create_user();
        cohort_add_member($kohorte->id, $berufsbildner->id);

        $link = $this->lege_link_an((int) $kohorte->id, (int) $berufsbildner->id);
        $ergebnis = (new kohorten_sync_service())->synchronisiere_link($link);

        $this->assertSame(0, $ergebnis['erzeugt']);
    }

    /**
     * Ohne diesen Schutz wuerde eine bereits per Retention geloeschte
     * Zuordnung einer abgeschlossenen Lehre beim naechsten Sync-Lauf
     * einfach wieder auferstehen (siehe Klassendocblock).
     */
    public function test_beendete_lehre_wird_nicht_neu_zugeordnet(): void {
        $this->resetAfterTest();

        $kohorte = $this->getDataGenerator()->create_cohort();
        $berufsbildner = $this->getDataGenerator()->create_user();
        $lernende = $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_lernende([
            'beruf' => 'AU_EFZ',
            'jahrgang' => '2010',
        ]);
        cohort_add_member($kohorte->id, $lernende->id);

        $link = $this->lege_link_an((int) $kohorte->id, (int) $berufsbildner->id);
        $ergebnis = (new kohorten_sync_service())->synchronisiere_link($link);

        $this->assertSame(0, $ergebnis['erzeugt']);
        $this->assertFalse(api::is_zustaendig((int) $berufsbildner->id, (int) $lernende->id));
    }
}
