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
 * Tests fuer die Fachvorgesetzten der Ausbildungsbloecke und ihre Rolle.
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_berufsbildung\service;

use advanced_testcase;
use context_system;
use local_berufsbildung\api;
use local_berufsbildung\persistent\block;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\fachvorgesetzte_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_berufsbildung\service\fachvorgesetztenrolle_service::class)]
/**
 * Tests fuer fachvorgesetzte_service und fachvorgesetztenrolle_service.
 *
 * @covers \local_berufsbildung\service\fachvorgesetzte_service
 * @covers \local_berufsbildung\service\fachvorgesetztenrolle_service
 */
final class fachvorgesetzte_service_test extends advanced_testcase {
    /**
     * Ein betrieblicher Block.
     *
     * @param string $nummer
     * @return block
     */
    private function block(string $nummer): block {
        return $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_block([
            'nummer' => $nummer,
            'name' => 'Abteilung ' . $nummer,
            'ist_betrieb' => true,
        ]);
    }

    /**
     * Hat die Person die vom Plugin vergebene Fachvorgesetztenrolle?
     *
     * @param int $userid
     * @return bool
     */
    private function hat_rolle(int $userid): bool {
        global $DB;

        return $DB->record_exists('role_assignments', [
            'roleid' => (new fachvorgesetztenrolle_service())->rolle_id(),
            'userid' => $userid,
            'contextid' => context_system::instance()->id,
            'component' => 'local_berufsbildung',
        ]);
    }

    public function test_rolle_existiert_nach_installation(): void {
        $this->resetAfterTest();

        $this->assertNotNull((new fachvorgesetztenrolle_service())->rolle_id());
    }

    public function test_setzen_vergibt_und_entzieht_rolle(): void {
        $this->resetAfterTest();

        $a = $this->block('A1');
        $b = $this->block('B1');
        $fv = (int) $this->getDataGenerator()->create_user()->id;
        $service = new fachvorgesetzte_service();

        $service->setze_fuer_block((int) $a->get('id'), [$fv]);
        $service->setze_fuer_block((int) $b->get('id'), [$fv]);
        $this->assertTrue($this->hat_rolle($fv));
        $this->assertTrue(api::ist_fachvorgesetzt_fuer_block($fv, (int) $a->get('id')));
        $this->assertSame([(int) $a->get('id'), (int) $b->get('id')], api::get_bloecke_als_fachvorgesetzt($fv));

        // Aus einem Block entfernt: noch im anderen, Rolle bleibt.
        $service->setze_fuer_block((int) $a->get('id'), []);
        $this->assertFalse(api::ist_fachvorgesetzt_fuer_block($fv, (int) $a->get('id')));
        $this->assertTrue($this->hat_rolle($fv));

        // Aus dem letzten Block entfernt: Rolle weg.
        $service->setze_fuer_block((int) $b->get('id'), []);
        $this->assertFalse($this->hat_rolle($fv));
        $this->assertFalse(api::ist_fachvorgesetzt($fv));
    }

    public function test_fremde_person_ist_nie_fachvorgesetzt(): void {
        $this->resetAfterTest();

        $a = $this->block('A1');
        $fv = (int) $this->getDataGenerator()->create_user()->id;
        $fremd = (int) $this->getDataGenerator()->create_user()->id;
        (new fachvorgesetzte_service())->setze_fuer_block((int) $a->get('id'), [$fv]);

        $this->assertFalse(api::ist_fachvorgesetzt_fuer_block($fremd, (int) $a->get('id')));
        $this->assertSame([$fv], api::get_fachvorgesetzte((int) $a->get('id')));
    }

    public function test_geloeschtes_konto_zaehlt_nicht_und_wird_bereinigt(): void {
        $this->resetAfterTest();

        $a = $this->block('A1');
        $fv = $this->getDataGenerator()->create_user();
        $bleibt = (int) $this->getDataGenerator()->create_user()->id;
        (new fachvorgesetzte_service())->setze_fuer_block((int) $a->get('id'), [(int) $fv->id, $bleibt]);

        delete_user($fv);

        $this->assertSame([$bleibt], api::get_fachvorgesetzte((int) $a->get('id')));
        $this->assertFalse(api::ist_fachvorgesetzt((int) $fv->id));
    }

    public function test_block_loeschen_entfernt_fachvorgesetzte(): void {
        $this->resetAfterTest();

        $a = $this->block('A1');
        $fv = (int) $this->getDataGenerator()->create_user()->id;
        (new fachvorgesetzte_service())->setze_fuer_block((int) $a->get('id'), [$fv]);

        (new block_loesch_service())->loesche($a);

        $this->assertFalse(api::ist_fachvorgesetzt($fv));
        $this->assertFalse($this->hat_rolle($fv));
    }

    public function test_vollstaendiger_abgleich_laesst_handvergabe_stehen(): void {
        global $DB;
        $this->resetAfterTest();

        $a = $this->block('A1');
        $fv = (int) $this->getDataGenerator()->create_user()->id;
        $vonhand = (int) $this->getDataGenerator()->create_user()->id;
        $verwaist = (int) $this->getDataGenerator()->create_user()->id;
        $rolle = new fachvorgesetztenrolle_service();
        $roleid = $rolle->rolle_id();
        $system = context_system::instance()->id;

        // Eintrag ohne Service (Rolle fehlt), eine verwaiste und eine von Hand vergebene Zuweisung.
        $DB->insert_record('local_berufsbildung_block_fv', (object) [
            'blockid' => (int) $a->get('id'), 'userid' => $fv, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        role_assign($roleid, $verwaist, $system, 'local_berufsbildung');
        role_assign($roleid, $vonhand, $system);

        $ergebnis = $rolle->synchronisiere();

        $this->assertSame(['zugewiesen' => 1, 'entzogen' => 1], $ergebnis);
        $this->assertTrue($this->hat_rolle($fv));
        $this->assertFalse($this->hat_rolle($verwaist));
        $this->assertTrue($DB->record_exists('role_assignments', ['roleid' => $roleid, 'userid' => $vonhand, 'component' => '']));
    }

    public function test_namen_fuer_bloecke(): void {
        $this->resetAfterTest();

        $a = $this->block('A1');
        $b = $this->block('B1');
        $zora = (int) $this->getDataGenerator()->create_user(['firstname' => 'Zora', 'lastname' => 'Meier'])->id;
        $anna = (int) $this->getDataGenerator()->create_user(['firstname' => 'Anna', 'lastname' => 'Keller'])->id;
        (new fachvorgesetzte_service())->setze_fuer_block((int) $a->get('id'), [$zora, $anna]);

        $namen = (new fachvorgesetzte_service())->namen_fuer_bloecke([(int) $a->get('id'), (int) $b->get('id')]);

        $this->assertSame(['Anna Keller', 'Zora Meier'], $namen[(int) $a->get('id')]);
        $this->assertArrayNotHasKey((int) $b->get('id'), $namen);
    }

    public function test_block_kompetenzen_mit_uebergeordneten(): void {
        $this->resetAfterTest();

        $competency = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $rahmen = $competency->create_framework();
        $hk = $competency->create_competency(['competencyframeworkid' => $rahmen->get('id')]);
        $lk = $competency->create_competency(['competencyframeworkid' => $rahmen->get('id'), 'parentid' => $hk->get('id')]);
        $a = $this->block('A1');
        $leer = $this->block('B1');
        $this->getDataGenerator()->get_plugin_generator('local_berufsbildung')->create_block_competency([
            'blockid' => $a->get('id'),
            'competencyid' => $lk->get('id'),
        ]);

        $kompetenzen = api::get_block_kompetenzen((int) $a->get('id'));
        sort($kompetenzen);

        $this->assertSame([(int) $hk->get('id'), (int) $lk->get('id')], $kompetenzen);
        $this->assertSame([], api::get_block_kompetenzen((int) $leer->get('id')));
        $this->assertTrue(api::ist_betrieblicher_block((int) $a->get('id')));
        $this->assertFalse(api::ist_betrieblicher_block(0));
    }
}
